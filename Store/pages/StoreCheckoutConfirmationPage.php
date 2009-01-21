<?php

require_once 'Swat/SwatDate.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'SwatDB/SwatDBTransaction.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Store/pages/StoreCheckoutUIPage.php';
require_once 'Store/dataobjects/StoreOrderItemWrapper.php';
require_once 'Store/dataobjects/StoreCartEntry.php';
require_once 'Store/exceptions/StorePaymentAddressException.php';
require_once 'Store/exceptions/StorePaymentPostalCodeException.php';
require_once 'Store/exceptions/StorePaymentCvvException.php';

/**
 * Confirmation page of checkout
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 */
class StoreCheckoutConfirmationPage extends StoreCheckoutUIPage
{
	// {{{ public function __construct()

	public function __construct(SiteAbstractPage $page)
	{
		parent::__construct($page);
		$this->ui_xml = 'Store/pages/checkout-confirmation.xml';
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();
		$this->checkOrder();
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		if ($this->ui->hasWidget('checkout_progress')) {
			$checkout_progress = $this->ui->getWidget('checkout_progress');
			$checkout_progress->current_step = 2;
		}
	}

	// }}}
	// {{{ protected function checkOrder()

	protected function checkOrder()
	{
		$order = $this->app->session->order;

		if (!($order->billing_address instanceof StoreOrderAddress))
			throw new StoreException('Missing billing address.  '.
				'StoreOrder::billing_address must be a valid reference to a '.
				'StoreOrderAddress object by this point.');

		if (!($order->shipping_address instanceof StoreOrderAddress))
			throw new StoreException('Missing shipping address.  '.
				'StoreOrder::shipping_address must be a valid reference to a '.
				'StoreOrderAddress object by this point.');
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		parent::process();

		$this->ui->process();

		$form = $this->ui->getWidget('form');

		if ($form->isProcessed() && !$form->hasMessage())
			$this->processOrder();
	}

	// }}}
	// {{{ protected function processOrder()

	protected function processOrder()
	{
		$saved = $this->save();

		if (!$saved)
			return;

		try {
			$this->processPayment();

			$order = $this->app->session->order;
			$order->sendConfirmationEmail($this->app);
			$this->removeCartEntries();
			$this->cleanupSession();
			$this->updateProgress();
			$this->app->relocate('checkout/thankyou');

		} catch (StorePaymentException $e) {
			$this->handlePaymentException($e);

			// duplicate order
			$new_order = $order->duplicate();
			$new_order->previous_attempt = $order;
			$this->app->session->order = $new_order;
		}
	}

	// }}}
	// {{{ protected function processPayment()

	/**
	 * Does automatic card payment processing for an order
	 *
	 * By default, no automatic payment processing is done. Subclasses should
	 * override this method to perform automatic payment processing.
	 *
	 * @see StorePaymentProvider
	 */
	protected function processPayment()
	{
	}

	// }}}
	// {{{ protected function save()

	protected function save()
	{
		// If there is no transaction in progress, save the order
		// otherwise it has already been saved.
		if ($this->app->session->transaction === null) {
			if ($this->app->session->checkout_with_account) {
				$db_transaction = new SwatDBTransaction($this->app->db);
				$duplicate_account = $this->app->session->account->duplicate();
				try {
					// Save account
					$this->saveAccount();
					$db_transaction->commit();
				} catch (Exception $e) {
					$db_transaction->rollback();
					$this->app->session->account = $duplicate_account;

					if (!($e instanceof SwatException))
						$e = new SwatException($e);

					$e->process();

					$message = new SwatMessage(Store::_('A system error '.
						'occured while processing your order'),
						SwatMessage::SYSTEM_ERROR);

					$message->content_type = 'text/xml';
					$message->secondary_content = Store::_(
						'Your account was not created, '.
						'your order was <em>not</em> placed, and you have '.
						'<em>not</em> been billed. The error has been logged '.
						'and we will attempt to fix it as quickly as '.
						'possible.');

					$this->app->messages->add($message);
					$this->ui->getWidget('message_display')->add($message);
					return false;
				}
			}

			$db_transaction = new SwatDBTransaction($this->app->db);
			$duplicate_order = $this->app->session->order->duplicate();

			try {
				// Save order
				if ($this->saveOrder() !== false) {
					$db_transaction->commit();
				} else {
					$db_transaction->rollback();
					$this->app->session->order = $duplicate_order;
					return false;
				}
			} catch (Exception $e) {
				$db_transaction->rollback();
				$this->app->session->order = $duplicate_order;

				if (!($e instanceof SwatException))
					$e = new SwatException($e);

				$e->process();

				$message = new SwatMessage(Store::_('A system error '.
					'occured while processing your order'),
					SwatMessage::SYSTEM_ERROR);

				$message->content_type = 'text/xml';
				$message->secondary_content = Store::_(
					'Your account was created, but your order was '.
					'<em>not</em> placed and you have '.
					'<em>not</em> been billed. The error has been logged '.
					'and we will attempt to fix it as quickly as '.
					'possible.');

				$this->ui->getWidget('message_display')->add($message);
				return false;
			}
		}

		return true;
	}

