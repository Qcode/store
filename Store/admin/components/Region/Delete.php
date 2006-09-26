<?php

require_once 'Admin/pages/AdminDBDelete.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Admin/AdminListDependency.php';
require_once 'Admin/AdminSummaryDependency.php';
require_once 'Admin/AdminDependencySummaryWrapper.php';

/**
 * Delete confirmation page for Regions
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 */
class StoreRegionDelete extends AdminDBDelete
{
	// process phase
	// {{{ protected function processDBData()

	protected function processDBData()
	{
		parent::processDBData();

		$item_list = $this->getItemList('integer');

		$sql = sprintf('delete from Region where id in (%s)
			and id not in (select region from ItemRegionBinding)
			and id not in (select region from Locale)', $item_list);

		$num = SwatDB::exec($this->app->db, $sql);

		$msg = new SwatMessage(sprintf(ngettext('One region has been deleted.',
			'%d regions have been deleted.', $num), $num),
			SwatMessage::NOTIFICATION);

		$this->app->messages->add($msg);
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	public function buildInternal()
	{
		parent::buildInternal();

		$item_list = $this->getItemList('integer');

		$dep = new AdminListDependency();
		$dep->title = 'Region';
		$dep->entries = AdminListDependency::queryEntries($this->app->db,
			'Region', 'integer:id', null, 'text:title', 'id',
			'id in ('.$item_list.')', AdminDependency::DELETE);

		// dependent products
		$dep_products = new AdminSummaryDependency();
		$dep_products->title = 'Product';
		
		$sql = sprintf('select count(distinct Item.product) as count,
				ItemRegionBinding.region as parent, %s::integer as status_level
			from Product 
				inner join Item on Item.product = Product.id
				inner join ItemRegionBinding on ItemRegionBinding.item = Item.id 
					and ItemRegionBinding.region in (%s)
			group by ItemRegionBinding.region',
			AdminDependency::NODELETE, $item_list);

		$summaries = SwatDB::query($this->app->db, $sql,
			'AdminDependencySummaryWrapper');

		$dep_products->summaries = $summaries->getArray();

		$dep->addDependency($dep_products);

		// dependent locales
		$dep_locales = new AdminSummaryDependency();
		$dep_locales->title = 'Locale';
		$dep_locales->summaries = AdminSummaryDependency::querySummaries(
			$this->app->db, 'Locale', 'integer:id', 'integer:region',
			'region in ('.$item_list.')', AdminDependency::NODELETE);

		$dep->addDependency($dep_locales);

		$message = $this->ui->getWidget('confirmation_message');
		$message->content = $dep->getMessage();
		$message->content_type = 'text/xml';

		if ($dep->getStatusLevelCount(AdminDependency::DELETE) == 0)
			$this->switchToCancelButton();
	}

	// }}}
}

?>
