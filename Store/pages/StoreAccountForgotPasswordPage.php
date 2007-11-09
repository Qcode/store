<?php

require_once 'Store/dataobjects/StoreAccount.php';
require_once 'Store/pages/StoreAccountPage.php';
require_once 'Swat/SwatUI.php';
require_once 'Store/dataobjects/StoreAccount.php';
require_once 'Text/Password.php';

/**
 * Page for requesting a new password for forgotten account passwords
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreAccount
 * @see       StoreAccountResetPasswordPage
 */
class StoreAccountForgotPasswordPage extends StoreAccountPage
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/pages/account-forgot-password.xml';
	protected $ui;

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->ui = new SwatUI();
		$this->ui->loadFromXML($this->ui_xml);

		$form = $this->ui->getWidget('password_form');
		$form->action = $this->source;

		$this->ui->init();
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		parent::process();

		$form = $this->ui->getWidget('password_form');

		$form->process();

		if ($form->isProcessed()) {
			if (!$form->hasMessage())
				$this->generatePassword();

			if (!$form->hasMessage())
				$this->app->relocate('account/forgotpassword/sent');
		}
	}

	// }}}
	// {{{ protected function getAccount()

	/**
	 * Gets the account to which to sent the forgot password email
	 *
	 * @param string $email the email address of the account.
	 *
	 * @return StoreAccount the account or null if no such account exists.
	 */
	protected function getAccount($email)
	{
		$class_name = SwatDBClassMap::get('SiteAccount');
		$account = new $class_name();
		$account->setDatabase($this->app->db);
		$found = $account->loadWithEmail($email);

		if ($found === false)
				$account = null;

		return $account;
	}

	// }}}
	// {{{ private function generatePassword()

	private function generatePassword()
	{
		$email = $this->ui->getWidget('email')->value;

		$account = $this->getAccount($email);

		if ($account === null) {
			$message = new SwatMessage(Store::_(
				'There is no account with this email address.'),
				SwatMessage::ERROR);

			$message->secondary_content = sprintf(Store::_(
				'Make sure you entered it correctly, or '.
				'%screate a New Account%s.'),
				'<a href="account/edit">', '</a>');

			$message->content_type = 'text/xml';
			$this->ui->getWidget('email')->addMessage($message);
		} else {
			$password_tag = $account->resetPassword($this->app);
			$password_link = $this->app->getBaseHref().
				'account/resetpassword/'.$password_tag;

			$account->sendResetPasswordMailMessage($this->app, $password_link);
		}
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$email = $this->app->initVar('email');
		if ($email !== null)
			$this->ui->getWidget('email')->value = $email;

		$this->layout->startCapture('content', true);
		$this->ui->display();
		$this->layout->endCapture();
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
