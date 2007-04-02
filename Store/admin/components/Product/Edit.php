<?php

require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'Admin/exceptions/AdminNoAccessException.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Date.php';

/**
 * Edit page for Products
 *
 * @package   Store
 * @copyright 2005-2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreProductEdit extends AdminDBEdit
{
	// {{{ protected properties

	protected $fields;
	protected $ui_xml = 'Store/admin/components/Product/edit.xml';

	// }}}

	// {{{ private properties

	private $category_id;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->ui_xml);
		
		$this->fields = array('title', 'shortname', 'catalog', 'bodytext');

		$this->category_id = SiteApplication::initVar('category');

		if ($this->category_id === null && $this->id === null)
			throw new AdminNoAccessException(Store::_(
				'A category ID or a product ID must be passed in the URL.'));

		$catalog_flydown = $this->ui->getWidget('catalog');
		$catalog_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'Catalog', 'title', 'id', 'title'));

		// Only show blank option if there is more than one catalogue to choose
		// from.
		$catalog_flydown->show_blank = (count($catalog_flydown->options) > 1);

		if ($this->id === null) {
			$this->ui->getWidget('shortname_field')->visible = false;
			$this->ui->getWidget('submit_continue_button')->visible = true;
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
				$this->ui->getWidget('title')->value, $this->id);
			$this->ui->getWidget('shortname')->value = $shortname;

		} elseif (!$this->validateShortname($shortname, $this->id)) {
			$message = new SwatMessage(
				Store::_('Shortname already exists and must be unique.'),
				SwatMessage::ERROR);

			$this->ui->getWidget('shortname')->addMessage($message);
		}
	}

	// }}}
	// {{{ protected function validateShortname()

	protected function validateShortname($shortname)
	{
		$sql = 'select shortname from Product
				where shortname = %s and id %s %s';

		$sql = sprintf($sql,
			$this->app->db->quote($shortname, 'text'),
			SwatDB::equalityOperator($this->id, true),
			$this->app->db->quote($this->id, 'integer'));

		$query = SwatDB::query($this->app->db, $sql);

		return (count($query) == 0);
	}

	// }}}
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$values = $this->getUIValues();

		if ($this->id === null) {
			$this->fields[] = 'date:createdate';
			$date = new Date();
			$date->toUTC();
			$values['createdate'] = $date->getDate();
					
			$this->id = SwatDB::insertRow($this->app->db, 'Product',
				$this->fields, $values, 'id');

			$category = 
				$this->ui->getWidget('edit_form')->getHiddenField('category');

			$sql = sprintf('insert into CategoryProductBinding
				(category, product) values (%s, %s)', $category, $this->id);

			SwatDB::query($this->app->db, $sql);
		} else {
			SwatDB::updateRow($this->app->db, 'Product', $this->fields, $values,
				'id', $this->id);
		}

		$this->addToSearchQueue();

		$message = new SwatMessage(sprintf(Store::_('“%s” has been saved.'),
			$values['title']));

		$this->app->messages->add($message);
	}

	// }}}
	// {{{ protected function getUIValues()

	protected function getUIValues()
	{
		return $this->ui->getValues(array('title', 'shortname', 'catalog',
			'bodytext'));
	}

	// }}}
	// {{{ protected function addToSearchQueue()

	protected function addToSearchQueue()
	{
		$sql = sprintf('delete from NateGoSearchQueue
			where document_id = %s and document_type = %s',
			$this->app->db->quote($this->id, 'integer'),
			$this->app->db->quote(2, 'integer'));

		SwatDB::exec($this->app->db, $sql);

		$sql = sprintf('insert into NateGoSearchQueue
			(document_id, document_type) values (%s, %s)',
			$this->app->db->quote($this->id, 'integer'),
			$this->app->db->quote(2, 'integer'));

		SwatDB::exec($this->app->db, $sql);
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate()
	{
		$button = $this->ui->getWidget('submit_continue_button');
		
		if ($button->hasBeenClicked()) {
			// manage skus
			$this->app->relocate(
				$this->app->getBaseHref().'Product/Details?id='.$this->id);
		} else {
			parent::relocate();
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		// smart defaulting of the catalog
		if ($this->id === null) {
			// TODO: use $this->app->session
			if (isset($_SESSION['catalog']) &&
				is_numeric($_SESSION['catalog'])) {
				$catalog = $_SESSION['catalog'];
			} else {
				$sql = 'select count(catalog) as num_products, catalog 
					from Product 
					where id in (
						select product from CategoryProductBinding 
						where category = %s) 
					group by catalog 
					order by num_products desc
					limit 1';

				$row = SwatDB::queryRow($this->app->db, sprintf($sql, 
					$this->category_id));

				$catalog = ($row === null) ? null : $row->catalog;
			}
			$this->ui->getWidget('catalog')->value = $catalog;
		}
	}

	// }}}
	// {{{ protected function buildForm()

	protected function buildForm()
	{
		parent::buildForm();
		$form = $this->ui->getWidget('edit_form');
		$form->addHiddenField('category', $this->category_id);
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar() 
	{
		if ($this->category_id !== null) {
			$this->navbar->popEntry();
			$this->navbar->addEntry(new SwatNavBarEntry(
				Store::_('Product Categories'), 'Category'));

			$cat_navbar_rs = SwatDB::executeStoredProc($this->app->db,
				'getCategoryNavbar', array($this->category_id));

			foreach ($cat_navbar_rs as $entry)
				$this->navbar->addEntry(new SwatNavBarEntry($entry->title,
					'Category/Index?id='.$entry->id));
		}

		if ($this->id === null) {
			$this->title = Store::_('New Product');
			$this->navbar->addEntry(new SwatNavBarEntry(
				Store::_('New Product')));

		} else {
			$product_title = SwatDB::queryOneFromTable($this->app->db, 
				'Product', 'text:title', 'id', $this->id);

			if ($this->category_id === null)
				$link = sprintf('Product/Details?id=%s', $this->id);
			else
				$link = sprintf('Product/Details?id=%s&category=%s', $this->id,
					$this->category_id);

			$this->navbar->addEntry(new SwatNavBarEntry($product_title, $link));
			$this->navbar->addEntry(new SwatNavBarEntry(Store::_('Edit')));
			$this->title = $product_title;
		}
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		$row = SwatDB::queryRowFromTable($this->app->db, 'Product',
			$this->fields, 'id', $this->id);

		if ($row === null)
			throw new AdminNotFoundException(sprintf(
				Store::_('A product with an id of ‘%d’ does not exist.'),
				$this->id));

		$this->ui->setValues(get_object_vars($row));
	}

	// }}}
}

?>
