function StoreCheckoutShippingAddressPage(id)
{
	this.container = document.getElementById('shipping_address_form');
	this.list = document.getElementsByName('shipping_address_list');
	this.list_new = document.getElementById('shipping_address_list_new');

	StoreCheckoutShippingAddressPage.superclass.constructor.call(this, id);
}

YAHOO.lang.extend(StoreCheckoutShippingAddressPage, StoreCheckoutAddressPage, {

getFieldNames: function()
{
	return [
		'shipping_address_fullname',
		'shipping_address_company',
		'shipping_address_line1',
		'shipping_address_line2',
		'shipping_address_city',
		'shipping_address_provstate_flydown',
		'shipping_address_provstate_entry',
		'shipping_address_postalcode',
		'shipping_address_phone',
		'shipping_address_country'
	];
}

});
