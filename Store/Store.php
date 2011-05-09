<?php

require_once 'Swat/Swat.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Site/Site.php';

/**
 * Container for package wide static methods
 *
 * @package   Store
 * @copyright 2006-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Store
{
	// {{{ constants

	/**
	 * The package identifier
	 */
	const PACKAGE_ID = 'Store';

	const GETTEXT_DOMAIN = 'store';

	// }}}
	// {{{ public static function _()

	public static function _($message)
	{
		return Store::gettext($message);
	}

	// }}}
	// {{{ public static function gettext()

	public static function gettext($message)
	{
		return dgettext(Store::GETTEXT_DOMAIN, $message);
	}

	// }}}
	// {{{ public static function ngettext()

	public static function ngettext($singular_message,
		$plural_message, $number)
	{
		return dngettext(Store::GETTEXT_DOMAIN,
			$singular_message, $plural_message, $number);
	}

	// }}}
	// {{{ public static function setupGettext()

	public static function setupGettext()
	{
		bindtextdomain(Store::GETTEXT_DOMAIN, '@DATA-DIR@/Store/locale');
		bind_textdomain_codeset(Store::GETTEXT_DOMAIN, 'UTF-8');
	}

	// }}}
	// {{{ public static function getDependencies()

	/**
	 * Gets the packages this package depends on
	 *
	 * @return array an array of package IDs that this package depends on.
	 */
	public static function getDependencies()
	{
		return array(Swat::PACKAGE_ID, Site::PACKAGE_ID);
	}

	// }}}
	// {{{ public static function getConfigDefinitions()

	/**
	 * Gets configuration definitions used by the Store package
	 *
	 * Applications should add these definitions to their config module before
	 * loading the application configuration.
	 *
	 * @return array the configuration definitions used by the Store package.
	 *
	 * @see SiteConfigModule::addDefinitions()
	 */
	public static function getConfigDefinitions()
	{
		return array(
			'store.multiple_payment_support' => false,
			'store.multiple_payment_ui' => false,
			'store.path' => 'store/',

			// Expiry dates for the privateer data deleter
			'expiry.accounts'       => '3 years',
			'expiry.orders'         => '1 year',

			// Froogle
			'froogle.filename'      => null,
			'froogle.server'        => null,
			'froogle.username'      => null,
			'froogle.password'      => null,

			// Bing Shopping
			'bing.filename' => 'bingshopping.txt',
			'bing.server'   => null,
			'bing.username' => null,
			'bing.password' => null,

			// Optional Wordpress API key for Akismet spam filtering.
			'store.akismet_key'     => null,

			// Optional StrikeIron API keys for address verification.
			'strikeiron.verify_address_usa_key'    => null,
			'strikeiron.verify_address_canada_key' => null,

			// Optional Email address to send feedback too
			'email.feedback_address' => null,

			// mailchimp
			// Optional Plugin ID for reporting sales for mailchimp stats
			'mail_chimp.plugin_id'    => null,
			'mail_chimp.track_orders' => false,

			// AdWords
			'adwords.conversion_id'    => null,
			'adwords.conversion_label' => null,
			// client manager email address used for automating ad creation
			'adwords.email'            => null,
			// client ID used for automating ad creation
			'adwords.client_id'        => null,
			// developer token used for automating ad creation
			'adwords.developer_token'  => null,
		);
	}

	// }}}
	// {{{ private function __construct()

	/**
	 * Prevent instantiation of this static class
	 */
	private function __construct()
	{
	}

	// }}}
}

Store::setupGettext();
SwatUI::mapClassPrefixToPath('Store', 'Store');

SwatDBClassMap::addPath('Store/dataobjects');
SwatDBClassMap::add('SiteAccount',        'StoreAccount');
SwatDBClassMap::add('SiteContactMessage', 'StoreContactMessage');
SwatDBClassMap::add('SiteArticle',        'StoreArticle');
SwatDBClassMap::add('SiteArticleWrapper', 'StoreArticleWrapper');

if (class_exists('Blorg')) {
	require_once 'Site/SiteViewFactory.php';
	SiteViewFactory::addPath('Store/views');
	SiteViewFactory::registerView('post-search', 'StoreBlorgPostSearchView');
}

?>
