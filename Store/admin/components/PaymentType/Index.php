<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'Admin/AdminTableStore.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Store/admin/components/PaymentType/include/'.
	'StorePaymentTypeStatusCellRenderer.php';


/**
 * Index page for payment types
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StorePaymentTypeIndex extends AdminIndex
{
	// {{{ private variables

	private $regions;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML(dirname(__FILE__).'/index.xml');
		$this->regions = SwatDB::getOptionArray($this->app->db, 'Region',
			'title', 'id');

	}

	// }}}

	// process phase
	// {{{ protected function processActions()

	protected function processActions(SwatTableView $view, SwatActions $actions)
	{
		$num = count($view->checked_items);
		$message = null;

		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('PaymentType/Delete');
			$this->app->getPage()->setItems($view->checked_items);
			break;

		case 'enable':
			$region = $this->ui->getWidget('enable_region')->value;
			$region_list = ($region > 0) ?
				array($region) : array_flip($this->regions);

			foreach ($view->checked_items as $item) {
				SwatDB::updateBinding($this->app->db,
					'PaymentTypeRegionBinding', 'payment_type', $item, 'region',
					$region_list, 'Region', 'id');
			}

			$num = count($view->checked_items);

			$message = new SwatMessage(sprintf(Store::ngettext(
				'One payment type has been enabled.',
				'%d payment types have been enabled.', $num),
				SwatString::numberFormat($num)));

			break;

		case 'disable':
			$region = $this->ui->getWidget('disable_region')->value;
			$region_list = ($region > 0) ?
				array($region) : array_flip($this->regions);

			foreach ($view->checked_items as $item) {
				SwatDB::updateBinding($this->app->db,
					'PaymentTypeRegionBinding', 'payment_type', $item, 'region',
					$region_list, 'Region', 'id');
			}

			$num = count($view->checked_items);

			$message = new SwatMessage(sprintf(Store::ngettext(
				'One payment type has been disabled.',
				'%d payment types have been disabled.', $num),
				SwatString::numberFormat($num)));

			break;
		}
		
		if ($message !== null)
			$this->app->messages->add($message);
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		// setup the flydowns for enabled/disabled actions
		$regions = $this->regions;
		$regions[0] = Store::_('All Regions');

		$this->ui->getWidget('enable_region')->addOptionsByArray($regions);
		$this->ui->getWidget('disable_region')->addOptionsByArray($regions);
	}

	// }}}

	// {{{ protected function getTableStore()

	protected function getTableStore($view)
	{
		$sql = sprintf('select id, title, shortname
				from PaymentType order by %s',
			$this->getOrderByClause($view, 'displayorder, title'));

		$store = SwatDB::query($this->app->db, $sql, 'AdminTableStore');

		$view = $this->ui->getWidget('index_view');
		$view->getColumn('status')->getRendererByPosition()->db =
			$this->app->db;

		return $store;
	}

	// }}}
}	

?>
