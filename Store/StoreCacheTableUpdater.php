<?php

require_once 'Store/Store.php';
require_once 'Site/SiteCommandLineApplication.php';
require_once 'Site/SiteConfigModule.php';
require_once 'Site/SiteDatabaseModule.php';
require_once 'SwatDB/SwatDB.php';

/**
 * Cache table updater application
 *
 * This application checks the dirty cache table list and runs an SQL function
 * for each entry found in the dirty cache table list.
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreCacheTableUpdater extends SiteCommandLineApplication
{
	// {{{ class constants

	/**
	 * Verbosity level for showing nothing.
	 */
	const VERBOSITY_NONE = 0;

	/**
	 * Verbosity level for showing errors.
	 */
	const VERBOSITY_ERRORS = 1;

	/**
	 * Verbosity level for showing normal messages.
	 */
	const VERBOSITY_MESSAGES = 2;

	// }}}
	// {{{ public function __construct()

	public function __construct($id, $title, $documentation)
	{
		parent::__construct($id, $title, $documentation);

		$verbosity = new SiteCommandLineArgument(array('-v', '--verbose'),
			'setVerbosity', 'Sets the level of verbosity of the updater. '.
			'Pass 0 to turn off all output.');

		$verbosity->addParameter('integer',
			'--verbose expects a level between 0 and 2.',
			self::VERBOSITY_MESSAGES);

		$this->addCommandLineArgument($verbosity);
	}

	// }}}
	// {{{ public function run()

	public function run()
	{
		$this->initModules();
		$this->parseCommandLineArguments();

		$this->output(Store::_('Pass 1/2:')."\n\n",
			self::VERBOSITY_MESSAGES, true);

		$this->updateCacheTables();

		// run twice to handle two-level dependencies
		$this->output("\n".Store::_('Pass 2/2:')."\n\n",
			self::VERBOSITY_MESSAGES, true);

		$this->updateCacheTables();
	}

	// }}}
	// {{{ protected function updateCacheTables()

	protected function updateCacheTables()
	{
		$sql = 'select shortname from CacheFlag where dirty = true';
		$tables = SwatDB::query($this->db, $sql);
		$total = count($tables);
		if ($total > 0) {
			$this->output(sprintf(Store::_('Found %s dirty cache tables:')."\n",
				$total), self::VERBOSITY_MESSAGES);

			foreach ($tables as $row) {
				$this->output(sprintf('=> '.Store::_('updating %s ... '),
					$row->shortname), self::VERBOSITY_MESSAGES);

				$update_function =
					$this->getCacheTableUpdateFunction($row->shortname);

				$sql = sprintf('select * from %s()', $update_function);
				SwatDB::exec($this->db, $sql);

				$this->output(Store::_('done')."\n", self::VERBOSITY_MESSAGES);
			}

			$this->output(Store::_('Done.')."\n", self::VERBOSITY_MESSAGES);
		} else {
			$this->output(Store::_('No dirty cache tables found.')."\n",
				self::VERBOSITY_MESSAGES);
		}
	}

	// }}}
	// {{{ protected function getCacheTableUpdateFunction()

	/**
	 * Gets the SQL function to run for a dirty cache table entry
	 *
	 * If the given table name does not correspond to a known cache table, this
	 * method displays an error message and terminates the application.
	 *
	 * @param string $table_name the name of the dirty cache table.
	 *
	 * @return string the SQL function to update (clean) the dirty cache table.
	 */
	protected function getCacheTableUpdateFunction($table_name)
	{
		switch ($table_name) {
		case 'CategoryVisibleProductCountByRegion':
			$update_function = 'updateCategoryVisibleProductCountByRegion';
			break;

		case 'VisibleProduct':
			$update_function = 'updateVisibleProduct';
			break;

		default:
			$this->terminate(sprintf(
				Store::_('Unknown dirty cache table %s.')."\n",
				$table_name), self::VERBOSITY_ERRORS);

			break;

		}

		return $update_function;
	}

	// }}}
	// {{{ protected function getDefaultModuleList()

	protected function getDefaultModuleList()
	{
		return array(
			'config'   => 'SiteConfigModule',
			'database' => 'SiteDatabaseModule',
		);
	}

	// }}}
	// {{{ protected function addConfigDefinitions()

	/**
	 * Adds configuration definitions to the config module of this application
	 *
	 * @param SiteConfigModule $config the config module of this application to
	 *                                  witch to add the config definitions.
	 */
	protected function addConfigDefinitions(SiteConfigModule $config)
	{
		parent::addConfigDefinitions($config);
		$config->addDefinitions(Store::getConfigDefinitions());
	}

	// }}}
}

?>
