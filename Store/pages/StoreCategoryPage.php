<?php

require_once 'Swat/SwatString.php';
require_once 'Store/StoreProductSearchEngine.php';
require_once 'Store/pages/StorePage.php';
require_once 'Store/dataobjects/StoreLocaleWrapper.php';
require_once 'Store/dataobjects/StoreCategoryWrapper.php';
require_once 'Store/dataobjects/StoreProductWrapper.php';
require_once 'Store/dataobjects/StoreProductImageWrapper.php';

/**
 * @package   Store
 * @copyright 2005-2007 silverorange
 */
class StoreCategoryPage extends StorePage
{
	// {{{ protected properties

	protected $category;
	protected $products;

	// }}}

	// init phase
	// {{{ public function isVisibleInRegion()

	public function isVisibleInRegion(StoreRegion $region)
	{
		$category = null;

		if ($this->path !== null) {
			$path_entry = $this->path->getLast();
			if ($path_entry !== null) {
				$category_id = $path_entry->id;

				$sql = sprintf('select category from VisibleCategoryView
					where category = %s and (region = %s or region is null)',
					$this->app->db->quote($category_id, 'integer'),
					$this->app->db->quote($region->id, 'integer'));

				$category = SwatDB::queryOne($this->app->db, $sql);
			}
		}

		return ($category !== null);
	}

	// }}}
	// {{{ protected function getSelectedCategoryId()

	protected function getSelectedCategoryId()
	{
		$category_id = null;

		if ($this->path !== null) {
			$path_entry = $this->path->getLast();
			if ($path_entry !== null)
				$category_id = $path_entry->id;
		}

		return $category_id;
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$this->buildNavBar();
		$category_id = $this->path->getLast()->id;
		$this->category = $this->queryCategory($category_id);

		$this->layout->data->title =
			SwatString::minimizeEntities($this->category->title);

		$this->layout->data->description =
			SwatString::minimizeEntities($this->category->description);

		if ($this->category->bodytext != '')
			$this->layout->data->content =
				'<div class="store-category-bodytext">'.
				SwatString::toXHTML($this->category->bodytext).'</div>';
		else
			$this->layout->data->content = '';

		if ($this->category->description === null) {
			$this->layout->data->meta_description =
				SwatString::minimizeEntities(SwatString::condense(
				SwatString::stripXHTMLTags($this->category->bodytext, 400)));
		} else {
			$this->layout->data->meta_description =
				SwatString::minimizeEntities($this->category->description);
		}

		$this->products = $this->getProducts();
		$this->buildPage();
	}

	// }}}
	// {{{ protected function buildPage()

