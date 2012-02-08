<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Store/dataobjects/StoreRegion.php';
require_once 'Store/dataobjects/StoreItemWrapper.php';
require_once 'Store/dataobjects/StoreCartEntry.php';

/**
 * An item in an order
 *
 * A single order contains multiple order items. An order item contains all
 * price, product, quantity and discount information from when the order was
 * placed. An order item is a combination of important fields from an item,
 * a cart entry and a product.
 *
 * You can automatically create StoreOrderItem objects from StoreCartEntry
 * objects using the {@link StoreCartEntry::createOrderItem()} method.
 *
 * @package   Store
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreCartEntry::createOrderItem()
 */
class StoreOrderItem extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * Merchant's stocking keeping unit (SKU)
	 *
	 * @var string
	 */
	public $sku;

	/**
	 * Sku Alias
	 *
	 * @var string
	 */
	public $alias_sku;

	/**
	 * Quantity
	 *
	 * @var integer
	 */
	public $quantity;

	/**
	 * Price
	 *
	 * @var float
	 */
	public $price;

	/**
	 * Whether or not this item has a custom-overide price
	 *
	 * @var boolean
	 */
	public $custom_price;

	/**
	 * Description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Extension
	 *
	 * @var float
	 */
	public $extension;

	/**
	 * Item identifier
	 *
	 * @var integer
	 */
	public $item;

	/**
	 * Product identifier
	 *
	 * @var integer
	 */
	public $product;

	/**
	 * Product title
	 *
	 * @var string
	 */
	public $product_title;

	/**
	 * Catalog id
	 *
	 * @var integer
	 */
	public $catalog;

	/**
	 * Where this order item was created.
	 *
	 * Uses StoreCartEntry::SOURCE_* constants
	 *
	 * @var integer
	 * @see StoreCartEntry
	 */
	public $source;

	/**
	 * Category related to  the source of this order item.
	 *
	 * @var integer
	 * @see StoreCartEntry
	 */
	public $source_category;

	/**
	 * Whether or not this item was ordered through the quick-order tool
	 *
	 * @var boolean
	 */
	public $quick_order;

	/**
	 * Sale discount identifier
	 *
	 * @var integer
	 */
	public $sale_discount;

	/**
	 * Discount off normal price
	 *
	 * @float
	 */
	public $discount;

	/**
	 * Discount extension
	 *
	 * @float
	 */
	public $discount_extension;

	// }}}
	// {{{ protected properties

	/**
	 * Cart entry id this order item was created from.
	 *
	 * @var integer
	 */
	protected $cart_entry_id = null;

	// }}}
	// {{{ public function getDescription()

	/**
	 * Gets the description for this order item
	 *
	 * @return string the description for this order item.
	 */
	public function getDescription()
	{
		return $this->description;
	}

	// }}}
	// {{{ public function setCartEntryId()

	public function setCartEntryId($id)
	{
		$this->cart_entry_id = $id;
	}

	// }}}
	// {{{ public function getCartEntryId()

	public function getCartEntryId()
	{
		return $this->cart_entry_id;
	}

	// }}}
	// {{{ public function getAvailableItemId()

	/**
	 * Gets the id of the item belonging to this order item if the item is
	 * still available on the site
	 *
	 * @param StoreRegion $region the region to get the item in.
	 *
	 * @return integer the id of the item belonging to this order item or null
	 *                  if no such item exists.
	 */
	public function getAvailableItemId(StoreRegion $region)
	{
		$sql = 'select Item.id from Item
			inner join AvailableItemView
				on AvailableItemView.item = Item.id
				and AvailableItemView.region = %s
			where Item.id = %s';

		$sql = sprintf($sql,
			$this->db->quote($region->id, 'integer'),
			$this->db->quote($this->item, 'integer'));

		$id = SwatDB::queryOne($this->db, $sql);

		if ($id === null) {
			$sql = 'select Item.id from Item
				inner join AvailableItemView
					on AvailableItemView.item = Item.id
					and AvailableItemView.region = %s
				where Item.sku = %s';

			$sql = sprintf($sql,
				$this->db->quote($region->id, 'integer'),
				$this->db->quote($this->sku, 'text'));

			$id = SwatDB::queryOne($this->db, $sql);
		}

		return $id;
	}

	// }}}
	// {{{ public function getAvailableItem()

	/**
	 * Gets StoreItem belonging to this order item if the item is
	 * still available on the site
	 *
	 * @param StoreRegion $region the region to get the item in.
	 *
	 * @return StoreItem The currently available item related to this order
	 *                   item.
	 */
	public function getAvailableItem(StoreRegion $region)
	{
		$item = null;

		$sql = 'select Item.* from Item
			inner join AvailableItemView
				on AvailableItemView.item = Item.id
				and AvailableItemView.region = %s
			where Item.id = %s';

		$sql = sprintf($sql,
			$this->db->quote($region->id, 'integer'),
			$this->db->quote($this->item, 'integer'));

		$items = SwatDB::query($this->db, $sql,
			SwatDBClassMap::get('StoreItemWrapper'));

		if (count($items) > 0)
			$item = $items->getFirst();

		if ($item === null) {
			$sql = 'select Item.* from Item
				inner join AvailableItemView
					on AvailableItemView.item = Item.id
					and AvailableItemView.region = %s
				where Item.sku = %s';

			$sql = sprintf($sql,
				$this->db->quote($region->id, 'integer'),
				$this->db->quote($this->sku, 'text'));

			$items = SwatDB::query($this->db, $sql,
				SwatDBClassMap::get('StoreItemWrapper'));

			if (count($items) > 0)
				$item = $items->getFirst();
		}

		return $item;
	}

	// }}}
	// {{{ public function getItem()

	/**
	 * Gets StoreItem belonging to this order item.
	 *
	 * @return StoreItem The item ordered or null it it no longer exists.
	 */
	public function getItem()
	{
		$item = null;

		$sql = 'select Item.* from Item where Item.id = %s';

		$sql = sprintf($sql,
			$this->db->quote($this->item, 'integer'));

		$items = SwatDB::query($this->db, $sql,
			SwatDBClassMap::get('StoreItemWrapper'));

		if (count($items) > 0) {
			$item = $items->getFirst();
		}

		return $item;
	}

	// }}}
	// {{{ public function getSourceCategoryTitle()

	public function getSourceCategoryTitle()
	{
		$title = null;

		if ($this->source_category !== null) {
			$this->checkDB();

			$sql = sprintf('select title from Category where id = %s',
				$this->source_category);

			$title = SwatDB::queryOne($this->db, $sql);
		}

		return $title;
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerInternalProperty('ordernum',
			SwatDBClassMap::get('StoreOrder'));

		$this->registerDeprecatedProperty('quick_order');

		$this->table = 'OrderItem';
		$this->id_field = 'integer:id';
	}

	// }}}
	// {{{ protected function getSerializablePrivateProperties()

	protected function getSerializablePrivateProperties()
	{
		$properties = parent::getSerializablePrivateProperties();
		$properties[] = 'cart_entry_id';
		return $properties;
	}

	// }}}
}

?>
