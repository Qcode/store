<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Store/dataobjects/StoreItem.php';
require_once 'Store/dataobjects/StoreAccount.php';
require_once 'Store/dataobjects/StoreOrderItem.php';
require_once 'Store/dataobjects/StoreItemAlias.php';

/**
 * An entry in a shopping cart for an e-commerce web application
 *
 * All cart specific item information is stored in this object. This includes
 * things like special finishes or engraving information that is not specific
 * to an item, but is specific to an item in a customer's shopping cart.
 *
 * For specific sites, this class must be subclassed to provide specific
 * features. For example, on a site supporting the engraving of items, a
 * subclass of this class could have a getEngravingCost() method.
 *
 * The StoreCart*View classes handle all the displaying of StoreCartEntry
 * objects. StoreCartEntry must provide sufficient toString() methods to allow
 * the StoreCart*View classes to display cart entries. Remember when
 * subclassing this class to add these toString() methods.
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreCart
 */
class StoreCartEntry extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * A unique identifier of this cart entry
	 *
	 * The unique identifier is not always present on every cart entry.
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * The session this cart belongs to
	 *
	 * If this cart does not belong to an account, it must belong to a session.
	 *
	 * @var string
	 */
	public $sessionid;

	/**
	 * Number of individual items in this cart entry
	 *
	 * This does not represent the number of StoreItem objects in this cart
	 * entry -- that number is always one. This number instead represents the
	 * quantity of the StoreItem that the customer has added to their cart.
	 *
	 * @var integer
	 */
	public $quantity;

	/**
	 * Whether or not this cart entry is saved for later
	 *
	 * Entries that are saved for later are not included in orders.
	 *
	 * @var boolean
	 */
	public $saved;

	/**
	 * Whether or not this cart entry was created on the quick order page
	 *
	 * @var boolean
	 */
	public $quick_order;

	// }}}
	// {{{ public function getQuantity()

	/**
	 * Gets the number of items this cart entry represents
	 *
	 * @return integer the number of items this cart entry represents.
	 */
	public function getQuantity()
	{
		return $this->quantity;
	}

	// }}}
	// {{{ public function setQuantity()

	/**
	 * Sets the number of items this cart entry represents
	 *
	 * @param integer $quantity the new quantity of this entry's item.
	 */
	public function setQuantity($quantity)
	{
		$this->quantity = (integer)$quantity;
	}

	// }}}
	// {{{ public function getItemId()

	/**
	 * Gets the id of the item in this cart entry
	 *
	 * @return integer the id of the item of this cart entry.
	 */
	public function getItemId()
	{
		return $this->item->id;
	}

	// }}}
	// {{{ public function getQuantityDiscountedItemPrice()

	/**
	 * Gets the unit cost of the StoreItem with quantity discounts
	 *
	 * The unit cost is calculated using the current quantity and quantity
	 * discounts.
	 *
	 * @return double the unit cost of the StoreItem for this cart entry.
	 */
	public function getQuantityDiscountedItemPrice()
	{
		$price = $this->item->getPrice();

		// This relies on the ordering of quantity discounts. They are ordered
		// with the smallest quantity first.
		foreach ($this->item->quantity_discounts as $quantity_discount) {
			if ($this->getQuantity() >= $quantity_discount->quantity)
				$price = $quantity_discount->getPrice();
		}

		return $price;
	}

	// }}}
	// {{{ public function getQuantityDiscountedExtension()

	/**
	 * Gets the extension cost of this cart entry with only qunatity discounts.
	 *
	 * The cost is calculated with quantity discounts.
	 *
	 * @return double the extension cost of this cart entry.
	 */
	public function getQuantityDiscountedExtension()
	{
		return ($this->getQuantityDiscountedItemPrice() * $this->getQuantity());
	}

	// }}}
	// {{{ public function getCalculatedItemPrice()

	/**
	 * Gets the unit cost of the StoreItem for this cart entry
	 *
	 * The unit cost is calculated based on discounts.
	 *
	 * @return double the unit cost of the StoreItem for this cart entry.
	 */
	public function getCalculatedItemPrice()
	{
		$price = $this->getQuantityDiscountedItemPrice();

		$sale = $this->item->sale_discount;
		if ($sale !== null)
			$price = round($price * (1 - $sale->discount_percentage), 2);

		return $price;
	}

	// }}}
	// {{{ public function getDiscount()

	/**
	 * Gets how much money is saved by discounts
	 *
	 * Discounts include all types of discount schemes. By default, this is
	 * quantity discounts. Subclasses are encouraged to account for other
	 * site-specific discounts in this method.
	 *
	 * @return double how much money is saved from discounts or zero if no
	 *                 discount applies.
	 */
	public function getDiscount()
	{
		return $this->item->getPrice() - $this->getCalculatedItemPrice();
	}

	// }}}
	// {{{ public function getDiscountExtension()

	/**
	 * Gets how much total money is saved by discounts
	 *
	 * @return double how much money is saved from discounts or zero if no
	 *                 discount applies.
	 *
	 * @see StoreCartEntry::getDiscount()
	 */
	public function getDiscountExtension()
	{
		return $this->getDiscount() * $this->getQuantity();
	}

	// }}}
	// {{{ public function getExtension()

	/**
	 * Gets the extension cost of this cart entry
	 *
	 * The cost is calculated as this cart entry's item unit cost multiplied
	 * by this cart entry's quantity. This value is called the extension.
	 *
	 * @return double the extension cost of this cart entry.
	 */
	public function getExtension()
	{
		return ($this->getCalculatedItemPrice() * $this->getQuantity());
	}

	// }}}
	// {{{ public function compare()

	/**
	 * Compares this entry with another entry by item
	 *
	 * @param StoreCartEntry $entry the entry to compare this entry to.
	 *
	 * @return integer a tri-value indicating how this entry compares to the
	 *                  given entry. The value is negative if this entry is
	 *                  less than the given entry, zero if this entry is equal
	 *                  to the given entry and positive it this entry is
	 *                  greater than the given entry.
	 */
	public function compare(StoreCartEntry $entry)
	{
		if ($this->getItemId() === $entry->getItemId())
			return 0;

		return ($this->getItemId() < $entry->getItemId()) ? -1 : 1;
	}

	// }}}
	// {{{ public function combine()

	/**
	 * Combines an entry with this entry
	 *
	 * The quantity is updated to the sum of quantities of the two entries.
	 * This is useful if you want to add entries to a cart that already has
	 * an equivalent entry.
	 *
	 * @param StoreCartEntry $entry the entry to combine with this entry.
	 */
	public function combine(StoreCartEntry $entry)
	{
		if ($this->compare($entry) == 0)
			$this->quantity += $entry->getQuantity();
	}

	// }}}
	// {{{ public function isSaved()

	/**
	 * Whether or not this entry is saved for later
	 *
	 * @return boolean whether or not this entry is saved for later.
	 */
	public function isSaved()
	{
		return $this->saved;
	}

	// }}}
	// {{{ public function createOrderItem()

	/**
	 * Creates a new order item dataobject that corresponds to this cart entry
	 *
	 * @return StoreOrderItem a new StoreOrderItem object that corresponds to
	 *                         this cart entry.
	 */
	public function createOrderItem()
	{
		$class = SwatDBClassMap::get('StoreOrderItem');
		$order_item = new $class();

		$order_item->setCartEntryId($this->id);
		$order_item->sku                = $this->item->sku;
		$order_item->price              = $this->getCalculatedItemPrice();
		$order_item->quantity           = $this->getQuantity();
		$order_item->extension          = $this->getExtension();
		$order_item->description        = $this->getOrderItemDescription();
		$order_item->item               = $this->item->id;
		$order_item->product            = $this->item->product->id;
		$order_item->product_title      = $this->item->product->title;
		$order_item->quick_order        = $this->quick_order;
		$order_item->discount           = $this->getDiscount();
		$order_item->discount_extension = $this->getDiscountExtension();

		if ($this->alias !== null)
			$order_item->alias_sku = $this->alias->sku;

		if ($this->item->sale_discount !== null)
			$order_item->sale_discount = $this->item->sale_discount->id;

		// set database if it exists
		if ($this->db !== null)
			$order_item->setDatabase($this->db);

		return $order_item;
	}

	// }}}
	// {{{ protected function getOrderItemDescription()

	protected function getOrderItemDescription()
	{
		$description = null;

		if ($this->item->getDescription() !== null)
			$description.= '<div>'.$this->item->getDescription().'</div>';

		if ($this->item->getGroupDescription() !== null)
			$description.= '<div>'.$this->item->getGroupDescription().
				'</div>';

		if ($this->item->getPartCountDescription() !== null)
			$description.= '<div>'.$this->item->getPartCountDescription().
				'</div>';

		return $description;
	}

	// }}}
	// {{{ protected function init()

	/**
	 * Sets up this cart entry data object
	 */
	protected function init()
	{
		$this->registerInternalProperty('item',
			SwatDBClassMap::get('StoreItem'));

		$this->registerInternalProperty('account',
			SwatDBClassMap::get('StoreAccount'));

		$this->registerInternalProperty('alias',
			SwatDBClassMap::get('StoreItemAlias'));

		$this->table = 'CartEntry';
		$this->id_field = 'integer:id';
	}

	// }}}
}

?>
