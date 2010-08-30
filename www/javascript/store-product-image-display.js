// {{{ StoreProductImageDisplay()

var StoreProductImageDisplay = function(data, config)
{

	this.semaphore     = false;
	this.data          = data;
	this.opened        = false;
	this.current_image = 0;

	this.configure(config);

	this.dimensions = { container: {} };

	// preload images and create id-to-index lookup table, needs to be added
	// to an instance variable so the images don't get garbage-collected by
	// some browsers.
	var image;
	this.images = [];
	this.image_indexes_by_id = {};
	for (var i = 0; i < this.data.images.length; i++ ) {
		// preload images
		image = new Image();
		image.src = this.data.images[i].large_uri
		this.images.push(image);

		// build id-to-index table
		this.image_indexes_by_id[this.data.images[i].id] = i;
	}

	// custom events allow us to track effectivness of UI design in 3rd party
	// analytics software.
	this.onOpen        = new YAHOO.util.CustomEvent('open');
	this.onClose       = new YAHOO.util.CustomEvent('close');
	this.onSelectImage = new YAHOO.util.CustomEvent('selectImage');

	// IE6 is not supported for this cool feature
	if (!StoreProductImageDisplay.ie6) {
		YAHOO.util.Event.onDOMReady(this.init, this, true);
	}
};

// }}}

StoreProductImageDisplay.ie6 = false /*@cc_on || @_jscript_version < 5.7 @*/;

StoreProductImageDisplay.close_text = 'Close';

