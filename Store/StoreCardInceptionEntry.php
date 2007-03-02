<?php

require_once 'Swat/SwatDateEntry.php';
require_once 'Swat/SwatMessage.php';

/**
 * A widget for inception date of a card
 *
 * @package   Store
 * @copyright 2007 silverorange
 */
class StoreCardInceptionEntry extends SwatDateEntry
{
	// {{{ public function __construct()

	public function __construct($id = null)
	{
		parent::__construct($id);

		$this->show_month_number = true;
		$this->display_parts = self::MONTH | self::YEAR;

		// allow dates up to 5 years in the past
		$this->setValidRange(-5, 0);

		// set start date fields to first day of the current month
		$today = new Date();
		$this->valid_range_start->setMonth($today->getMonth());
		$this->valid_range_start->setDay(1);
		$this->valid_range_start->setHour(0);
		$this->valid_range_start->setMinute(0);
		$this->valid_range_start->setSecond(0);
	}

	// }}}
	// {{{ public function isValid()

	public function isValid()
	{
		if (Date::compare($this->value, $this->valid_range_start, true) == -1)
			return false;
		else
			return true;
	}

	// }}}
}

?>
