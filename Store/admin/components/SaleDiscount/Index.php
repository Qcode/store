<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'SwatDB/SwatDB.php';

/**
 * Index page for SaleDiscounts
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreSaleDiscountIndex extends AdminIndex
{
	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->mapClassPrefixToPath('Store', 'Store');
		$this->ui->loadFromXML(dirname(__FILE__).'/index.xml');
	}

	// }}}

	// process phase
	// {{{ protected function processActions()

	protected function processActions(SwatTableView $view, SwatActions $actions)
	{
		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('SaleDiscount/Delete');
			$this->app->getPage()->setItems($view->getSelection());
			break;
		}
	}

	// }}}

	// build phase
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$sql = 'select SaleDiscount.*
				from SaleDiscount
				order by %s';

		$sql = sprintf($sql, $this->getOrderByClause($view, 'title'));

		$rs = SwatDB::query($this->app->db, $sql);

		return $rs;
	}

	// }}}
}

?>
