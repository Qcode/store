<?php

require_once 'Swat/SwatNavBarEntry.php';
require_once 'Store/dataobjects/StoreDataObject.php';
require_once 'Store/dataobjects/StoreCategoryImage.php';
require_once 'Store/dataobjects/StoreArticleWrapper.php';
require_once 'Store/dataobjects/StoreRegion.php';

/**
 * A category for an e-commerce web application
 *
 * Categories are a navigational network that lies beneath the products and
 * items of a store. The sole purpose of categories is to organize products
 * and items into meaningful and navigatable sets.
 *
 * <pre>
 * Category
 * |
 * -- Product
 *    |
 *    -- Item
 * </pre>
 *
 * One category may belong to another category and may contain multiple
 * categories. There is no restriction on placing a single category into
 * multiple categories so categories do not represent a tree structure.
 * 
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCategory extends StoreDataObject
{
	// {{{ class constants

	/**
	 * The maximum depth of categories in the category tree
	 *
	 * Objects that interact with categories may choose not to respect
	 * categories with a depth greater than this value.
	 *
	 * The root category is the zero-th level category. 
	 */
	const MAX_DEPTH = 8;

	// }}}
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * Identifier of parent category
	 *
	 * If this category is a root category, this property is null.
	 *
	 * @var integer
	 */
	public $parent;

	/**
	 * Short, textual identifier of this category
	 *
	 * This identifier is designed to be used in URL's.
	 *
	 * @var string
	 */
	public $shortname;

	/**
	 * User visible title
	 *
	 * @var string 
	 */
	public $title;

	/**
	 * Description of this category
	 *
	 * This text is intended to be displayed on the parent page of this
	 * category.
	 *
	 * @var string 
	 */
	public $description;

	/**
	 * Body text of this category
	 *
	 * This text is intended to be displayed on a page dedicated to this
	 * category.
	 *
	 * @var string 
	 */
	public $bodytext;

	/**
	 * The date this category was created
	 *
	 * @var Date 
	 */
	public $createdate;

	/**
	 * Order of display of this category
	 *
	 * @var integer
	 */
	public $displayorder;

	// }}}
	// {{{ protected properties

	/**
	 * @var StoreRegion
	 */
	protected $region;

	/**
	 * @var boolean
	 */
	protected $limit_by_region = true;

	/**
	 * Cache of product counts for this category indexed by region id
	 *
	 * This is an array of integers.
	 *
	 * @var array
	 */
	protected $product_count = array();

	/**
	 * @var array
	 *
	 * @see StoreCategory::getNavBarEntries()
	 */
	protected $navbar_entries;

	/**
	 * @var array
	 *
	 * @see StoreCategory::getAdminNavBarEntries()
	 */
	protected $admin_navbar_entries;

	// }}}
	// {{{ public function setRegion()

	public function setRegion(StoreRegion $region, $limiting = true)
	{
		$this->region = $region;
		$this->limit_by_region = $limiting;

		// TODO: there is no loadProducts() method
		if ($this->hasSubDataObject('products'))
			foreach ($this->products as $product)
				$product->setRegion($region, $limiting);
	}

	// }}}
	// {{{ public function getProductCount()

	/**
	 * Loads the count of visible products in this category in a region
	 *
	 * If you are calling this method frequently during a single request, it is
	 * more efficient to include the 'product_count' and 'region_id' fields in
	 * the initial category query.
	 *
	 * @param StoreRegion $region optional. Region for which to get product
	 *                             count. If no region is specified, the region
	 *                             set using {@link StoreItem::setRegion()}
	 *                             is used.
	 *
	 * @return integer the count of visible products in this category in the
	 *                 given region.
	 */
	public function getProductCount($region = null)
	{
		if ($region !== null && !($region instanceof StoreRegion))
			throw new StoreException(
				'$region must be an instance of StoreRegion.');

		// If region is not specified but is set through setRegion() use
		// that region instead.
		if ($region === null && $this->region !== null)
			$region = $this->region;

		// A region is required.
		if ($region === null)
			throw new StoreException(
				'$region must be specified unless setRegion() is called '.
				'beforehand.');

		// We can set this to zero because if there is a null result in the
		// CategoryVisibleProductCountByRegionCache this is the same as having
		// no products.
		$product_count = 0;

		if ($this->region->id == $region->id &&
			isset($this->product_count[$region->id])) {
			$product_count = $this->product_count[$region->id];
		} else {
			$sql = 'select product_count
				from CategoryVisibleProductCountByRegionCache
				where region = %s and category = %s';

			$sql = sprintf($sql,
				$this->db->quote($region->id, 'integer'),
				$this->db->quote($this->id, 'integer'));

			$product_count = SwatDB::queryOne($this->db, $sql);
			if ($product_count === null)
				$product_count = 0;

			$this->product_count[$region->id] = $product_count;
		}

		return $product_count;
	}

	// }}}
	// {{{ public function getNavBarEntries()

	/**
	 * Gets the set of {@link SwatNavBarEntry} objects for this category
	 *
	 * @return array the set of SwatNavBarEntry objects for this category.
	 */
	public function getNavBarEntries()
	{
		if ($this->navbar_entries === null) {
			$this->navbar_entries = array();

			$path = 'store';
			foreach ($this->queryNavBar() as $row) {
				$path.= '/'.$row->shortname;
				$this->navbar_entries[] =
					new SwatNavBarEntry($row->title, $path);
			}
		}

		return $this->navbar_entries;
	}

	// }}}
	// {{{ public function getAdminNavBarEntries()

	/**
	 * Gets the set of {@link SwatNavBarEntry} objects for this category
	 * with links for the admin site
	 *
	 * @return array the set of SwatNavBarEntry objects for this category.
	 */
	public function getAdminNavBarEntries()
	{
		if ($this->admin_navbar_entries === null) {
			$this->admin_navbar_entries = array();

			foreach ($this->queryNavBar() as $row) {
				$link = sprintf('Category/Index?id=%s', $row->id);
				$this->admin_navbar_entries[] =
					new SwatNavBarEntry($row->title, $link);
			}
		}

		return $this->admin_navbar_entries;
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerDateProperty('createdate');

		$this->registerInternalProperty('path');
		$this->registerInternalProperty('image',
			$this->class_map->resolveClass('StoreCategoryImage'));

		$this->table = 'Category';
		$this->id_field = 'integer:id';
	}

	// }}}
	// {{{ protected function initFromRow()

	/**
	 * Initializes this category from a row object
	 *
	 * If the row object has a 'region_id' field and the fields
	 * 'product_count' the product_count value is cached for subsequent calls
	 * to the getProductCount() method.
	 */
	protected function initFromRow($row)
	{
		parent::initFromRow($row);

		if (is_object($row))
			$row = get_object_vars($row);

		if (isset($row['region_id'])) {
			if (isset($row['product_count']))
				$this->product_count[$row['region_id']] = $row['product_count'];
		}
	}

	// }}}

	// loader methods
	// {{{ protected function loadPath()

	/**
	 * Loads the URL fragment of this category
	 *
	 * If the path was part of the initial query to load this category, that
	 * value is returned. Otherwise, a separate query gets the path of this
	 * category. If you are calling this method frequently during a single
	 * request, it is more efficient to include the path in the initial
	 * category query.
	 */
	protected function loadPath()
	{
		$path = '';

		if ($this->hasInternalValue('path') &&
			$this->getInternalValue('path') !== null) {
			$path = $this->getInternalValue('path');
		} else {
			$sql = sprintf('select getCategoryPath(%s)',
				$this->db->quote($this->id, 'integer'));

			$path = SwatDB::queryOne($this->db, $sql);
		}

		return $path;
	}

	// }}}
	// {{{ protected function loadRelatedArticles()

	/**
	 * Loads related articles
	 *
	 * Related articles are ordered by the article table's display order.
	 *
	 * @see StoreArticle::loadRelatedCategories()
	 */
	protected function loadRelatedArticles()
	{
		$sql = 'select Article.*, getArticlePath(id) as path
			from Article 
				inner join ArticleCategoryBinding
					on Article.id = ArticleCategoryBinding.article
						and ArticleCategoryBinding.category = %s
			order by Article.displayorder asc';

		$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));
		$wrapper = $this->class_map->resolveClass('StoreArticleWrapper');
		return SwatDB::query($this->db, $sql, $wrapper);
	}

	// }}}
	// {{{ private function queryNavBar()

	/**
	 * Helper method for loading navbar entries of this category
	 */
	protected function queryNavBar()
	{
		$sql = sprintf('select * from getCategoryNavbar(%s)',
			$this->db->quote($this->id, 'integer'));

		return SwatDB::query($this->db, $sql);
	}

	// }}}

	// display methods
	// {{{ public function displayAsTile()

	/**
	 * Displays the category as:
	 *
	 * [ IMAGE ] _Title_
	 *
	 * @param string $link the link to use when displaying. This link should go
	 *                      to this category's page.
	 */
	public function displayAsTile($link)
	{
		$anchor_tag = new SwatHtmlTag('a');
		$anchor_tag->class = 'category-tile-link';
		$anchor_tag->href = $link;

		$title_span = new SwatHtmlTag('span');
		$title_span->class = 'category-tile-title';
		$title_span->setContent($this->title);

		if ($this->getProductCount() > 1) {
			$details_span = new SwatHtmlTag('span');
			$details_span->class = 'category-tile-details';
			$details_span->setContent(sprintf(
				ngettext('%s product', '%s products', $this->getProductCount()),
				$this->getProductCount()));
		}

		if (strlen($this->description)) {
			$description_p = new SwatHtmlTag('p');
			$description_p->class = 'category-description';
			$description_p->setContent($this->description);
		}

		if ($this->image !== null) {
			$img_tag = $this->image->getImgTag('thumb');
		} else {
			$img_tag = new SwatHtmlTag('img');
			$img_tag->src = 'images/elements/category-place-holder.png';
			$img_tag->width = CategoryImage::THUMB_WIDTH;
			$img_tag->height = CategoryImage::THUMB_HEIGHT;
			$img_tag->class = 'store-border-on';
		}

		$img_tag->alt = 'Photo of '.$this->title;

		$anchor_tag->open();
		$img_tag->display();
		$title_span->display();
		$anchor_tag->close();
		echo ' ';

		if ($this->getProductCount() > 1)
			$details_span->display();

		if (strlen($this->description))
			$description_p->display();
	}

	// }}}
}

?>