(function() {

	var Dom    = YAHOO.util.Dom;
	var Event  = YAHOO.util.Event;
	var Anim   = YAHOO.util.Anim;
	var Motion = YAHOO.util.Motion;
	var Easing = YAHOO.util.Easing;

	// {{{ configure()

	StoreProductImageDisplay.prototype.configure = function(config)
	{
		if (!config) {
			config = {};
		}

		this.config = {
			period: {
				open:          0.500, // in sec
				fadeOut:       0.250, // in sec
				fadeIn:        0.100, // in sec
				resize:        0.100, // in sec
				locationCheck: 0.200  // in sec
			},
			geometry: {
				top: 40               // in px
			}
		};

		var override = function(base_config, new_config) {
			for (var key in base_config) {
				if (typeof base_config[key] == 'Object' &&
					typeof new_config[key] == 'Object') {
					override(base_config[key], new_config[key]);
				} else if (typeof config[key] != 'undefined' &&
					typeof new_config[key] != 'Object') {
					base_config[key] = new_config[key];
				}
			}
		};

		override(this.config, config);
	};

	// }}}
	// {{{ init()

	StoreProductImageDisplay.prototype.init = function()
	{
		this.html = document.getElementsByTagName('html')[0];
		this.html_overflow = Dom.getStyle(this.html, 'overflowY');

		this.body = document.getElementsByTagName('body')[0];
		this.body_overflow = Dom.getStyle(this.body, 'overflowY');

		this.initLinks();
		this.drawOverlay();
		this.initContainerDimensions();
		this.initLocation();
	};

	// }}}
	// {{{ initLinks()

	StoreProductImageDisplay.prototype.initLinks = function()
	{
		this.image_link = document.getElementById('product_image_link');

		Event.on(this.image_link, 'click', function(e) {
			Event.preventDefault(e);
			this.selectImage(0);
			this.onSelectImage.fire(this.data.images[0].id,
				'mainProductImageLink');

			this.openWithAnimation();
			this.onOpen.fire(this.data.images[0].id, 'mainProductImageLink');
		}, this, true);

		var pinky_list = document.getElementById('product_secondary_images');
		if (pinky_list) {
			var that = this;
			var pinky_link;
			var pinky_items = Dom.getChildren(pinky_list);
			for (var i = 0; i < pinky_items.length; i++) {
				pinky_link = Dom.getFirstChildBy(pinky_items[i],
					function(n) { return (n.nodeName == 'A'); } );

				if (pinky_link) {
					(function() {
						var index = i + 1;
						Event.on(pinky_link, 'click', function(e) {
							Event.preventDefault(e);
							that.selectImage(index);
							that.onSelectImage.fire(
								that.data.images[index].id,
								'secondaryProductImageLink');

							that.openWithAnimation();
							that.onOpen.fire(
								that.data.images[index].id,
								'secondaryProductImageLink');

						}, that , true);
					}());
				}
			}
		}
	};

	// }}}

	// draw
	// {{{ drawOverlay()

	StoreProductImageDisplay.prototype.drawOverlay = function()
	{
		this.overlay = document.createElement('div');
		this.overlay.className = 'store-product-image-display-overlay';
		this.overlay.style.display = 'none';

		this.overlay.appendChild(this.drawOverlayMask());
		this.overlay.appendChild(this.drawContainer());
		this.overlay.appendChild(this.drawHeader());
//		this.overlay.appendChild(this.drawCloseLink());

		var pinkies = this.drawPinkies();
		if (pinkies) {
			this.overlay.appendChild(pinkies);
		}

		this.body.appendChild(this.overlay);
	};

	// }}}
	// {{{ drawOverlayMask()

	StoreProductImageDisplay.prototype.drawOverlayMask = function()
	{
		var mask = document.createElement('a');
		mask.href = '#close';
		mask.className = 'store-product-image-display-overlay-mask';

		Event.on(mask, 'click', function(e) {
			Event.preventDefault(e);
			this.close();
			this.onClose.fire('overlayMask');
		}, this, true);

		Event.on(mask, 'mouseover', function(e) {
			YAHOO.util.Dom.addClass(this.close_link,
				'store-product-image-display-close-hover');
		}, this, true);

		Event.on(mask, 'mouseout', function(e) {
			YAHOO.util.Dom.removeClass(this.close_link,
				'store-product-image-display-close-hover');
		}, this, true);

		SwatZIndexManager.raiseElement(mask);

		return mask;
	}

	// }}}
	// {{{ drawHeader()

	StoreProductImageDisplay.prototype.drawHeader = function()
	{
		var header = document.createElement('div');
		header.className = 'store-product-image-display-header';

		header.appendChild(this.drawLinks());
		header.appendChild(this.drawTitle());

		SwatZIndexManager.raiseElement(header);

		return header;
	};

	// }}}
	// {{{ drawLinks()

	StoreProductImageDisplay.prototype.drawLinks = function()
	{
		var links = document.createElement('div');
		links.className = 'store-product-image-display-links';
		links.appendChild(this.drawCloseLink());
		return links;
	};

	// }}}
	// {{{ drawCloseLink()

	StoreProductImageDisplay.prototype.drawCloseLink = function()
	{
		this.close_link = document.createElement('a');
		this.close_link.className = 'store-product-image-display-close';
		this.close_link.href = '#close';

		// uses a unicode narrow space character
		this.close_link.appendChild(document.createTextNode(
			'× ' + StoreProductImageDisplay.close_text));

		Event.on(this.close_link, 'click', function(e) {
			Event.preventDefault(e);
			this.close();
			this.onClose.fire('closeLink');
		}, this, true);

		SwatZIndexManager.raiseElement(this.close_link);

		return this.close_link;
	};

	// }}}
	// {{{ drawTitle()

	StoreProductImageDisplay.prototype.drawTitle = function()
	{
		this.title = document.createElement('div');
		this.title.className = 'store-product-image-display-title';

//		SwatZIndexManager.raiseElement(this.title);

		return this.title;
	};

	// }}}
	// {{{ drawPinkies()

	StoreProductImageDisplay.prototype.drawPinkies = function()
	{
		this.pinky_list = null;

		this.pinkies = [];

		if (this.data.images.length > 1) {

			var pinky, image, link;
			this.pinky_list = document.createElement('ul');
			this.pinky_list.className = 'store-product-image-display-pinkies';
			for (var i = 0; i < this.data.images.length; i++) {

				image = document.createElement('img');
				image.src = this.data.images[i].pinky_uri;
				image.width = this.data.images[i].pinky_width;
				image.height = this.data.images[i].pinky_height;

				link = document.createElement('a');
				link.href = '#image' + this.data.images[i].id;
				link.hideFocus = true; // for IE6/7
				link.appendChild(image);

				var that = this;
				(function() {
					var index = i;
					Event.on(link, 'click', function(e) {
						Event.preventDefault(e);
						that.selectImage(index);
						that.onSelectImage.fire(that.data.images[index].id,
							'pinkyImage');
					}, that, true);
				}());

				pinky = document.createElement('li');
				if (i == 0) {
					pinky.className = 'store-product-image-display-pinky-first';
				} else {
				}
				pinky.appendChild(link);

				this.pinkies.push(pinky);

				this.pinky_list.appendChild(pinky);
			}

			SwatZIndexManager.raiseElement(this.pinky_list);

		}

		return this.pinky_list;
	};

	// }}}
	// {{{ drawContainer()

	StoreProductImageDisplay.prototype.drawContainer = function()
	{
		this.container = document.createElement('div');
		this.container.style.display = 'none';
		this.container.className = 'store-product-image-display-container';

		var wrapper = document.createElement('div');
		wrapper.className = 'store-product-image-display-wrapper';

		wrapper.appendChild(this.drawImage());

		this.container.appendChild(wrapper);

		SwatZIndexManager.raiseElement(this.container);

		return this.container;
	};

	// }}}
	// {{{ drawImage()

	StoreProductImageDisplay.prototype.drawImage = function()
	{
		this.image = document.createElement('img');
		this.image.className = 'store-product-image-display-image';
		return this.image;
	};

	// }}}

	// dimensions
	// {{{ initContainerDimensions()

	StoreProductImageDisplay.prototype.initContainerDimensions = function()
	{
		var el = this.container.firstChild;

		this.dimensions.container = {
			paddingTop:    parseInt(Dom.getStyle(el, 'paddingTop')),
			paddingRight:  parseInt(Dom.getStyle(el, 'paddingRight')),
			paddingBottom: parseInt(Dom.getStyle(el, 'paddingBottom')),
			paddingLeft:   parseInt(Dom.getStyle(el, 'paddingLeft'))
		};
	};

	// }}}

	// image selection
	// {{{ selectImage()

	StoreProductImageDisplay.prototype.selectImage = function(index)
	{
		if (!this.data.images[index] ||
			(this.opened && this.current_image == index)) {
			return false;
		}

		var data = this.data.images[index];

		var w = data.large_width +
			this.dimensions.container.paddingLeft +
			this.dimensions.container.paddingRight;

		this.container.style.display = 'block';
		this.container.style.marginLeft = -Math.floor(w / 2) + 'px';
		this.container.style.top = this.config.geometry.top + 'px';

		this.image.src = data.large_uri;
		this.image.width = data.large_width;
		this.image.height = data.large_height;

		// required for IE6
		this.image.parentNode.style.width = data.large_width + 'px';
		this.image.parentNode.style.height = data.large_height + 'px';

		this.setTitle(data, this.data.product);

		Dom.removeClass(
			this.pinkies[this.current_image],
			'store-product-image-display-pinky-selected');

		Dom.addClass(
			this.pinkies[index],
			'store-product-image-display-pinky-selected');

		this.current_image = index;

		// Set page scroll height so we can't scroll the image out of view.
		// This doesn't work correctly in IE6 and 7 or in
		// Opera (Bug #CORE-22089); however it degrades nicely.
		var window_height = Math.max(
			// 10 extra px to  keep larger than viewport to keep scroll bars
			Dom.getViewportHeight() + 10,
			// 15 extra px to contain image paddings
			data.large_height + this.config.geometry.top + 15);

		this.html.style.overflowY = 'auto';
		this.html.style.height    = window_height + 'px';
		this.body.style.overflowY = 'hidden';
		this.body.style.height    = window_height + 'px';
		this.overlay.style.height = window_height + 'px';

		// Set final overlay height to account for any margins and padding on
		// the body or html elements.
		this.overlay.style.height = Dom.getDocumentHeight() + 'px';

		// set address bar to current image
		var baseLocation = location.href.split('#')[0];
		location.href = baseLocation + '#image' + data.id;

		return true;

	};

	// }}}
	// {{{ selectPreviousImage()

	StoreProductImageDisplay.prototype.selectPreviousImage = function()
	{
		var index = this.current_image - 1;

		if (index < 0) {
			index = this.data.images.length - 1;
		}

		this.selectImage(index);
	};

	// }}}
	// {{{ selectNextImage()

	StoreProductImageDisplay.prototype.selectNextImage = function()
	{
		var index = this.current_image + 1;

		if (index >= this.data.images.length) {
			index = 0;
		}

		this.selectImage(index);
	};

	// }}}
	// {{{ setTitle()

	StoreProductImageDisplay.prototype.setTitle = function(image, product)
	{
		if (image.title) {
			this.title.innerHTML = product.title + ' - ' + image.title;
		} else {
			this.title.innerHTML = product.title;
		}
	};

	// }}}

	// location
	// {{{ initLocation()

	StoreProductImageDisplay.prototype.initLocation = function()
	{
		var hash = location.hash;
		hash = (hash.substring(0, 1) == '#') ? hash.substring(1) : hash;
		var image_id = hash.replace(/[^0-9]/g, '');

		if (image_id) {
			image_id = parseInt(image_id);
			if (typeof this.image_indexes_by_id[image_id] != 'undefined') {
				this.selectImage(this.image_indexes_by_id[image_id]);
				this.onSelectImage.fire(image_id, 'location');
				this.open();
				this.onOpen.fire(image_id, 'location');
			}
		}

		// check if window location changes from back/forward button use
		// this doesn't matter in IE and Opera but is nice for Firefox and
		// recent Safari users.
		var that = this;
		setInterval(function() {
			that.checkLocation();
		}, this.config.period.locationCheck * 1000);
	};

	// }}}
	// {{{ checkLocation()

	StoreProductImageDisplay.prototype.checkLocation = function()
	{
		if (this.semaphore) {
			return;
		}

		var current_image_id = this.data.images[this.current_image].id;

		var hash = location.hash;
		hash = (hash.substring(0, 1) == '#') ? hash.substring(1) : hash;
		var image_id = hash.replace(/[^0-9]/g, '');

		if (image_id) {
			image_id = parseInt(image_id);
		}

		if (image_id && image_id != current_image_id) {
			if (typeof this.image_indexes_by_id[image_id] != 'undefined') {
				this.selectImage(this.image_indexes_by_id[image_id]);
				this.onSelectImage.fire(image_id, 'location');
			}
		}

		if (image_id == '' && current_image_id && this.opened) {
			this.close();
			this.onClose.fire('location');
		} else if (image_id && !this.opened) {
			this.open();
			this.onOpen.fire(current_image_id, 'location');
		}
	};

	// }}}

	// open/close
	// {{{ open()

	StoreProductImageDisplay.prototype.open = function()
	{
		if (this.opened) {
			return;
		}

		this.showOverlay();
		this.container.style.display = 'block';
		this.opened = true;
	};

	// }}}
	// {{{ openWithAnimation()

	StoreProductImageDisplay.prototype.openWithAnimation = function()
	{
		if (typeof this.container.style.filter === 'string') {
			// YUI opacity animations overrite all CSS filters so just skip them
			this.open();
			return;
		}

		if (this.semaphore || this.opened) {
			return;
		}

		this.semaphore = true;

		this.showOverlay();

		Dom.setStyle(this.container, 'opacity', '0');
		this.container.style.display = 'block';

		var anim = new Anim(this.container, {
			opacity: { from: 0, to: 1 }
		}, this.config.period.open, Easing.easeOut);

		anim.onComplete.subscribe(function() {
			this.semaphore = false;
		}, this, true);

		anim.animate();

		this.opened = true;
	};

	// }}}
	// {{{ close()

	StoreProductImageDisplay.prototype.close = function()
	{
		if (this.semaphore) {
			return;
		}

		this.hideOverlay();

		this.container.style.display = 'none';

		// remove image from address bar
		var baseLocation = location.href.split('#')[0];
		location.href = baseLocation + '#closed';

		// unset keydown handler
		Event.removeListener(document, 'keydown', this.handleKeyDown);

		// reset window scroll height
		this.html.style.overflowY = this.html_overflow;
		this.html.style.height    = 'auto';
		this.body.style.overflowY = this.body_overflow;
		this.body.style.height    = 'auto';

		this.opened = false;
	};

	// }}}
	// {{{ showOverlay()

	StoreProductImageDisplay.prototype.showOverlay = function()
	{
		// init keydown handler for escape key to close
		Event.on(document, 'keydown', this.handleKeyDown, this, true);

		this.overlay.style.height = Dom.getDocumentHeight() + 'px';
		this.overlay.style.visible = 'hidden';
		this.overlay.style.display = 'block';

		if (this.pinky_list) {
			this.pinky_list.style.top = this.config.geometry.top + 'px';
		}

		this.overlay.style.visible = 'visible';


	}

	// }}}
	// {{{ hideOverlay()

	StoreProductImageDisplay.prototype.hideOverlay = function()
	{
		this.overlay.style.display = 'none';
	};

	// }}}

	// keyboard
	// {{{ handleKeyDown()

	StoreProductImageDisplay.prototype.handleKeyDown = function(e)
	{
		// close preview on backspace or escape
		if (e.keyCode == 8 || e.keyCode == 27) {
			Event.preventDefault(e);
			this.close();
			this.onClose.fire('keyboard');
		} else if (this.data.images.length > 1 && this.opened) {
			if (e.keyCode == 37 || e.keyCode == 38) {
				Event.preventDefault(e);
				this.selectPreviousImage();
				this.onSelectImage.fire(
					this.data.images[this.current_image].id,
					'keyboard');
			} else if (e.keyCode == 39 || e.keyCode == 40) {
				Event.preventDefault(e);
				this.selectNextImage();
				this.onSelectImage.fire(
					this.data.images[this.current_image].id,
					'keyboard');
			}
		}
	};

	// }}}

}());
