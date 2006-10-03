<?php

require_once 'Swat/SwatCellRenderer.php';

/**
 * Cell renderer that displays a summary of the status of a catelogue
 *
 * @package   veseys2
 * @copyright 2005-2006 silverorange
 */
class CatalogStatusCellRenderer extends SwatCellRenderer
{
	public $catalog;
	public $db;

	public function render()
	{
		$sql = 'select Region.title, available
			from Region
				left outer join CatalogRegionBinding on
					Region.id = region and catalog = %s
			order by Region.title';

		$sql = sprintf($sql, $this->db->quote($this->catalog, 'integer'));
		$catalog_statuses = SwatDB::query($this->db, $sql);

		foreach ($catalog_statuses as $row) {
			echo SwatString::minimizeEntities($row->title);
			echo ': ';

			if ($row->available === null) {
				echo 'disabled';
			} else {
				echo ($row->available) ? 
					'enabled (in season)' : 'enabled (out of season)';
			}

			echo '<br />';
		}
	}
}

?>
