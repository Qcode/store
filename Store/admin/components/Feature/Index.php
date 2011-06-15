<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Admin/pages/AdminIndex.php';
require_once 'Store/dataobjects/StoreFeatureWrapper.php';

/**
 * Index page for Features
 *
 * @package   Store
 * @copyright 2010-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreFeatureIndex extends AdminIndex
{
	// init phase
	// {{{ protected function initInternal()
	protected function initInternal()
	{
		$this->ui->loadFromXML(dirname(__FILE__).'/index.xml');
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
			$this->app->replacePage('Feature/Delete');
			$this->app->getPage()->setItems($view->getSelection());
			break;

		case 'enable':
			SwatDB::updateColumn($this->app->db, 'Feature',
				'boolean:enabled', true, 'id', $view->getSelection());

			if (isset($this->app->memcache))
				$this->app->memcache->flushNs('StoreFeature');

			$message = new SwatMessage(sprintf(Store::ngettext(
				'One feature has been enabled.',
				'%s features have been enabled.', $num),
				SwatString::numberFormat($num)));

			break;

		case 'disable':
			SwatDB::updateColumn($this->app->db, 'Feature',
				'boolean:enabled', false, 'id', $view->getSelection());

			if (isset($this->app->memcache))
				$this->app->memcache->flushNs('StoreFeature');

			$message = new SwatMessage(sprintf(Store::ngettext(
				'One feature has been disabled.',
				'%s features have been disabled.', $num),
				SwatString::numberFormat($num)));

			break;
		}

		if ($message !== null)
			$this->app->messages->add($message);
	}

	// }}}

	// build phase
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$instance_where = ($this->app->getInstanceId() === null) ?
			'1 = 1': sprintf('instance = %s',
				$this->app->db->quote($this->app->getInstanceId(), 'integer'));

		$sql = sprintf('select * from Feature
			where %s
			order by display_slot, priority, start_date',
			$instance_where);

		$wrapper = SwatDBClassMap::get('StoreFeatureWrapper');
		$features = SwatDB::query($this->app->db, $sql, $wrapper);

		$store = new SwatTableStore();
		$counts = array();

		$instance_count = SwatDB::queryOne($this->app->db,
			'select count(id) from Instance');

		if ($this->app->getInstanceId() === null && $instance_count > 0) {
			$view = $this->ui->getWidget('index_view');
			$view->getColumn('instance')->visible = true;
		}

		foreach ($features as $feature) {
			$ds = new SwatDetailsStore($feature);

			if (!isset($counts[$ds->display_slot]))
				$counts[$ds->display_slot] = 0;

			$counts[$ds->display_slot]++;

			if ($feature->region === null)
				$ds->region = 'All';
			else
				$ds->region = $feature->region->title;

			if ($feature->instance !== null) {
				$ds->instance_title = $feature->instance->title;
			}

			$store->add($ds);
		}

		foreach ($store as $ds)
			$ds->priority_sensitive = ($counts[$ds->display_slot] > 1);

		return $store;
	}

	// }}}
}

?>