	protected function buildPage()
	{
		$this->layout->startCapture('content');
		$this->displayRelatedArticles($this->category);
		$this->displayFeaturedProducts($this->category);
		$this->displayPage();
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function querySubCategories()

	protected function querySubCategories(StoreCategory $category = null)
	{
		$sql = 'select Category.id, Category.title, Category.shortname,
				Category.description, Category.image,
				a.product_count as available_product_count,
				c.product_count, c.region as region_id, always_visible
			from Category
			left outer join CategoryAvailableProductCountByRegionCache as a
				on a.category = Category.id and a.region = %1$s
			left outer join CategoryVisibleProductCountByRegionCache as c
				on c.category = Category.id and c.region = %1$s
			where parent %2$s %3$s
			and id in
				(select Category from VisibleCategoryView
				where region = %4$s or region is null)
			order by displayorder, title';

		$category_id = ($category === null) ? null : $category->id;

		$sql = sprintf($sql,
			$this->app->db->quote($this->app->getRegion()->id, 'integer'),
			SwatDB::equalityOperator($category_id),
			$this->app->db->quote($category_id, 'integer'),
			$this->app->db->quote($this->app->getRegion()->id, 'integer'));

		$wrapper_class = SwatDBClassMap::get('StoreCategoryWrapper');
		$sub_categories = SwatDB::query($this->app->db, $sql, $wrapper_class);
		$sub_categories->setRegion($this->app->getRegion());

		if (count($sub_categories) == 0)
			return $sub_categories;

		$sql = 'select * from Image where id in (%s)';
		$wrapper_class = SwatDBClassMap::get('StoreCategoryImageWrapper');
		$sub_categories->loadAllSubDataObjects(
			'image', $this->app->db, $sql, $wrapper_class);

		return $sub_categories;
	}

	// }}}
	// {{{ protected function displaySubCategories()

	protected function displaySubCategories(StoreCategoryWrapper $categories)
	{
		if (count($categories) == 0)
			return;

		echo '<ul class="store-category-list">';

		foreach ($categories as $category) {
			echo '<li class="store-category-tile">';
			$link = $this->source.'/'.$category->shortname;
			$category->displayAsTile($link);
			echo '</li>';
		}

		echo '</ul>';
	}

	// }}}
	// {{{ protected function displayProducts()

	protected function displayProducts($products, $path = null)
	{
		if ($path === null)
			$path = $this->source;

		echo '<ul class="store-product-list">';

		foreach ($products as $product) {
			echo '<li class="store-product-icon">';
			$link = $path.'/'.$product->shortname;
			$product->displayAsIcon($link);
			echo '</li>';
		}

		echo '</ul>';
	}

	// }}}
	// {{{ protected function displayPage()

	protected function displayPage()
	{
		$sub_categories = $this->querySubCategories($this->category);

		if (count($this->products) == 1 && count($sub_categories) == 0) {
			$link = $this->source.'/'.$this->products->getFirst()->shortname;
			$this->app->relocate($link);
		} elseif (count($this->products) == 0 && count($sub_categories) == 1) {
			$link = $this->source.'/'.$sub_categories->getFirst()->shortname;
			$this->app->relocate($link);
		}

		if (count($sub_categories) > 0)
			$this->displaySubCategories($sub_categories);

		if (count($this->products) > 0)
			$this->displayProducts($this->products);
	}

	// }}}
	// {{{ protected function displayFeaturedProducts()

	protected function displayFeaturedProducts(StoreCategory $category)
	{
		$products = $this->getFeaturedProducts($category);
		if (count($products) > 0) {
			$div = new SwatHtmlTag('div');
			$div->id = 'featured_products';
			$div->open();

			$header_tag = new SwatHtmlTag('h4');
			$header_tag->setContent(Store::_('Featuring:'));
			$header_tag->display();

			$ul_tag = new SwatHtmlTag('ul');
			$ul_tag->class = 'store-product-list';
			$ul_tag->open();

			$li_tag = new SwatHtmlTag('li');
			$li_tag->class = 'store-product-text';

			foreach ($products as $product) {
				$li_tag->open();
				$path = 'store/'.$product->path;
				$product->displayAsText($path);
				$li_tag->close();
				echo ' ';
			}

			$ul_tag->close();
			echo '<div class="clear"></div>';
			$div->close();
		}
	}

	// }}}
	// {{{ protected function displayRelatedArticles()

	protected function displayRelatedArticles(StoreCategory $category)
	{
		if (count($category->related_articles) > 0) {
			$div = new SwatHtmlTag('div');
			$div->id = 'related_articles';
			$div->open();
			$this->displayRelatedArticlesTitle();

			$first = true;
			$anchor_tag = new SwatHtmlTag('a');
			foreach ($category->related_articles as $article) {
				if ($first)
					$first = false;
				else
					echo ', ';

				$anchor_tag->href = $article->path;
				$anchor_tag->setContent($article->title);
				$anchor_tag->display();
			}

			$div->close();
		}
	}

	// }}}
	// {{{ protected function displayRelatedArticlesTitle()

	protected function displayRelatedArticlesTitle()
	{
		echo Store::_('Related Articles: ');
	}

	// }}}
	// {{{ protected function instantiateProductSearchEngine()

	protected function instantiateProductSearchEngine()
	{
		return new StoreProductSearchEngine($this->app);
	}

	// }}}
	// {{{ protected function getProducts()

	protected function getProducts()
	{
		$engine = $this->instantiateProductSearchEngine();
		$engine->category = $this->category;
		$engine->include_category_descendants = false;
		$engine->addOrderByField('CategoryProductBinding.displayorder');
		$engine->addOrderByField('is_available desc');

		$products = $engine->search();

		return $products;
	}

	// }}}
	// {{{ protected function getFeaturedProducts()

	protected function getFeaturedProducts(StoreCategory $category)
	{
		$engine = $this->instantiateProductSearchEngine();
		$engine->featured_category = $category;
		$engine->addOrderByField('CategoryFeaturedProductBinding.displayorder');
		return $engine->search();
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		if ($this->path !== null) {
			$link = 'store';
			foreach ($this->path as $path_entry) {
				$link.= '/'.$path_entry->shortname;
				$this->layout->navbar->createEntry($path_entry->title, $link);
			}
		}
	}

	// }}}
}

?>