	// }}}
	// {{{ protected function saveAccount()

	protected function saveAccount()
	{
		// if we are checking out with an account, store new addresses and
		// payment methods in the account
		$account = $this->app->session->account;
		$order = $this->app->session->order;

		$address = $this->addAddressToAccount($order->billing_address);
		$account->setDefaultBillingAddress($address);

		// shipping address is only added if it differs from billing address
		if ($order->shipping_address !== $order->billing_address &&
			$order->shipping_address->getAccountAddressId() === null) {
			$address = $this->addAddressToAccount($order->shipping_address);
		}

		$account->setDefaultShippingAddress($address);

		// new payment methods are only added if a session flag is set
		if ($this->app->session->save_account_payment_method &&
			$order->payment_method !== null &&
			$order->payment_method->isSaveableWithAccount()) {
				$this->addPaymentMethodToAccount($order->payment_method);
		}

		$new_account = ($account->id === null);

		// if this is a new account, set createdate to now
		if ($new_account) {
			$account->createdate = new SwatDate();
			$account->createdate->toUTC();

			if ($this->app->hasModule('SiteMultipleInstanceModule'))
				$account->instance = $this->app->instance->getInstance();
		}

		// save account
		$account->save();

		// if this is a new account, log it in
		if ($new_account) {
			// clear account from session so we appear to not be logged in now that
			// the account is saved
			$this->app->session->account = null;
			$this->app->session->loginById($account->id);
		}
	}

	// }}}
	// {{{ protected function saveOrder()

	protected function saveOrder()
	{
		$order = $this->app->session->order;

		if ($this->app->hasModule('SiteMultipleInstanceModule'))
			$order->instance = $this->app->instance->getInstance();

		// if there was a previous order attempt, mark it as failed
		if ($order->previous_attempt !== null) {
			$order->previous_attempt->failed_attempt = true;
			$order->previous_attempt->save();
		}

		// attach order to account
		if ($this->app->session->checkout_with_account)
			$order->account = $this->app->session->account;

		// set createdate to now
		$order->createdate = new SwatDate();
		$order->createdate->toUTC();

		// save order
		$order->save();

		return true;
	}

	// }}}
	// {{{ protected function addAddressToAccount()

	/**
	 * @return StoreAccountAddress the account address used for this order.
	 */
	protected function addAddressToAccount(StoreOrderAddress $order_address)
	{
		$account = $this->app->session->account;

		// check that address is not already in account
		if ($order_address->getAccountAddressId() === null) {
			$class_name = SwatDBClassMap::get('StoreAccountAddress');
			$account_address = new $class_name();
			$account_address->copyFrom($order_address);
			$account_address->createdate = new SwatDate();
			$account_address->createdate->toUTC();
			$account->addresses->add($account_address);
		} else {
			$account_address = $account->addresses->getByIndex(
				$order_address->getAccountAddressId());
		}

		return $account_address;
	}

	// }}}
	// {{{ protected function addPaymentMethodToAccount()

	protected function addPaymentMethodToAccount(
		StoreOrderPaymentMethod $order_payment_method)
	{
		// check that payment method is not already in account
		if ($order_payment_method->getAccountPaymentMethodId() === null) {
			$class_name = SwatDBClassMap::get('StoreAccountPaymentMethod');
			$account_payment_method = new $class_name();
			$account_payment_method->copyFrom($order_payment_method);

			$this->app->session->account->payment_methods->add(
				$account_payment_method);
		}
	}

	// }}}
	// {{{ protected function removeCartEntries()

