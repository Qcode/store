<?php

/**
 * Search page for Articles
 *
 * @package   Store
 * @copyright 2005-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreArticleSearch extends SiteArticleSearch
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/admin/components/Article/search.xml';

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->mapClassPrefixToPath('Store', 'Store');

		$regions_sql = 'select id, title from Region';
		$regions = SwatDB::query($this->app->db, $regions_sql);
		$search_regions = $this->ui->getWidget('search_regions');
		foreach ($regions as $region) {
			$search_regions->addOption($region->id, $region->title);
			$search_regions->values[] = $region->id;
		}

		$this->ui->getWidget('article_region_action')->db = $this->app->db;
	}

	// }}}

	// process phase
	// {{{ protected function processActions()

	protected function processActions(SwatView $view, SwatActions $actions)
	{
		$processor = new StoreArticleActionsProcessor($this);
		$processor->process($view, $actions);
	}

	// }}}

	// build phase
	// {{{ protected function getWhereClause()

	protected function getWhereClause()
	{
		if ($this->where_clause === null) {
			$where = parent::getWhereClause();

			$search_regions = $this->ui->getWidget('search_regions');
			foreach ($search_regions->options as $option) {
				if (in_array($option->value, $search_regions->values)) {
					$where.= sprintf(' and id in
						(select article from ArticleRegionBinding
						where region = %s)',
						$this->app->db->quote($option->value, 'integer'));
				} else {
					$where.= sprintf(' and id not in
						(select article from ArticleRegionBinding
						where region = %s)',
						$this->app->db->quote($option->value, 'integer'));
				}
			}

			$this->where_clause = $where;
		}

		return $this->where_clause;
	}

	// }}}
}

?>
