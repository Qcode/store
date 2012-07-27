<?php

require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Store/pages/StoreCheckoutEditPage.php';
require_once 'Store/dataobjects/StoreAccountPaymentMethodWrapper.php';
require_once 'Store/dataobjects/StoreOrderPaymentMethod.php';
require_once 'Store/dataobjects/StorePaymentTypeWrapper.php';
require_once 'Store/dataobjects/StoreCardTypeWrapper.php';

/**
 * Payment method edit page of checkout
 *
 * @package   Store
 * @copyright 2005-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCheckoutPaymentMethodPage extends StoreCheckoutEditPage
{
	// {{{ protected properties

	protected $remove_button;

	// }}}
	// {{{ public function getUiXml()

	public function getUiXml()
	{
		return 'Store/pages/checkout-payment-method.xml';
	}

	// }}}
	// {{{ protected function getArgumentMap()

	protected function getArgumentMap()
	{
		return array(
			'tag' => array(0, null),
		);
	}

	// }}}
	// {{{ protected function getCartTotal()

	protected function getCartTotal()
	{
		$billing_address  = $this->app->session->order->billing_address;
		$shipping_address = $this->app->session->order->shipping_address;
		$shipping_type    = $this->app->session->order->shipping_type;
		$payment_methods  = $this->app->session->order->payment_methods;

		$class_name = SwatDBClassMap::get('StoreOrderAddress');

		if ($billing_address === null)
			$billing_address = new $class_name();

		if ($shipping_address === null)
			$shipping_address = new $class_name();

		return ($this->app->cart->checkout->getTotal($billing_address,
			$shipping_address, $shipping_type, $payment_methods));
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$payment_methods = $this->app->session->order->payment_methods;
		$order_payment_method = $this->getPaymentMethod($payment_methods);

		if ($this->app->config->store->multiple_payment_support &&
			$order_payment_method !== null) {

			$this->remove_button = new SwatButton('remove_payment_method');
			$this->remove_button->title = Store::_('Remove');
			$this->remove_button->confirmation_message = Store::_(
				'Are you sure you want to remove this payment method?');

			$this->ui->getWidget('footer_field')->add($this->remove_button);
		}
	}

	// }}}
	// {{{ public function initCommon()

	public function initCommon()
	{
		parent::initCommon();

		// set up account payment method replicator
		$methods = $this->getPaymentMethods();
		$replication_ids = $methods->getIndexes();
		$replicator = $this->ui->getWidget(
			'account_payment_methods_replicator');

		$replicator->replication_ids = $replication_ids;

		// if multiple payments are enabled, make payment amount visible
		if (!$this->app->config->store->multiple_payment_ui &&
			$this->ui->hasWidget('payment_amount_field')) {
				$this->ui->getWidget('payment_amount_field')->visible = false;
		}
	}

	// }}}
	// {{{ public function postInitCommon()

	public function postInitCommon()
	{
		parent::postInitCommon();

		$types = $this->getPaymentTypes();
		$methods = $this->getPaymentMethods();
		$this->initPaymentOptions($types, $methods);
	}

	// }}}
	// {{{ protected function initPaymentOptions()

	protected function initPaymentOptions(StorePaymentTypeWrapper $types,
		StorePaymentMethodWrapper $methods)
	{
		$list = $this->ui->getWidget('payment_option');

		// default visibility of list to false, payment options will determine
		// whether or not the list is visible
		$list->parent->visible = false;

		// init payment methods
		$replicator = $this->ui->getWidget(
			'account_payment_methods_replicator');

		foreach ($methods as $method) {
			$this->initPaymentMethod($method, $list, $replicator);
		}

		// init payment types
		foreach ($types as $type) {
			$this->initPaymentType($type, $list);
		}

		// If no value was set, default to first payment type
		if ($list->selected_page === null && $types->getFirst() !== null) {
			$list->selected_page = 'type_'.$types->getFirst()->id;
		}
	}

	// }}}
	// {{{ protected function initPaymentType()

	protected function initPaymentType(StorePaymentType $type,
		SwatRadioNoteBook $list)
	{
		switch ($type->shortname) {
		case 'card':
			$this->initPaymentTypeCard($type, $list);
			break;

		default:
			$this->initPaymentTypeDefault($type, $list);
			break;
		}
	}

	// }}}
	// {{{ protected function initPaymentTypeDefault()

	protected function initPaymentTypeDefault(StorePaymentType $type,
		SwatRadioNoteBook $list)
	{
		$page = new SwatNoteBookPage();
		$page->id = 'type_'.$type->id;
		$page->title = $this->getPaymentTypeTitle($type);
		$page->title_content_type = 'text/xml';

		$list->addPage($page);
		$list->parent->visible = true;

		return $page;
	}

	// }}}
	// {{{ protected function initPaymentTypeCard()

	protected function initPaymentTypeCard(StorePaymentType $type,
		SwatRadioNoteBook $list)
	{
		$page = new SwatNoteBookPage();
		$page->id = 'type_'.$type->id;
		$page->title = $this->getPaymentTypeTitle($type);
		$page->title_content_type = 'text/xml';

		// default to 'card' if it exists
		if ($list->selected_page === null) {
			$list->selected_page = 'type_'.$type->id;
		}

		// set up card types flydown
		$card_types = $this->getCardTypes();

		$type_flydown = $this->ui->getWidget('card_type');

		foreach ($card_types as $card_type) {
			$type_flydown->addOption(
				new SwatOption(
					$card_type->id,
					$this->getCardTypeTitle($card_type),
					'text/xml'
				)
			);
		}

		// default to first card type if no card type is set
		if ($type_flydown->value === null && $card_types->getFirst() !== null) {
			$type_flydown->value = $card_types->getFirst()->id;
		}

		// make card fields visible and add to page
		$fields = $this->ui->getWidget('card_fields_container');
		$fields->parent->remove($fields);
		$fields->visible = true;
		$page->add($fields);

		$list->addPage($page);

		return $page;
	}

	// }}}
	// {{{ protected function initPaymentMethod()

	protected function initPaymentMethod(StorePaymentMethod $method,
		SwatRadioNoteBook $list, SwatReplicableNoteBookChild $replicator)
	{
		$page = $replicator->getWidget('account_fields_container', $method->id);
		$page->id = 'method_'.$method->id;

		ob_start();
		$method->showCardExpiry(false);
		$method->display();
		$page->title = ob_get_clean();
		$page->title_content_type = 'text/xml';

		$list->parent->visible = true;

		return $page;
	}

	// }}}
	// {{{ protected function getPaymentMethod()

	protected function getPaymentMethod(
		StorePaymentMethodWrapper $payment_methods)
	{
		$payment_method = null;
		$payment_methods = $this->getEditablePaymentMethods($payment_methods);

		$tag = $this->getArgument('tag');

		if ($tag === 'new' || count($payment_methods) == 0)
			return null;

		if ($tag !== null) {
			foreach ($payment_methods as $payment_method_obj) {
				if ($tag == $payment_method_obj->getTag()) {
					$payment_method = $payment_method_obj;
					break;
				}
			}
		} else {
			$payment_method = $payment_methods->getFirst();
		}

		return $payment_method;
	}

	// }}}
	// {{{ protected function getPaymentMethods()

	/**
	 * Gets available payment methods
	 *
	 * @return StoreAccountPaymentMethodWrapper
	 */
	protected function getPaymentMethods()
	{
		$wrapper = SwatDBClassMap::get('StoreAccountPaymentMethodWrapper');
		$payment_methods = new $wrapper();

		if ($this->app->session->isLoggedIn()) {
			$region = $this->app->getRegion();
			$account = $this->app->session->account;
			foreach ($account->payment_methods as $method) {
				$payment_type = $method->payment_type;
				$card_type = $method->card_type;
				if ($payment_type->isAvailableInRegion($region) &&
					($card_type === null ||
						$card_type->isAvailableInRegion($region))) {
						$payment_methods->add($method);
				}
			}
		}

		return $payment_methods;
	}

	// }}}
	// {{{ protected function getPaymentTypeTitle()

	protected function getPaymentTypeTitle(StorePaymentType $type)
	{
		$title = SwatString::minimizeEntities($type->title);

		if (strlen($type->note) > 0) {
			$title.= sprintf(
				'<br /><span class="swat-note">%s</span>',
				$type->note
			);
		}

		return $title;
	}

	// }}}
	// {{{ protected function getPaymentTypes()

	/**
	 * Gets available payment types for new payment methods
	 *
	 * @return StorePaymentTypeWrapper
	 */
	protected function getPaymentTypes()
	{
		static $types = null;

		if ($types === null) {
			$sql = 'select PaymentType.* from PaymentType
				inner join PaymentTypeRegionBinding on
					payment_type = id and region = %s
				order by displayorder, title';

			$sql = sprintf($sql,
				$this->app->db->quote($this->app->getRegion()->id, 'integer'));

			$wrapper = SwatDBClassMap::get('StorePaymentTypeWrapper');
			$types = SwatDB::query($this->app->db, $sql, $wrapper);
		}

		return $types;
	}

	// }}}
	// {{{ protected function getCardTypeTitle()

	protected function getCardTypeTitle(StoreCardType $type)
	{
		$title = $type->title;

		return $title;
	}

	// }}}
	// {{{ protected function getCardTypes()

	/**
	 * Gets available card types for new payment methods
	 *
	 * @return StoreCardTypeWrapper
	 */
	protected function getCardTypes()
	{
		static $types = null;

		if ($types === null) {
			$sql = 'select CardType.* from CardType
				inner join CardTypeRegionBinding on
					card_type = id and region = %s
				order by displayorder, title';

			$sql = sprintf($sql,
				$this->app->db->quote($this->app->getRegion()->id, 'integer'));

			$wrapper = SwatDBClassMap::get('StoreCardTypeWrapper');
			$types = SwatDB::query($this->app->db, $sql, $wrapper);
		}

		return $types;
	}

	// }}}
	// {{{ protected function getOrderBalance()

	protected function getOrderBalance($exclude_current_method = false)
	{
		$methods = $this->app->session->order->payment_methods;
		$current_method = $this->getPaymentMethod($methods);

		$payment_total = 0;
		foreach ($methods as $method) {
			if (!$exclude_current_method || $current_method === null ||
				$method->getTag() != $current_method->getTag()) {

				$payment_total+= $method->amount;
			}
		}

		$total = $this->getCartTotal();
		return $total - $payment_total;
	}

	// }}}
	// {{{ protected function orderHasAdjustableMethod()

	protected function orderHasAdjustableMethod($exclude_current_method = false)
	{
		$methods = $this->app->session->order->payment_methods;
		$current_method = $this->getPaymentMethod($methods);

		$has_adjustable_method = false;
		foreach ($methods as $method) {
			if ($method->isAdjustable() &&
					(!$exclude_current_method || $current_method === null ||
					$method->getTag() != $current_method->getTag())) {

				$has_adjustable_method = true;
			}
		}

		return $has_adjustable_method;
	}

	// }}}

	// process phase
	// {{{ public function preProcessCommon()

	public function preProcessCommon()
	{
		if ($this->remove_button !== null) {
			$this->remove_button->process();
			if ($this->remove_button->hasBeenClicked()) {
				$methods = $this->app->session->order->payment_methods;
				$order_payment_method = $this->getPaymentMethod($methods);

				// remove from session
				if ($order_payment_method !== null)
					$methods->remove($order_payment_method);

				if (!$this->orderHasAdjustableMethod() && count($methods) > 0) {
					$methods->getFirst()->setAdjustable(true);
				}

				$this->app->relocate($this->getConfirmationSource());
			}
		}

		$this->ui->getWidget('card_number')->setDatabase($this->app->db);

		$option_list = $this->ui->getWidget('payment_option');
		$option_list->process();
		$option = $option_list->selected_page;

		// check if using an existing account payment method, or a new one
		if (strncmp('method_', $option, 7) == 0) {

			// set all card fields as not required when an existing payment method
			// is selected
			$container = $this->ui->getWidget('card_fields_container');
			$controls = $container->getDescendants('SwatInputControl');
			foreach ($controls as $control) {
				$control->required = false;
			}

			// set up CVV for selected saved payment method
			$replicator = $this->ui->getWidget(
				'account_payment_methods_replicator'
			);

			$method_id = substr($option, 7);
			$cvv = $replicator->getWidget(
				'account_card_verification_value',
				$method_id
			);

			if ($cvv instanceof StoreCardVerificationValueEntry) {
				$this->setupCardVerificationValue($cvv);
			}

		} else {

			// the account card verification value only needs to be required
			// for saved cards
			if ($this->ui->hasWidget('account_card_verification_value')) {
				$account_card_verification_value =
					$this->ui->getWidget('account_card_verification_value');

				$account_card_verification_value->required = false;
			}

			$payment_type = $this->getPaymentType();

			if ($payment_type !== null) {
				if ($payment_type->isCard()) {
					$widget = $this->ui->getWidget('card_verification_value');
					$this->setupCardVerificationValue($widget);

					// set debit card fields as required when a debit card is
					// used
					$card_type = $this->getCardType();
					if ($card_type !== null) {
						$this->ui->getWidget('card_inception')->required =
							$card_type->hasInceptionDate();

						$this->ui->getWidget('card_issue_number')->required =
							$card_type->hasIssueNumber();
					}
				} else {
					$this->ui->getWidget('card_type')->required = false;
					$this->ui->getWidget('card_number')->required = false;
					$this->ui->getWidget('card_expiry')->required = false;
					$this->ui->getWidget('card_fullname')->required = false;
					$this->ui->getWidget('card_inception')->required = false;
					$this->ui->getWidget('card_issue_number')->required = false;
					$this->ui->getWidget('card_verification_value')->required =
						false;
				}
			}

		}
	}

	// }}}
	// {{{ protected function setupCardVerificationValue()

	protected function setupCardVerificationValue(
		StoreCardVerificationValueEntry $card_verification_value_widget)
	{

		$card_type = $this->getCardType();
		if ($card_type == null) {
			// Card number not valid, use card type from existing payment
			// method.
			$order = $this->app->session->order;
			$order_payment_method = $order->payment_methods->getFirst();
			if ($order_payment_method instanceof StoreOrderPaymentMethod &&
				$order_payment_method->payment_type->isCard()) {
				$card_verification_value_widget->setCardType(
					$order_payment_method->card_type);

				$card_verification_value_widget->process();
			} else {
				// Just set the CVV to null if there is no pre-existing order
				// payment method.
				$card_verification_value_widget->process();
				$card_verification_value_widget->value = null;
			}
		} else {
			$card_verification_value_widget->setCardType($card_type);
			$card_verification_value_widget->process();
		}
	}

	// }}}
	// {{{ public function validateCommon()

	public function validateCommon()
	{
		// make sure expiry date is after (or equal) to the inception date
		$card_expiry = $this->ui->getWidget('card_expiry');
		$card_inception = $this->ui->getWidget('card_inception');
		if ($card_expiry->value !== null && $card_inception->value !== null &&
			SwatDate::compare($card_expiry->value,
				$card_inception->value) < 0) {

			$card_expiry->addMessage(new SwatMessage(Store::_(
				'The card expiry date must be after the card inception date.'),
				'error'));
		}

		// prevent the same credit card from being entered twice
		$methods = $this->app->session->order->payment_methods;
		if ($this->app->config->store->multiple_payment_support &&
			count($methods) > 0) {

			$current_payment_method = $this->getPaymentMethod($methods);
			$card_number = $this->ui->getWidget('card_number');
			if (!$card_number->hasMessage()) {
				$card_number_preview = substr($card_number->value, -4);
				foreach ($methods as $method) {
					if ($method === $current_payment_method)
						continue;

					if ($method->payment_type->isCard() &&
						$method->card_number_preview == $card_number_preview) {
						$message = new SwatMessage(Store::_(
							sprintf('This Card has already been applied to '.
								'this order as payment. Please use another '.
								'card or '.
								'<a href="checkout/confirmation/paymentmethod/%s">'.
								'edit the existing payment</a>.',
								$method->getTag())),
							'error');

						$message->content_type = 'text/xml';
						$card_number->addMessage($message);
					}
				}
			}
		}
	}

	// }}}
	// {{{ public function processCommon()

	public function processCommon()
	{
		$this->saveDataToSession();
	}

	// }}}
	// {{{ protected function saveDataToSession()

	protected function saveDataToSession()
	{
		$option_list = $this->ui->getWidget('payment_option');
		$payment_methods = $this->app->session->order->payment_methods;
		$order_payment_method = $this->getPaymentMethod($payment_methods);

		// remove from session
		if ($order_payment_method !== null)
			$payment_methods->remove($order_payment_method);

		if (strncmp('method_', $option_list->selected_page, 7) === 0) {

			$method_id = intval(substr($option_list->selected_page, 7));

			$account_payment_method =
				$this->app->session->account->payment_methods->getByIndex(
					$method_id);

			if (!($account_payment_method instanceof StoreAccountPaymentMethod)) {
				throw new StoreException(
					sprintf(
						'Account payment method with id ‘%s’ not found.',
						$method_id
					)
				);
			}

			// grab the card_verification_value from the old order payment
			// method if its exists, before we recreate the dataobject
			$old_card_verification_value = null;
			if ($order_payment_method instanceof StoreOrderPaymentMethod) {
				$old_card_verification_value =
					$order_payment_method->card_verification_value;
			}

			$class_name = SwatDBClassMap::get('StoreOrderPaymentMethod');
			$order_payment_method = new $class_name();
			$order_payment_method->copyFrom($account_payment_method);

			$replicator = $this->ui->getWidget(
				'account_payment_methods_replicator'
			);

			$cvv = $replicator->getWidget(
				'account_card_verification_value',
				$method_id
			);

			if ($cvv instanceof StoreCardverificationValueEntry) {
				$this->updatePaymentMethodCardVerificationValue(
					$cvv,
					$order_payment_method,
					$old_card_verification_value
				);
			}

			// if its a saved method, we want the confirmation page to use the
			// save code-path so that default payment method gets set
			$save_payment_method = true;

		} else {

			if ($order_payment_method === null ||
				$order_payment_method->getAccountPaymentMethodId() !== null) {

				$class_name = SwatDBClassMap::get('StoreOrderPaymentMethod');
				$order_payment_method = new $class_name();
				$order_payment_method->setDatabase($this->app->db);
			}

			$this->updatePaymentMethod($order_payment_method);

			if ($order_payment_method->payment_type === null) {
				$order_payment_method = null;
			} else {
				if ($order_payment_method->payment_type->isCard() &&
					$order_payment_method->card_type === null)
						throw new StoreException('Order payment method must '.
							'be a card_type when isCard() is true.');
			}

			$save_payment_method =
				$this->ui->getWidget('save_account_payment_method')->value;

		}

		$class_name = SwatDBClassMap::get('StoreOrderPaymentMethodWrapper');
		$new_payment_methods = new $class_name();

		if ($order_payment_method !== null)
			$new_payment_methods->add($order_payment_method);

		if ($this->app->config->store->multiple_payment_support)
			foreach ($payment_methods as $payment_method)
				$new_payment_methods->add($payment_method);

		$this->app->session->order->payment_methods = $new_payment_methods;

		if ($this->app->session->checkout_with_account) {
			$this->app->session->save_account_payment_method =
				$save_payment_method;
		}
	}

	// }}}
	// {{{ protected function getEditablePaymentMethods()

	protected function getEditablePaymentMethods($payment_methods)
	{
		return $payment_methods;
	}

	// }}}
	// {{{ protected function updatePaymentMethod()

	/**
	 * Updates session order payment method properties from form values
	 *
	 * @param StoreOrderPaymentMethod $payment_method
	 */
	protected function updatePaymentMethod(
		StoreOrderPaymentMethod $payment_method)
	{
		$payment_type = $this->getPaymentType();

		$payment_method->payment_type = $payment_type;
		$payment_method->surcharge = $payment_type->surcharge;

		if ($this->ui->hasWidget('payment_amount')) {
			$amount = $this->ui->getWidget('payment_amount')->value;

			if ($this->orderHasAdjustableMethod(true)) {
				$payment_method->setAdjustable(false);
			} elseif ($amount == null ||
				$amount >= $this->getOrderBalance(true)) {

				$payment_method->setAdjustable(true);
			} else {
				$payment_method->setAdjustable(false);
			}

			$payment_method->amount = $amount;
		}


		if ($payment_type->isCard()) {
			$payment_method->setMaxAmount(null);

			$this->updatePaymentMethodCardNumber($payment_method);

			$this->updatePaymentMethodCardVerificationValue(
				$this->ui->getWidget('card_verification_value'),
				$payment_method
			);

			$payment_method->card_issue_number =
				$this->ui->getWidget('card_issue_number')->value;

			$payment_method->card_expiry =
				$this->ui->getWidget('card_expiry')->value;

			$payment_method->card_inception =
				$this->ui->getWidget('card_inception')->value;

			$payment_method->card_fullname =
				$this->ui->getWidget('card_fullname')->value;
		} else {
			$payment_method->card_fullname = null;
			$payment_method->card_inception = null;
			$payment_method->card_expiry = null;
			$payment_method->card_issue_number = null;
			$payment_method->card_type = null;
			$payment_method->card_number = null;
			$payment_method->card_number_preview = null;
		}
	}

	// }}}
	// {{{ protected function updatePaymentMethodCardNumber()

	/**
	 * Updates session order payment method card number from form values
	 *
	 * The card number is stored encrypted in the payment method. Subclasses
	 * can override this method to optionally store an unencrypted version
	 * of the card number.
	 *
	 * @param StoreOrderPaymentMethod $payment_method
	 */
	protected function updatePaymentMethodCardNumber(
		StoreOrderPaymentMethod $payment_method)
	{
		$card_number = $this->ui->getWidget('card_number')->value;
		if ($card_number !== null) {
			$payment_method->setCardNumber($card_number);
			$payment_method->card_type = $this->getCardType();
		}
	}

	// }}}
	// {{{ protected function updatePaymentMethodCardVerificationValue()

	/**
	 * Updates session order payment method card verification value
	 *
	 * The card verification value is stored unencrypted in the payment method.
	 * Subclasses can override this method to optionally store an encrypted
	 * version of the card verification value.
	 *
	 * @param StoreCardVerificationValueEntry $entry
	 * @param StoreOrderPaymentMethod $payment_method
	 * @param string $old_card_verification_value
	 */
	protected function updatePaymentMethodCardVerificationValue(
		StoreCardVerificationValue $entry,
		StoreOrderPaymentMethod $payment_method,
		$old_card_verification_value = null)
	{
		$value = $entry->value;

		if ($value !== null) {
			$payment_method->setCardVerificationValue($value);
		} elseif ($old_card_verification_value !== null) {
			$payment_method->card_verification_value =
				$old_card_verification_value;
		}
	}

	// }}}
	// {{{ protected function getPaymentType()

	protected function getPaymentType()
	{
		// note: this only works for a single instance
		static $type = null;

		if ($type === null) {
			$type = $this->getPaymentTypes()->getFirst();

			$option_list = $this->ui->getWidget('payment_option');
			if (isset($_POST['payment_option'])) {
				$option_list->process();
				if (strncmp('type_', $option_list->selected_page, 5) === 0) {
					$class_name = SwatDBClassMap::get('StorePaymentType');
					$type = new $class_name();
					$type->setDatabase($this->app->db);
					$type->load(substr($option_list->selected_page, 5));
				}
			}
		}

		return $type;
	}

	// }}}
	// {{{ protected function getCardType()

	protected function getCardType()
	{
		static $card_type = null;

		if ($card_type === null) {
			$option_list = $this->ui->getWidget('payment_option');
			$option_list->process();

			// check if account payment method or new payment method was selected
			if (strncmp('method_', $option_list->selected_page, 7) === 0) {

				$method_id = intval(substr($option_list->selected_page, 7));

				$account_payment_method =
					$this->app->session->account->payment_methods->getByIndex(
						$method_id);

				$card_type = $account_payment_method->card_type;

			} else {

				$order = $this->app->session->order;
				$order_payment_method = $order->payment_methods->getFirst();
				$type_list = $this->ui->getWidget('card_type');

				if (isset($_POST['card_type'])) {
					$type_list->process();
					$card_type_id = $type_list->value;
				} else {
					$card_number = $this->ui->getWidget('card_number');
					$card_number->process();

					if ($card_number->value == null &&
						$order_payment_method != null) {
						$card_type_id =
							$order_payment_method->getInternalValue('card_type');
					} else {
						$card_type_id = $card_number->getCardType();
					}
				}

				$class_name = SwatDBClassMap::get('StoreCardType');
				$type = new $class_name();
				$type->setDatabase($this->app->db);
				if ($type->load($card_type_id)) {
					$card_type = $type;
				}

			}
		}

		return $card_type;
	}

	// }}}

	// build phase
	// {{{ public function buildCommon()

	public function buildCommon()
	{
		$this->buildForm();
	}

	// }}}
	// {{{ public function postBuildCommon()

	public function postBuildCommon()
	{
		$this->layout->startCapture('content');
		Swat::displayInlineJavaScript($this->getInlineJavaScript());
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();
		$this->buildCurrentPaymentMethods();
	}

	// }}}
	// {{{ protected function buildForm()

	protected function buildForm()
	{
		$types = $this->getPaymentTypes();
		$methods = $this->getPaymentMethods();

		if ((count($types) + count($methods)) === 1) {
			$option_list = $this->ui->getWidget('payment_option');
			$parent = $option_list->parent->getFirstAncestor(
				'SwatDisplayableContainer'
			);
			$parent->classes[] = 'store-payment-method-single';
		}


		if (!$this->ui->getWidget('form')->isProcessed()) {
			$this->loadDataFromSession();
		}

		if ($this->app->session->checkout_with_account) {
			$this->buildAccountSpecificFields();
		}
	}

	// }}}
	// {{{ protected function buildAccountSpecificFields()

	protected function buildAccountSpecificFields()
	{
		$this->ui->getWidget('save_account_payment_method_field')->visible =
			true;

		$this->ui->getWidget('payment_method_note')->content = sprintf(
			Store::_('%sSee our %sprivacy &amp; security policy%s for '.
			'more information about how your information will be used.%s'),
			'<p class="small-print">', '<a href="about/website/privacy">',
			'</a>', '</p>');
	}

	// }}}
	// {{{ protected function getInlineJavaScript()

	protected function getInlineJavaScript()
	{
		$id = 'checkout_payment_method';
		$inception_date_ids = array();
		$issue_number_ids = array();
		foreach ($this->getCardTypes() as $type) {
			if ($type->hasInceptionDate())
				$inception_date_ids[] = $type->id;

			if ($type->hasIssueNumber())
				$issue_number_ids[] = $type->id;
		}

		$card_ids = array();
		foreach ($this->getPaymentTypes() as $type) {
			if ($type->isCard()) {
				$card_ids[] = $type->id;
			}
		}

		return sprintf("var %s_obj = ".
			"new StoreCheckoutPaymentMethodPage('%s', [%s], [%s], [%s]);",
			$id,
			$id,
			implode(', ', $inception_date_ids),
			implode(', ', $issue_number_ids),
			implode(', ', $card_ids));
	}

	// }}}
	// {{{ protected function getNewPaymentMethodText()

	protected function getNewPaymentMethodText()
	{
		return Store::_('Add a New Payment Method');
	}

	// }}}
	// {{{ protected function loadDataFromSession()

	protected function loadDataFromSession()
	{
		$order = $this->app->session->order;
		$tag = $this->getArgument('tag');
		$order_payment_method = $this->getPaymentMethod($order->payment_methods);

		if ($this->ui->hasWidget('payment_amount_field')) {
			// only display the amount field if the order has
			// an adjustable payment method.
			$this->ui->getWidget('payment_amount_field')->visible =
				$this->orderHasAdjustableMethod(true);
		}

		$option_list = $this->ui->getWidget('payment_option');

		if ($order_payment_method === null) {
			$this->ui->getWidget('card_fullname')->value =
				$this->app->session->account->fullname;

			$default_payment_method = $this->getDefaultPaymentMethod();
			if ($default_payment_method !== null) {
				$option_list->selected_page =
					'method_'.$default_payment_method->id;
			}
		} else {
			if ($order_payment_method->getAccountPaymentMethodId() === null) {
				$option_list->selected_page =
					'type_'.$order_payment_method->getInternalValue(
						'payment_type');

				if ($this->ui->hasWidget('payment_amount')) {
					$this->ui->getWidget('payment_amount')->value =
						$order_payment_method->amount;
				}

				$this->ui->getWidget('card_type')->value =
					$order_payment_method->getInternalValue('card_type');

				/*
				 *  Note: We can't repopulate the card number entry since we
				 *        only store the encrypted number in the dataobject.
				 */
				if ($order_payment_method->hasCardNumber()) {
					$this->ui->getWidget('card_number')->show_blank_value =
						true;
				}

				if ($order_payment_method->hasCardVerificationValue()) {
					$cvv = $this->ui->getWidget('card_verification_value');
					$card_type = $this->getCardType();
					if ($card_type !== null) {
						$cvv->setCardType($card_type);
						$cvv->show_blank_value = true;
					}
				}

				$this->ui->getWidget('card_issue_number')->value =
					$order_payment_method->card_issue_number;

				$this->ui->getWidget('card_expiry')->value =
					$order_payment_method->card_expiry;

				$this->ui->getWidget('card_inception')->value =
					$order_payment_method->card_inception;

				$this->ui->getWidget('card_fullname')->value =
					$order_payment_method->card_fullname;
			} else {
				$method_id = $order_payment_method->getAccountPaymentMethodId();
				$this->ui->getWidget('payment_option')->selected_page =
					'method_'.$method_id;

				if ($order_payment_method->hasCardVerificationValue()) {
					$replicator = $this->ui->getWidget(
						'account_payment_methods_replicator'
					);

					$cvv = $replicator->getWidget(
						'account_card_verification_value',
						$method_id
					);


					if ($cvv instanceof StoreCardVerificationValueEntry) {
						$card_type = $this->getCardType();
						if ($card_type !== null) {
							$cvv->setCardType($card_type);
							$cvv->show_blank_value = true;
						}
					}
				}
			}
		}

		if ($this->app->session->checkout_with_account &&
			isset($this->app->session->save_account_payment_method)) {
			$this->ui->getWidget('save_account_payment_method')->value =
				$this->app->session->save_account_payment_method;
		}
	}

	// }}}
	// {{{ protected function getDefaultPaymentMethod()

	protected function getDefaultPaymentMethod()
	{
		$payment_method = null;

		if ($this->app->session->isLoggedIn()) {
			$default_payment_method =
				$this->app->session->account->getDefaultPaymentMethod();

			if ($default_payment_method instanceof StorePaymentMethod) {
				// only default to a payment method that appears in the list
				$payment_option = $this->ui->getWidget('payment_option');
				$page_id = 'method_'.$default_payment_method->id;
				if ($payment_option->getPage($page_id)
					instanceof SwatNoteBookPage) {
					$payment_method = $default_payment_method;
				}
			}
		}

		return $payment_method;
	}

	// }}}
	// {{{ protected function buildCurrentPaymentMethods()

	protected function buildCurrentPaymentMethods()
	{
		if ($this->app->config->store->multiple_payment_ui &&
			$this->ui->hasWidget('current_payment_methods')) {

			$methods = $this->app->session->order->payment_methods;

			if (count($methods) > 0) {
				$block = $this->ui->getWidget('current_payment_methods');
				$block->parent->visible = true;

				ob_start();
				$this->displayMultiplePaymentMethods($methods);
				$block->content = ob_get_clean();
			}
		}
	}

	// }}}
	// {{{ protected function displayMultiplePaymentMethods()

	protected function displayMultiplePaymentMethods($methods)
	{
		echo '<table class="multiple-payment-table"><tbody>';

		foreach ($methods as $method) {
			echo '<tr><th class="payment">';
			$method->showCardExpiry(false);
			$method->display();
			echo '</th><td class="payment-amount">';
			$method->displayAmount();
			echo '</td></tr>';
		}

		echo '</tbody><tfoot>';

		$locale = SwatI18NLocale::get();
		$balance = $this->getOrderBalance();

		echo '<tr><th>Payment Total:</th><td class="payment-amount">';
		echo $locale->formatCurrency($this->getCartTotal() - $balance);
		echo '</td></tr>';

		if ($balance > 0) {
			echo '<tr class="payment-remaining swat-error">'.
				'<th>Remaining Balance:</th><td class="payment-amount">';

			echo $locale->formatCurrency($balance);
			echo '</td></tr>';
		}

		echo '</tfoot></table>';
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/styles/store-checkout-payment-method-page.css',
			Store::PACKAGE_ID));

		$this->layout->addHtmlHeadEntrySet(
			$this->ui->getRoot()->getHtmlHeadEntrySet());

		$yui = new SwatYUI(array('dom', 'event'));
		$this->layout->addHtmlHeadEntrySet($yui->getHtmlHeadEntrySet());

		$path = 'packages/store/javascript/';
		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			$path.'store-checkout-page.js', Store::PACKAGE_ID));

		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			$path.'store-checkout-payment-method-page.js',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
