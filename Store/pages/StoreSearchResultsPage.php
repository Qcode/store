<?php

require_once 'Site/pages/SiteSearchResultsPage.php';
require_once 'Store/StoreArticleSearchEngine.php';
require_once 'Store/StoreProductSearchEngine.php';
require_once 'Store/StoreCategorySearchEngine.php';
require_once 'Store/dataobjects/StoreProductWrapper.php';
require_once 'Store/dataobjects/StoreAttributeWrapper.php';
require_once 'Store/dataobjects/StoreCategoryImageWrapper.php';
require_once 'Store/dataobjects/StoreProductImageWrapper.php';

/**
 * Page for displaying search results
 *
 * @package   Store
 * @copyright 2007 silverorange
 */
class StoreSearchResultsPage extends SiteSearchResultsPage
{
	// init phase
	// {{{ public function init()

	public function init()
	{
		$this->ui_xml = 'Store/pages/search-results.xml';
		$this->addSearchDataField('type');
		$this->addSearchDataField('category');
		$this->addSearchDataField('attr', true);
		$this->addSearchDataField('price');

		parent::init();

		if ($this->hasSearchDataValue('keywords')) {
			$keywords = $this->getSearchDataValue('keywords');

			if (count(explode(' ', $keywords)) === 1)
				$this->searchItem($keywords);
		}
	}

	// }}}
	// {{{ protected function searchItem()

