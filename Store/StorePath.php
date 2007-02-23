<?php

require_once 'Store/StorePathEntry.php';

/**
 * An ordered set of {@link StorePathEntry} objects representing a
 * path in a {@link StoreApplication}
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class StorePath implements Iterator, Countable
{
	// {{{ private properties

	/**
	 * The entries in this path
	 *
	 * This is an array of {@link StorePathEntry} objects
	 *
	 * @var array
	 *
	 * @see StorePath::addEntry()
	 */
	private $path_entries = array();

	/**
	 * The current index of the iterator interface
	 *
	 * @var integer
	 */
	private $current_index = 0;
	
	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new path object
	 *
	 * @param StoreApplication $app the application this path exists in.
	 * @param integer $id the database id of the object to create the path for.
	 *                     If no database id is specified, an empty path is
	 *                     created.
	 */
	public function __construct(StoreApplication $app, $id = null)
	{
		if ($id !== null)
			$this->loadFromId($app, $id);
	}

	// }}}
	// {{{ public function addEntry()

	/**
	 * Adds an entry to this path
	 *
	 * Entries are always added to the beginning of the path. This way path
	 * strings can be parsed left-to-right and entries can be added in the
	 * same order.
	 *
	 * @param StorePathEntry $entry the entry to add.
	 */
	public final function addEntry(StorePathEntry $entry)
	{
		array_unshift($this->path_entries, $entry);
	}

	// }}}
	// {{{ public function hasId()

	/**
	 * Whether or not this path contains the given id
	 *
	 * @return boolean true if this path contains an entry with the
	 *                  given id and false if this path does not contain
	 *                  such an entry.
	 */
	public function hasId($id)
	{
		$found = false;

		foreach ($this as $entry) {
			if ($entry->id == $id) {
				$found = true;
				break;
			}
		}

		return $found;
	}

	// }}}
	// {{{ public function getFirst()

	/**
	 * Retrieves the first entry in this path
	 *
	 * @return StorePathEntry the first entry in this path or null if there is
	 *                         no first entry (empty path).
	 */
	public function getFirst()
	{
		if (isset($this->path_entries[0]))
			return $this->path_entries[0];

		return null;
	}

	// }}}
	// {{{ public function getLast()

	/**
	 * Retrieves the last entry in this path
	 *
	 * @return StorePathEntry the last entry in this path or null if there is
	 *                         no last entry (empty path).
	 */
	public function getLast()
	{
		if (count($this) > 0)
			return $this->path_entries[count($this) - 1];

		return null;
	}

	// }}}
	// {{{ public function __toString()

	/**
	 * Gets a string representation of this path
	 *
	 * The string is built from the shortnames of entries within this path.
	 * Each shortname is separated by a '/' character.
	 *
	 * @return string the string representation of this path.
	 */
	public function __toString()
	{
		$path = '';
		$first = true;

		foreach ($this as $entry) {
			if ($first)
				$first = false;
			else
				$path.= '/'

			$path.= $entry->shortname;
		}

		return $path;
	}

	// }}}
	// {{{ public function current()

	/**
	 * Returns the current element
	 *
	 * @return mixed the current element.
	 */
	public function current()
	{
		return $this->path_entries[$this->current_index];
	}

	// }}}
	// {{{ public function key()

	/**
	 * Returns the key of the current element
	 *
	 * @return integer the key of the current element
	 */
	public function key()
	{
		return $this->current_index;
	}

	// }}}
	// {{{ public function next()

	/**
	 * Moves forward to the next element
	 */
	public function next()
	{
		$this->current_index++;
	}

	// }}}
	// {{{ public function rewind()

	/**
	 * Rewinds this iterator to the first element
	 */
	public function rewind()
	{
		$this->current_index = 0;
	}

	// }}}
	// {{{ public function valid()

	/**
	 * Checks is there is a current element after calls to rewind() and next()
	 *
	 * @return boolean true if there is a current element and false if there
	 *                  is not.
	 */
	public function valid()
	{
		return isset($this->path_entries[$this->current_index]);
	}

	// }}}
	// {{{ public function get()

	/**
	 * Retrieves the an object
	 *
	 * @return mixed the object or null if it does not exist
	 */
	public function get($key = 0)
	{
		if (isset($this->path_entries[$key]))
			return $this->path_entries[$key];

		return null;
	}

	// }}}
	// {{{ public function count()

	/**
	 * Gets the number of entries in this path
	 *
	 * Satisfies the countable interface.
	 *
	 * @return integer the number of entries in this path
	 */
	public function count()
	{
		return count($this->path_entries);
	}

	// }}}
	// {{{ protected abstract function loadFromId()

	/**
	 * Loads this path from a database id
	 *
	 * Sub-classes are required to implement some sort of loader here. For
	 * example, if a sub-class accepts the id of an article, this loader should
	 * load the path for the article with the given id.
	 *
	 * @param StoreApplication $app the application this path exists in.
	 * @param integer $id the database id to load this path from.
	 */
	protected abstract function loadFromId(StoreApplication $app, $id);

	// }}}
}

?>
