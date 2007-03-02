<?php

require_once 'Store/dataobjects/StorePaymentTransaction.php';

abstract class StorePaymentProvider
{
	/**
	 * Use Address Verification Service (AVS)
	 */
	const AVS_ON  = true;

	/**
	 * Don't use Address Verification Service (AVS)
	 */
	const AVS_OFF = false;

	/**
	 * The Address Verification Service (AVS) mode
	 *
	 * One of either StorePaymentProvider::AVS_ON or
	 * StorePaymentProvider::AVS_OFF.
	 *
	 * @var boolean
	 */
	protected $avs_mode = self::AVS_OFF;

	/**
	 * Creates a new payment provider instance
	 *
	 * This is the main mechanism for starting an online payment transaction.
	 *
	 * @param string $driver the payment provider driver to use.
	 * @param array $parameters an array of additional key-value parameters to
	 *                           pass to the payment provider driver.
	 *
	 * @return StorePaymentProvider a payment provider instance using the
	 *                               specified driver.
	 *
	 * @throws StoreException if the driver specified by <i>$driver</i> could
	 *                         not be loaded.
	 */
	public static function factory($driver, array $parameters = array())
	{
		static $loaded_drivers = array();

		if (array_key_exists($driver, $loaded_drivers)) {
			$class_name = $loaded_drivers[$driver];
		} else {
			$sanitized_driver = basename($driver);
			include_once 'Store/Store'.$sanitized_driver.'PaymentProvider.php';
			$class_name = 'Store'.$sanitized_driver.'PaymentProvider';

			if (!class_exists($class_name)) {
				throw new Exception(sprintf('No payment provider available '.
					'for driver %s', $driver));
			}

			$loaded_drivers[$sanitized_driver] = $class_name;
		}

		$reflector = new ReflectionClass($class_name);
		return $reflector->newInstance($parameters);
	}

	/**
	 * Creates a new payment provider
	 *
	 * @param array $paramaters an array of key-value pairs containing driver-
	 *                           specific constructor properties. See
	 *                           individual driver documentation for valid
	 *                           parameters.
	 */
	abstract public function __construct(array $paramaters);

	/**
	 * Set the Address Verification Service (AVS) mode
	 *
	 * Using AVS allows site code to validate transactions based on address and
	 * card verification value. Using AVS never prevents transactions, it just
	 * allows site code to decided whether or not to make a transaction. As
	 * such, it does not make much sense to use AVS with the
	 * {@link StorePaymentProvider::pay()} method. AVS is not used by default.
	 *
	 * @param boolean $mode optional. The AVS mode to use. One of either
	 *                       {@link StorePaymentProvider::AVS_ON} or
	 *                       {@link StorePaymentProvider::AVS_OFF}. If not
	 *                       specified, defaults to AVS_ON.
	 */
	public function setAvsMode($mode = self::AVS_ON)
	{
		$this->avs_mode = (boolean)$mode;
	}

	/**
	 * Pay for an order immediately
	 *
	 * @param StoreOrder $order the order to pay for.
	 * @param string $card_number the card number to use for payment.
	 * @param string $card_verification_value optional. Card verification value
	 *                                         used for fraud prevention.
	 *
	 * @return StorePaymentTransaction the transaction object for the payment.
	 *                                  this object contains information such
	 *                                  as the transaction identifier and
	 *                                  Address Verification Service (AVS)
	 *                                  results.
	 */
	public function pay(StoreOrder $order, $card_number,
		$card_verification_value = null)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	/**
	 * Release funds held for an order payment
	 *
	 * @param StorePaymentTransaction $transaction the tranaction used to place
	 *                                              a hold on the funds. This
	 *                                              should be a transaction
	 *                                              returned by
	 *                                              {@link StorePaymentProvider::hold()}.
	 *
	 * @see StorePaymentProvider::hold()
	 */
	public function release(StorePaymentTransaction $transaction)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	/**
	 * Place a hold on funds for an order
	 *
	 * @param StoreOrder $order the order to hold funds for.
	 * @param string $card_number the card number to place the hold on.
	 * @param string $card_verification_value optional. Card verification value
	 *                                         used for fraud prevention.
	 *
	 * @return StorePaymentTransaction the transaction object for the payment.
	 *                                  this object contains information such
	 *                                  as the transaction identifier and
	 *                                  Address Verification Service (AVS)
	 *                                  results.
	 *
	 * @see StorePaymentProvider::release()
	 */
	public function hold(StoreOrder $order, $card_number,
		$card_verification_value = null)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	public function authorize(StoreOrder $order, $card_number,
		$card_verification_value = null)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	public function refund(StorePaymentTransaction $transaction, $amount = null)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	public function authorizedPay(StoreOrder $order)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	public function void(StorePaymentTransaction $transaction)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}

	public function abort(StorePaymentTransaction $transaction)
	{
		require_once 'Store/exceptions/StoreUnimplementedException.php';
		throw new StoreUnimplementedException(sprintf(
			'%s does not implement the %s() method.',
			get_class($this), __FUNCTION__));
	}
}

?>
