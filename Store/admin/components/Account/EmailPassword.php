<?php

require_once 'Admin/pages/AdminConfirmation.php';
require_once 'Admin/exceptions/AdminNoAccessException.php';
require_once 'Store/dataobjects/StoreAccount.php';
require_once 'SwatDB/SwatDB.php';

require_once '../../include/NewPasswordMailMessage.php';

/**
 * Page to generate a new password for an account and email the new password
 * to the account holder
 *
 * @package   veseys2
 * @copyright 2006 silverorange
 */
class AccountEmailPassword extends AdminConfirmation
{
	/**
	 * @var StoreAccount
	 */
	private $account;

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$this->ui->mapClassPrefixToPath('Store', 'Store');
		$this->ui->loadFromXML('Admin/pages/confirmation.xml');

		$this->id = SiteApplication::initVar('id');

		$sql = sprintf('select id, fullname, email from Account where id = %s',
			$this->app->db->quote($this->id, 'integer'));

		$this->account = SwatDB::query($this->app->db, $sql,
			'StoreAccountWrapper')->getFirst();

		if ($this->account === null)
			throw new AdminNoAccessException();
	}

	// }}}

	// process phase
	// {{{ protected function processDBData()

	protected function processResponse()
	{
		$form = $this->ui->getWidget('confirmation_form');

		if ($form->button->id == 'yes_button') {
			$this->account->generateNewPassword($this->app);

			$msg = new SwatMessage(sprintf(
				'%s’s password has been reset and has been emailed to '.
				'<a href="email:%s">%s</a>.',
				$this->account->fullname, $this->account->email,
				$this->account->email));

			$msg->content_type = 'text/xml';

			$this->app->messages->add($msg);
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$form = $this->ui->getWidget('confirmation_form');
		$form->addHiddenField('id', $this->id);

		$this->title = $this->account->fullname;

		$this->navbar->createEntry($this->account->fullname,
			sprintf('Account/Details?id=%s', $this->id));

		$this->navbar->createEntry('Email New Password Confirmation');

		$message = $this->ui->getWidget('confirmation_message');
		$message->content = $this->getConfirmationMessage();
		$message->content_type = 'text/xml';

		$this->ui->getWidget('yes_button')->title = 'Reset & Email Password';
	}

	// }}}
	// {{{ private function getConfirmationMessage()

	private function getConfirmationMessage()
	{
		ob_start();
		$confirmation_title = new SwatHtmlTag('h3');

		$confirmation_title->setContent(
			sprintf('Are you sure you want to reset the password for %s?', 
				$this->account->fullname));

		$confirmation_title->display();

		$email_anchor = new SwatHtmlTag('a');
		$email_anchor->href = sprintf('email:%s', $this->account->email);
		$email_anchor->setContent($this->account->email);

		echo 'A new password will be generated and sent to ';
		$email_anchor->display();
		echo '.';

		return ob_get_clean();
	}

	// }}}
}

?>
