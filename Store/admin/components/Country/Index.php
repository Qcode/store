<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'Admin/AdminTableStore.php';
require_once 'SwatDB/SwatDB.php';

/**
 * Index page for Countries
 *
 * @package   veseys2
 * @copyright 2006 silverorange
 */
class CountryIndex extends AdminIndex
{
	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML(dirname(__FILE__).'/index.xml');
	}

	// }}}

	// process phase
	// {{{ protected function processActions()

	protected function processActions(SwatTableView $view, SwatActions $actions)
	{
		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('Country/Delete');
			$this->app->getPage()->setItems($view->checked_items);
			break;
		}
	}

	// }}}

	// build phase
	// {{{ protected function getTableStore()

	protected function getTableStore($view)
	{
		$sql = sprintf('select id, title from Country order by %s',
			$this->getOrderByClause($view, 'title'));

		$store = SwatDB::query($this->app->db, $sql, 'AdminTableStore');

		return $store;
	}

	// }}}
}

?>
