<?php

require_once('Site/SitePage.php');

/**
 * @package   Store
 * @copyright 2005 silverorange
 */
abstract class StorePage extends SitePage
{
	public $found = false;

	protected $source = null;

	public function setSource($source)
	{
		$this->source = $source;
	}

	public function getSource()
	{
		return $this->source;
	}

	public function init()
	{
		$this->initInternal();
	}

	public function process()
	{
		$this->processInternal();
	}

	public function build()
	{
		$this->buildInternal();
	}

	protected function initInternal()
	{
	}

	protected function processInternal()
	{
	}

	protected function buildInternal()
	{
	}
}

?>
