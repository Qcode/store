<?php

require_once 'Site/SiteApplicationModule.php';
require_once 'Store/StoreRecentStack.php';

/**
 * Tracks recently viewed things in a web-store application
 *
 * @package   Store
 * @copyright 2009 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreRecentModule extends SiteApplicationModule
{
	// {{{ public function depends()

	/**
	 * Gets the module features this module depends on
	 *
	 * @return array an array of {@link SiteModuleDependency} objects defining
	 *                        the features this module depends on.
	 */
	public function depends()
	{
		$depends = parent::depends();
		$depends[] = new SiteApplicationModuleDependency(
			'SiteCookieModule');

		return $depends;
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
		$new = false;

		if (!isset($this->app->cookie->recent) ||
			!($this->app->cookie->recent instanceof ArrayObject)) {
				$new = true;
				$this->app->cookie->setCookie('recent', new ArrayObject());
		}

		return $new;
	}

	// }}}
	// {{{ public function add()

	public function add($stack_name, $id)
	{
		if ($this->init())
			return;

		$stacks = $this->app->cookie->recent;

		if (!$stacks->offsetExists($stack_name))
			$stacks->offsetSet($stack_name, new StoreRecentStack());

		$stack = $stacks->offsetGet($stack_name);
		$stack->add($id);

		$this->app->cookie->setCookie('recent', $stacks);
	}

	// }}}
	// {{{ public function get()

	public function get($stack_name, $count = null)
	{
		if ($this->init())
			return null;

		$exclude_id = null;

		$page = $this->app->getPage();
		if ($stack_name == 'products' && $page instanceof StoreProductPage)
			$exclude_id = $page->product_id;

		$stacks = $this->app->cookie->recent;

		if ($stacks->offsetExists($stack_name)) {
			$stack = $this->app->cookie->recent->offsetGet($stack_name);
			$values =  $stack->get($count, $exclude_id);
		} else {
			$stacks->offsetSet($stack_name, new StoreRecentStack());
			$this->app->cookie->setCookie('recent', $stacks);
			$values = null;
		}

		return $values;
	}

	// }}}
}

?>
