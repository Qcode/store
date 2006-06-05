<?php

require_once 'Store/dataobjects/StorePaymentMethod.php';
require_once 'Swat/SwatConfirmationButton.php';
require_once 'Swat/SwatHtmlTag.php';
require_once 'Swat/SwatControl.php';

/**
 * A viewer for an address object.
 *
 * @package   Store
 * @copyright 2005 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */

class StoreAddressView extends SwatControl
{
	private $address = null;
	private $remove_button;
	private $edit_address_link = 'account/address%s';

	public function __construct(StoreAddress $address)
	{
		$this->address = $address;
		$this->remove_button =
			new SwatConfirmationButton('address_remove_'.$address->id);
	}

	public function process()
	{
		$this->remove_button->process();
	}

	public function hasBeenClicked()
	{
		return $this->remove_button->hasBeenClicked();
	}

	public function getHtmlHeadEntries()
	{
		$out = new SwatHtmlHeadEntrySet($this->html_head_entries);
		$out->addEntrySet($this->remove_button->getHtmlHeadEntries());

		return $out;
	}

	public function display()
	{
		ob_start();
		//$this->address->displayCondensedAsText();
		//TEMP because the above is currently broken
		echo 'Steven Garrity, Loserville,
			IN, USA, 50567';
		$address_text = ob_get_clean();

		$div = new SwatHtmlTag('div');

		$div->open();

		//TEMP because the above is currently broken
		//$this->address->displayCondensed();
		echo 'Steven Garrity, Loserville, IN, USA, 50567';

		$a = new SwatHtmlTag('a');
		$a->href = sprintf($this->edit_address_link, $this->address->id);
		$a->setContent('edit address');
		$a->display();

		$this->remove_button->title = 'Remove Address';
		$this->remove_button->confirmation_message = sprintf(
			"Are you sure you want to remove the following address?\n\n%s",
			$address_text);
		$this->remove_button->display();

		$div->close();
	}
}

?>
