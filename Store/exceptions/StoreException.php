<?php

require_once 'Swat/exceptions/SwatException.php';

/**
 * An exception in the Store package
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreException extends SwatException
{
	public $title = null;
}
