<?php

require_once 'Store/dataobjects/StoreDataObject.php';

/**
 * A payment transaction for an e-commerce web application
 *
 * Payment transactions are usually tied to {@link StoreOrder} objects. The
 * set of {@link StorePaymentProvider} classes return StorePaymentTransaction
 * objects for most transactions.
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StorePaymentProvider
 */
class StorePaymentMethod extends StoreDataObject
{
	// {{{ class constants

	/**
	 * Field was checked and passed checks
	 */
	const STATUS_PASSED     = 0;

	/**
	 * Field was checked and failed checks
	 */
	const STATUS_FAILED     = 1;

	/**
	 * Field may or may not have been provided but was not checked either
	 * because check is not supported by card provider or the developer opted
	 * not to check AVS fields.
	 */
	const STATUS_NOTCHECKED = 2;

	/**
	 * Field was not provided and thus not checked
	 */
	const STATUS_MISSING    = 3;

	// }}}
	// {{{ public properties

	/**
	 * Payment transaction identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * The payment-provider specific transaction identifier
	 *
	 * @var string
	 */
	public $transaction_id;

	/**
	 * Security key used to validate the <i>transaction_id</i>
	 *
	 * The security key is not used for every payment provider. For payment
	 * providers that do not use a security key, this property is null.
	 *
	 * @var string
	 */
	public $security_key;

	/**
	 * Status of address check
	 *
	 * One of StorePaymentTransaction::STATUS_*.
	 * {@link StorePaymentTransaction::STATUS_NOTCHECKED} by default.
	 *
	 * @var integer
	 */
	public $address_status = self::STATUS_NOTCHECKED;

	/**
	 * Status of zip/postal code check
	 *
	 * One of StorePaymentTransaction::STATUS_*.
	 * {@link StorePaymentTransaction::STATUS_NOTCHECKED} by default.
	 *
	 * @var integer
	 */
	public $postal_code_status = self::STATUS_NOTCHECKED;

	/**
	 * Status of card verification value check
	 *
	 * One of StorePaymentTransaction::STATUS_*.
	 * {@link StorePaymentTransaction::STATUS_NOTCHECKED} by default.
	 *
	 * @var integer
	 */
	public $card_verification_value_status = self::STATUS_NOTCHECKED;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->id_field = 'integer:id';
	}

	// }}}
}

?>
