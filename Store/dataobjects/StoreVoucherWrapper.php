<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Store/dataobjects/StoreVoucher.php';

/**
 * A recordset wrapper class for StoreVoucher objects
 *
 * @package   Store
 * @copyright 2007-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreVoucher
 */
class StoreVoucherWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class = SwatDBClassMap::get('StoreVoucher');
	}

	// }}}
}

?>
