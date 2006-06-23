<?php

require_once 'Store/dataobjects/StoreDataObject.php';

/**
 * Regions are areas in which products may be sold. Each region may have
 * region-specific pricing and shipping rules. Sometimes regionscorrespond
 * directly with countries and other times, regions are more general. Examples
 * of regions are:
 *
 * - Canada
 * - Quebec
 * - U.S.A.
 * - Europe
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreRegion extends StoreDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * The title of thie region
	 *
	 * This is something like "Canada", "U.S.A." or "Europe".
	 *
	 * @var string
	 */
	public $title;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->table = 'Region';
		$this->id_field = 'integer:id';
	}

	// }}}
}

?>
