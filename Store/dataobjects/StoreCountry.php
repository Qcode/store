<?php

require_once 'SwatDB/SwatDBDataObject.php';

/**
 * A country data object
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCountry extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier of this country 
	 *
	 * @var string 
	 */
	public $id;

	/**
	 * User visible title of this country 
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Whether or not to show this country on the front-end
	 *
	 * @var boolean
	 */
	public $show;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->table = 'Country';
		$this->id_field = 'text:id';
	}

	// }}}
}

?>
