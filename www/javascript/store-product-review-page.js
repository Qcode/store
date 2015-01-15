/**
 * Handles loading more product reviews
 *
 * @param Number product_id
 * @param Number offset
 * @param String container_id
 *
 * @copyright 2008-2015 silverorange
 */
function StoreProductReviewPage(product_id, offset, container_id,
	disclosure_id, message, show_all)
{
	this.product_id    = product_id;
	this.offset        = offset;
	this.container_id  = container_id;
	this.disclosure_id = disclosure_id;
	this.message       = message;
	this.show_all      = show_all;
	this.loaded        = false;

	if (this.show_all) {
		YAHOO.util.Event.onDOMReady(this.initReviews, this, true);
	}
}

StoreProductReviewPage.prototype.initReviews = function()
{
	this.review_disclosure = document.getElementById(this.disclosure_id);

	// create show-all link
	this.show_all_link = document.createElement('a');
	this.show_all_link.className = 'store-product-review-all';
	this.show_all_link.href = '#';
	this.show_all_link.appendChild(document.createTextNode(this.message));

	// create show-all span
	this.show_all_span = document.createElement('span');
	this.show_all_span.className = 'store-product-review-all ' +
		'store-product-review-all-insensitive';

	this.show_all_span.appendChild(document.createTextNode(this.message));

	// add link to disclosure header
	this.review_disclosure.insertBefore(this.show_all_link,
		this.review_disclosure.firstChild);

	// set up event handler
	YAHOO.util.Event.on(this.show_all_link, 'click', function(e)
	{
		YAHOO.util.Event.preventDefault(e);
		this.loadAllReviews();
	}, this, true);
};

StoreProductReviewPage.prototype.loadAllReviews = function()
{
	if (!this.show_all || this.loaded)
		return;

	// insensitize show-all link and add loading class
	this.review_disclosure.replaceChild(this.show_all_span,
		this.show_all_link);

	YAHOO.util.Dom.addClass(this.show_all_span,
		'store-product-review-all-loading');

	var that = this;
	function callBack(response)
	{
		var reviews_container = document.getElementById(that.container_id);

		if (!reviews_container)
			return;

		var counter = 0;

		// The following code adds each review to the DOM after a timeout. This
		// creates a nice scrolling effect as reviews are loaded.
		var addReview = function()
		{
			// We add a div to the DOM here, as appending innerHTML will
			// recreate other elements defined in the innerHTML. Recreating
			// these elements would break JavaScript that refers to these
			// elements.
			var div = document.createElement('div');
			div.innerHTML += response[counter].content;
			reviews_container.appendChild(div);

			// when the review is avaialable, run its JavaScript
			YAHOO.util.Event.onAvailable(response[counter].id, function()
			{
				eval(this.javascript);
			}, response[counter], true);

			counter++;
			if (counter < response.length) {
				setTimeout(addReview, 10);
			}
		};

		// Add the first review, this in turn sets the timeout for the second
		// review to be added.
		addReview();

		that.loaded = true;

		// remove loading style from show-all span
		YAHOO.util.Dom.removeClass(that.show_all_span,
			'store-product-review-all-loading');
	}

	// Make remote call to get more reviews for the product at the specified
	// offset.
	var client = new XML_RPC_Client('xml-rpc/product-reviews');
	client.callProcedure('getReviews', callBack,
		[this.product_id, 0, this.offset],
		['int', 'int', 'int']);
};
