<?php

require_once 'Swat/SwatTableView.php';

/**
 * A table view that displays categories with no products in a special way
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreItemTableView extends SwatTableView
{
	// {{{ protected function getRowClasses()

	protected function getRowClasses($row, $count)
	{
		$classes = parent::getRowClasses($row, $count);

		if (!$row->enabled)
			$classes[] = 'item-disabled';

		return $classes;
	}

	// }}}
}

?>
