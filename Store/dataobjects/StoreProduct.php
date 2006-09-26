<?php

require_once 'Store/dataobjects/StoreDataObject.php';
require_once 'Store/dataobjects/StoreItemWrapper.php';
require_once 'Store/dataobjects/StoreItemGroupWrapper.php';
require_once 'Store/dataobjects/StoreCatalog.php';
require_once 'Store/dataobjects/StoreProductImage.php';

/**
 * A product for an e-commerce web application
 *
 * Products are in the middle of the product structure. Each product can have
 * multiple items and can belong to multiple categories. Procucts are usually
 * displayed on product pages. A product is different from an item in that
 * the product contains a very general idea of what is available and an item
 * describes an exact item that a customer can purchase.
 *
 * <pre>
 * Category
 * |
 * -- Product
 *    |
 *    -- Item
 * </pre>
 *
 * Ideally, products are displayed one to a page but it is possible to display
 * many products on one page.
 *
 * If there are many StoreProduct objects that must be loaded for a page
 * request, the MDB2 wrapper class called StoreProductWrapper should be used to
 * load the objects.
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreProductWrapper
 */
class StoreProduct extends StoreDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * A short textual identifier for this product
	 *
	 * This identifier is designed to be used in URL's and must be unique
	 * within a catalog.
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
	 * User visible content
	 *
	 * @var string
	 */
	public $bodytext;

	/**
	 * Create date
	 *
	 * @var Date
	 */
	public $createdate;

	// }}}
	// {{{ protected properties

	/**
	 * The region to use when loading region-specific fields in item sub-data-
	 * objects
	 *
	 * @var integer
	 * @see StoreProduct::setRegion()
	 */ 
	protected $join_region = null;

	/**
	 * Whether or not to exclude items unavailable in the current join region
	 * when loading item sub-data-objects
	 *
	 * @var boolean
	 * @see StoreProduct::setRegion()
	 */
	protected $limit_by_region = true;

	// }}}
	// {{{ public function setRegion()

	/**
	 * Sets the region to use when loading region-specific fields for item
	 * sub-data-objects
	 *
	 * @param integer $join_region the unique identifier of the region to use.
	 * @param boolean $limiting whether or not to exclude items unavailable in
	 *                           the current join region when loading item
	 *                           sub-data-objects.
	 */
	public function setRegion($region, $limiting = true)
	{
		$this->join_region = $region;
		$this->limit_by_region = $limiting;
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerInternalProperty('primary_category',
			$this->class_map->resolveClass('StoreCategory'));

		$this->registerInternalProperty('path');
		$this->registerDateProperty('createdate');

		$this->registerInternalProperty('catalog',
			$this->class_map->resolveClass('StoreCatalog'));

		$this->registerInternalProperty('image',
			$this->class_map->resolveClass('StoreProductImage'));

		$this->table = 'Product';
		$this->id_field = 'integer:id';
	}

	// }}}

	// loader methods
	// {{{ protected function loadItems()

	/**
	 * Loads item sub-data-objects for this product
	 *
	 * If you want to loead region-specific fields on the items, call the
	 * {@link StoreProduct::setRegion()} method first.
	 *
	 * @see StoreProduct::setRegion()
	 */
	protected function loadItems()
	{
		$items = null;
		$wrapper = $this->class_map->resolveClass('StoreItemWrapper');

		if ($this->join_region === null) {
			$sql = 'select id from Item where product = %s';
			$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));
			$items = call_user_func(array($wrapper, 'loadSetFromDB'),
				$this->db, $sql);
		} else {
			$sql = 'select id from Item where product = %s';
			$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));
			$items = call_user_func(array($wrapper, 'loadSetFromDBWithRegion'),
				$this->db, $sql, $this->join_region, $this->limit_by_region);
		}

		return $items;
	}

	// }}}
	// {{{ protected function loadPath()

	/**
	 * Loads the URL fragment of this product 
	 *
	 * If the path was part of the initial query to load this product, that
	 * value is returned. Otherwise, a separate query gets the path of this
	 * product. If you are calling this method frequently during a single
	 * request, it is more efficient to include the path in the initial
	 * product query.
	 */
	protected function loadPath()
	{
		$path = '';

		if ($this->hasInternalValue('path') &&
			$this->getInternalValue('path') !== null) {
			$path = $this->getInternalValue('path').'/'.$this->shortname;
		} elseif ($this->hasInternalValue('primary_category')) {
			$primary_category = $this->getInternalValue('primary_category');

			$sql = sprintf('select getCategoryPath(%s)',
				$this->db->quote($primary_category, 'integer'));

			$path = SwatDB::queryOne($this->db, $sql);
			$path.= '/'.$this->shortname;
		}

		return $path;
	}

	// }}}
	// {{{ protected function loadItemGroups()

	protected function loadItemGroups()
	{
		$sql = 'select * from ItemGroup
			where product = %s order by displayorder';

		$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));
		return SwatDB::query($this->db, $sql, 'StoreItemGroupWrapper');
	}

	// }}}
}

?>
