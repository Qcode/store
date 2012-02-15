<?php

require_once 'Site/admin/components/Account/Index.php';

/**
 * Index page for Accounts
 *
 * @package   Store
 * @copyright 2006-2012 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreAccountIndex extends SiteAccountIndex
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/admin/components/Account/index.xml';

	/**
	 * @var string
	 */
	protected $search_xml = 'Store/admin/components/Account/search.xml';

	// }}}

	// build phase
	// {{{ protected function getSQL()

	protected function getSQL()
	{
		return 'select Account.id, Account.fullname,
				Account.email, Account.instance,
				coalesce(AccountOrderCountView.order_count, 0) as order_count
			from Account
			left outer join AccountOrderCountView on
				Account.id = AccountOrderCountView.account
			%s
			where %s
			order by %s';
	}

	// }}}
	// {{{ protected function getDetailsStore()

	protected function getDetailsStore(SiteAccount $account, $row)
	{
		$ds = parent::getDetailsStore($account, $row);
		$ds->order_count = $row->order_count;
		return $ds;
	}

	// }}}
}

?>
