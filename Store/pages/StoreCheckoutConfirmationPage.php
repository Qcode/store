<?php

require_once 'Swat/SwatDate.php';
require_once 'Store/pages/StoreCheckoutPage.php';
require_once 'Store/dataobjects/StoreOrderItemWrapper.php';
require_once 'Store/dataobjects/StoreCartEntry.php';

/**
 * Confirmation page of checkout
 *
 * @package   Store
 * @copyright 2006 silverorange
 */
class StoreCheckoutConfirmationPage extends StoreCheckoutPage
{
	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();
		$this->checkOrder();
	}

	// }}}
	// {{{ private function checkOrder()

	private function checkOrder()
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

		if ($form->isProcessed()) {
			$this->updateProgress();

			$ordered_entries = $this->processOrder();
			$this->updateInventory($ordered_entries);
			$this->sendConfirmationEmail($order);

			$this->app->relocate('checkout/thankyou');
		}
	}

	// }}}
	// {{{ protected function processOrder()

	/**
	 * @return array a reference to an array of ordered entries
	 */
	protected function &processOrder()
	{
		$order = $this->app->session->order;

		// set createdate to now
		$order->createdate = new SwatDate();

		// save order
		$order->save();

		// we're done, remove order from session
		$this->app->session->order = null;

		// remove entries from cart that were ordered
		$ordered_entries = 
			$this->app->cart->checkout->removeAvailableEntries();

		return $ordered_entries;
	}

	// }}}
	// {{{ protected function updateInventory()

	protected function updateInventory($entries)
	{
	}

	// }}}
	// {{{ protected function sendConfirmationEmail()

	protected function sendConfirmationEmail($order)
	{
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();
		$this->createOrder();
		$this->createOrderItems();
	}

	// }}}
	// {{{ protected function createOrder()

	protected function createOrder()
	{
		$cart = $this->app->cart->checkout;
		$order = $this->app->session->order;

		$order->locale = $this->app->getLocale();

		if (isset($this->session->ad))
			$order->ad = $this->session->ad;

		$order->item_total = $cart->getItemTotal();

		$order->shipping_total = $cart->getShippingTotal($order->billing_address,
			 $order->shipping_address);

		$order->tax_total = $cart->getTaxTotal($order->billing_address,
			 $order->shipping_address);

		$order->total = $cart->getTotal($order->billing_address,
			$order->shipping_address);
	}

	// }}}
	// {{{ protected function createOrderItems()

	protected function createOrderItems()
	{
		$order = $this->app->session->order;
		$class_map = StoreClassMap::instance();
		$wrapper = $class_map->resolveClass('StoreOrderItemWrapper');
		$order->items = new $wrapper();

		$tax_provstate = $this->app->cart->checkout->getTaxProvState(
			$order->billing_address, $order->shipping_address);

		foreach ($this->app->cart->checkout->getAvailableEntries() as $entry) {
			$order_item = $entry->createOrderItem($tax_provstate);
			$order->items->add($order_item);
		}
	}

	// }}}
}

?>
