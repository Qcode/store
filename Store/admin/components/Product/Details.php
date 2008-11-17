<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatHtmlTag.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Swat/SwatMoneyEntry.php';
require_once 'Swat/SwatNavBar.php';
require_once 'Admin/pages/AdminIndex.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Store/StoreItemStatusList.php';
require_once 'Store/dataobjects/StoreProduct.php';
require_once 'Store/dataobjects/StoreRegionWrapper.php';
require_once 'Store/dataobjects/StoreItemWrapper.php';
require_once 'Store/dataobjects/StoreProductImageWrapper.php';
require_once 'Store/dataobjects/StoreAttributeTypeWrapper.php';
require_once 'Store/admin/components/Product/include/StoreItemTableView.php';
require_once 'Store/admin/components/Product/include/StoreItemGroupGroup.php';
require_once 'Store/admin/components/Product/include/StoreItemGroupAction.php';
require_once
	'Store/admin/components/Product/include/StoreProductImageDisplay.php';
require_once
	'Store/admin/components/Item/include/StoreItemStatusCellRenderer.php';

require_once
	'Store/admin/components/Product/include/StoreItemDiscountCellRenderer.php';

require_once
	'Store/admin/components/Product/include/StoreAdminItemPriceCellRenderer.php';

/**
 * Details page for Products
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreProductDetails extends AdminIndex
{
	// {{{ protected properties

	protected $ui_xml = 'Store/admin/components/Product/details.xml';
	protected $id;
	protected $category_id;

	// }}}
	// {{{ private properties

	/**
	 * Cache of regions used by queryRegions()
	 *
	 * @var RegionsWrapper
	 */
	private $regions = null;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->ui_xml);

		$this->ui->getRoot()->addStyleSheet(
			'packages/store/admin/styles/store-product-details-page.css');

		$this->ui->getRoot()->addStyleSheet(
			'packages/store/admin/styles/store-image-preview.css');

		$this->id = SiteApplication::initVar('id');
		$this->category_id = SiteApplication::initVar('category', null,
			SiteApplication::VAR_GET);

		$this->ui->getWidget('item_group')->db = $this->app->db;
		$this->ui->getWidget('item_group')->product_id = $this->id;

		$regions = $this->queryRegions();
		$view = $this->ui->getWidget('items_view');

		// add dynamic columns to items view
		$this->appendPriceColumns($view, $regions);

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

		$related_products_form = $this->ui->getWidget('related_products_form');
		$related_products_view = $this->ui->getWidget('related_products_view');
		if ($related_products_form->isProcessed() &&
			count($related_products_view->getSelection()) != 0)
			$this->processRelatedProducts($related_products_view);

		// add new items
		if ($this->ui->getWidget('index_actions')->selected !== null &&
			$this->ui->getWidget('index_actions')->selected->id == 'add') {

			$this->addNewItems();
		}
	}

	// }}}
	// {{{ protected function processActions()

	protected function processActions(SwatTableView $view, SwatActions $actions)
	{
		switch ($view->id) {
		case 'items_view':
			$this->processItemsActions($view, $actions);
			break;
		}
	}

	// }}}
	// {{{ protected function processItemsActions()

	protected function processItemsActions(SwatTableView $view, SwatActions $actions)
	{
		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('Item/Delete');
			$this->app->getPage()->setItems($view->getSelection());
			break;

		case 'change_group':
			$item_group_action = $this->ui->getWidget('item_group');
			$message = $item_group_action->processAction($view->getSelection());
			$this->app->messages->add($message);
			break;

		case 'change_status':
			$new_status = $this->ui->getWidget('status')->value;
			$this->changeStatus($view, $new_status);
			break;

		case 'enable':
			$region = $this->ui->getWidget('enable_region')->value;

			$sql = 'update ItemRegionBinding set enabled = %s
				where price is not null and %s item in (%s)';

			$region_sql = ($region > 0) ?
				sprintf('region = %s and', $this->app->db->quote($region,
					'integer')) : '';

			SwatDB::exec($this->app->db, sprintf($sql,
				$this->app->db->quote(true, 'boolean'),
				$region_sql,
				SwatDB::implodeSelection($this->app->db,
					$view->getSelection())));

			$num = count($view->getSelection());

			$message = new SwatMessage(sprintf(Store::ngettext(
				'One item has been enabled.',
				'%d items have been enabled.', $num),
				SwatString::numberFormat($num)));

			$this->app->messages->add($message);
			break;

		case 'disable':
			$region = $this->ui->getWidget('disable_region')->value;

			$sql = 'update ItemRegionBinding set enabled = %s
				where %s item in (%s)';

			$region_sql = ($region > 0) ?
				sprintf('region = %s and', $this->app->db->quote($region,
					'integer')) : '';

			SwatDB::exec($this->app->db, sprintf($sql,
				$this->app->db->quote(false, 'boolean'),
				$region_sql,
				SwatDB::implodeSelection($this->app->db,
					$view->getSelection())));

			$num = count($view->getSelection());

			$message = new SwatMessage(sprintf(Store::ngettext(
				'One item has been disabled.',
				'%d items have been disabled.', $num),
				SwatString::numberFormat($num)));

			$this->app->messages->add($message);
			break;

		case 'sale_discount' :
			$sale_discount =
				$this->ui->getWidget('sale_discount_flydown')->value;

			if ($sale_discount === null)
				break;

			SwatDB::updateColumn($this->app->db, 'Item',
				'integer:sale_discount', $sale_discount, 'id',
					$view->getSelection());

			$num = count($view->getSelection());
			$message = new SwatMessage(sprintf(Store::ngettext(
				'A sale discount has been applied to one item.',
				'A sale discount has been applied to %s items.', $num),
				SwatString::numberFormat($num)));

			$this->app->messages->add($message);

			break;
		}
	}

	// }}}
	// {{{ protected function validateItemRows()

	protected function validateItemRows($input_row, $catalog)
	{
		$validate = true;
		$replicators = $input_row->getReplicators();

		foreach ($replicators as $replicator_id) {
			// validate sku
			$sku_widget = $input_row->getWidget('sku', $replicator_id);
			if (!StoreItem::validateSku($this->app->db,
				$sku_widget->getState(), $catalog, $this->id)) {
				$sku_widget->addMessage(new SwatMessage(
					Store::_('%s must be unique amongst all catalogs unless '.
					'catalogs are clones of each other.')));

				$validate = false;
			}
		}

		return $validate;
	}

	// }}}
	// {{{ protected function addNewItemExtras()

	protected function addNewItemExtras($item_id)
	{
		/**
		 * this is a placeholder function, for the occasional case where a site
		 * would require that we insert rows into other tables on item creation
		 */
	}

	// }}}
	// {{{ protected final function changeStatus()

	protected final function changeStatus(SwatTableView $view, $status)
	{
		$num = count($view->getSelection());

		SwatDB::updateColumn($this->app->db, 'Item', 'integer:status', $status,
			'id', $view->getSelection());

		$message = new SwatMessage(sprintf(Store::ngettext(
			'The status of one item has been changed.',
			'The status of %s items has been changed.', $num),
			SwatString::numberFormat($num)));

		$this->app->messages->add($message);
	}

	// }}}
	// {{{ protected function addNewItems()

	protected function addNewItems()
	{
		$sql = sprintf('select catalog from Product where id = %s',
			$this->app->db->quote($this->id, 'integer'));

		$catalog = SwatDB::queryOne($this->app->db, $sql);

		$view = $this->ui->getWidget('items_view');
		$input_row = $view->getRow('input_row');

		$regions = $this->queryRegions();

		$fields = array(
			'text:sku',
			'text:description',
			'integer:product',
		);

		$item_region_fields = array(
			'integer:item',
			'integer:region',
			'decimal:price',
			'boolean:enabled',
		);

		if ($this->validateItemRows($input_row, $catalog)) {
			$new_skus = array();
			$replicators = $input_row->getReplicators();
			foreach ($replicators as $replicator_id) {
				if (!$input_row->rowHasMessage($replicator_id)) {
					$sku = $input_row->getWidget(
						'sku', $replicator_id)->value;

					$description = $input_row->getWidget(
						'description', $replicator_id)->value;

					// Create new item
					$values = array(
						'sku' => $sku,
						'description' => $description,
						'product' => $this->id,
					);

					$item_id = SwatDB::insertRow($this->app->db, 'Item',
						$fields, $values, 'id');

					foreach ($regions as $region) {
						$price = $input_row->getWidget('price_'.$region->id,
							$replicator_id);

						if ($price->getState() !== null) {
							// Create new item_region binding
							$item_region_values = array('item' => $item_id,
								'region' => $region->id,
								'price' => $price->getState(),
								'enabled' => true);

							SwatDB::insertRow($this->app->db,
								'ItemRegionBinding', $item_region_fields,
								$item_region_values);
						}
					}

					$this->addNewItemExtras($item_id);

					// remove the row after we entered it
					$input_row->removeReplicatedRow($replicator_id);

					$new_skus[] = SwatString::minimizeEntities($sku);
				}
			}

			if (count($new_skus) == 1) {
				$message = new SwatMessage(sprintf(
					Store::_('“%s” has been added.'), $new_skus[0]));

				$this->app->messages->add($message);
			} elseif (count($new_skus) > 1) {
				$sku_list = '<ul><li>'.implode('</li><li>', $new_skus).
					'</li></ul>';

				$message = new SwatMessage(
					Store::_('The following items have been added:'));

				$message->secondary_content = $sku_list;
				$message->content_type = 'text/xml';
				$this->app->messages->add($message);
			}
		} else {
			$message = new SwatMessage(Store::_('There was a problem adding '.
				'the item(s). Please check the highlighted fields below.'),
				SwatMessage::ERROR);

			$this->app->messages->add($message);
		}
	}

	// }}}
	// {{{ private function processRelatedProducts()

	private function processRelatedProducts($view)
	{
		$this->app->replacePage('Product/RelatedProductDelete');
		$this->app->getPage()->setItems($view->getSelection());
		$this->app->getPage()->setId($this->id);
		$this->app->getPage()->setCategory($this->category_id);
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();
		$this->buildProduct();
		$this->buildItems();
		$this->buildProductImages();
		$this->buildRelatedProducts();
	}

	// }}}
	// {{{ protected function buildForms()

	protected function buildForms()
	{
		parent::buildForms();

		// always show add new item action regardless of entries in item table
		// but also keep all other actions hidden
		if (count($this->ui->getWidget('items_view')->model) == 0) {
			$index_actions = $this->ui->getWidget('index_actions');
			$index_actions->visible = true;
			foreach ($index_actions->getActionItems() as $id => $widget)
				if ($widget->id !== 'add') $widget->visible = false;
		}
	}

	// }}}
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		switch ($view->id) {
			case 'items_view':
				return $this->getItemsTableModel($view);
			case  'related_products_view':
				return $this->getRelatedProductsTableModel($view);
		}
	}

	// }}}
	// {{{ protected function buildCategoryToolBarLinks()

	protected function buildCategoryToolBarLinks(SwatToolBar $toolbar)
	{
		if ($this->category_id === null) {
			$toolbar->setToolLinkValues($this->id);
		} else {
			foreach ($toolbar->getToolLinks() as $tool_link)
				if (substr($tool_link->link, -5) === 'id=%s' ||
					substr($tool_link->link, -10) === 'product=%s')
					$tool_link->link.= '&category=%s';

			$toolbar->setToolLinkValues(array($this->id, $this->category_id));
		}
	}

	// }}}
	// {{{ protected function buildCategoryTableViewLinks()

	protected function buildCategoryTableViewLinks(SwatTableView $view)
	{
		if ($this->category_id !== null) {
			$link_suffix = sprintf('&category=%s', $this->category_id);
			foreach ($view->getColumns() as $column)
				foreach ($column->getRenderers() as $renderer)
					if ($renderer instanceof SwatLinkCellRenderer)
						$renderer->link.= $link_suffix;

			foreach ($view->getGroups() as $group)
				foreach ($group->getRenderers() as $renderer)
					if ($renderer instanceof SwatLinkCellRenderer)
						$renderer->link.= $link_suffix;
		}
	}

	// }}}

	// build phase - product details
	// {{{ protected function getProductDetailsStore()

	protected function getProductDetailsStore($product)
	{
		$ds = new SwatDetailsStore($product);

		ob_start();
		$this->displayCategories($product->categories);
		$ds->categories = ob_get_clean();

		ob_start();
		$this->displayCategories($product->featured_categories);
		$ds->featured_categories = ob_get_clean();

		// format the bodytext
		$ds->bodytext = SwatString::condense(SwatString::toXHTML(
			$product->bodytext));

		$ds->attributes = $this->buildAttributes($product);

		return $ds;
	}

	// }}}
	// {{{ protected function buildAttributes()

	protected function buildAttributes($product)
	{
		if ($product->attributes === null)
			return;

		$types = SwatDB::query($this->app->db,
			'select * from attributetype order by shortname',
			SwatDBClassMap::get('StoreAttributeTypeWrapper'));

		$count = 0;
		ob_start();

		foreach ($types as $type) {
			$attributes = $product->attributes->getByType($type->shortname);

			if (count($attributes) > 0) {
				if ($count > 0)
					echo '</ul>';

				echo ucfirst($type->shortname), ':';
				echo '<ul>';

				foreach ($attributes as $attribute) {
					echo '<li>';
					$this->displayAttribute($attribute);
					echo '</li>';
				}

				echo '</ul>';
			}
			$count++;
		}

		return ob_get_clean();
	}

	// }}}
	// {{{ protected function displayAttribute()

	protected function displayAttribute(StoreAttribute $attribute)
	{
		$attribute->display();
	}

	// }}}
	// {{{ private function buildProduct()

	private function buildProduct()
	{
		$product = $this->loadProduct();

		$ds = $this->getProductDetailsStore($product);
		$details_view = $this->ui->getWidget('details_view');
		$details_view->data = $ds;

		$details_frame = $this->ui->getWidget('details_frame');
		$details_frame->title = Store::_('Product');
		$details_frame->subtitle = $product->title;
		$this->title = $product->title;

		$toolbar = $this->ui->getWidget('details_toolbar');
		$this->buildCategoryToolBarLinks($toolbar);
		$this->buildViewInStoreToolLinks($product);
		$this->buildProductNavBar($product);
	}

	// }}}
	// {{{ private function buildViewInStoreToolLinks()

	private function buildViewInStoreToolLinks(StoreProduct $product)
	{
		$some_category = $product->categories->getFirst();
		if ($some_category !== null) {
			$prototype_tool_link = $this->ui->getWidget('view_in_store');
			$toolbar = $prototype_tool_link->parent;
			$toolbar->remove($prototype_tool_link);
			$path = $some_category->path;

			foreach ($this->queryRegions() as $region) {
				$locale = $region->getFirstLocale();
				if ($locale !== null) {
					$sql = sprintf('select product from VisibleProductView
						where region = %s and product = %s',
						$this->app->db->quote($region->id, 'integer'),
						$this->app->db->quote($product->id, 'integer'));

					$visible_in_region =
						(SwatDB::queryOne($this->app->db, $sql) !== null);

					$tool_link = clone $prototype_tool_link;
					$tool_link->id.= '_'.$region->id;
					$tool_link->value = $locale->getURLLocale();
					$tool_link->value.= 'store/'.$path.'/'.$product->shortname;
					$tool_link->title.= sprintf(' (%s)', $region->title);
					if (!$visible_in_region)
						$tool_link->sensitive = false;

					$toolbar->packEnd($tool_link);
				}
			}
		}
	}

	// }}}
	// {{{ private function displayCategories()

	private function displayCategories($categories)
	{
		if (count($categories) == 1) {
			$category = $categories->getFirst();
			$navbar = new SwatNavBar();
			$navbar->addEntries($category->getAdminNavBarEntries());
			$navbar->display();

		// multiple categories, show in list
		} elseif (count($categories) > 1) {
			echo '<ul>';

			foreach ($categories as $category) {
				$navbar = new SwatNavBar();
				$navbar->addEntries($category->getAdminNavBarEntries());

				echo '<li>';
				$navbar->display();
				echo '</li>';
			}

			echo '</ul>';
		}
	}

	// }}}
	// {{{ protected function buildProductNavBar()

	protected function buildProductNavBar($product)
	{
		if ($this->category_id !== null) {
			// use category navbar
			$this->navbar->popEntry();
			$this->navbar->addEntry(new SwatNavBarEntry(
				Store::_('Product Categories'), 'Category'));

			$cat_navbar_rs = SwatDB::executeStoredProc($this->app->db,
				'getCategoryNavbar', array($this->category_id));

			foreach ($cat_navbar_rs as $entry)
				$this->navbar->addEntry(new SwatNavBarEntry($entry->title,
					'Category/Index?id='.$entry->id));
		}

		$this->navbar->addEntry(new SwatNavBarEntry($product->title));
	}

	// }}}
	// {{{ private function loadProduct()

	private function loadProduct()
	{
		$product_class = SwatDBClassMap::get('StoreProduct');
		$product = new $product_class();
		$product->setDatabase($this->app->db);

		if (!$product->load($this->id))
			throw new AdminNotFoundException(sprintf(
				Store::_('A product with an id of ‘%d’ does not exist.'),
				$this->id));

		return $product;
	}

	// }}}

	// build phase - items
	// {{{ protected function buildItems()

	protected function buildItems()
	{
		$view = $this->ui->getWidget('items_view');
		$toolbar = $this->ui->getWidget('items_toolbar');
		$form = $this->ui->getWidget('items_form');
		$view->addStyleSheet('packages/store/admin/styles/disabled-rows.css');
		$form->action = $this->getRelativeURL();
		//$this->ui->getWidget('index_actions')->setViewSelector($view);

		$this->buildItemGroups();

		// show default status for new items
		$input_status =
			$view->getColumn('status')->getInputCell()->getPrototypeWidget();

		$input_status->content =
			StoreItemStatusList::status('available')->title;

		$this->buildStatusList();

		// setup the flydowns for enabled/disabled actions
		$regions = SwatDB::getOptionArray($this->app->db, 'Region', 'title',
			'id');

		$regions[0] = Store::_('All Regions');

		$this->ui->getWidget('enable_region')->addOptionsByArray($regions);
		$this->ui->getWidget('disable_region')->addOptionsByArray($regions);

		$view->getColumn('quantity_discounts')->getRendererByPosition()->db =
			$this->app->db;

		$this->buildCategoryToolBarLinks($toolbar);
		$this->buildCategoryTableViewLinks($view);
	}

	// }}}
	// {{{ protected function buildStatusList()

	protected function buildStatusList()
	{
		foreach (StoreItemStatusList::statuses() as $status)
			$this->ui->getWidget('status')->addOption(
				new SwatOption($status->id, $status->title));
	}

	// }}}
	// {{{ protected function getItemsTableModel()

	protected function getItemsTableModel(SwatTableView $view)
	{
		$sql = $this->getItemsSql($view);
		$items = SwatDB::query($this->app->db, $sql,
			SwatDBClassMap::get('StoreItemWrapper'));

		$store = new SwatTableStore();

		foreach ($items as $item) {
			$ds = new SwatDetailsStore($item);

			$ds->description = $this->getItemDescription($item);
			$ds->status = $item->getStatus();

			$ds->item_group_title = ($item->item_group === null) ?
				Store::_('[Ungrouped]') : $item->item_group->title;

			$ds->item_group_id = ($item->item_group === null) ?
				0 : $item->item_group->id;

			$enabled = false;

			foreach ($this->queryRegions() as $region) {
				$price_field_name = sprintf('price_%s', $region->id);
				$enabled_field_name = sprintf('enabled_%s', $region->id);
				$is_on_sale_field_name = sprintf('is_on_sale_%s',
					$region->id);

				$original_price_field_name = sprintf('original_price_%s',
					$region->id);

				$ds->$price_field_name = $item->getDisplayPrice($region);
				$ds->$original_price_field_name = $item->getPrice($region);
				$ds->$enabled_field_name = $item->isEnabled($region);
				$ds->$is_on_sale_field_name =
					$ds->$price_field_name != $ds->$original_price_field_name;

				$enabled = $enabled || $ds->$enabled_field_name;
			}

			$ds->enabled = $enabled;

			$store->add($ds);
		}

		return $store;
	}

	// }}}
	// {{{ protected function getItemsSql()

	protected function getItemsSql(SwatTableView $view)
	{
		/*
		 * This dynamic SQL is needed to make the table orderable by the price
		 * columns.
		 */
		$regions = $this->queryRegions();

		$regions_join_base =
			'left outer join ItemRegionBinding as ItemRegionBinding_%1$s
				on ItemRegionBinding_%1$s.item = Item.id
					and ItemRegionBinding_%1$s.region = %2$s';

		$regions_select_base = 'ItemRegionBinding_%s.price as price_%s';

		$regions_join = '';
		$regions_select = '';
		foreach ($regions as $region) {
			$regions_join.= sprintf($regions_join_base,
				$region->id,
				$this->app->db->quote($region->id, 'integer')).' ';

			$regions_select.= sprintf($regions_select_base,
				$region->id,
				$this->app->db->quote($region->id, 'integer')).', ';
		}

		$sql = 'select Item.*,
					-- regions select piece goes here
					%s
					-- put ungrouped items at the top
					coalesce(ItemGroup.displayorder, -1) as group_order
				from Item
					left outer join ItemGroup
						on ItemGroup.id = Item.item_group
					-- region join piece goes here
					%s
				where Item.product = %s
				order by group_order, ItemGroup.title, Item.item_group, %s';

		$sql = sprintf($sql,
			$regions_select,
			$regions_join,
			$this->app->db->quote($this->id, 'integer'),
			$this->getOrderByClause($view, $this->getItemsOrderByClause()));

		return $sql;
	}

	// }}}
	// {{{ protected function getItemsOrderByClause()

	protected function getItemsOrderByClause()
	{
		return 'Item.displayorder, Item.sku';
	}

	// }}}
	// {{{ protected function getItemDescription()

	protected function getItemDescription(StoreItem $item)
	{
		return implode(' - ', $item->getDescriptionArray());
	}

	// }}}
	// {{{ protected final function queryRegions()

	protected final function queryRegions()
	{
		if ($this->regions === null) {
			$sql = 'select id, title from Region order by id';

			$this->regions = SwatDB::query($this->app->db, $sql,
				SwatDBClassMap::get('StoreRegionWrapper'));
		}

		return $this->regions;
	}

	// }}}
	// {{{ private function buildItemGroups()

	private function buildItemGroups()
	{
		$view = $this->ui->getWidget('items_view');
		$group_header = $view->getGroup('group');
		$groups = $this->queryItemGroups();
		$has_items = (count($groups) > 0);

		// if there is one row and the groupnum is 0 then there are no
		// item_groups with items in them for this product
		if (count($groups) == 0) {
			// there are no items
		} elseif (count($groups) == 1 && $groups->getFirst()->item_group == 0) {
			$num_groups = 0;
		} elseif ($groups->getFirst()->item_group == 0) {
			$num_groups = count($groups) - 1;
		} else {
			$num_groups = count($groups);
		}

		$group_info = array();
		foreach ($groups as $group)
			$group_info[$group->item_group] = $group->num_items;

		$group_header->group_info = $group_info;

		$order_link = $this->ui->getWidget('items_order');

		if ($has_items) {
			if ($num_groups == 0) {
				// order items link orders items
				// and don't show group headers
				$group_header->visible = false;
				// order link is insensitive if there is only 1 item
				$order_link->sensitive = ($groups->getFirst()->num_items > 1);
			} elseif ($num_groups == 1) {
				// order groups link is not sensitive.
				// order items through the group header
				$order_link->title = Store::_('Change Group Order');
				$order_link->sensitive = false;
			} else {
				// order items link orders item_groups
				$order_link->title = Store::_('Change Group Order');
				$order_link->link = 'ItemGroup/Order?product=%s';
			}
		} else {
			$order_link->sensitive = false;
		}
	}

	// }}}
	// {{{ private function queryItemGroups()

	private function queryItemGroups()
	{
		// get information about item groups used in this product
		$sql = 'select
					-- coalesce to 0 to match select query in getTableView()
					coalesce(item_group, 0) as item_group,
					count(id) as num_items
				from Item where
					product = %s and
					(item_group is null or
					item_group in (select id from ItemGroup where product = %s))
				group by item_group
				-- make sure the empty group is first
				order by item_group desc';

		$sql = sprintf($sql,
			$this->app->db->quote($this->id, 'integer'),
			$this->app->db->quote($this->id, 'integer'));

		return SwatDB::query($this->app->db, $sql);
	}

	// }}}
	// {{{ private function appendPriceColumns()

	private function appendPriceColumns(SwatTableView $view, $regions)
	{
		foreach ($regions as $region) {
			$column = new SwatTableViewOrderableColumn('price_'.$region->id);
			$column->title = sprintf(Store::_('%s Price'), $region->title);

			// discount renderer (only displayed if sale-discount is set)
			$discount_renderer = new SwatPercentageCellRenderer();
			$discount_renderer->locale = $region->getFirstLocale()->id;
			$column->addrenderer($discount_renderer);

			$column->addmappingtorenderer($discount_renderer,
				'sale_discount.discount_percentage', 'value');

			$column->addMappingToRenderer($discount_renderer,
				'is_on_sale_'.$region->id, 'visible');

			// " Off" cell renderer (only displayed if sale-discount is set)
			$off_renderer = new SwatTextCellRenderer();
			$off_renderer->text = Store::_(' Off');
			$column->addrenderer($off_renderer);
			$column->addMappingToRenderer($off_renderer,
				'is_on_sale_'.$region->id, 'visible');

			// original price renderer (only displayed if sale-discount is set)
			$sale_renderer = new StorePriceCellRenderer();
			$sale_renderer->locale = $region->getFirstLocale()->id;
			$sale_renderer->classes[] = 'store-sale-discount-original-price';
			$column->addrenderer($sale_renderer);

			$column->addmappingtorenderer($sale_renderer,
				'original_price_'.$region->id, 'value');

			$column->addMappingToRenderer($sale_renderer,
				'is_on_sale_'.$region->id, 'visible');

			// price renderer
			$price_renderer = new StoreAdminItemPriceCellRenderer();
			$price_renderer->locale = $region->getFirstLocale()->id;
			$column->addRenderer($price_renderer);

			$column->addMappingToRenderer($price_renderer,
				'price_'.$region->id, 'value');

			$column->addMappingToRenderer($price_renderer,
				'singular_unit', 'singular_unit');

			$column->addMappingToRenderer($price_renderer,
				'plural_unit', 'plural_unit');

			$column->addMappingToRenderer($price_renderer,
				'enabled_'.$region->id, 'enabled');


			$money_entry = new SwatMoneyEntry('input_price_'.$region->id);
			$money_entry->locale = $region->getFirstLocale()->id;
			$money_entry->size = 4;

			$cell = new SwatInputcell();
			$cell->setWidget($money_entry);

			$column->setInputCell($cell);

			$view->appendColumn($column);

			// need to manually init here
			$column->init();
		}
	}

	// }}}

	// build phase - product images
	// {{{ protected function buildProductImages()

	protected function buildProductImages()
	{
		$toolbar = $this->ui->getWidget('product_images_toolbar');
		$this->buildCategoryToolBarLinks($toolbar);

		$images = $this->getProductImages();
		$form = $this->ui->getWidget('product_images_form');

		$order_link = $this->ui->getWidget('image_order');
		$order_link->sensitive = (count($images) > 1);

		foreach ($images as $image) {
			$widget = $this->getProductImageDisplay();
			$widget->image_id = $image->id;
			$widget->category_id = $this->category_id;
			$widget->product_id = $this->id;
			$widget->image = $image->getUri('thumb', '../');
			$widget->width = $image->getWidth('thumb');
			$widget->height = $image->getHeight('thumb');
			$widget->alt = '';

			$form->addChild($widget);
		}
	}

	// }}}
	// {{{ protected function getProductImageDisplay()

	protected function getProductImageDisplay()
	{
		return new StoreProductImageDisplay();
	}

	// }}}
	// {{{ private function getProductImages()

	private function getProductImages()
	{
		$sql = 'select * from Image
			inner join ProductImageBinding on
				ProductImageBinding.image = Image.id
			where ProductImageBinding.product = %s
			order by displayorder';

		$sql = sprintf($sql,
			$this->app->db->quote($this->id, 'integer'));

		return SwatDB::query($this->app->db, $sql, 'StoreProductImageWrapper');
	}

	// }}}

	// build phase - related products
	// {{{ private function buildRelatedProducts()

	private function buildRelatedProducts()
	{
		$toolbar = $this->ui->getWidget('related_products_toolbar');
		$view = $this->ui->getWidget('related_products_view');
		$this->buildCategoryToolBarLinks($toolbar);
		$this->buildCategoryTableViewLinks($view);
	}

	// }}}
	// {{{ private function getRelatedProductsTableModel()

	private function getRelatedProductsTableModel(SwatTableView $view)
	{
		$sql = 'select id, title
			from Product
				inner join ProductRelatedProductBinding on id = related_product
					and source_product = %s
			order by title';

		$sql = sprintf($sql,
			$this->app->db->quote($this->id, 'integer'));

		$rs = SwatDB::query($this->app->db, $sql);

		if (count($rs) == 0) {
			$view->visible = false;
			$this->ui->getWidget('related_products_footer')->visible = false;
		}

		return $rs;
	}

	// }}}
}

?>
