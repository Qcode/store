<?php

require_once 'Store/pages/StoreCheckoutUIPage.php';

/**
 * Front page of checkout
 *
 * @package   Store
 * @copyright 2006 silverorange
 */
class StoreCheckoutFrontPage extends StoreCheckoutUIPage
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/pages/checkout-front.xml';
	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		// skip the checkout front page if logged in
		if ($this->app->session->isLoggedIn()) {
			$this->resetProgress();
			$this->updateProgress();
			$this->app->session->checkout_with_account = true;
			$this->app->relocate('checkout/first');
		}

		parent::init();
	}

	// }}}
	// {{{ protected function loadUI()

	protected function loadUI()
	{
		$this->ui = new StoreUI();
		$this->ui->loadFromXML($this->ui_xml);
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		foreach ($this->ui->getRoot()->getDescendants('SwatForm') as $form)
			$form->action = $this->source;
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		parent::process();
		$this->ui->process();

		$create_account_form = $this->ui->getWidget('create_account_form');
		$just_place_form = $this->ui->getWidget('just_place_form');
		$login_form = $this->ui->getWidget('login_form');

		if ($create_account_form->isProcessed())
			$this->processCreateAccount();

		if ($just_place_form->isProcessed())
			$this->processJustPlace();

		if ($login_form->isProcessed())
			$this->processLogin($login_form);
	}

	// }}}
	// {{{ private function processCreateAccount()

	private function processCreateAccount()
	{
		$this->initDataObjects();
		$this->resetProgress();
		$this->updateProgress();
		$this->app->session->checkout_with_account = true;
		$this->app->relocate('checkout/first');
	}

	// }}}
	// {{{ private function processJustPlace()

	private function processJustPlace()
	{
		$this->app->session->checkout_with_account = false;
		$this->initDataObjects();
		$this->resetProgress();
		$this->updateProgress();
		$this->app->relocate('checkout/first');
	}

	// }}}
	// {{{ private function processLogin()

	private function processLogin($login_form)
	{
		if (!$login_form->hasMessage()) {
			$email = $this->ui->getWidget('email_address')->value;
			$password = $this->ui->getWidget('password')->value;

			if ($this->app->session->login($email, $password)) {
				$this->app->session->checkout_with_account = true;
				$this->initDataObjects();
				$this->resetProgress();
				$this->updateProgress();
				$this->app->relocate('checkout/first');
			} else {
				$message = new SwatMessage(Store::_('Login Incorrect'),
					SwatMessage::WARNING);

				$tips = array(
					Store::_('Please check the spelling on your email '.
						'address or password'),
					sprintf(Store::_('Password is case-sensitive. Make sure '.
                        'your %sCaps Lock%s key is off'), '<kbd>', '</kbd>'),
				);
				$message->secondary_content =
					vsprintf('<ul><li>%s</li><li>%s</li></ul>', $tips);

				$message->content_type = 'text/xml';

				$this->ui->getWidget('message_display')->add($message);
			}
		}
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();
	
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/styles/store-checkout-front-page.css',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
