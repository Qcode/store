<?php

require_once 'Swat/SwatObject.php';

/**
 * Maps Store package class names to site-specific overridden class-names
 *
 * This class implements a singleton pattern. Get an instance of the class-
 * mapping object using the {@link instance()} method.
 *
 * This class also tries to require site-specific class files for mapped
 * class names. You can modify the behaviour of the automatic require using the
 * {@link setDataObjectPath()} method.
 *
 * @package   Store
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreClassMap extends SwatObject
{
	// {{{ private properties

	/**
	 * Singleton instance
	 *
	 * @var StoreClassMap
	 */
	private static $instance = null;

	/**
	 * An associative array of class-mappings
	 *
	 * The array is of the form 'StoreClass' => 'SiteSpecificOverrideClass'.
	 *
	 * @var array
	 */
	private $map = array();

	/**
	 * The path to search for site-specific class files
	 *
	 * @var string
	 */
	private $path = null;

	// }}}
	// {{{ public static function instance()

	/**
	 * Gets the singleton instance of the class-mapping object
	 *
	 * @return StoreClassMap the singleton instance of the class-
	 *                                  mapping object.
	 */
	public static function instance()
	{
		if (self::$instance === null)
			self::$instance = new self();

		return self::$instance;
	}

	// }}}
	// {{{ public function addMapping()

	/**
	 * Adds a class-mapping to the class-mapping object
	 *
	 * @param string $store_class_name the name of the Store package data-
	 *                                  object to override.
	 * @param string $class_nam the name of the site-specific class.
	 */
	public function addMapping($store_class_name, $class_name)
	{
		$this->map[$store_class_name] = $class_name;
	}

	// }}}
	// {{{ public function resolveClass()

	/**
	 * Gets the appropriate class name for a given Store package class name
	 *
	 * @param string $name the name of the Store package class to get the
	 *                      mapped name of.
	 *
	 * @return string the appropriate class name for site-specific code. If
	 *                 the site-specific code has overridden a Store package
	 *                 data-object class, the site-specific overridden value is
	 *                 returned. Otherwise, the Store package default class
	 *                 name is returned.
	 */
	public function resolveClass($name)
	{
		$class_name = $name;

		if (array_key_exists($name, $this->map)) {
			$class_name = $this->map[$name];

			if ($this->path !== null) {
				$class_file = sprintf('%s/%s.php', $this->path, $class_name);
				require_once $class_file;
			}
		}

		return $class_name;
	}

	// }}}
	// {{{ public function setDataObjectPath()

	/**
	 * Sets the path to search for site-specific class files
	 *
	 * @param string $path the path to search for site-specific class files.
	 */
	public function setDataObjectPath($path)
	{
		$this->path = $path;
	}

	// }}}
	// {{{ private function __construct()

	/**
	 * Creates a Store data-object class-mapping object
	 *
	 * The constructor is private as this class uses the singleton pattern.
	 */
	private function __construct()
	{
	}

	// }}}
}

?>
