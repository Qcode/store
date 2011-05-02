function StoreCheckoutBillingAddressPage(id, provstate_other_index)
{
	this.container = document.getElementById('billing_address_form');
	this.list = document.getElementsByName('billing_address_list');
	this.list_new = document.getElementById('billing_address_list_new');

	this.provstate = document.getElementById('billing_address_provstate');
	this.provstate_other_id = 'billing_address_provstate_other';
	this.provstate_other = document.getElementById(this.provstate_other_id);

	StoreCheckoutBillingAddressPage.superclass.constructor.call(this, id,
		provstate_other_index);
}

YAHOO.lang.extend(StoreCheckoutBillingAddressPage, StoreCheckoutAddressPage, {

getFieldNames: function()
{
	return [
		'billing_address_fullname',
		'billing_address_phone',
		'billing_address_company',
		'billing_address_line1',
		'billing_address_line2',
		'billing_address_city',
		'billing_address_provstate',
		'billing_address_postalcode',
		'billing_address_country'
	];
}

});
