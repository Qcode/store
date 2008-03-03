<?php

require_once 'Swat/SwatObject.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Store/StoreFroogleFeed.php';
require_once 'Store/StoreFroogleFeedEntry.php';
require_once 'Store/dataobjects/StoreProductWrapper.php';
require_once 'AtomFeed/AtomFeedAuthor.php';
require_once 'AtomFeed/AtomFeedLink.php';

/**
 * @package   Store
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class StoreFroogleGenerator extends SwatObject
{
	// {{{ private properties

	/**
	 * @var MDB2_Driver_Common
	 */
	private $db;

	/**
	 * @var SiteConfigModule
	 */
	private $config;

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new froogle generator
	 *
	 * @param MDB2_Driver_Common $db
	 * @param SiteConfigModule $config
	 */
	public function __construct(MDB2_Driver_Common $db,
		SiteConfigModule $config)
	{
		$this->db = $db;
		$this->config = $config;
	}

	// }}}
	// {{{ public function generate()

	public function generate()
	{
		$feed = new StoreFroogleFeed();

		$base_href = $this->config->uri->absolute_base;

		$feed->title = sprintf(Store::_('%s Products'),
			$this->config->site->title);

		$feed->addAuthor(new AtomFeedAuthor($this->config->site->title));
		$feed->link = new AtomFeedLink($base_href, 'self');
		$feed->id = sprintf('tag:%s,%s:/products/',
			substr($base_href, 7),
			$this->getSiteInceptionDate());

		$this->addEntries($feed);

		ob_start();
		$feed->display();
		return ob_get_clean();
	}

	// }}}
	// {{{ protected function addEntries()

	/**
	 * Add atom entries to the feed
	 */
	protected function addEntries(StoreFroogleFeed $feed)
	{
		foreach ($this->getItems() as $item) {
			$feed->addEntry($this->getEntry($item));
		}
	}

	// }}}
	// {{{ abstract protected function getEntry()

	/**
	 * @return StoreFroogleFeedEntry
	 */
	abstract protected function getEntry(StoreItem $item);

	// }}}
	// {{{ abstract protected function getItems()

	/**
	 * @return StoreItemWrapper
	 */
	abstract protected function getItems();

	// }}}
	// {{{ abstract protected function getSiteInceptionDate()

	/**
	 * @return string ISO-8601 date (yyy-mm-dd).
	 */
	abstract protected function getSiteInceptionDate();

	// }}}
}

?>
