<?php

require_once 'Swat/SwatTableViewRow.php';
require_once 'Swat/SwatHtmlTag.php';
require_once 'Swat/SwatPercentageCellRenderer.php';

/**
 * Displays totals in a special row in a table view.
 *
 * @package   Store
 * @copyright 2006-2012 silverorange
 */
class StorePercentageTotalRow extends SwatTableViewRow
{
	// {{{ public properties

	/**
	 * Title of this total row
	 *
	 * @var string
	 */
	public $title = null;

	/**
	 * Link href
	 *
	 * If not specified, the title is displayed as a span. If specified, the
	 * title is displayed as an anchor element with this value as the href
	 * attribute value.
	 *
	 * @var string
	 */
	public $link = null;

	/**
	 * Link title
	 *
	 * If the {@link StoreTotalRow::$link} is set, this value will be used as
	 * the anchor element's title attribute value.
	 *
	 * @var string
	 */
	public $link_title = null;

	/**
	 * The total value to display for this row
	 *
	 * If the value is null, this row is not displayed.
	 *
	 * @var float
	 */
	public $value = null;

	/**
	 * Optional number of additional columns that exist to the right of the
	 * total column
	 *
	 * Dy default, no additional columns are displayed and the total values are
	 * in the last column of the table.
	 *
	 * @var integer
	 */
	public $offset = 0;

	/**
	 * Optional note to display with the title
	 *
	 * @var string
	 */
	public $note = null;

	/**
	 * Optional content type for {@link StoreTotalRow::$note}
	 *
	 * Defaults to text/plain, use text/xml for XHTML fragments.
	 *
	 * @var string
	 */
	public $note_content_type = 'text/plain';

	/**
	 * Whether or not to show a colon following the title
	 *
	 * By default, a colon is displayed following the row title.
	 *
	 * @var boolean
	 */
	public $show_colon = true;

	// }}}
	// {{{ protected properties

	/**
	 * Percentage cell renderer used to display the value
	 *
	 * @var SwatPercentageCellRenderer
	 */
	protected $percentage_cell_renderer;

	// }}}
	// {{{ public function __construct()

	public function __construct()
	{
		parent::__construct();
		$this->percentage_cell_renderer = new SwatPercentageCellRenderer();
	}

	// }}}
	// {{{ public function display()

	public function display(SwatDisplayContext $context)
	{
		if (!$this->visible || $this->value === null) {
			return;
		}

		parent::display($context);

		$tr_tag = new SwatHtmlTag('tr');
		$tr_tag->class = 'store-total-row';
		$tr_tag->id = $this->id;

		$tr_tag->open($context);

		$this->displayHeader($context);
		$this->displayTotal($context);
		$this->displayBlank($context);

		$tr_tag->close($context);

		$context->addStyleSheet('packages/store/styles/store-total-row.css');
	}

	// }}}
	// {{{ protected function displayHeader()

	protected function displayHeader(SwatDisplayContext $context)
	{
		$colspan = $this->view->getXhtmlColspan();
		$th_tag = new SwatHtmlTag('th');
		$th_tag->colspan = $colspan - 1 - $this->offset;

		$th_tag->open($context);
		$this->displayTitle($context);
		$th_tag->close($context);
	}

	// }}}
	// {{{ protected function displayTitle()

	protected function displayTitle()
	{
		if ($this->link === null) {
			$title = SwatString::minimizeEntities($this->title);
		} else {
			$anchor_tag = new SwatHtmlTag('a');
			$anchor_tag->href = $this->link;
			$anchor_tag->title = $this->link_title;
			$anchor_tag->setContent($this->title);
			$title = $anchor_tag->__toString();
		}

		if ($this->note !== null) {
			$span = new SwatHtmlTag('span');
			$span->class = 'note';
			$span->setContent(
				sprintf(
					'(%s)',
					$this->note
				),
				$this->note_content_type
			);

			$title.= ' '.$span->__toString();
		}

		if ($this->show_colon) {
			$context->out(sprintf(Store::_('%s:'), $title));
		} else {
			$context->out($title);
		}
	}

	// }}}
	// {{{ protected function displayTotal()

	protected function displayTotal(SwatDisplayContext $context)
	{
		$td_tag = new SwatHtmlTag('td');
		$td_tag->class = $this->getCSSClassString();
		$td_tag->open($context);
		$this->displayValue($context);
		$td_tag->close($context);
	}

	// }}}
	// {{{ protected function displayValue()

	protected function displayValue(SwatDisplayContext $context)
	{
		$this->percentage_cell_renderer->value = $this->value;
		$this->percentage_cell_renderer->render($context);
	}

	// }}}
	// {{{ protected function displayBlank()

	protected function displayBlank(SwatDisplayContext $context)
	{
		if ($this->offset > 0) {
			$td_tag = new SwatHtmlTag('td');
			$td_tag->colspan = $this->offset;
			$td_tag->setContent('&nbsp;');
			$td_tag->display($context);
		}
	}

	// }}}
	// {{{ protected function getCSSClassNames()

	protected function getCSSClassNames()
	{
		$classes = array();

		// renderer inheritance classes
		$classes = array_merge($classes,
			$this->percentage_cell_renderer->getInheritanceCSSClassNames());

		// renderer base classes
		$classes = array_merge($classes,
			$this->percentage_cell_renderer->getBaseCSSClassNames());

		// user specified classes
		$classes = array_merge($classes, $this->classes);

		return $classes;
	}

	// }}}
}

?>
