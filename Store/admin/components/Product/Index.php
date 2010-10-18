<?php

require_once 'Admin/pages/AdminSearch.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatTreeFlydownNode.php';
require_once 'Swat/SwatFlydownDivider.php';
require_once 'Store/StoreCatalogSelector.php';
require_once 'Store/StoreItemStatusList.php';
require_once 'Store/dataobjects/StoreAttributeTypeWrapper.php';
require_once 'Store/dataobjects/StoreAttributeWrapper.php';
require_once 'Store/admin/components/Product/include/StoreProductTableView.php';
require_once 'Store/admin/components/Product/include/StoreProductSearch.php';
require_once
	'Store/admin/components/Product/include/StoreProductTitleCellRenderer.php';

require_once
	'Store/admin/components/Product/include/StoreProductStatusCellRenderer.php';

/**
 * Index page for Products
 *
 * @package   Store
 * @copyright 2005-2010 silverorange
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

		$this->initCatalogSelector();
		$this->initSaleDiscountFlydown();
		$this->initAttributeList();

		$products_frame = $this->ui->getWidget('results_frame');
		$products_frame->addStyleSheet(
			'packages/store/admin/styles/disabled-rows.css');

		if ($this->layout->menu instanceof StoreAdminMenuView) {
			$quick_search_form = $this->layout->menu->getForm();
			$quick_search_form->process();

			if ($quick_search_form->isProcessed())
				$this->clearState();
		}

		$status_array = array();
		foreach (StoreItemStatusList::statuses() as $status) {
			$status_array[$status->id]= $status->title;
		}
		$status_flydown = $this->ui->getWidget('search_item_status');
		$status_flydown->addOptionsByArray($status_array);

		$sale_discount_flydown = $this->ui->getWidget('sale_discount_flydown');
		$sale_discount_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'SaleDiscount', 'title', 'id', 'title'));

		$flydown = $this->ui->getWidget('item_minimum_quantity_group_flydown');
		$search_flydown = $this->ui->getWidget(
			'search_item_minimum_quantity_group');

		$options = SwatDB::getOptionArray($this->app->db,
			'ItemMinimumQuantityGroup', 'title', 'id', 'title');

		$flydown->addOptionsByArray($options);
		$search_flydown->addOptionsByArray($options);

		$this->ui->getWidget('item_minimum_quantity_group')->visible =
			(count($options) > 0);

		$this->ui->getWidget(
			'search_item_minimum_quantity_group_field')->visible =
			(count($options) > 0);
	}

	// }}}
	// {{{ private function initCatalogSelector()

	/**
	 * Builds the catalog selector. Selector does not get shown unless there is
	 * more than one catalog, as its not useful when there is only one.
	 */
	private function initCatalogSelector()
	{
		$this->ui->getWidget('catalog_selector')->db = $this->app->db;

		$sql = 'select count(id) from Catalog';
		$catalog_count = SwatDB::queryOne($this->app->db, $sql);
		if ($catalog_count == 1)
			$this->ui->getWidget('catalog_field')->visible = false;
	}

	// }}}
	// {{{ private function initSaleDiscountFlydown()

	private function initSaleDiscountFlydown()
	{
		$flydown = $this->ui->getWidget('search_sale_discount');
		$flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'SaleDiscount', 'title', 'id', 'title'));
	}

	// }}}
	// {{{ private function initAttributeList()

	/**
	 * Builds the list of attributes using an image and a title
	 */
	private function initAttributeList()
	{
		$replicators = array();

		$attribute_types = SwatDB::query($this->app->db,
			'select * from attributetype order by shortname',
			SwatDBClassMap::get('StoreAttributeTypeWrapper'));

		foreach ($attribute_types as $type)
			$replicators[$type->id] = ucfirst($type->shortname);

		$attributes_field =
			$this->ui->getWidget('attributes_form_field');

		$attributes_field->replicators = $replicators;

		$attributes_field =
			$this->ui->getWidget('remove_attributes_form_field');

		$attributes_field->replicators = $replicators;

		// TODO: if/when SwatActionItems can have better error handling on
		// embedded widgets, make the queueing actions only work in the future.
		// For now, make the valid date range sane, and if someone mistaken sets
		// a date in the past, the system script will do it anyhow.
		$actions = $this->ui->getWidget('index_actions');
		foreach ($actions->getDescendants('SwatDateEntry') as $entry) {
			$entry->setValidRange(0, 1);
		};
	}

	// }}}

	// process phase
	// {{{ protected function processInternal()

	protected function processInternal()
	{
		parent::processInternal();

		if ($this->layout->menu instanceof StoreAdminMenuView) {
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
	// {{{ protected function processActions()

	protected function processActions(SwatTableView $view, SwatActions $actions)
	{
		$flush_memcache = false;
		$item_list      = array();
		foreach ($view->getSelection() as $item)
			$item_list[] = $this->app->db->quote($item, 'integer');

		// if nothing is selected, we have no actions to process
		if (count($item_list) == 0)
			return;

		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('Product/Delete');
			$this->app->getPage()->setItems($view->getSelection());
			break;

		case 'add_attributes' :
			$flush_memcache = $this->processAddAttributes($view, $item_list);
			break;

		case 'remove_attributes_action' :
			$flush_memcache = $this->processRemoveAttributes($view, $item_list);
			break;

		case 'add_sale_discount' :
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

			$flush_memcache = true;

			break;
		case 'remove_sale_discount' :
			$num = SwatDB::queryOne($this->app->db, sprintf(
				'select count(id) from Item where product in (%s)
					and sale_discount is not null',
				SwatDB::implodeSelection($this->app->db,
					$view->getSelection())));

			if ($num > 0) {
				SwatDB::updateColumn($this->app->db, 'Item',
					'integer:sale_discount', null, 'product', $item_list);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'A sale discount has been removed from one item.',
					'A sale discount has been removed from %s items.', $num),
					SwatString::numberFormat($num)));

				$this->app->messages->add($message);

				$flush_memcache = true;
			} else {
				$this->app->messages->add(new SwatMessage(Store::_(
					'None of the items selected had a sale discount.')));
			}

			break;

		case 'item_minimum_quantity_group' :
			$value = $this->ui->getWidget(
				'item_minimum_quantity_group_flydown')->value;

			if ($value !== null) {
				$num = SwatDB::queryOne($this->app->db, sprintf(
					'select count(id) from Item where product in (%s)',
					SwatDB::implodeSelection($this->app->db,
						$view->getSelection())));

				SwatDB::updateColumn($this->app->db, 'Item',
					'integer:minimum_quantity_group', $value,
					'product', $item_list);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One item has been added to a minimum quantity sale group.',
					'%s items have been added to a minimum quantity sale group.',
					$num),
					SwatString::numberFormat($num)));

				$this->app->messages->add($message);

				$flush_memcache = true;
			}

			break;
		case 'remove_item_minimum_quantity_group' :
			$num = SwatDB::queryOne($this->app->db, sprintf(
				'select count(id) from Item where product in (%s)
					and Item.minimum_quantity_group is not null',
				SwatDB::implodeSelection($this->app->db,
					$view->getSelection())));

			if ($num > 0) {
				SwatDB::updateColumn($this->app->db, 'Item',
					'integer:minimum_quantity_group', null,
					'product', $item_list);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One item has been removed from a %s.',
					'%s items have been removed from a %s.',
					$num),
					SwatString::numberFormat($num),
					Store::_('minimum quantity sale group')));

				$flush_memcache = true;
			} else {
				$message = new SwatMessage(Store::_('None of the items '.
					'selected had a minimum quantity sale group.'));
			}

			$this->app->messages->add($message);

			break;
		}

		if ($flush_memcache === true && isset($this->app->memcache)) {
			$this->app->memcache->flushNs('product');
		}
	}

	// }}}
	// {{{ protected function processAddAttributes()

	protected function processAddAttributes(SwatTableView $view,
		array $products)
	{
		$flush_memcache = false;
		$attributes     = $this->getAttributeArray('attributes');

		if (count($attributes) == 0)
			return $flush_memcache;

		if ($this->ui->getWidget('attributes_action_date')->value
			instanceof SwatDate) {
			$this->queueProductAttributes($products, $attributes,
				$this->ui->getWidget('attributes_action_date')->value, 'add');
		} else {
			$flush_memcache = $this->addProductAttributes($products,
				$attributes);
		}

		return $flush_memcache;
	}

	// }}}
	// {{{ protected function processRemoveAttributes()

	protected function processRemoveAttributes(SwatTableView $view,
		array $products)
	{
		$flush_memcache = false;
		$attributes     = $this->getAttributeArray('remove_attributes');

		if (count($attributes) == 0)
			return $flush_memcache;

		if ($this->ui->getWidget('remove_attributes_action_date')->value
			instanceof SwatDate) {
			$this->queueProductAttributes($products, $attributes,
				$this->ui->getWidget('remove_attributes_action_date')->value,
				'remove');
		} else {
			$flush_memcache = $this->removeProductAttributes($products,
				$attributes);
		}

		return $flush_memcache;
	}

	// }}}
	// {{{ protected function getAttributeArray()

	protected function getAttributeArray($widget_title, $form_field_title = '')
	{
		$attribute_array = array();
		if ($form_field_title == '')
			$form_field_title = $widget_title.'_form_field';

		$attributes_field = $this->ui->getWidget($form_field_title);

		foreach ($attributes_field->replicators as $id => $title) {
			foreach ($attributes_field->getWidget($widget_title, $id)->values as
				$value) {
				$attribute_array[] = $this->app->db->quote($value, 'integer');
			}
		}

		return $attribute_array;
	}

	// }}}
	// {{{ private function addProductAttributes()

	private function addProductAttributes(array $products, array $attributes)
	{
		$flush_memcache = false;

		$sql = sprintf('delete from ProductAttributeBinding
			where product in (%s) and attribute in (%s)',
			implode(',', $products), implode(',', $attributes));

		$delete_count = SwatDB::exec($this->app->db, $sql);

		$sql = sprintf('insert into ProductAttributeBinding
			(product, attribute)
			select Product.id, Attribute.id
			from Product cross join Attribute
			where Product.id in (%s) and Attribute.id in (%s)',
			implode(',', $products), implode(',', $attributes));

		$add_count = SwatDB::exec($this->app->db, $sql);

		if ($add_count != $delete_count) {
			$flush_memcache = true;
		}

		// TODO: we could have better messages for this that gave accurate
		// numbers of products updated, versus ones that already had said
		// attributes.
		$message = new SwatMessage(sprintf(Store::ngettext(
			'One product has had %2$s %3$s added.',
			'%1$s products has had %2$s %3$s added.', count($products)),
			SwatString::numberFormat(count($products)),
			SwatString::numberFormat(count($attributes)),
			Store::ngettext('attribute', 'attributes',
				count($attributes))));

		$this->app->messages->add($message);

		return $flush_memcache;
	}

	// }}}
	// {{{ private function removeProductAttributes()

	private function removeProductAttributes(array $products, array $attributes)
	{
		$flush_memcache = false;

		$sql = sprintf('delete from ProductAttributeBinding
			where product in (%s) and attribute in (%s)',
			implode(',', $products), implode(',', $attributes));

		$count = SwatDB::exec($this->app->db, $sql);

		if ($count > 0) {
			$flush_memcache = true;

			// TODO: we could have better messages for this that gave accurate
			// numbers of products updated, versus ones that already had said
			// attributes.
			$message = new SwatMessage(sprintf(Store::ngettext(
				'One product has had %2$s %3$s removed.',
				'%1$s products have had %2$s %3$s removed.', count($products)),
				SwatString::numberFormat(count($products)),
				SwatString::numberFormat(count($attributes)),
				Store::ngettext('attribute', 'attributes',
					count($attributes))));
		} else {
			$message = new SwatMessage(Store::_(
				'None of the products selected had attributes to remove.'));
		}

		$this->app->messages->add($message);

		return $flush_memcache;
	}

	// }}}
	// {{{ private function queueProductAttributes()

	private function queueProductAttributes(array $products, array $attributes,
		SwatDate $action_date, $queue_action)
	{
		// build message first to avoid converting the date to UTC and then back
		$message = new SwatMessage(sprintf(Store::ngettext(
			'One product will have %2$s %3$s %4$s on %5$s at %6$s.',
			'%1$s products will have %2$s %3$s %4$s on %5$s at %6$s.',
				count($products)),
			SwatString::numberFormat(count($products)),
			SwatString::numberFormat(count($attributes)),
			Store::ngettext('attribute', 'attributes',
				count($attributes)),
			($queue_action == 'add') ? Store::_('added') : Store::_('removed'),
			$action_date->formatLikeIntl(SwatDate::DF_DATE),
			$action_date->formatLikeIntl(SwatDate::DF_TIME)));

		$action_date->setTZ($this->app->default_time_zone);
		$action_date->toUTC();

		$sql = sprintf('insert into ProductAttributeBindingQueue
			(product, attribute, queue_action, action_date)
			select Product.id, Attribute.id, %s, %s
			from Product cross join Attribute
			where Product.id in (%s) and Attribute.id in (%s)',
			$this->app->db->quote($queue_action, 'text'),
			$this->app->db->quote($action_date, 'date'),
			implode(',', $products),
			implode(',', $attributes));

		SwatDB::exec($this->app->db, $sql);

		$this->app->messages->add($message);
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$this->ui->getWidget('toolbar')->visible = true;

		$this->ui->getWidget('search_items_disclosure')->open =
			($this->ui->getWidget('search_items')->value != '');

		$category_flydown = $this->ui->getWidget('search_category');

		$tree = $category_flydown->getTree();
		$tree->addChild(new SwatTreeFlydownNode(-1, '<uncategorized>'));
		$tree->addChild(new SwatTreeFlydownNode(new SwatFlydownDivider()));

		$rs = SwatDB::executeStoredProc($this->app->db, 'getCategoryTree',
			'null');

		$category_tree = SwatDB::getDataTree($rs, 'title', 'id', 'levelnum');
		$tree->addTree($category_tree);

		$this->buildAttributes('attributes_form_field', 'attributes');
		$this->buildAttributes('remove_attributes_form_field',
			'remove_attributes');
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
					Catalog.title as catalog_title,
					ProductItemCountByStatusView.*
				from Product
					inner join Catalog on Product.catalog = Catalog.id
					inner join ProductItemCountByStatusView on
						ProductItemCountByStatusView.product = Product.id
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
	// {{{ protected function displayAttribute()

	protected function displayAttribute(StoreAttribute $attribute)
	{
		$attribute->display();
	}

	// }}}
	// {{{ private function buildAttributes()

	private function buildAttributes($form_field_id, $check_list_id)
	{
		$sql = 'select id, shortname, title, attribute_type from Attribute
			order by attribute_type, displayorder, id';

		$attributes = SwatDB::query($this->app->db, $sql,
			SwatDBClassMap::get('StoreAttributeWrapper'));

		$attributes_field = $this->ui->getWidget($form_field_id);

		foreach ($attributes as $attribute) {
			ob_start();
			$this->displayAttribute($attribute);
			$option = ob_get_clean();

			$attributes_field->getWidget($check_list_id,
				$attribute->attribute_type->id)->addOption(
					$attribute->id, $option, 'text/xml');
		}
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