	/**
	 * Searches for a direct SKU match and if found, relocates directly to the
	 * coresponding product page
	 *
	 * Only SKUs attached to items in products belonging to at least one
	 * category are automatically redirected.
	 *
	 * @param string $keywords the item SKU to search for.
	 */
	protected function searchItem($keywords)
	{
		$sku = trim(strtolower($keywords));

		if (substr($sku, 0, 1) === '#' && strlen($sku) > 1)
			$sku = substr($sku, 1);

		$sql = 'select Product.id, Product.shortname,
				ProductPrimaryCategoryView.primary_category
			from Product
				inner join VisibleProductCache on
					VisibleProductCache.product = Product.id
				left outer join ProductPrimaryCategoryView
					on ProductPrimaryCategoryView.product = Product.id
			where VisibleProductCache.region = %1$s and
				Product.id in
				(select Item.product from Item where lower(Item.sku) like %2$s
					or Item.id in (select ItemAlias.item from ItemAlias
					where (lower(ItemAlias.sku) like %2$s))
				)';

		$sql = sprintf($sql,
			$this->app->db->quote($this->app->getRegion()->id, 'integer'),
			$this->app->db->quote($sku.'%', 'text'));

		$products = SwatDB::query($this->app->db, $sql,
			SwatDBClassMap::get('StoreProductWrapper'));

		if (count($products) == 1) {
			$first_product = $products->getFirst();
			$path = 'store/'.$first_product->path;
			$this->app->relocate($path);
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildResults()

	protected function buildResults()
	{
		$fulltext_result = $this->searchFulltext();

		if ($this->hasSearchDataValue('type')) {
			$type = $this->getSearchDataValue('type');

			if ($type === 'article')
				$this->buildArticles($fulltext_result);
			elseif ($type === 'product')
				$this->buildProducts($fulltext_result);

			$this->ui->getWidget('product_results_frame')->title = null;

		} elseif (count($this->getSearchDataValues()) === 1 &&
			$this->hasSearchDataValue('keywords')) {

			// keywords only
			$this->buildArticles($fulltext_result);
			$this->buildCategories($fulltext_result);
			$this->buildProducts($fulltext_result);
		} else {
			$this->buildProducts($fulltext_result);
			$this->ui->getWidget('product_results_frame')->title = null;
		}

		if ($fulltext_result !== null) {
			$this->buildMisspellings($fulltext_result);
			$fulltext_result->saveHistory();
		}

		if (count($this->has_results) > 1) {
			$pager = $this->ui->getWidget('article_pager');
			$pager->display_parts = SwatPagination::NEXT | SwatPagination::PREV;
		} else {
			// set the article frame to use the whole width
			$frame = $this->ui->getWidget('article_results_frame');
			$frame->classes[] = 'store-article-results-full-width';
		}

		return true;
	}

	// }}}
	// {{{ protected function buildMessages()

	protected function buildMessages()
	{
		parent::buildMessages();

		$messages = $this->ui->getWidget('results_message');

		// display no product results message
		if (count($this->has_results) > 1 &&
			 $messages->getMessageCount() == 0 &&
			!in_array('product', $this->has_results)) {

			$message = $this->getNoResultsMessage();
			$message->primary_content = Store::_('No product results found.');
			$messages = $this->ui->getWidget('results_message');
			$messages->add($message);
		}
	}

	// }}}
	// {{{ protected function getSearchTips()

	protected function getSearchTips()
	{
		$tips = parent::getSearchTips();

		$tips[] = Site::_('You can search by an item’s number');

		return $tips;
	}

	// }}}
	// {{{ protected function getSearchSummary()

	/**
	 * Get a summary of the criteria that was used to perform the search
	 *
	 * @return array an array of summary strings.
	 */
	protected function getSearchSummary()
	{
		$summary = array();

		if ($this->hasSearchEngine('product')) {
			$engine = $this->getSearchEngine('product');
			$summary = $engine->getSearchSummary();
		}

		return $summary;
	}

	// }}}
	// {{{ protected function getFulltextTypes()

	protected function getFulltextTypes()
	{
		$types = parent::getFulltextTypes();
		$types[] = 'product';
		$types[] = 'category';

		return $types;
	}

	// }}}

	// build phase - articles
	// {{{ protected function instantiateArticleSearchEngine()

	protected function instantiateArticleSearchEngine()
	{
		$engine = new StoreArticleSearchEngine($this->app);
		$this->setSearchEngine('article', $engine);

		return $engine;
	}

	// }}}

	// build phase - categories
	// {{{ protected function buildCategories()

	protected function buildCategories($fulltext_result)
	{
		$engine = $this->instantiateCategorySearchEngine();
		$engine->setFulltextResult($fulltext_result);
		$categories = $engine->search();
		$categories->setRegion($this->app->getRegion());

		$this->result_count['category'] = count($categories);

		if (count($categories) > 0) {
			$this->has_results[] = 'category';

			$frame = $this->ui->getWidget('category_results_frame');
			$results = $this->ui->getWidget('category_results');
			$frame->visible = true;

			ob_start();
			$this->displayCategories($categories);
			$results->content = ob_get_clean();
		}
	}

	// }}}
	// {{{ protected function instantiateCategorySearchEngine()

	protected function instantiateCategorySearchEngine()
	{
		$engine = new StoreCategorySearchEngine($this->app);
		$this->setSearchEngine('category', $engine);

		return $engine;
	}

	// }}}
	// {{{ protected function displayCategories()

	/**
	 * Displays search results for a collection of categories
	 *
	 * @param StoreCategoryWrapper $categories the categories to display
	 *                                          search results for.
	 */
	protected function displayCategories(StoreCategoryWrapper $categories)
	{
		echo '<ul class="site-search-results">';

		foreach ($categories as $category) {
			$navbar = new SwatNavBar();
			$navbar->addEntries($category->getNavBarEntries());
			$path = $navbar->getLastEntry()->link;

			echo '<li class="store-category-tile">';
			$category->displayAsTile($path);
			$navbar->display();
			echo '</li>';
		}

		echo '</ul>';
	}

	// }}}
	// {{{ protected function getCategory()

	protected function getCategory()
	{
		$category = null;

		if ($this->hasSearchDataValue('category')) {
			$sql = 'select id, shortname, title from Category
				where id = findCategory(%s) and id in
					(select category from VisibleCategoryView
					where region = %s or region is null)';

			$sql = sprintf($sql,
				$this->app->db->quote($this->getSearchDataValue('category')),
				$this->app->db->quote($this->app->getRegion()->id, 'integer'));

			$category = SwatDB::query($this->app->db, $sql,
				'StoreCategoryWrapper')->getFirst();
		}

		return $category;
	}

	// }}}
	// {{{ protected function getAttributes()

	protected function getAttributes()
	{
		$attributes = null;
		$attribute_shortnames = array();

		if ($this->hasSearchDataValue('attr')) {
			$value = $this->getSearchDataValue('attr');
			if (is_array($value))
				$attribute_shortnames = $value;
		}

		if (count($attribute_shortnames) > 0) {
			$sql = 'select * from Attribute
				where shortname in (%s)';

			foreach ($attribute_shortnames as &$shortname)
				$shortname = $this->app->db->quote($shortname);

			$sql = sprintf($sql, implode(',', $attribute_shortnames));

			$attributes = SwatDB::query($this->app->db, $sql,
				SwatDBClassMap::get('StoreAttributeWrapper'));
		}

		return $attributes;
	}

	// }}}

	// build phase - products
	// {{{ protected function buildProducts()

	protected function buildProducts($fulltext_result)
	{
		$pager = $this->ui->getWidget('product_pager');
		$engine = $this->instantiateProductSearchEngine();
		$engine->setFulltextResult($fulltext_result);
		$products = $engine->search($pager->page_size, $pager->current_record);

		$pager->total_records = $engine->getResultCount();
		$pager->link = $this->source;

		$this->result_count['product'] = count($products);

		if (count($products) > 0) {
			$this->has_results[] = 'product';

			$frame = $this->ui->getWidget('product_results_frame');
			$results = $this->ui->getWidget('product_results');
			$frame->visible = true;

			ob_start();
			$this->displayProducts($products);
			$results->content = ob_get_clean();
		}
	}

	// }}}
	// {{{ protected function instantiateProductSearchEngine()

	protected function instantiateProductSearchEngine()
	{
		$engine = new StoreProductSearchEngine($this->app);
		$this->setSearchEngine('product', $engine);

		$engine->attributes = $this->getAttributes();
		$engine->category = $this->getCategory();
		$engine->supress_duplicate_products = true;
		$engine->addOrderByField('is_available desc');

		return $engine;
	}

	// }}}
	// {{{ protected function displayProducts()

	/**
	 * Displays search results for a collection of products
	 *
	 * @param StoreProductWrapper $products the products to display search
	 *                                       results for.
	 */
	protected function displayProducts(StoreProductWrapper $products)
	{
		echo '<ul class="site-search-results">';
		$li_tag = new SwatHtmlTag('li');

		foreach ($products as $product) {
			echo '<li class="store-product-tile">';
			$link_href = 'store/'.$product->path;
			$product->displayAsTile($link_href);
			echo '</li>';
		}

		echo '</ul>';
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntrySet(
			$this->ui->getRoot()->getHtmlHeadEntrySet());

		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/styles/store-search-results-page.css', Store::PACKAGE_ID));
	}

	// }}}
}

?>
