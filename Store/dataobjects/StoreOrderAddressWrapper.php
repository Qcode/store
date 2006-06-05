<?php

require_once 'Store/dataobjects/StoreRecordsetWrapper.php';
require_once 'Store/dataobjects/StoreOrderAddress.php';

/**
 * A recordset wrapper class for StoreOrderAddress objects
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @see       StoreOrderAddress
 */
class StoreOrderAddressWrapper extends StoreRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class =
			$this->class_map->resolveClass('StoreOrderAddress');
	}

	// }}}
}

?>