	protected function removeCartEntries()
	{
		$order = $this->app->session->order;

		// remove entries from cart that were ordered
		foreach ($order->items as $order_item) {
			$entry_id = $order_item->getCartEntryId();
			$this->app->cart->checkout->removeEntryById($entry_id);
		}

		$this->app->cart->save();
	}

	// }}}
	// {{{ protected function cleanupSession()

	protected function cleanupSession()
	{
		// unset session variable flags
		$this->app->analytics->clearAd();
		unset($this->app->session->save_account_payment_method);
	}

	// }}}
	// {{{ protected function handlePaymentException()

	/**
	 * Handles exceptions produced by automatic card payment processing
	 *
	 * @see StorePaymentProvider
	 */
	protected function handlePaymentException(StorePaymentException $e)
	{
		// log all payment exceptions
		$e->process(false);

		if ($e instanceof StorePaymentAddressException) {
			$this->ui->getWidget('message_display')->add(
				$this->getPaymentErrorMessage('address'));
		} elseif ($e instanceof StorePaymentPostalCodeException) {
			$this->ui->getWidget('message_display')->add(
				$this->getPaymentErrorMessage('postal-code'));
		} elseif ($e instanceof StorePaymentCvvException) {
			$this->ui->getWidget('message_display')->add(
				$this->getPaymentErrorMessage('card-verification-value'));
		} elseif ($e instanceof StorePaymentCardTypeException) {
			$this->ui->getWidget('message_display')->add(
				$this->getPaymentErrorMessage('card-type'));
		} else {
			// relocate on fatal payment processing errors and give no
			// opportunity to edit the order
			if (isset($this->app->session->transaction))
				unset($this->app->session->transaction);

			$order = $this->app->session->order;
			$order->sendPaymentFailedEmail($this->app);
			$this->removeCartEntries();
			$this->cleanupSession();
			$this->updateProgress();
			$this->app->relocate('checkout/paymentfailed');
		}
	}

	// }}}
	// {{{ protected function getPaymentErrorMessage()

	/**
	 * Gets the error message for a payment error
	 *
	 * @param string $message_id the id of the message to get. Message ids
	 *                            defined in this class are: 'address',
	 *                            'postal-code' and 'card-verification-value'.
	 *
	 * @return SwatMessage the payment error message corresponding to the
	 *                      specified <i>$message_id</i> or null if no such
	 *                      message exists.
	 */
	protected function getPaymentErrorMessage($message_id)
	{
		$message = null;

		switch ($message_id) {
		case 'address':
			$message = new SwatMessage(
				Store::_('There was a problem processing your payment.'),
				SwatMessage::ERROR);

			$message->content_type = 'text/xml';
			$message->secondary_content =
				'<p>'.sprintf(
				Store::_('%sBilling address does not correspond with card '.
					'number.%s Your order has %snot%s been placed. '.
					'Please edit your %sbilling address%s and try again.'),
					'<strong>', '</strong>', '<em>', '</em>',
					'<a href="checkout/confirmation/billingaddress">', '</a>').
				' '.Store::_('No funds have been removed from your card.').
				'</p><p>'.sprintf(
				Store::_('If you are still unable to complete your order '.
					'after confirming your payment information, please '.
					'%scontact us%s. Your order details have been recorded.'),
					'<a href="about/contact">', '</a>').
				'</p>';

			break;
		case 'postal-code':
			$message = new SwatMessage(
				Store::_('There was a problem processing your payment.'),
				SwatMessage::ERROR);

			$message->content_type = 'text/xml';
			$message->secondary_content =
				'<p>'.sprintf(
				Store::_('%sBilling postal code / ZIP code does not correspond '.
					'with card number.%s Your order has %snot%s been placed. '.
					'Please edit your %sbilling address%s and try again.'),
					'<strong>', '</strong>', '<em>', '</em>',
					'<a href="checkout/confirmation/billingaddress">', '</a>').
				' '.Store::_('No funds have been removed from your card.').
				'</p><p>'.sprintf(
				Store::_('If you are still unable to complete your order '.
					'after confirming your payment information, please '.
					'%scontact us%s. Your order details have been recorded.'),
					'<a href="about/contact">', '</a>').
				'</p>';

			break;
		case 'card-verification-value':
			$message = new SwatMessage(
				Store::_('There was a problem processing your payment.'),
				SwatMessage::ERROR);

			$message->content_type = 'text/xml';
			$message->secondary_content =
				'<p>'.sprintf(
				Store::_('%sCard security code does not correspond with card '.
					'number.%s Your order has %snot%s been placed. '.
					'Please edit your %spayment information%s and try again.'),
					'<strong>', '</strong>', '<em>', '</em>',
					'<a href="checkout/confirmation/paymentmethod">', '</a>').
				' '.Store::_('No funds have been removed from your card.').
				'</p><p>'.sprintf(
				Store::_('If you are still unable to complete your order '.
					'after confirming your payment information, please '.
					'%scontact us%s. Your order details have been recorded.'),
					'<a href="about/contact">', '</a>').
				'</p>';

			break;
		case 'card-type':
			$message = new SwatMessage(
				Store::_('There was a problem processing your payment.'),
				SwatMessage::ERROR);

			$message->content_type = 'text/xml';
			$message->secondary_content =
				'<p>'.sprintf(
				Store::_('%sCard type does not correspond with card '.
					'number.%s Your order has %snot%s been placed. '.
					'Please edit your %spayment information%s and try again.'),
					'<strong>', '</strong>', '<em>', '</em>',
					'<a href="checkout/confirmation/paymentmethod">', '</a>').
				' '.Store::_('No funds have been removed from your card.').
				'</p><p>'.sprintf(
				Store::_('If you are still unable to complete your order '.
					'after confirming your payment information, please '.
					'%scontact us%s. Your order details have been recorded.'),
					'<a href="about/contact">', '</a>').
				'</p>';

			break;
		}

		return $message;
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		$this->buildMessage();
		$this->buildOrder();

		$order = $this->app->session->order;
		$this->buildItems($order);
		$this->buildBasicInfo($order);
		$this->buildBillingAddress($order);
		$this->buildShippingAddress($order);
		$this->buildShippingType($order);
		$this->buildPaymentMethod($order);
	}

