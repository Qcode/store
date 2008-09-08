<?php

require_once 'Swat/SwatControl.php';
require_once 'Store/Store.php';

/**
 * @package   Store
 * @copyright 2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCheckoutProgress extends SwatControl
{
	// {{{ public properties

	/**
	 * @var integer
	 */
	public $current_step = 0;

	// }}}
	// {{{ public function __construct()

	public function __construct($id = null)
	{
		parent::__construct($id);
		$this->addStyleSheet(
			'packages/store/styles/store-checkout-progress.css',
			Store::PACKAGE_ID);
	}

	// }}}
	// {{{ public function display()

	public function display()
	{
		if (!$this->visible)
			return;

		parent::display();

		$ol_tag = new SwatHtmlTag('ol');
		$ol_tag->id = $this->id;
		if ($this->current_step > 0)
			$ol_tag->class = ' store-checkout-progress-step'.$this->current_step;


		echo '<div class="store-checkout-progress">';
		$ol_tag->open();

		foreach ($this->getSteps() as $id => $step) {
			$li_tag = new SwatHtmlTag('li');
			$li_tag->class = 'store-checkout-progress-step'.$id;
			$li_tag->setContent('<span>'.$step.'</span>', 'text/xml');
			$li_tag->display();
		}

		$ol_tag->close();
		echo '<div class="store-checkout-progress-clear"></div>';
		echo '</div>';

	}

	// }}}
	// {{{ private static function getSteps()

	private static function getSteps()
	{
		return array(
			'1' => Store::_('Your Information'),
			'2' => Store::_('Review Order'),
			'3' => Store::_('Order Completed'),
		);
	}

	// }}}
}

?>
