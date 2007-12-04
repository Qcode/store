<?php

require_once 'Store/StoreApplication.php';
require_once 'Store/dataobjects/StoreRegionWrapper.php';

/**
 *
 *
 * @package   Store
 * @copyright 2004-2007 silverorange
 */
abstract class StoreLocaleApplication extends StoreApplication
{
	// {{{ public function getBaseHref()

	public function getBaseHref($secure = null, $locale = null)
	{
		if ($locale === null)
			$locale = $this->locale;

		if ($locale === false || $locale === null)
			return parent::getBaseHref($secure);

		$language = substr($locale, 0, 2);
		$country = strtolower(substr($locale, 3, 2));
		return parent::getBaseHref($secure).$country.'/'.$language.'/';
	}

	// }}}
	// {{{ public function getRegion()

	/**
	 * @return StoreRegion
	 */
	public function getRegion()
	{
		return $this->region;
	}

	// }}}
	// {{{ protected function loadPage()

	protected function loadPage()
	{
		$this->parseLocale(self::initVar('locale'));

		parent::loadPage();
	}

	// }}}
	// {{{ protected function getBaseHrefRelativeUri()

	protected function getBaseHrefRelativeUri($secure = null)
	{
		$uri = parent::getBaseHrefRelativeUri($secure);

		// trim locale from beginning of relative uri
		$uri = preg_replace('|^[a-z][a-z]/[a-z][a-z]/|', '', $uri);

		return $uri;
	}

	// }}}
	// {{{ private function parseLocale()

	private function parseLocale($locale)
	{
		$this->locale = null;
		$this->region = null;

		$matches = array();
		if (preg_match('|([a-z][a-z])/([a-z][a-z])|', $locale, $matches) != 1)
			return;

		$this->locale = $matches[2].'_'.strtoupper($matches[1]);

		$sql = 'select id, title from Region where id in
			(select region from Locale where id = %s)';

		$sql = sprintf($sql, $this->db->quote($this->locale, 'text'));
		$regions = SwatDB::query($this->db, $sql, 'StoreRegionWrapper');
		$this->region = $regions->getFirst();

		if ($this->region === null)
			$this->locale = null;
	}

	// }}}
}

?>
