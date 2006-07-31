<?php

require_once 'Store/dataobjects/StoreRecordsetWrapper.php';
require_once 'Store/dataobjects/StoreCountryWrapper.php';
require_once 'Store/dataobjects/StoreRegion.php';

/**
 * A recordset wrapper class for StoreRegion objects
 *
 * @package   Store 
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreRegionWrapper extends StoreRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class =
			$this->class_map->resolveClass('StoreRegion');
	}

	// }}}
	// {{{ protected function loadBillingCountries()

	/** 
	 * Gets countries that orders may be billed to in this region
	 *
	 * @return StoreCountryWrapper a recordset of StoreCountry objects that
	 *                              orders may be billed to.
	 */
	protected function loadBillingCountries()
	{
		$this->checkDB();

		$sql = 'select id, title from Country
			inner join on BillingCountryRegionBinding where
				Country.id = BillingCountryRegionBinding.country and
					BillingCountryRegionBinding.region = %s';

		$sql = sprintf($sql, $this->id);
		return SwatDB::query($this->db, $sql, 'StoreCountryWrapper');
	}

	// }}}
	// {{{ protected function loadShippingCountries()

	/** 
	 * Gets countries that orders may be shipped to in this region
	 *
	 * @return StoreCountryWrapper a recordset of StoreCountry objects that
	 *                              orders may be shipped to.
	 */
	protected function loadShippingCountries()
	{
		$this->checkDB();

		$sql = 'select id, title from Country
			inner join on ShippingCountryRegionBinding where
				Country.id = ShippingCountryRegionBinding.country and
					ShippingCountryRegionBinding.region = %s';

		$sql = sprintf($sql, $this->id);
		return SwatDB::query($this->db, $sql, 'StoreCountryWrapper');

	}

	// }}}
}

?>
