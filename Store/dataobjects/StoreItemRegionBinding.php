<?php

require_once 'SwatDB/SwatDBDataObject.php';

/**
 * Dataobject for item region bindings
 *
 * @package   Store
 * @copyright silverorange 2006
 */
class StoreItemRegionBinding extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * Price of the item
	 *
	 * @var float
	 */
	public $price;

	/**
	 * Optional original price of the item
	 *
	 * @var float
	 */
	public $original_price;

	/**
	 * If the item should be available
	 *
	 * @var boolean
	 */
	public $enabled;


	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerInternalProperty('region',
			SwatDBClassMap::get('StoreRegion'));

		$this->registerInternalProperty('item',
			SwatDBClassMap::get('StoreItem'));

		$this->table = 'ItemRegionBinding';
	}

	// }}}
}

?>
