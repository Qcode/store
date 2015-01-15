<?php

/**
 * A shipment method for an e-commerce web application
 *
 * @package   Store
 * @copyright 2005-2015 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreShipmentMethod
{
	// {{{ public function getTimeToDeliver()

	/**
	 * An approximation of how long it takes to ship items with this method.
	 *
	 * @return DateInterval the approximate time it will take to deliver a
	 *                      shipment using this method.
	 */
	public function getTimeToDeliver($address)
	{
	}

	// }}}
}

?>
