<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Store/dataobjects/StoreFeedback.php';

/**
 * A recordset wrapper class for StoreFeedback objects
 *
 * @package   Store
 * @copyright 2009 silverorange
 */
class StoreFeedbackWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class = SwatDBClassMap::get('StoreFeedback');
		$this->index_field = 'id';
	}

	// }}}
}

?>