	// }}}
	// {{{ protected function buildMessage()

	protected function buildMessage()
	{
		if ($this->ui->getWidget('message_display')->getMessageCount() == 0) {
			$message = new SwatMessage(Store::_('Please Review Your Order'));
			$message->content_type= 'text/xml';
			$message->secondary_content = sprintf(Store::_('Press the '.
				'%sPlace Order%s button to complete your order.'),
				'<em>', '</em>');

			$this->ui->getWidget('message_display')->add($message,
				SwatMessageDisplay::DISMISS_OFF);
		}
	}

	// }}}
	// {{{ protected function buildOrder()

	protected function buildOrder()
	{
		if ($this->app->session->order->isFromInvoice())
			$this->createOrderFromInvoice();
		else
			$this->createOrder();
	}

	// }}}
	// {{{ protected function buildBasicInfo()

	protected function buildBasicInfo($order)
	{
		$ds = new SwatDetailsStore($order);
		$view = $this->ui->getWidget('basic_info_details');

		if ($this->app->session->isLoggedIn())
			$ds->fullname = $this->app->session->account->fullname;
		else
			$view->getField('fullname_field')->visible = false;

		$view->data = $ds;
	}

	// }}}
	// {{{ protected function buildBillingAddress()

	protected function buildBillingAddress($order)
	{
		ob_start();
		$order->billing_address->display();

		$this->ui->getWidget('billing_address')->content = ob_get_clean();
		$this->ui->getWidget('billing_address')->content_type = 'text/xml';
	}

	// }}}
	// {{{ protected function buildShippingAddress()

	protected function buildShippingAddress($order)
	{
		ob_start();
		// compare references since these are not saved yet
		if ($order->shipping_address === $order->billing_address) {
			$span_tag = new SwatHtmlTag('span');
			$span_tag->class = 'swat-none';
			$span_tag->setContent(Store::_('<ship to billing address>'));
			$span_tag->display();
		} else {
			$order->shipping_address->display();
		}

		$this->ui->getWidget('shipping_address')->content = ob_get_clean();
		$this->ui->getWidget('shipping_address')->content_type = 'text/xml';
	}

	// }}}
	// {{{ protected function buildPaymentMethod()

	protected function buildPaymentMethod($order)
	{
		ob_start();

		if ($order->payment_method instanceof StorePaymentMethod) {
			$order->payment_method->display();
		} else {
			$span_tag = new SwatHtmlTag('span');
			$span_tag->class = 'swat-none';
			$span_tag->setContent(Store::_('<none>'));
			$span_tag->display();
		}

		$this->ui->getWidget('payment_method')->content = ob_get_clean();
		$this->ui->getWidget('payment_method')->content_type = 'text/xml';
	}

