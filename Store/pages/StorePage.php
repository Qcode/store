<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Site/pages/SitePathPage.php';
require_once 'Store/StoreCategoryPath.php';
require_once 'Store/dataobjects/StoreCategoryWrapper.php';
require_once 'Store/dataobjects/StoreCategoryImageWrapper.php';

/**
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @see       StorePageFactory
 */
abstract class StorePage extends SitePathPage
{
	// {{{ private properties

	private $category;

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->layout->selected_top_category_id =
			$this->getSelectedTopCategoryId();

		$this->layout->selected_secondary_category_id =
			$this->getSelectedSecondaryCategoryId();

		$this->layout->selected_category_id = $this->getSelectedCategoryId();

		$this->initInternal();
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
	}

	// }}}
	// {{{ protected function getSelectedTopCategoryId()

	protected function getSelectedTopCategoryId()
	{
		$category_id = null;

		if ($this->path !== null) {
			$top_category = $this->path->getFirst();
			if ($top_category !== null)
				$category_id = $top_category->id;
		}

		return $category_id;
	}

	// }}}
	// {{{ protected function getSelectedSecondaryCategoryId()

	protected function getSelectedSecondaryCategoryId()
	{
		$secondary_category_id = null;

		if ($this->path !== null) {
			$secondary_category = $this->path->get(1);
			if ($secondary_category !== null)
				$secondary_category_id = $secondary_category->id;
		}

		return $secondary_category_id;
	}

	// }}}
	// {{{ protected function getSelectedCategoryId()

	protected function getSelectedCategoryId()
	{
		return null;
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		if (property_exists($this->layout, 'navbar'))
			$this->layout->navbar->createEntry(Store::_('Store'), 'store');

		parent::build();
	}

	// }}}
	// {{{ protected function queryCategory()

	protected function queryCategory($category_id)
	{
		if (isset($this->app->memcache)) {
			$key = 'StorePage.category.'.$category_id;
			$this->category = $this->app->memcache->getNs('product', $key);
			if ($this->category !== false)
				return $this->category;
		}

		$sql = 'select * from Category where id = %s';

		$sql = sprintf($sql,
			$this->app->db->quote($category_id, 'integer'));

		$categories = SwatDB::query($this->app->db, $sql,
			'StoreCategoryWrapper');

		$this->category = $categories->getFirst();

		return $this->category;
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();

		if (isset($this->app->memcache) && $this->category !== null) {
			$key = 'StorePage.category.'.$this->category->id;
			$this->app->memcache->setNs('product', $key, $this->category);
		}

		$this->layout->startCapture('content');
		$this->app->timer->display();
		$this->layout->endCapture();
	}

	// }}}
}

?>
