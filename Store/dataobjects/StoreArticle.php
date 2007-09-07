<?php

require_once 'Site/dataobjects/SiteArticle.php';
require_once 'Store/dataobjects/StoreCategoryWrapper.php';

/**
 * An article in an e-commerce web application
 *
 * Articles on an e-commerce web application represent navigatable pages that
 * are outside the category/product structure hierarchy. Examples include
 * an "about us" page, a newsletter signup page and a shipping policy page.
 *
 * StoreArticle objects themselves may represent a tree structure by accessing
 * the {@link StoreArticle::$parent} property. 
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreArticle extends SiteArticle
{
	// {{{ protected properties

	/**
	 * The region to use when loading region-specific sub-articles
	 *
	 * @var StoreRegion
	 * @see StoreProduct::setRegion()
	 */ 
	protected $region = null;

	// }}}
	// {{{ public function setRegion()

	/**
	 * Sets the region to use when loading region-specific sub-articles
	 *
	 * @param StoreRegion $region the region to use.
	 */
	public function setRegion(StoreRegion $region)
	{
		$this->region = $region;

		if ($this->hasSubDataObject('sub_articles'))
			$this->sub_articles->setRegion($region);
	}

	// }}}
	// {{{ public function getVisibleSubArticles()

	/**
	 * Get the sub-articles of this article that are both shown and enabled
	 * in the current region.
	 *
	 * @return SiteArticleWrapper a recordset of sub-articles of the
	 *                              specified article.
	 */
	public function getVisibleSubArticles()
	{
		$sql = 'select id, title, shortname, description, createdate
			from Article
			where parent = %s and show = %s and id in 
			(select id from VisibleArticleView where region = %s)
			order by displayorder, title';

		if ($this->region === null)
			throw new StoreException('Region not set on article dataobject; '.
				'call the setRegion() method.');

		$sql = sprintf($sql,
			$this->db->quote($this->id, 'integer'),
			$this->db->quote(true, 'boolean'),
			$this->db->quote($this->region->id, 'integer'));

		$wrapper = SwatDBClassMap::get('SiteArticleWrapper');
		$articles = SwatDB::query($this->db, $sql, $wrapper);

		foreach ($articles as $article)
			$article->setRegion($this->region);

		return $articles;
	}

	// }}}
	// {{{ public function loadWithPath()

	/**
	 * Loads an article from the database with a path in a region
	 *
	 * @param string $path the path of the article in the article tree. Article
	 *                      nodes are separated by a '/' character.
	 * @param StoreRegion $region the region to filter the article by.
	 * @param array $fields the article fields to load from the database. By
	 *                       default, only the id and title are loaded. The
	 *                       path pseudo-field is always populated from the
	 *                       <code>$path</code> parameter.
	 *
	 * @return boolean true if an article was successfully loaded and false if
	 *                  no article was found in the given region at the
	 *                  specified path.
	 */
	public function loadWithPath($path, StoreRegion $region,
		$fields = array('id', 'title'))
	{
		$this->checkDB();

		$found = false;

		$id_field = new SwatDBField($this->id_field, 'integer');
		foreach ($fields as &$field)
			$field = $this->table.'.'.$field;

		$sql = 'select %1$s from
				findArticle(%2$s)
			inner join %3$s on findArticle = %3$s.%4$s
			inner join VisibleArticleView on
				findArticle = VisibleArticleView.id and
					VisibleArticleView.region = %5$s';

		$sql = sprintf($sql,
			implode(', ', $fields),
			$this->db->quote($path, 'text'),
			$this->table,
			$id_field->name,
			$this->db->quote($region->id, 'integer'));

		$row = SwatDB::queryRow($this->db, $sql);
		if ($row !== null) {
			$this->initFromRow($row);
			$this->setInternalValue('path', $path);
			$this->generatePropertyHashes();
			$found = true;
		}

		return $found;
	}

	// }}}

	// loader methods
	// {{{ protected function loadRelatedCategories()

	/**
	 * Loads related cateogries 
	 *
	 * Related categories are ordered by the category table's display order.
	 *
	 * @see StoreCategory::loadRelatedArticles()
	 */
	protected function loadRelatedCategories()
	{
		$sql = 'select Category.*, getCategoryPath(id) as path
			from Category 
				inner join ArticleCategoryBinding
					on Category.id = ArticleCategoryBinding.category
						and ArticleCategoryBinding.article = %s
			order by Category.displayorder asc';

		$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));
		$wrapper = SwatDBClassMap::get('StoreCategoryWrapper');
		return SwatDB::query($this->db, $sql, $wrapper);
	}

	// }}}
	// {{{ protected function loadSubArticles()

	/**
	 * Loads the sub-articles of this article
	 *
	 * @return SiteArticleWrapper a recordset of sub-articles of the
	 *                              specified article.
	 */
	protected function loadSubArticles()
	{
		$sql = 'select id, title, shortname, description, createdate
			from Article
			where parent = %s and id in 
			(select id from VisibleArticleView where region = %s)
			order by displayorder, title';

		if ($this->region === null)
			throw new StoreException('Region not set on article dataobject; '.
				'call the setRegion() method.');

		$sql = sprintf($sql,
			$this->db->quote($this->id, 'integer'),
			$this->db->quote($this->region->id, 'integer'));

		$wrapper = SwatDBClassMap::get('SiteArticleWrapper');
		$articles = SwatDB::query($this->db, $sql, $wrapper);

		foreach ($articles as $article)
			$article->setRegion($this->region);

		return $articles;
	}

	// }}}
}

?>
