<?php

require_once 'Site/pages/SiteUiPage.php';
require_once 'Store/StorePaymentRequest.php';
require_once 'Store/dataobjects/StoreAccount.php';
require_once 'Store/dataobjects/StoreOrder.php';

/**
 * Base class for checkout pages
 *
 * @package   Store
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class StoreCheckoutPage extends SiteUiPage
{
	// {{{ public function setUI()

	public function setUI($ui = null)
	{
		$this->ui = $ui;
	}

	// }}}
	// {{{ protected function getBaseUiXml()

	protected function getBaseUiXml()
	{
		return 'Store/pages/checkout.xml';
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		if (!$this->app->session->isActive())
			$this->app->relocate($this->getCartSource());

		if (!$this->checkCart())
			$this->app->relocate($this->getCartSource());

		$this->app->session->activate();

		// initialize session variable to track checkout progress
		if (!isset($this->app->session->checkout_progress))
			$this->resetProgress();

		$this->initDataObjects();
		$this->checkProgress();

		// If the order has been saved then the checkout process is complete.
		// If the user isn't on the thank you page then relocate there now.
		// This prevents duplicate orders when the confirmation page is
		// submitted multiple times.
		$thank_you_source = $this->getThankYouSource();
		if ($this->app->session->order->id !== null &&
			$this->getSource() !== $thank_you_source) {
			// The thank you page has checkout/confirmation as a progress
			// dependency. As we have a completed order on the session, make
			// sure the dependency is set before relocated there. Prevents a
			// relocation loop.
			$this->app->checkout->setProgress($this->getConfirmationSource());
			$this->app->relocate($thank_you_source);
		}

		SitePageDecorator::init();

		$this->loadUI();
		$this->initInternal();
		$this->ui->init();
	}

	// }}}
	// {{{ protected function loadUI()

	protected function loadUI()
	{
		$this->ui = new SwatUI();
		$this->ui->loadFromXML($this->getBaseUiXml());

		/*
		 * Only load the page's xml if it actually exists. This allows
		 * subclasses to use StoreCheckoutPage, but not define any extra xml
		 * (for example: a payment processing landing page that executes some
		 * code and then relocates).
		 */
		$form = $this->ui->getWidget('form');
		$xml  = $this->getUiXml();
		if ($xml !== null)
			$this->ui->loadFromXML($xml, $form);
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$form = $this->ui->getWidget('form');
		$form->action = $this->source;
	}

	// }}}
	// {{{ protected function getProgressDependencies()

	protected function getProgressDependencies()
	{
		return array();
	}

	// }}}
	// {{{ protected function initDataObjects()

	protected function initDataObjects()
	{
		$this->app->checkout->initDataObjects();
	}

	// }}}
	// {{{ protected function checkCart()

	protected function checkCart()
	{
		return $this->app->cart->checkout->checkoutEnabled();
	}

	// }}}
	// {{{ protected function checkProgress()

	/**
	 * Enforces dependencies for progressing through the checkout
	 *
	 * If a dependency is not met for this page, the user is redicted to the
	 * unmet dependency page.
	 */
	protected function checkProgress()
	{
		foreach ($this->getProgressDependencies() as $dependency) {
			if (!$this->app->checkout->hasProgressDependency($dependency)) {
				$this->app->relocate($dependency);
			}
		}
	}

	// }}}
	// {{{ protected function getConfirmationSource()

	protected function getConfirmationSource()
	{
		return 'checkout/confirmation';
	}

	// }}}
	// {{{ protected function getThankYouSource()

	protected function getThankYouSource()
	{
		return 'checkout/thankyou';
	}

	// }}}
	// {{{ protected function getCartSource()

	protected function getCartSource()
	{
		return 'cart';
	}

	// }}}

	// process phase
	// {{{ protected function updateProgress()

	protected function updateProgress()
	{
		$this->app->checkout->setProgress($this->getSource());
	}

	// }}}
	// {{{ protected function resetProgress()

	protected function resetProgress()
	{
		$this->app->checkout->resetProgress();
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		if ($this->app->session->order->isFromInvoice()) {
			$entry = $this->layout->navbar->getEntryByPosition(1);
			$entry->link = sprintf('checkout/invoice%s',
				$this->app->session->order->invoice->id);
		}
	}

	// }}}
}

?>
