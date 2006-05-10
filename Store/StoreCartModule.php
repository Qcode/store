<?php

require_once 'Site/SiteApplicationModule.php';
require_once 'Store/dataobjects/StoreCartEntryWrapper.php';
require_once 'Swat/SwatMessage.php';

/**
 * A shopping-cart object.
 *
 * This class contains cart functionality common to all sites. It is typically
 * extended on a per-site basis.
 *
 * There is intentionally no getEntryById() method because cart entries are
 * un-indexed. When an item is added to the cart, it does not have a cartid to
 * index by. Only after the cart is saved do all entries have unique ids.
 *
 * @package   Store
 * @copyright 2005 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class StoreCartModule extends SiteApplicationModule
{
	// {{{ protected properties

	/**
	 * The entries in this cart
	 *
	 * This is an array of StoreCartEntry data objects. The array is
	 * intentionally unindexed.
	 *
	 * @var array
	 */
	protected $entries = array();

	/**
	 * An array of cart entries that were removed from the cart
	 *
	 * After the cart is loaded and before it is saved, this array keeps track
	 * of entries there were removed from the cart. The array is unindexed.
	 *
	 * @var array
	 */
	protected $removed_entries = array();

	/**
	 * A cache of cart totals
	 *
	 * Cart totalling methods may optionally use this array to cache their
	 * values. When the setChanged() method is called, the cache is cleared.
	 *
	 * @var boolean
	 * @see StoreCartModule::setChanged()
	 */
	protected $totals = array();

	/**
	 * An array of cart messages
	 *
	 * An array of SwatMessages used to display cart entry status messages
	 *
	 * @var array
	 */
	protected $messages = array();

	protected $changed = false;
	// }}}

	// {{{ public function init()

	public function init()
	{
	}

	// }}}

	// {{{ public abstract function load()

	/**
	 * Loads this cart
	 *
	 * Subclasses may load this cart from the database, a session or using some
	 * other method.
	 */
	public abstract function load();
	// }}}
	// {{{ public abstract function save()

	/**
	 * Saves this cart
	 *
	 * Subclasses may save this cart to the database, a session or some other
	 * storage medium.
	 *
	 * @see StoreCartModule::load()
	 */
	public abstract function save();

	// }}}
	// {{{ public function addEntry()

	/**
	 * Adds a StoreCartEntry to this cart
	 *
	 * If an equivalent entry already exists in the cart, the two entries are
	 * combined.
	 *
	 * @param StoreCartEntry $cartEntry the StoreCartEntry to add.
	 */
	public function addEntry(StoreCartEntry $cart_entry)
	{
		$cart_entry->setDatabase($this->app->db);

		if (!$this->validateEntry($cart_entry))
			return;

		$already_in_cart = false;

		// check for item
		foreach ($this->entries as $entry) {
			if ($entry->compare($cart_entry) == 0) {
				$already_in_cart = true;
				$entry->combine($cart_entry);
				break;
			}
		}

		if (!$already_in_cart)
			$this->entries[] = $cart_entry;

		$this->setChanged();
	}
	// }}}
	// {{{ public function removeEntryById()

	/**
	 * Removes a StoreCartEntry from this cart
	 *
	 * @param integer $entry_id the index value of the StoreCartEntry object
	 *                           to remove.
	 *
	 * @return StoreCartEntry the entry that was removed or null if no entry
	 *                         was removed.
	 */
	public function removeEntryById($entry_id)
	{
		$old_entry = null;

		foreach ($this->entries as $entry) {
			if ($entry->id == $entry_id) {
				$key = key($this->entries);
				$old_entry = $this->entries[$key];
				unset($this->entries[$key]);
				$this->removed_entries[] = $old_entry;
				$this->setChanged();
				break;
			}
		}

		return $old_entry;
	}
	// }}}
	// {{{ public function removeEntry()

	/**
	 * Removes a StoreCartEntry from this cart
	 *
	 * @param StoreCartEntry $entry the StoreCartEntry object to remove.
	 *
	 * @return StoreCartEntry the entry that was removed or null if no entry
	 *                         was removed.
	 */
	public function removeEntry($entry)
	{
		$old_entry = null;

		if (in_array($entry, $this->entries)) {
			foreach ($this->entries as $key => $cart_entry) {
				if ($cart_entry === $entry) {
					$old_entry = $this->entries[$key];
					unset($this->entries[$key]);
					$this->removed_entries[] = $old_entry;
					$this->setChanged();
					break;
				}
			}
		}

		return $old_entry;
	}

	// }}}
	// {{{ public function getEntries()

	/**
	 * Gets a reference to the internal array of StoreCartEntry objects.
	 *
	 * @return array an array of StoreCartEntry objects.
	 */
	public function &getEntries()
	{
		return $this->entries;
	}
	// }}}
	// {{{ public function getEntriesByItemId()

	/**
	 * Returns an array of entries in the cart based on the database item id
	 *
	 * An array is returned because database ids are not required to be unique
	 * across StoreCartItems in a single cart.
	 *
	 * @param integer $item_id the database id of the StoreItem in the cart to
	 *                          be returned.
	 *
	 * @return array an array of StoreCartEntry objects.
	 */
	public function &getEntriesByItemId($item_id)
	{
		$entries = array();
		foreach ($this->entries as $entry) {
			if ($entry->getItemId() == $item_id)
				$entries[] = $entry;
		}
		return $entries;
	}

	// }}}
	// {{{ public function removeAllEntries()

	/**
	 * Removes all entries from this cart
	 *
	 * @return array the array of StoreCartEntry objects that were removed from
	 *                this cart.
	 */
	public function &removeAllEntries()
	{
		$entries =& $this->entries;
		$this->entries = array();
		$this->setChanged();
		return $entries;
	}
	// }}}
	// {{{ public function isEmpty()

	/**
	 * Checks if this cart is empty
	 *
	 * @return boolean true if this cart is empty and false if it is not.
	 */
	public function isEmpty()
	{
		return count($this->entries) ? false : true;
	}

	// }}}
	// {{{ public function getEntryCount()	

	/**
	 * Gets the number of StoreCartEntry objects in this cart
	 *
	 * @return integer the number of StoreCartEntry objects in this cart
	 */
	public function getEntryCount()
	{
		return count($this->entries);
	}

	// }}}
	// {{{ public function getItemCount()

	/**
	 * Returns the number of StoreItems in this cart
	 *
	 * The number is calculated based based on StoreCartEntry quantities.
	 *
	 * @return integer the number of StoreItems in this cart.
	 */
	public function getItemCount()
	{
		$total_quantity = 0;

		foreach ($this->entries as $entry)
			$total_quantity += $entry->getQuantity();

		return $total_quantity;
	}

	// }}}
	// {{{ public function setEntryQuantity()

	/**
	 * Updates the quantity of an entry in this cart
	 *
	 * @param StoreCartEntry $entry the entry to update.
	 * @param integer $value the new entry value.
	 */
	public function setEntryQuantity(StoreCartEntry $entry, $value)
	{
		if (in_array($entry, $this->entries)) {
			if ($value == 0) {
				$this->removeEntry($entry);
			} else {
				$entry->setQuantity($value);
				$this->setChanged();
			}
		}
	}
	// }}}
	// {{{ public function getMessages()

	/**
	 * Returns the cart status messages of this cart
	 *
	 * @return array An array of SwatMessages.
	 */
	public function getMessages()
	{
		return $this->messages;
	}

	// }}}
	// {{{ protected function validateEntry()

	/**
	 * Checks to see if the entry is valid
	 *
	 * Used to verify that the entry exists and is available for purchase.
	 *
	 * @param StoreCartEntry $cartEntry the StoreCartEntry to validate.
	 */
	protected function validateEntry(StoreCartEntry $cart_entry)
	{
		return true;
	}

	// }}}

	/*
	 * Implementation note:
	 *   Totalling methods should call protected methods to ease sub-classing
	 *   the StoreCart class.
	 */

	// {{{ public abstract function getTaxCost()

	/**
	 * Gets the value of taxes for this cart
	 *
	 * Calculates applicable taxes based on the contents of this cart. Tax
	 * Calculations need to know where purchase is made in order to correctly
	 * apply tax.
	 *
	 * @param StoreAddress $address a StoreAddress where this purchase is made
	 *                               from.
	 *
	 * @return double the value of tax for this cart.
	 */
	public abstract function getTaxCost(StoreProvState $provstate);
	
	// }}}
	// {{{ public abstract function getTotalCost()

	/**
	 * Gets the total cost for an order of the contents of this cart
	 *
	 * The total is calculated as subtotal + tax + shipping.
	 *
	 * @return double the cost of this cart's contents.
	 */
	public abstract function getTotalCost(StoreProvState $provstate);

	// }}}
	// {{{ public abstract function getShippingCost()

	/**
	 * Gets the cost of shipping the contents of this cart
	 *
	 * @return double the cost of shipping this order.
	 */
	public abstract function getShippingCost();

	// }}}
	// {{{ public function getSubtotalCost()

	/**
	 * Gets the cost of the StoreCartEntry objects in this cart
	 *
	 * @return double the sum of the extensions of all StoreCartEntry objects
	 *                 in this cart.
	 */
	public function getSubtotalCost()
	{
		if ($this->cachedValueExists('subtotal'))
			return $this->getCachedValue('subtotal');

		$subtotal = 0;
		foreach ($this->entries as $entry)
			$subtotal += $entry->getExtensionCost();

		$this->setCachedValue('subtotal', $subtotal);

		return $subtotal;
	}

	// }}}

	// {{{ protected function setChanged()

	/**
	 * Sets this cart as modified
	 *
	 * This clears the totals cache if it has entries.
	 */
	protected function setChanged()
	{
		$this->changed = true;

		foreach ($this->totals as $key => $value)
			$this->totals[$key] = null;
	}

	// }}}
	// {{{ protected function cachedValueExists()

	protected function cachedValueExists($name)
	{
		return isset($this->totals[$name]);
	}

	// }}}
	// {{{ protected function getCachedValue()

	protected function getCachedValue($name)
	{
		return $this->totals[$name.get_class($this)];
	}

	// }}}
	// {{{ protected function setCachedValue()

	protected function setCachedValue($name, $value)
	{
		$this->totals[$name.get_class($this)] = $value;
	}

	// }}}
}

?>
