<?php

/**
 * Dataobject for quantity-discount region bindings
 *
 * @package   Store
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreQuantityDiscountRegionBinding extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * Price of the quantity discount 
	 *
	 * @var float
	 */
	public $price;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerInternalProperty('region',
			SwatDBClassMap::get('StoreRegion'));

		$this->registerInternalProperty('quantity_discount',
			SwatDBClassMap::get('StoreQuantityDiscount'));

		$this->table = 'QuantityDiscountRegionBinding';
	}

	// }}}
}

?>
