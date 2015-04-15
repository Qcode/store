<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'Store/dataobjects/StoreRegionWrapper.php';

/**
 * Displays sales summaries by year and country/provstate.
 *
 * @package   Store
 * @copyright 2015 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreSalesByRegionReportIndex extends AdminIndex
{
	// {{{ protected properties

	/**
	 * Cache of regions used by getRegions()
	 *
	 * @var StoreRegionWrapper
	 */
	protected $regions = null;

	/**
	 * @var boolean
	 */
	protected $show_shipping = false;

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'Store/admin/components/SalesByRegionReport/index.xml';
	}

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();
		$this->ui->loadFromXML($this->getUiXml());
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$view = $this->ui->getWidget('index_view');
		$view->getColumn('shipping')->visible = $this->show_shipping;
	}

	// }}}
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$now = new SwatDate();
		$now->setTimezone($this->app->default_time_zone);

		$first_order_date_string = SwatDB::queryOne(
			$this->app->db,
			'select min(createdate) from Orders'
		);

		$first_order_date = new SwatDate($first_order_date_string);
		$first_order_date->setTimezone($this->app->default_time_zone);

		// create an array of years with default values
		$years = array();
		$start_date = clone $now;
		for (
			$i = $start_date->getYear();
			$i >= $first_order_date->getYear();
			$i--
		) {
			$key = $i;

			$ds = new SwatDetailsStore();

			$ds->id             = $key;
			$ds->gross_total    = 0;
			$ds->shipping_total = 0;

			$ds->title          = sprintf(
				($start_date->getYear() === $now->getYear())
					? '%s (YTD)'
					: '%s',
				$start_date->formatLikeIntl(Store::_('YYYY'))
			);

			$years[$key] = $ds;

			$start_date->setYear($i - 1);
		}

		// fill our array with values from the database if the values exist
		foreach ($this->getYearTotals() as $row) {
			$key = $row->year;

			$years[$key]->gross_total    = $row->gross_total;
			$years[$key]->shipping_total = $row->shipping_total;
		}

		// turn the array into a table model
		$store = new SwatTableStore();
		foreach ($years as $year) {
			$store->add($year);
		}

		return $store;
	}

	// }}}
	// {{{ protected function getYearTotals()

	protected function getYearTotals()
	{
		$sql = sprintf(
			'select sum(Orders.total) gross_total,
				sum(Orders.shipping_total) as shipping_total,
				extract(year from convertTZ(Orders.createdate, %1$s))
					as year
			from Orders
			where Orders.createdate is not null %2$s
			group by year',
			$this->app->db->quote(
				$this->app->default_time_zone->getName(),
				'text'
			),
			$this->getInstanceWhereClause()
		);

		return SwatDB::query($this->app->db, $sql);
	}

	// }}}
	// {{{ protected function getInstanceWhereClause()

	protected function getInstanceWhereClause()
	{
		if ($this->app->isMultipleInstanceAdmin()) {
			return '';
		}

		$instance_id = $this->app->getInstanceId();

		return sprintf(
			'and Orders.instance %s %s',
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer')
		);
	}

	// }}}
}

?>
