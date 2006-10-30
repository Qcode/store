<?php

require_once 'Swat/SwatHtmlTag.php';
require_once 'Swat/SwatToolLink.php';
require_once 'Store/pages/StoreStorePage.php';
require_once 'Store/dataobjects/StoreProduct.php';

/**
 * @package   Store
 * @copyright 2006 silverorange
 */
class StoreProductImagePage extends StoreStorePage
{
	// {{{ public properties

	public $product_id;
	public $image_id;

	// }}}
	// {{{ protected properties

	public $product;
	public $image;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, SiteLayout $layout)
	{
		parent::__construct($app, $layout);
		$this->back_link = new SwatToolLink();
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$class_map = StoreClassMap::instance();
		$product_class = $class_map->resolveClass('StoreProduct');
		$this->product = new $product_class();
		$this->product->setDatabase($this->app->db);
		$this->product->setRegion($this->app->getRegion()->id);
		$this->product->load($this->product_id);
		$this->buildNavBar();
		$this->layout->data->title = sprintf(Store::_('%s: Image'),
			$this->product->title);

		$this->layout->addHtmlHeadEntrySet(
			$this->back_link->getHtmlHeadEntrySet());

		if ($this->image_id === null)
			$this->image = $this->product->primary_image;
		else
			$this->image = $this->product->images->getByIndex($this->image_id);

		if ($this->image === null)
			throw new SiteNotFoundException();

		$this->layout->startCapture('content');

		echo '<div id="product_images">';

		$this->displayImage();

		if (count($this->product->images) > 1)
			$this->displayOtherImages();

		echo '</div>';

		$this->layout->endCapture();
	}

	// }}}

	// {{{ private function buildNavBar()

	private function buildNavBar()
	{
		$link = 'store';

		foreach ($this->path as $path_entry) {
			$link .= '/'.$path_entry->shortname;
			$this->layout->navbar->createEntry($path_entry->title, $link);
		}

		$link .= '/'.$this->product->shortname;
		$this->layout->navbar->createEntry($this->product->title, $link);
		$this->layout->navbar->createEntry(Store::_('Image'));
	}

	// }}}
	// {{{ private function displayImage()

	private function displayImage()
	{
		$this->back_link->title = Store::_('Back to Product Page');
		$this->back_link->link =
			$this->layout->navbar->getEntryByPosition(-1)->link;

		$this->back_link->display();

		$div = new SwatHtmlTag('div');
		$div->id = 'product_image_large';

		$img_tag = new SwatHtmlTag('img');
		$img_tag->src = $this->image->getURI('large');
		$img_tag->width = $this->image->large_width;
		$img_tag->height = $this->image->large_height;
		$img_tag->alt = sprintf(Store::_('Photo of %s'), $this->product->title);

		$div->open();
		$img_tag->display();
		$div->close();
	}

	// }}}
	// {{{ protected function displayOtherImages()

	protected function displayOtherImages()
	{
		$li_tag = new SwatHtmlTag('li');
		$img_tag = new SwatHtmlTag('img');
		$img_tag->alt = sprintf(Store::_('Additional Photo of %s'), $this->product->title);

		echo '<ul id="product_secondary_images">';

		foreach ($this->product->images as $image) {
			if ($this->image->id === $image->id)
				continue;

			$img_tag->src = $image->getURI('thumb');
			$img_tag->width = $image->thumb_width;
			$img_tag->height = $image->thumb_height;

			$anchor = new SwatHtmlTag('a');
			$anchor->href = sprintf('%s/image%s', $this->source, $image->id);
			$anchor->title = Store::_('View Larger Image');

			$li_tag->open();
			$anchor->open();
			$img_tag->display();
			echo Store::_('<span>View Larger Image</span>');
			$anchor->close();
			$li_tag->close();
		}

		echo '</ul>';
	}

	// }}}
}

?>
