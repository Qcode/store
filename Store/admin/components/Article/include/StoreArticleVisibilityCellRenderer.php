<?php

require_once 'Swat/SwatCellRenderer.php';

/**
 * Cell renderer that displays a summary of the visibility of an article
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreArticleVisibilityCellRenderer extends SwatCellRenderer
{
	public $article = false;
	public $db = false;

	public $searchable = false;
	public $show_in_menu = false;

	public $display_positive_states = false;
	public $separator = '<br />';

	public function render()
	{
		$sql = 'select Region.title from Region
			inner join ArticleRegionBinding on
				Region.id = region and article = %s
			order by Region.title';

		$sql = sprintf($sql, $this->db->quote($this->article, 'integer'));
		$region_availability = SwatDB::query($this->db, $sql);

		$messages = array();

		if (count($region_availability)) {

			if ($this->display_positive_states) {
				$regions = array();
				foreach ($region_availability as $region)
					$regions[] = $region->title;

				echo Store::_('accessible to: '), implode(', ', $regions),
					'<br />';
			}

			if (!$this->searchable)
				$messages[] = Store::_('not searchable');
			elseif ($this->display_positive_states)
				$messages[] = Store::_('searchable');

			if (!$this->show_in_menu)
				$messages[] = Store::_('not shown in menu');
			elseif ($this->display_positive_states)
				$messages[] = Store::_('shown in menu');

		} else {
			$messages[] = Store::_('not accessible');
		}

		echo implode($this->separator, $messages);
	}
}

?>
