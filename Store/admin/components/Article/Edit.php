<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatDate.php';

/**
 * Edit page for Articles
 *
 * @package   veseys2
 * @copyright 2005-2006 silverorange
 */
class StoreArticleEdit extends AdminDBEdit
{
	// {{{ protected properties 

	protected $fields;

	// }}}
	// {{{ private properties 

	private $parent;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->mapClassPrefixToPath('Store', 'Store');
		$this->ui->loadFromXML(dirname(__FILE__).'/edit.xml');

		$this->fields = array(
			'title',
			'shortname',
			'description',
			'bodytext',
			'boolean:show',
			'boolean:searchable',
			'date:modified_date',
		);

		$this->parent = SiteApplication::initVar('parent');

		if ($this->id === null) {
			$this->ui->getWidget('shortname_field')->visible = false;
		} else {
			$sql = sprintf('select parent from Article where id = %s',
				$this->app->db->quote($this->id, 'integer'));

			$this->parent = SwatDB::queryOne($this->app->db, $sql);
		}
	}

	// }}}

	// process phase
	// {{{ protected function validate()

	protected function validate()
	{
		$shortname = $this->ui->getWidget('shortname')->value;

		if ($this->id === null && $shortname === null) {
			$shortname = $this->generateShortname(
				$this->ui->getWidget('title')->value);
			$this->ui->getWidget('shortname')->value = $shortname;

		} elseif (!$this->validateShortname($shortname)) {
			$msg = new SwatMessage(
				Store::_('Shortname already exists and must be unique.'), 
				SwatMessage::ERROR);

			$this->ui->getWidget('shortname')->addMessage($msg);
		}
	}

	// }}}
	// {{{ protected function validateShortname()

	protected function validateShortname($shortname)
	{
		$sql = 'select shortname from Article 
				where shortname = %s and parent %s %s and id %s %s';

		$sql = sprintf($sql,
			$this->app->db->quote($shortname, 'text'),
			SwatDB::equalityOperator($this->parent, false),
			$this->app->db->quote($this->parent, 'integer'),
			SwatDB::equalityOperator($this->id, true),
			$this->app->db->quote($this->id, 'integer'));

		$query = SwatDB::query($this->app->db, $sql);

		return (count($query) == 0);
	}

	// }}}
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$values = $this->ui->getValues(array('title', 'shortname', 'bodytext',
			'description', 'show', 'searchable'));

		$now = new SwatDate();
		$now->toUTC();
		$values['modified_date'] = $now->getDate();

		if ($this->id === null) {
			$this->fields[] = 'date:createdate';
			$values['createdate'] = $now->getDate();
			
			$this->fields[] = 'integer:parent';
			$values['parent'] = 
				$this->ui->getWidget('edit_form')->getHiddenField('parent');
			
			$this->id = SwatDB::insertRow($this->app->db, 'Article',
				$this->fields, $values, 'integer:id');
		} else {
			SwatDB::updateRow($this->app->db, 'Article', $this->fields, 
				$values, 'integer:id', $this->id);
		}

		$this->saveRegions();

		$this->addToSearchQueue();

		$msg = new SwatMessage(
			sprintf(Store::_('“%s” has been saved.'), $values['title']));

		$this->app->messages->add($msg);
	}

	// }}}
	// {{{ protected function saveRegions()

	protected function saveRegions()
	{
		$region_list = $this->ui->getWidget('regions');
		
		SwatDB::updateBinding($this->app->db, 'ArticleRegionBinding', 
			'article', $this->id, 'region', $region_list->values, 'Region',
			'id');
	}

	// }}}
	// {{{ protected function addToSearchQueue()

	protected function addToSearchQueue()
	{
		$sql = sprintf('delete from NateGoSearchQueue
			where document_id = %s and document_type = %s',
			$this->app->db->quote($this->id, 'integer'),
			$this->app->db->quote(1, 'integer'));

		SwatDB::exec($this->app->db, $sql);

		$sql = sprintf('insert into NateGoSearchQueue
			(document_id, document_type) values (%s, %s)',
			$this->app->db->quote($this->id, 'integer'),
			$this->app->db->quote(1, 'integer'));

		SwatDB::exec($this->app->db, $sql);
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		$form = $this->ui->getWidget('edit_form');
		$form->addHiddenField('parent', $this->parent);

		$regions = $this->ui->getWidget('regions');
		$regions->options = SwatDB::getOptionArray($this->app->db,
			'Region', 'text:title', 'integer:id');
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		$row = SwatDB::queryRowFromTable($this->app->db, 'Article', 
			$this->fields, 'integer:id', $this->id);

		if ($row === null)
			throw new AdminNotFoundException(
				sprintf(Store::_('Article with id ‘%s’ not found.'),
					$this->id));

		$this->ui->setValues(get_object_vars($row));

		$this->loadRegions();
	}

	// }}}
	// {{{ protected function loadRegions()

	protected function loadRegions()
	{
		$regions_list = $this->ui->getWidget('regions');

		$sql = sprintf('select region
			from ArticleRegionBinding where article = %s',
			$this->app->db->quote($this->id, 'integer'));

		$bindings = SwatDB::query($this->app->db, $sql);

		foreach ($bindings as $binding)
			$regions_list->values[] = $binding->region;
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		if ($this->id !== null) {
			$navbar_rs = SwatDB::executeStoredProc($this->app->db, 
				'getArticleNavBar', array($this->id));

			foreach ($navbar_rs as $elem)
				$this->navbar->addEntry(new SwatNavBarEntry($elem->title,
					'Article/Index?id='.$elem->id));
		} elseif ($this->parent !== null) {
			$navbar_rs = SwatDB::executeStoredProc($this->app->db, 
				'getArticleNavBar', array($this->parent));

			foreach ($navbar_rs as $elem)
				$this->navbar->addEntry(new SwatNavBarEntry($elem->title,
					'Article/Index?id='.$elem->id));
		}

		parent::buildNavBar();
	}

	// }}}
}
?>
