<?php

require_once 'AtomFeed/AtomFeed.php';
require_once 'Store/StoreFroogleFeedEntry.php';

/**
 * A class for constructing Froogle Atom feeds
 *
 * @package   Store
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreFroogleFeed extends AtomFeed
{
	// {{{ public function __construct()

	/**
	 * Creates a new Atom feed
	 */
	public function __construct()
	{
		$this->addNameSpace('g', 'http://base.google.com/ns/1.0');
		$this->addNameSpace('c', 'http://base.google.com/cns/1.0');
	}

	// }}}
	// {{{ public static function getTextNode()

	/**
	 * Get text node
	 */
	public static function getTextNode($document, $name, $value,
		$name_space = null)
	{
		// value must be text-only
		$value = strip_tags($value);

		return parent::getTextNode($document, $name, $value, $name_space);
	}

	// }}}
	// {{{ public static function getBooleanNode()

	/**
	 * Get text node
	 */
	public static function getBooleanNode($document, $name, $value,
		$name_space = null)
	{
		// set the value to the boolean values google expects.
		$value = ($value === true) ? 'y' : 'n';

		return parent::getTextNode($document, $name, $value, $name_space);
	}

	// }}}
	// {{{ public static function getDateNode()

	/**
	 * Get date node
	 */
	public static function getDateNode($document, $name, DateTime $date,
		$name_space = null)
	{
		if ($name == 'expiration_date') {
			if ($name_space !== null) {
				$name = $name_space.':'.$name;
			}

			if (!($date instanceof SwatDate)) {
				$date = new SwatDate($date->format('c'));
				$date->setTZ($date->getTimezone()->getName());
			}

			return self::getTextNode($document,
				$name,
				$date->formatLikeIntl('yyyy-MM-dd'));
		}

		return parent::getDateNode($document, $name, $date, $name_space);
	}

	// }}}
}

?>
