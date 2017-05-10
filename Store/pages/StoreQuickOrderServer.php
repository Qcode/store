<?php

/**
 * Handles XML-RPC requests from the quick order page
 *
 * @package   Store
 * @copyright 2006-2016 silverorange
 */
class StoreQuickOrderServer extends SiteXMLRPCServer
{
	// {{{ public function getItemDescription()

	/**
	 * Returns the XHTML required to display a textual description of the item
	 *
	 * @param string $sku the item number of the item descriptions to get.
	 *                     In some stores, multiple items may have the same item
	 *                     number.
	 * @param string $replicator_id the id to be appended to the widget id
	 *                                   returned by this procedure.
	 * @param integer $sequence the sequence id of this request to prevent
	 *                           race conditions.
	 *
	 * @return string the XHTML required to display an item description.
	 */
	public function getItemDescription($sku, $replicator_id, $sequence)
	{
		$form = new SwatForm();

		$selector = new StoreQuickOrderItemSelector(
			'item_selector_renderer_'.$replicator_id);

		$selector->db = $this->app->db;
		$selector->region = $this->app->getRegion();
		$selector->sku = $sku;
		$form->add($selector);

		$form->init();

		ob_start();
		$selector->displayContent();

		$response = array();
		$response['description'] = ob_get_clean();
		$response['sequence'] = $sequence;

		return $response;
	}

	// }}}
}

?>
