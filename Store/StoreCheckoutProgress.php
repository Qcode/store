<?php

require_once 'Swat/SwatControl.php';
require_once 'Store/Store.php';

/**
 * @package   Store
 * @copyright 2007-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCheckoutProgress extends SwatControl
{
	// {{{ public properties

	/**
	 * @var integer
	 */
	public $current_step = 0;

	/**
	 * @var array
	 */
	public $steps = array();

	// }}}
	// {{{ public function __construct()

	public function __construct($id = null)
	{
		parent::__construct($id);

		$this->steps = array(
			'1' => array(
				'title' => Store::_('Your Information'),
				'link'  => null,
			),
			'2' => array(
				'title' => Store::_('Review Order'),
				'link'  => null,
			),
			'3' => array(
				'title' => Store::_('Order Completed'),
				'link'  => null,
			),
		);

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

		if ($this->current_step > 0) {
			$ol_tag->class = ' store-checkout-progress-step'.
				$this->current_step;
		}

		echo '<div class="store-checkout-progress">';
		$ol_tag->open();

		foreach ($this->steps as $id => $step) {
			$li_tag = new SwatHtmlTag('li');
			$li_tag->class = 'store-checkout-progress-step'.$id;
			$li_tag->open();

			if (isset($step['link']) && $step['link'] != '') {
				printf(
					'<a class="title" href="%s">'.
					'<span class="number">%s</span> '.
					'<span class="content">%s</span>'.
					'</a>',
					SwatString::minimizeEntities($step['link']),
					SwatString::minimizeEntities($id),
					SwatString::minimizeEntities($step['title'])
				);
			} else {
				printf(
					'<span class="title">'.
					'<span class="number">%s</span> '.
					'<span class="content">%s</span>'.
					'</span>',
					SwatString::minimizeEntities($id),
					SwatString::minimizeEntities($step['title'])
				);
			}

			$li_tag->close();
		}

		$ol_tag->close();
		echo '<div class="store-checkout-progress-clear"></div>';
		echo '</div>';

	}

	// }}}
}

?>
