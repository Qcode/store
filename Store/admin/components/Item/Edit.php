<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'Admin/exceptions/AdminNoAccessException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatMessage.php';
require_once 'Swat/SwatYUI.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Store/StoreItemStatusList.php';
require_once 'Store/dataobjects/StoreItem.php';
require_once 'Store/dataobjects/StoreRegionWrapper.php';

/**
 * Edit page for Items
 *
 * @package   Store
 * @copyright 2005-2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreItemEdit extends AdminDBEdit
{
	// {{{ protected properties

	protected $ui_xml = 'Store/admin/components/Item/edit.xml';
	protected $product;
	protected $item;

	// }}}
	// {{{ private properties

	/**
	 * Used to build the navbar.
	 *
	 * If the user navigated to this page from the Product Categories page then
	 *  then this variable will be set and will cause the navbar to display
	 *  differently.
	 *
	 * @var integer
	 */
	private $category_id;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->ui_xml);

		$this->product     = SiteApplication::initVar('product');
		$this->category_id = SiteApplication::initVar('category');

		$this->initItem();

		if ($this->product === null && $this->item->id === null)
			throw new AdminNoAccessException(Store::_(
				'A product ID or an item ID must be passed in the URL.'));

		$status_radiolist = $this->ui->getWidget('status');
		foreach (StoreItemStatusList::statuses() as $status) {
			$status_radiolist->addOption(
				new SwatOption($status->id, $status->title));
		}

		$regions = SwatDB::getOptionArray($this->app->db, 'Region', 'title',
			'id', 'title');

		$price_replicator = $this->ui->getWidget('price_replicator');
		$price_replicator->replicators = $regions;

		$form = $this->ui->getWidget('edit_form');
		$form->addHiddenField('product', $this->product);
	}

	// }}}
	// {{{ protected function initItem()

	protected function initItem()
	{
		$class_name = SwatDBClassMap::get('StoreItem');
		$this->item = new $class_name();
		$this->item->setDatabase($this->app->db);

		if ($this->id !== null) {
			if (!$this->item->load($this->id))
				throw new AdminNotFoundException(
					sprintf(Store::_('Item with id "%s" not found.'),
						$this->id));
		}
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		/*
		 * Pre-process "enabled" checkboxes to set required flag on price
		 * entries.  Also set correct locale on the Price Entry.
		 */
		$sql = 'select id, title from Region order by Region.id';
		$regions = SwatDB::query($this->app->db, $sql,
			SwatDBClassMap::get('StoreRegionWrapper'));

		$replicator = $this->ui->getWidget('price_replicator');

		foreach ($regions as $region) {
			$enabled_widget = $replicator->getWidget('enabled', $region->id);
			$enabled_widget->process();

			$price_widget = $replicator->getWidget('price', $region->id);
			$price_widget->required = $enabled_widget->value;
			$price_widget->locale = $region->getFirstLocale()->id;
		}

		parent::process();
	}

	// }}}
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$this->updateItem();
		$this->item->save();
		$this->addToSearchQueue();
		$this->saveItemRegionFields();

		$message = new SwatMessage(
			sprintf(Store::_('“%s” has been saved.'), $this->item->sku));

		$this->app->messages->add($message);
	}

	// }}}
	// {{{ protected function updateItem()

	protected function updateItem()
	{
		$values = $this->ui->getValues(array('description', 'sku', 'status'));

		$this->item->sku         = $values['sku'];
		$this->item->status      = $values['status'];
		$this->item->description = $values['description'];
		$this->item->product     = $this->product;
	}

	// }}}
	// {{{ protected function validate()

	protected function validate()
	{
		$sql = sprintf('select catalog from Item
				inner join Product on Item.product = Product.id
				where Item.id %s %s',
			SwatDB::equalityOperator($this->id),
			$this->app->db->quote($this->id, 'integer'));

		$catalog = SwatDB::queryOne($this->app->db, $sql);

		// validate main sku
		$sku = $this->ui->getWidget('sku');
		$valid =
			($this->item->sku !== null) ? array($this->item->sku) : array();

		if (!StoreItem::validateSku($this->app->db, $sku->value, $catalog,
			$this->product, $valid)) {
			$sku->addMessage(new SwatMessage(
				Store::_('%s must be unique amongst all catalogs unless '.
				'catalogs are clones of each other.')));
		}
	}

	// }}}
	// {{{ protected function addToSearchQueue()

	protected function addToSearchQueue()
	{
		$sql = sprintf('delete from NateGoSearchQueue
			where document_id = %s and document_type = %s',
			$this->app->db->quote($this->product, 'integer'),
			$this->app->db->quote(2, 'integer'));

		SwatDB::exec($this->app->db, $sql);

		$sql = sprintf('insert into NateGoSearchQueue
			(document_id, document_type) values (%s, %s)',
			$this->app->db->quote($this->product, 'integer'),
			$this->app->db->quote(2, 'integer'));

		SwatDB::exec($this->app->db, $sql);
	}

	// }}}
	// {{{ protected function saveItemRegionFields()

	protected function saveItemRegionFields()
	{
		/*
		 * NOTE: This stuff is automatically wrapped in a transaction in
		 *       AdminDBEdit::saveData()
		 *
		 * Once upon a time, we checked to see if there was an entry in the
		 * ItemRegionBinding table per region to see if the item was enabled in
		 * the region, but realized this meant we dropped any pricing data
		 * upon disabling, which sucks.  So now we use the enabled bit on the
		 * row, and hence we always insert the row, regardless of whether price
		 * is null
		 */

		$delete_sql = 'delete from ItemRegionBinding where item = %s';
		$delete_sql = sprintf($delete_sql,
			$this->app->db->quote($this->item->id, 'integer'));

		SwatDB::exec($this->app->db, $delete_sql);

		$insert_sql = 'insert into ItemRegionBinding
			(item, region, price, enabled)
			values (%s, %%s, %%s, %%s)';

		$insert_sql = sprintf($insert_sql,
			$this->app->db->quote($this->item->id, 'integer'));

		$price_replicator = $this->ui->getWidget('price_replicator');

		foreach ($price_replicator->replicators as $region => $title) {
			$price_field = $price_replicator->getWidget('price', $region);
			$enabled_field = $price_replicator->getWidget('enabled', $region);

			$sql = sprintf($insert_sql,
				$this->app->db->quote($region, 'integer'),
				$this->app->db->quote($price_field->value, 'decimal'),
				$this->app->db->quote($enabled_field->value, 'boolean'));

			SwatDB::query($this->app->db, $sql);
		}
	}

	// }}}

	// build phase
	// {{{ protected function display()

	protected function display()
	{
		parent::display();
		Swat::displayInlineJavaScript($this->getInlineJavaScript());
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		$this->navbar->popEntry();

		if ($this->category_id === null) {
			$this->navbar->addEntry(new SwatNavBarEntry(
				Store::_('Product Search'), 'Product'));

		} else {
			$this->navbar->addEntry(new SwatNavBarEntry(
				Store::_('Product Categories'), 'Category'));

			$cat_navbar_rs = SwatDB::executeStoredProc($this->app->db,
				'getCategoryNavbar', array($this->category_id));

			foreach ($cat_navbar_rs as $entry)
				$this->navbar->addEntry(new SwatNavBarEntry($entry->title,
					'Category/Index?id='.$entry->id));
		}

		$product_title = SwatDB::queryOneFromTable($this->app->db, 'Product',
			'text:title', 'id', $this->product);

		if ($this->category_id === null)
			$link = sprintf('Product/Details?id=%s', $this->product);
		else
			$link = sprintf('Product/Details?id=%s&category=%s',
				$this->product, $this->category_id);

		$this->navbar->addEntry(new SwatNavBarEntry($product_title, $link));
		$this->title = $product_title;

		if ($this->id === null)
			$this->navbar->addEntry(new SwatNavBarEntry(Store::_('New Item')));
		else
			$this->navbar->addEntry(new SwatNavBarEntry(Store::_('Edit Item')));
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		$this->ui->setValues(get_object_vars($this->item));
		// TODO: descriptions aren't getting set the same as the table in
		// Product/Details becasue they aren't both using $item->description

		$this->product = $this->item->getInternalValue('product');
		$form = $this->ui->getWidget('edit_form');
		$form->addHiddenField('product', $this->product);

		$this->loadReplicators();
	}

	// }}}
	// {{{ protected function getInlineJavaScript()

	protected function getInlineJavaScript()
	{
		$price_replicator = $this->ui->getWidget('price_replicator');
		$replicator_ids = array_keys($price_replicator->replicators);
		$replicator_ids = implode(', ', $replicator_ids);
		$form_id = 'edit_form';
		return sprintf(
			"var item_edit_page = new StoreItemEditPage('%s', [%s]);",
			$form_id,
			$replicator_ids);
	}

	// }}}
	// {{{ private function loadReplicators()

	private function loadReplicators()
	{
		$price_replicator = $this->ui->getWidget('price_replicator');

		$sql = sprintf('select Region.id as region, price, enabled
			from Region
			left outer join ItemRegionBinding on
				ItemRegionBinding.region = Region.id
				and item = %s',
			$this->app->db->quote($this->id, 'integer'));

		$rs = SwatDB::query($this->app->db, $sql);
		foreach ($rs as $row) {
			$price_replicator->getWidget('price', $row->region)->value =
				$row->price;

			$price_replicator->getWidget('enabled', $row->region)->value =
				$row->enabled;
		}
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$yui = new SwatYUI(array('dom', 'event'));
		$this->layout->addHtmlHeadEntrySet($yui->getHtmlHeadEntrySet());

		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			'packages/store/admin/javascript/store-item-edit-page.js',
			Store::PACKAGE_ID));

		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/admin/styles/store-item-edit-page.css',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
