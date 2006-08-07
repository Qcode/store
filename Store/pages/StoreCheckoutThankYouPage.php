<?php

require_once 'Store/pages/StoreCheckoutPage.php';

/**
 * Confirmation page of checkout
 *
 * @package   Store
 * @copyright 2006 silverorange
 */
class StoreCheckoutThankYouPage extends StoreCheckoutPage
{
	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->resetProgress();
	}

	// }}}
	// {{{ protected function checkCart()

	protected function checkCart()
	{
		// do nothing - cart should be empty now
	}

	// }}}
	// {{{ protected function getProgressDependencies()

	protected function getProgressDependencies()
	{
		return array('checkout/confirmation');
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$this->buildInternal();

		$this->layout->startCapture('content');
		$this->display();
		$this->layout->endCapture();

		// clear the order and logout
		unset($this->app->session->order);
		$this->app->session->logout();
	}

	// }}}
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
	}

	// }}}
	// {{{ protected function display()

	protected function display()
	{
	}

	// }}}
}

?>
