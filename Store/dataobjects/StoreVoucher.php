<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Swat/SwatString.php';
require_once 'Site/dataobjects/SiteInstance.php';

/**
 * @package   Store
 * @copyright 2007-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreVoucher extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * Redemption Code
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Type
	 *
	 * One of either:
	 * - 'gift-certificate',
	 * - 'coupon', or
	 * - 'merchandise-credit'
	 *
	 * @var string
	 */
	public $voucher_type;

	/**
	 * Amount
	 *
	 * @var float
	 */
	public $amount;

	/**
	 * Used date
	 *
	 * @var SwtaDate
	 */
	public $used_date;

	// }}}
	// {{{ public function loadFromCode()

	/**
	 * Loads this voucher from its code
	 *
	 * @param string $code the code for this voucher.
	 * @param SiteInstance $instance the site instance.
	 *
	 * @return boolean true if this voucher was loaded and false if it
	 *                  was not.
	 */
	public function loadFromCode($code, SiteInstance $instance)
	{
		$this->checkDB();

		$row = null;
		$loaded = false;

		if ($this->table !== null) {

			// strip prefix if there is one
			$prefix = strtolower($this->getCouponPrefix($instance));
			if (strtolower(substr($code, 0, strlen($prefix))) == $prefix) {
				$code = substr($code, strlen($prefix));
			}

			$sql = sprintf(
				'select * from Voucher
				where lower(Voucher.code) = lower(%s)
					and used_date %s %s and instance = %s',
				$this->db->quote($code, 'text'),
				SwatDB::equalityOperator(null),
				$this->db->quote(null, 'date'),
				$this->db->quote($instance->id, 'integer')
			);

			$rs = SwatDB::query($this->db, $sql, null);
			$row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
		}

		if ($row !== null) {
			$this->initFromRow($row);
			$this->generatePropertyHashes();
			$loaded = true;
		}

		return $loaded;
	}

	// }}}
	// {{{ public function getCouponPrefix()

	/**
	 * Gets the valid prefix for coupons
	 *
	 * @param SiteInstance $instance the site instance.
	 *
	 * @return string prefix for coupons.
	 */
	public function getCouponPrefix(SiteInstance $instance)
	{
		return null;
	}

	// }}}
	// {{{ public function getTitle()

	/**
	 * Gets a displayable title for the particular voucher type
	 *
	 * @param $show_amount boolean whether or not to show the amount of this
	 *                              voucher.
	 *
	 * @return string title for this voucher.
	 */
	public function getTitle($show_amount = false)
	{
		switch ($this->voucher_type) {
		case'gift-certificate':
			$type = Store::_('Gift Certificate');
			break;

		case 'merchandise-credit':
			$type =  Store::_('Merchandise Credit');
			break;

		case 'coupon':
			$type =  Store::_('Coupon');
			break;

		default :
			$type = Store::_('Voucher');
			break;
		}

		$title = sprintf(
			Store::_('%s #%s'),
			$type,
			$this->code
		);

		if ($show_amount) {
			$locale = SwatI18NLocale::get('en_US');
			$title.= ' ('.$locale->formatCurrency($this->amount).')';
		}

		return $title;
	}

	// }}}
	// {{{ public function getTitleWithAmount()

	/**
	 * Gets a displayable title for the particular voucher type including the
	 * amount of this voucher
	 *
	 * @return string title for this voucher including the amount of this
	 *                voucher.
	 */
	public function getTitleWithAmount()
	{
		return $this->getTitle(true);
	}

	// }}}
	// {{{ public function isUsed()

	public function isUsed()
	{
		return ($this->used_date instanceof SwatDate);
	}

	// }}}
	// {{{ public function copyFrom()

	public function copyFrom(StoreVoucher $voucher)
	{
		$this->code         = $voucher->code;
		$this->voucher_type = $voucher->voucher_type;
		$this->used_date    = $voucher->used_date;
		$this->instance     = $voucher->instance;
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->table = 'Voucher';
		$this->id_field = 'integer:id';

		$this->registerInternalProperty('instance',
			SwatDBClassMap::get('SiteInstance')
		);

		$this->registerDateProperty('used_date');
	}

	// }}}
}

?>
