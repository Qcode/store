<?php

require_once 'Store/dataobjects/StoreDataObject.php';
require_once 'Store/dataobjects/StoreAccount.php';
require_once 'Store/dataobjects/StoreOrderAddress.php';
require_once 'Store/dataobjects/StoreOrderPaymentMethod.php';

/**
 *
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreOrder extends StoreDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * Snapshot of the customer's email address
	 *
	 * @var string
	 */
	public $email;

	/**
	 * Snapshot of the customer's Phone Number
	 *
	 * @var string
	 */
	public $phone;

	/**
	 * Comments
	 *
	 * @var string
	 */
	public $comments;

	/**
	 * Creation date
	 *
	 * @var date
	 */
	public $createdate;

	/**
	 * Ship to billing address?
	 *
	 * @var boolean
	 */
	public $ship_to_billing_address;

	/**
	 * Shipping amount
	 *
	 * @var float
	 */
	public $shipping;

	/**
	 * Total amount
	 *
	 * @var float
	 */
	public $total;

	/**
	 * Subtotal amount
	 *
	 * @var float
	 */
	public $subtotal;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerInternalProperty('account',
			$this->class_map->resolveClass('StoreAccount'));

		$this->registerInternalProperty('billing_address',
			$this->class_map->resolveClass('StoreOrderAddress'));

		$this->registerInternalProperty('shipping_address',
			$this->class_map->resolveClass('StoreOrderAddress'));

		$this->registerInternalProperty('payment_method',
			$this->class_map->resolveClass('StoreOrderPaymentMethod'));

		$this->registerDateProperty('createdate');

		$this->table = 'Orders';
		$this->id_field = 'integer:id';
	}

	// }}}
}

?>
