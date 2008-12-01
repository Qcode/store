<?php

require_once 'Swat/SwatYUI.php';
require_once 'Site/pages/SiteAccountPage.php';
require_once 'Store/dataobjects/StoreAccountAddress.php';
require_once 'Swat/SwatUI.php';

/**
 * Page for adding and editing addresses stored on accounts
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreAccount
 */
class StoreAccountAddressEditPage extends SiteAccountPage
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/pages/account-address-edit.xml';

	/**
	 * @var SwatUI
	 */
	protected $ui;

	// }}}
	// {{{ private properties

	private $id;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteAbstractPage $page)
	{
		parent::__construct($page);

		$this->id = intval($this->getArgument('id'));

		if ($this->id === 0)
			$this->id = null;
	}

	// }}}
	// {{{ protected function getArgumentMap()

	protected function getArgumentMap()
	{
		return array(
			'id' => array(0, 0),
		);
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->ui = new SwatUI();
		$this->ui->loadFromXML($this->ui_xml);

		$this->initInternal();
		$this->ui->init();
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		$form = $this->ui->getWidget('edit_form');
		$form->action = $this->source;
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		$form = $this->ui->getWidget('edit_form');

		if ($form->isSubmitted())
			$this->setupPostalCode();

		parent::process();
		$form->process();

		if ($form->isProcessed()) {
			$this->validate();

			if ($form->hasMessage()) {
				$message = new SwatMessage(Store::_('There is a problem with '.
					'the information submitted.'), SwatMessage::ERROR);

				$message->secondary_content = Store::_('Please address the '.
					'fields highlighted below and re-submit the form.');

				$this->ui->getWidget('message_display')->add($message);
			} else {
				$address = $this->findAddress();
				$this->updateAddress($address);

				if ($this->id === null) {
					$this->app->session->account->addresses->add($address);
					$this->addMessage(Store::_('One address has been added.'),
						$address);

				} elseif ($address->isModified()) {
					$this->addMessage(Store::_('One address has been updated.'),
						$address);
				}

				$this->app->session->account->save();
				$this->app->relocate('account');
			}
		}
	}

	// }}}
	// {{{ protected function updateAddress()

	protected function updateAddress(StoreAccountAddress $address)
	{
		$address->fullname = $this->ui->getWidget('fullname')->value;
		$address->company = $this->ui->getWidget('company')->value;
		$address->phone =  $this->ui->getWidget('phone')->value;
		$address->line1 = $this->ui->getWidget('line1')->value;
		$address->line2 = $this->ui->getWidget('line2')->value;
		$address->city = $this->ui->getWidget('city')->value;

		$provstate = $this->ui->getWidget('provstate')->value;
		$address->provstate = ($provstate === 'other') ? null : $provstate;

		$address->provstate_other =
			$this->ui->getWidget('provstate_other')->value;

		$address->postal_code = $this->ui->getWidget('postal_code')->value;
		$address->country = $this->ui->getWidget('country')->value;

		if ($this->id === null) {
			$address->createdate = new SwatDate();
			$address->createdate->toUTC();
		}
	}

	// }}}
	// {{{ protected function validate()

	protected function validate()
	{
	}

	// }}}
	// {{{ private function addMessage()

	private function addMessage($text, $address)
	{
		ob_start();
		$address->displayCondensed();
		$address_condensed = ob_get_clean();

		$message = new SwatMessage($text, SwatMessage::NOTIFICATION);
		$message->secondary_content = $address_condensed;
		$message->content_type = 'text/xml';
		$this->app->messages->add($message);
	}

	// }}}
	// {{{ private function setupPostalCode()

	private function setupPostalCode()
	{
		// set provsate and country on postal code entry
		$postal_code = $this->ui->getWidget('postal_code');
		$country = $this->ui->getWidget('country');
		$provstate = $this->ui->getWidget('provstate');

		$country->process();
		$provstate->country = $country->value;
		$provstate->setDatabase($this->app->db);
		$provstate->process();

		if ($provstate->value === 'other') {
			$this->ui->getWidget('provstate_other')->required = true;
		} elseif ($provstate->value !== null) {
			$sql = sprintf('select abbreviation from ProvState where id = %s',
				$this->app->db->quote($provstate->value));

			$provstate_abbreviation = SwatDB::queryOne($this->app->db, $sql);
			$postal_code->country = $country->value;
			$postal_code->provstate = $provstate_abbreviation;
		}
	}

	// }}}
	// {{{ private function findAddress()

	private function findAddress()
	{
		if ($this->id === null) {
			$class = SwatDBClassMap::get('StoreAccountAddress');
			return new $class;
		}

		$address =
			$this->app->session->account->addresses->getByIndex($this->id);

		if ($address === null)
			throw new SiteNotFoundException(
				sprintf('An address with an id of ‘%d’ does not exist.',
				$this->id));

		return $address;
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$form = $this->ui->getWidget('edit_form');
		$form->action = $this->source;

		if ($this->id === null) {
			$this->layout->navbar->createEntry(Store::_('Add a New Address'));
			$this->layout->data->title = Store::_('Add a New Address');
		} else {
			$this->layout->navbar->createEntry(
				Store::_('Edit an Existing Address'));

			$this->ui->getWidget('submit_button')->title =
				Store::_('Update Address');

			$this->layout->data->title = Store::_('Edit an Exisiting Address');
		}

		$provstate_flydown = $this->ui->getWidget('provstate');
		$provstate_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'ProvState', 'title', 'id', 'title'));

		$provstate_other = $this->ui->getWidget('provstate_other');
		if ($provstate_other->visible) {
			$provstate_flydown->addDivider();
			$option = new SwatOption('other', 'Other…');
			$provstate_flydown->addOption($option);
		}

		$country_flydown = $this->ui->getWidget('country');
		$country_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'Country', 'title', 'id', 'title',
			sprintf('visible = %s', $this->app->db->quote(true, 'boolean'))));

		if (!$form->isProcessed()) {
			if ($this->id === null) {
				$this->setDefaultValues($this->app->session->account);
			} else {
				$address = $this->findAddress();
				$this->setWidgetValues($address);
			}
		}

		$this->layout->startCapture('content');
		$this->ui->display();
		Swat::displayInlineJavaScript($this->getInlineJavaScript());
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function getInlineJavaScript()

	protected function getInlineJavaScript()
	{
		$provstate = $this->ui->getWidget('provstate');
		$provstate_other_index = count($provstate->options);
		$id = 'account_address';
		return sprintf("var %s_obj = new StoreAccountAddressPage('%s', %s);",
			$id, $id, $provstate_other_index);
	}

	// }}}
	// {{{ protected function setWidgetValues()

	protected function setWidgetValues(StoreAccountAddress $address)
	{
		$this->ui->getWidget('fullname')->value = $address->fullname;
		$this->ui->getWidget('company')->value = $address->company;
		$this->ui->getWidget('phone')->value = $address->phone;
		$this->ui->getWidget('line1')->value = $address->line1;
		$this->ui->getWidget('line2')->value = $address->line2;
		$this->ui->getWidget('city')->value = $address->city;

		$provstate_other = $this->ui->getWidget('provstate_other');
		if ($provstate_other->visible && $address->provstate === null)
			$this->ui->getWidget('provstate')->value = 'other';
		else
			$this->ui->getWidget('provstate')->value =
				$address->getInternalValue('provstate');

		$provstate_other->value = $address->provstate_other;

		$this->ui->getWidget('postal_code')->value = $address->postal_code;
		$this->ui->getWidget('country')->value = $address->country->id;
	}

	// }}}
	// {{{ protected function setDefaultValues()

	/**
	 * Sets default values of this address based on values from the account
	 *
	 * @param StoreAccount $account the account to set default values from.
	 */
	protected function setDefaultValues(StoreAccount $account)
	{
		$this->ui->getWidget('fullname')->value = $account->fullname;
		$this->ui->getWidget('company')->value = $account->company;
		$this->ui->getWidget('phone')->value = $account->phone;
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$yui = new SwatYUI(array('dom', 'event'));
		$this->layout->addHtmlHeadEntrySet($yui->getHtmlHeadEntrySet());
		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			'packages/store/javascript/store-account-address-page.js',
			Store::PACKAGE_ID));

		$this->layout->addHtmlHeadEntrySet(
			$this->ui->getRoot()->getHtmlHeadEntrySet());
	}

	// }}}
}

?>
