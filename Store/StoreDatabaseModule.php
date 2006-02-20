<?php

require_once 'Swat/SwatApplicationModule.php';
require_once 'SwatDB/exceptions/SwatDBException.php';
require_once 'MDB2.php';

/**
 * Web application module for database
 *
 * @package Store
 * @copyright silverorange 2006
 */
class StoreDatabaseModule extends SwatApplicationModule
{
    // {{{ public properties
	
	/**
	 * Name of the database
	 *
	 * This is the name of the database to connect to.  Set this before calling
	 * {@link AdminApplication::init()}, afterwords consider it readonly.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The database object
	 *
	 * @var MDB2_Connection Database connection object (readonly)
	 */
	public $mdb2 = null;

    // }}}
    // {{{ public function init()

	public function init()
	{
		// TODO: change to array /form of DSN and move parts to a secure include file.
		$dsn = "pgsql://php:test@zest/".$this->name;
		$this->mdb2 = MDB2::connect($dsn);
		$this->mdb2->options['debug'] = true;

		if (MDB2::isError($this->mdb2))
			throw new SwatDBException($this->mdb2);
	}

    // }}}
}

?>
