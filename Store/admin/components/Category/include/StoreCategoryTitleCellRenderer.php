<?php

require_once 'Admin/AdminTreeTitleLinkCellRenderer.php';
require_once 'Swat/SwatString.php';

/**
 * Cell renderer that displays category titles with no products in a special
 * way
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCategoryTitleCellRenderer extends AdminTreeTitleLinkCellRenderer
{
	public $product_count = 0;
	public $always_visible = false;

	public function render()
	{
		$this->base_stock_id = 'folder';

		if ($this->product_count == 0 && !$this->always_visible) {
			$this->text = SwatString::minimizeEntities($this->text);
			$this->content_type = 'text/xml';
			$this->text.= ' <span>&lt;no products&gt;</span>';
		} else {
			$this->content_type = 'text/plain';
		}

		parent::render();
	}

	protected function getTitle()
	{
		$out = array();

		if (intval($this->child_count) == 0)
			$out[] = 'no sub-categories';
		else
			$out[] = sprintf(ngettext('One sub-category', '%d sub-categories',
				$this->child_count), $this->child_count);

		if (intval($this->product_count) == 0)
			$out[] = 'no products in the selected catalogue(s)';
		else
			$out[] = sprintf(ngettext(
				'One product in the selected catalogue(s)',
				'%d products in the selected catalogue(s)',
				$this->product_count), $this->product_count);

		return implode(', ', $out);
	}
}

?>
