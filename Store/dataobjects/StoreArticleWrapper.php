<?php

require_once 'StoreRecordsetWrapper.php';
require_once 'StoreArticle.php';

/**
 * A recordset wrapper class for StoreArticle objects
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @see       StoreArticle
 */
class StoreArticleWrapper extends StoreRecordsetWrapper
{
	// {{{ public function getByShortname()

	public function getByShortname($shortname)
	{
		foreach($this as $article)
			if ($article->shortname === $shortname)
				return $article;

		return null;
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class =
			$this->class_map->resolveClass('StoreArticle');
	}

	// }}}
}

?>
