<?php

require_once 'Store/dataobjects/StoreRecordsetWrapper.php';
require_once 'Store/dataobjects/StoreProductImage.php';

/**
 * A recordset wrapper class for StoreProductImage objects
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @see       StoreProductImage
 */
class StoreProductImageWrapper extends StoreRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class =
			$this->class_map->resolveClass('StoreProductImage');

		$this->index_field = 'id';
	}

	// }}}
}

?>
