<?php

require_once 'Store/pages/StoreCheckoutPage.php';
require_once 'Swat/SwatUI.php';

/**
 * Base class for checkout pages with a UI
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 */
abstract class StoreCheckoutUIPage extends StoreCheckoutPage
{
	// {{{ protected properties

	protected $ui = null;
	protected $ui_xml = null;

	// }}}
	// {{{ public function setUI()

	public function setUI($ui = null)
	{
		$this->ui = $ui;
	}

	// }}}
	// {{{ public function getXml()

	public function getXml()
	{
		return $this->ui_xml;
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->loadUI();
		$this->initInternal();
		$this->ui->init();
	}

	// }}}
	// {{{ protected function loadUI()

	protected function loadUI()
	{
		$this->ui = new SwatUI();
		$this->ui->loadFromXML('Store/pages/checkout.xml');

		$form = $this->ui->getWidget('form');
		$this->ui->loadFromXML($this->ui_xml, $form);
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$form = $this->ui->getWidget('form');
		$form->action = $this->source;
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$this->buildInternal();

		if ($this->app->session->order->isFromInvoice()) {
			$entry = $this->layout->navbar->getEntryByPosition(1);
			$entry->link = sprintf('checkout/invoice%s',
				$this->app->session->order->invoice->id);
		}

		$this->layout->startCapture('content');
		$this->ui->display();
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntrySet(
			$this->ui->getRoot()->getHtmlHeadEntrySet());
	}

	// }}}
}

?>
