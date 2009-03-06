<?php

require_once 'Store/pages/StoreCheckoutFinalPage.php';

/**
 * Page displayed when an order is processed successfully on the checkout
 *
 * @package   Store
 * @copyright 2006-2009 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCheckoutThankYouPage extends StoreCheckoutFinalPage
{
	// {{{ public function getUiXml()

	public function getUiXml()
	{
		return 'Store/pages/checkout-thank-you.xml';
	}

	// }}}
	// {{{ protected function displayFinalNote()

	protected function displayFinalNote(StoreOrder $order)
	{
		echo '<div id="checkout_thank_you">';
		$header_tag = new SwatHtmlTag('h3');
		$header_tag->setContent(Store::_('Your order has been placed.'));
		$paragraph_tag = new SwatHtmlTag('p');
		$paragraph_tag->setContent(Store::_(
			'Thank you for your order. You will receive an email '.
			'confirmation which will include the following detailed '.
			'order receipt for your records. If you wish, you can print '.
			'a copy of this page for reference.'));

		$header_tag->display();
		$paragraph_tag->display();
		echo "</div>";
	}

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		if ($this->ui->hasWidget('checkout_progress')) {
			$checkout_progress = $this->ui->getWidget('checkout_progress');
			$checkout_progress->current_step = 3;
		}
	}

	// }}}
}

?>
