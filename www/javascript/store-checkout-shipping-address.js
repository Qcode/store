function StoreCheckoutShippingAddress(id)
{
	this.container = document.getElementById('shipping_address_container');
	this.list = document.getElementsByName('shipping_address_list');
	this.list_new = document.getElementById('shipping_address_list_new');

	this.provstate = document.getElementById('shipping_address_provstate');
	this.provstate_other_id = 'shipping_address_provstate_other';

	StoreCheckoutShippingAddress.superclass.constructor.call(this, id);
}

YAHOO.extend(StoreCheckoutShippingAddress, StoreCheckoutAddress);

StoreCheckoutShippingAddress.prototype.getFieldNames = function()
{
	return [
		'shipping_address_fullname',
		'shipping_address_line1',
		'shipping_address_line2',
		'shipping_address_city',
		'shipping_address_provstate',
		'shipping_address_postalcode',
		'shipping_address_country'];
}
