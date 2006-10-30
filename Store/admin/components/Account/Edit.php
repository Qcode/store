<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatMessage.php';

/**
 * Edit page for Accounts
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreAccountEdit extends AdminDBEdit
{
	// {{{ protected properties

	protected $fields;

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/admin/components/Account/edit.xml';

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->mapClassPrefixToPath('Store', 'Store');
		$this->ui->loadFromXML($this->ui_xml);

		$this->fields = array('fullname', 'email', 'phone');
	}

	// }}}

	// process phase
	// {{{ protected function validate()

	protected function validate()
	{
		if ($this->id !== null) {
			$email = $this->ui->getWidget('email');
			if ($email->hasMessage())
				return;
	
			$query = SwatDB::query($this->app->db, sprintf('select email
				from Account where lower(email) = lower(%s) and id %s %s',
				$this->app->db->quote($email->value, 'text'),
				SwatDB::equalityOperator($this->id, true),
				$this->app->db->quote($this->id, 'integer')));
	
			if (count($query) > 0) {
				$message = new SwatMessage(Store::_(
					'An account already exists with this email address.'),
					SwatMessage::ERROR);
	
				$email->addMessage($message);
			}
		}
	}

	// }}}
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$values = $this->getUIValues();

		SwatDB::updateRow($this->app->db, 'Account', $this->fields, $values,
			'id', $this->id);

		$msg = new SwatMessage(sprintf(Store::_('Account “%s” has been saved.'),
			$values['fullname']));

		$this->app->messages->add($msg);
	}

	// }}}
	// {{{ protected function getUIValues()

	protected function getUIValues()
	{
		return $this->ui->getValues(array('fullname', 'email', 'phone'));
	}

	// }}}

	// build phase
	// {{{ private function buildNavBar()

	protected function buildNavBar() 
	{
		$account_fullname = SwatDB::queryOneFromTable($this->app->db, 
			'Account', 'text:fullname', 'id', $this->id);

		$this->navbar->addEntry(new SwatNavBarEntry($account_fullname, 
			sprintf('Account/Details?id=%s', $this->id)));
		$this->navbar->addEntry(new SwatNavBarEntry(Store::_('Edit')));
		$this->title = $account_fullname;
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		$row = SwatDB::queryRowFromTable($this->app->db, 'Account', 
			$this->fields, 'id', $this->id);

		if ($row === null)
			throw new AdminNotFoundException(
				sprintf(Store::_("Account with id ‘%s’ not found."),
				$this->id));

		$this->ui->setValues(get_object_vars($row));
	}

	// }}}
}

?>
