<?php

require_once 'Admin/pages/AdminSearch.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatTreeFlydownNode.php';
require_once 'Swat/SwatFlydownDivider.php';
require_once 'Store/StoreCatalogSelector.php';

require_once 'Store/admin/components/Product/include/StoreProductTableView.php';
require_once 'Store/admin/components/Product/include/StoreProductSearch.php';

require_once
	'Store/admin/components/Product/include/StoreProductTitleCellRenderer.php';

require_once
	'Store/admin/components/Product/include/StoreItemStatusCellRenderer.php';

/**
 * Index page for Products
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreProductIndex extends AdminSearch
{
	// {{{ protected properties

	protected $index_xml = 'Store/admin/components/Product/index.xml';
	protected $search_xml = 'Store/admin/components/Product/search.xml';

	// }}}
	// {{{ private properties

	private $quick_sku_search = false;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->search_xml);
		$this->ui->loadFromXML($this->index_xml);

		$catalog_selector = $this->ui->getWidget('catalog_selector');
		$catalog_selector->db = $this->app->db;

		$products_frame = $this->ui->getWidget('results_frame');
		$products_frame->addStyleSheet(
			'packages/store/admin/styles/disabled-rows.css');

		$quick_search_form = $this->layout->menu->getForm();
		$quick_search_form->process();

		if ($quick_search_form->isProcessed())
			$this->clearState();

		$sale_discount_flydown = $this->ui->getWidget('sale_discount_flydown');
		$sale_discount_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'SaleDiscount', 'title', 'id', 'title'));
	}

	// }}}

	// process phase
	// {{{ protected function processInternal()

	protected function processInternal()
	{
		parent::processInternal();

		$quick_search_form = $this->layout->menu->getForm();

		if ($quick_search_form->isProcessed()) {
			$sku = $this->layout->menu->getItemEntry();
			$search_item = $this->ui->getWidget('search_item');
			$search_item->value = $sku->value;
			$search_item_op = $this->ui->getWidget('search_item_op');
			$search_item_op->value = AdminSearchClause::OP_CONTAINS;
			$this->quick_sku_search = true;

			$this->saveState();

			$this->quickSKUSearch();
		}

		$index = $this->ui->getWidget('results_frame');
		if ($this->hasState() && $index->visible === false) {
			$this->loadState();
			$index->visible = true;
		}

		$pager = $this->ui->getWidget('pager');
		$pager->process();
	}

	// }}}
	// {{{ protected function processActions()

	protected function processActions(SwatTableView $view, SwatActions $actions)
	{
		$item_list = array();
		foreach ($view->getSelection() as $item)
			$item_list[] = $this->app->db->quote($item, 'integer');

		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('Product/Delete');
			$this->app->getPage()->setItems($view->getSelection());
			break;
		case 'sale_discount' :
			$sale_discount =
				$this->ui->getWidget('sale_discount_flydown')->value;

			if ($sale_discount === null)
				break;

			$num = SwatDB::queryOne($this->app->db, sprintf(
				'select count(id) from Item where product in (%s)',
				SwatDB::implodeSelection($this->app->db,
					$view->getSelection())));

			SwatDB::updateColumn($this->app->db, 'Item',
				'integer:sale_discount', $sale_discount, 'product', $item_list);

			$message = new SwatMessage(sprintf(Store::ngettext(
				'A sale discount has been applied to one item.',
				'A sale discount has been applied to %s items.', $num),
				SwatString::numberFormat($num)));

			$this->app->messages->add($message);

			break;
		}
	}

	// }}}
	// {{{ protected function quickSKUSearch()

	protected function quickSKUSearch()
	{
		$search_item = $this->ui->getWidget('search_item');

		$sql = sprintf('select id from Product
			where id in (select product from Item where sku = %s)',
			$this->app->db->quote($search_item->value, 'text'));

		$products = SwatDB::query($this->app->db, $sql);

		if (count($products) == 1) {
			$product = $products->getFirst();
			$this->app->relocate('Product/Details?id='.$product->id);
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$this->ui->getWidget('toolbar')->visible = true;

		$category_flydown = $this->ui->getWidget('search_category');

		$tree = $category_flydown->getTree();
		$tree->addChild(new SwatTreeFlydownNode(-1, '<uncategorized>'));
		$tree->addChild(new SwatTreeFlydownNode(new SwatFlydownDivider()));

		$rs = SwatDB::executeStoredProc($this->app->db, 'getCategoryTree',
			'null');

		$category_tree = SwatDB::getDataTree($rs, 'title', 'id', 'levelnum');
		$tree->addTree($category_tree);
	}

	// }}}
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$search = $this->getProductSearch();

		$sql = sprintf('select count(id) from Product %s where %s',
			$search->getJoinClause(),
			$search->getWhereClause());

		$pager = $this->ui->getWidget('pager');
		$pager->total_records = SwatDB::queryOne($this->app->db, $sql);

		$sql = 'select Product.id,
					Product.title,
					statuses.count_total as item_count,
					statuses.count_available,
					statuses.count_outofstock,
					statuses.count_disabled
				from Product
					inner join ProductItemCountByStatusView as statuses on
						statuses.product = Product.id
					%s
				where %s
				order by %s';

		$sql = sprintf($sql,
			$search->getJoinClause(),
			$search->getWhereClause(),
			$this->getOrderByClause($view, $search->getOrderByClause()));

		$this->app->db->setLimit($pager->page_size, $pager->current_record);

		$rs = SwatDB::query($this->app->db, $sql);

		$this->setProductVisibility($rs);

		if (count($rs) > 0) {
			$this->ui->getWidget('results_message')->content =
				$pager->getResultsMessage('result', 'results');
		}

		return $rs;
	}

	// }}}
	// {{{ protected function setProductVisibility()

	protected function setProductVisibility(SwatTableModel $model)
	{
		if (count($model) > 0) {
			// get product visibility (does not depend on current catalogue)
			$quoted_ids = array();
			foreach ($model as $row)
				$quoted_ids[] = $this->app->db->quote($row->id, 'integer');

			$sql = sprintf('select product from VisibleProductView
				where product in (%s)',
				implode(', ', $quoted_ids));

			$rs = SwatDB::query($this->app->db, $sql);
			$visbile_products = array();

			foreach ($rs as $row)
				$visible_products[$row->product] = true;

			// set visibility for products
			foreach ($model as $row)
				$row->currently_visible = (isset($visible_products[$row->id]));
		}
	}

	// }}}
	// {{{ protected function getProductSearch()

	/**
	 * Gets the product search object
	 *
	 * @return StoreProductSearch the product search object.
	 */
	protected function getProductSearch()
	{
		return new StoreProductSearch($this->ui, $this->app->db);
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/admin/styles/store-product-index.css',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