	// }}}
	// {{{ protected function buildShippingType()

	protected function buildShippingType($order)
	{
		if (!$this->ui->hasWidget('shipping_type'))
			return;

		ob_start();

		if ($order->shipping_type instanceof StoreShippingType) {
			$order->shipping_type->display();
		} else {
			$span_tag = new SwatHtmlTag('span');
			$span_tag->class = 'swat-none';
			$span_tag->setContent(Store::_('<none>'));
			$span_tag->display();
		}

		$this->ui->getWidget('shipping_type')->content = ob_get_clean();
		$this->ui->getWidget('shipping_type')->content_type = 'text/xml';
	}

	// }}}
	// {{{ protected function buildItems()

	protected function buildItems($order)
	{
		$items_view = $this->ui->getWidget('items_view');
		$items_view->model = $order->getOrderDetailsTableStore();

		$items_view->getRow('subtotal')->value = $order->getSubtotal();
		$items_view->getRow('shipping')->value = $order->shipping_total;
		if ($order->surcharge_total > 0)
			$items_view->getRow('surcharge')->value = $order->surcharge_total;

		$items_view->getRow('total')->value = $order->total;

		// invoice the items can not be edited
		if ($this->app->session->order->isFromInvoice())
			$this->ui->getWidget('item_link')->visible = false;
	}

	// }}}
	// {{{ protected function createOrder()

	protected function createOrder()
	{
		$cart = $this->app->cart->checkout;
		$order = $this->app->session->order;

		$this->createOrderItems($order);

		$order->locale = $this->app->getLocale();

		$order->item_total = $cart->getItemTotal();

		$order->surcharge_total = $cart->getSurchargeTotal();

		$order->shipping_total = $cart->getShippingTotal(
			$order->billing_address, $order->shipping_address,
			$order->shipping_type);

		$order->tax_total = $cart->getTaxTotal($order->billing_address,
			 $order->shipping_address);

		$order->total = $cart->getTotal($order->billing_address,
			$order->shipping_address, $order->shipping_type);

		// Reload ad from the database to esure it exists before trying to save
		// the order. This prevents order failure when a deleted ad ends up in
		// the session.
		$session_ad = $this->app->analytics->getAd();
		if ($session_ad !== null) {
			$ad_class = SwatDBClassMap::get('SiteAd');
			$ad = new $ad_class();
			$ad->setDatabase($this->app->db);
			if ($ad->load($session_ad->id)) {
				$order->ad = $ad;
			}
		}
	}

	// }}}
	// {{{ protected function createOrderItems()

	protected function createOrderItems($order)
	{
		$wrapper = SwatDBClassMap::get('StoreOrderItemWrapper');
		$order->items = new $wrapper();

		foreach ($this->app->cart->checkout->getAvailableEntries() as $entry) {
			$order_item = $entry->createOrderItem();
			$order_item->setDatabase($this->app->db);
			$order->items->add($order_item);
		}
	}

	// }}}
	// {{{ protected function createOrderFromInvoice()

	protected function createOrderFromInvoice()
	{
		$order = $this->app->session->order;
		$invoice = $order->invoice;

		$this->createOrderItemsFromInvoice($order);

		$order->locale = $this->app->getLocale();

		$order->item_total = $invoice->getItemTotal();

		$order->shipping_total = $invoice->getShippingTotal(
			$order->billing_address, $order->shipping_address,
			$this->app->getRegion());

		$order->tax_total = $invoice->getTaxTotal($order->billing_address,
			 $order->shipping_address);

		$order->total = $invoice->getTotal($order->billing_address,
			$order->shipping_address, $this->app->getRegion());

		$order->ad = $this->app->app->analytics->getAd();
	}

	// }}}
	// {{{ protected function createOrderItemsFromInvoice()

	protected function createOrderItemsFromInvoice($order)
	{
		$wrapper = SwatDBClassMap::get('StoreOrderItemWrapper');
		$order->items = new $wrapper();

		foreach ($order->invoice->items as $invoice_item) {
			$order_item = $invoice_item->createOrderItem();
			$order_item->setDatabase($this->app->db);
			$order->items->add($order_item);
		}
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/styles/store-checkout-confirmation-page.css',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
