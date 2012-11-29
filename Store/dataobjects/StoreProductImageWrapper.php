<?php

require_once 'Site/dataobjects/SiteImageWrapper.php';
require_once 'Store/dataobjects/StoreProductImage.php';

/**
 * A recordset wrapper class for StoreProductImage objects
 *
 * @package   Store
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreProductImage
 */
class StoreProductImageWrapper extends SiteImageWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class = SwatDBClassMap::get('StoreProductImage');
	}

	// }}}
}

?>
