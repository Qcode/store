<?php

require_once 'Store/pages/StoreArticlePage.php';
require_once 'Store/StoreUI.php';

/**
 *
 * @package   Store
 * @copyright 2006 silverorange
 */
class StoreAccountResetPasswordThankyouPage extends StoreArticlePage
{
	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$this->layout->startCapture('content');

		echo '<div class="notification">',
			'<h4>', Store::_('Your Password has been Reset'), '</h4>',
			'<p>', Store::_('Your password has been reset and you are now '.
			'logged-in.'), '</p>',
			'<ul class="redirect-links">';

		if (count($this->app->cart->checkout->getAvailableEntries()) > 0)
			echo '<li><a href="checkout">', Store::_('Proceed to Checkout'),
				'</a></li>';

		echo '<li><a href="account">', Store::_('View your Account'),
			'</a></li>',
			'</ul>',
			'</div>';

		$this->layout->endCapture();
	}

	// }}}
}

?>
