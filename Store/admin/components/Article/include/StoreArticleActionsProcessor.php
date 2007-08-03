<?php

require_once 'Admin/pages/AdminPage.php';
require_once 'SwatDB/SwatDB.php';

/**
 * Processes actions on articles
 *
 * This class is used on both the article search results and on the articles
 * tree.
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreArticleActionsProcessor
{
	/**
	 * A reference to the page that is using this action processor
	 *
	 * @var AdminPage
	 */
	private $page;

	/**
	 * Creates a new article action processor
	 *
	 * @param AdminPage $page the page that is using this action processor
	 */
	public function __construct(AdminPage $page)
	{
		$this->page = $page;
	}

	/**
	 * Processes actions on articles
	 *
	 * @param SwatTableView $view the view to process.
	 * @param SwatActions $actions the list of actions.
	 */
	public function process($view, $actions)
	{
		$num = count($view->checked_items);
		$message = null;

		switch ($actions->selected->id) {
		case 'delete':
			$this->page->app->replacePage('Article/Delete');
			$this->page->app->getPage()->setItems($view->checked_items);
			break;

		case 'accessibility_action':
			$processor =
				$actions->selected->widget->getChild('article_region_action');

			$processor->setItems($view->checked_items);
			$processor->processAction();

			$message = new SwatMessage(sprintf(Store::ngettext(
				'Accessibility has been updated for one article.',
				'Accessibility has been updated for %d articles.', $num),
				SwatString::numberFormat($num)));

			break;

		case 'visibility_action' :
			$visibility = $actions->selected->widget->getChild('visibility');
			switch ($visibility->value) {
			case 'show_in_menu':
				SwatDB::updateColumn($this->page->app->db, 'Article',
					'boolean:show', true, 'id', $view->checked_items);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One article has been shown in the menu.',
					'%d articles have been shown in the menu.', $num),
					SwatString::numberFormat($num)));
				
				break;

			case 'hide_from_menu':
				SwatDB::updateColumn($this->page->app->db, 'Article',
					'boolean:show', false, 'id', $view->checked_items);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One article has been hidden from the menu.',
					'%d articles have been hidden from the menu.', $num),
					SwatString::numberFormat($num)));

				break;

			case 'show_in_search':
				SwatDB::updateColumn($this->page->app->db, 'Article',
					'boolean:searchable', true, 'id', $view->checked_items);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One article has been made searchable.',
					'%d articles have been made searchable.', $num),
					SwatString::numberFormat($num)));
				
				break;

			case 'hide_from_search':
				SwatDB::updateColumn($this->page->app->db, 'Article',
					'boolean:searchable', false, 'id', $view->checked_items);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One article has been hidden from the search results.',
					'%d articles have been hidden from the search results.',
					$num), SwatString::numberFormat($num)));

				break;

			case 'enable':
				SwatDB::updateColumn($this->page->app->db, 'Article',
					'boolean:enabled', true, 'id', $view->checked_items);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One article has been enabled.',
					'%d articles have been enabled.', $num),
					SwatString::numberFormat($num)));

				break;

			case 'disable':
				SwatDB::updateColumn($this->page->app->db, 'Article',
					'boolean:enabled', false, 'id', $view->checked_items);

				$message = new SwatMessage(sprintf(Store::ngettext(
					'One article has been disabled.',
					'%d articles have been disabled.', $num),
					SwatString::numberFormat($num)));

				break;

			break;
			}
		}

		if ($message !== null)
			$this->page->app->messages->add($message);
	}

	public static function getActions()
	{
		return array(
			'show_in_menu' => Store::_('show in menu'),
			'hide_from_menu' => Store::_('hide from menu'),
			'show_in_search' => Store::_('show in search'),
			'hide_from_search' => Store::_('hide from search'),
		);
	}
}

?>
