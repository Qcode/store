<?php

require_once 'Store/dataobjects/StoreDataObject.php';

/**
 * Dataobject for quantity-discount region bindings
 *
 * @package   Store
 * @copyright silverorange 2006
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreQuantityDiscountRegionBinding extends StoreDataObject
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
