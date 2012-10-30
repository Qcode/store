<?php

require_once 'Site/SiteCommandLineApplication.php';
require_once 'Site/SiteCommandLineArgument.php';
require_once 'Site/SiteConfigModule.php';
require_once 'Site/SiteDatabaseModule.php';
require_once 'Store/Store.php';
require_once 'Store/dataobjects/StoreOrderWrapper.php';

/**
 * Sends out a digest email of new order comments
 *
 * @package   Store
 * @copyright 2012 silverorange
 */
class StoreOrderCommentDigestMailer extends SiteCommandLineApplication
{
	// {{{ public properties

	/**
	 * A convenience reference to the database object
	 *
	 * @var MDB2_Driver
	 */
	public $db;

	// }}}
	// {{{ public function run()

	public function run()
	{
		$this->debug("Exporting recent order comments\n\n", true);

		if ($this->config->email->order_comments_digest_list === null) {
			throw new SiteCommandLineException(
				'Config setting email.order_comments_digest_list must be set'
			);
		}

		$this->initModules();
		$this->parseCommandLineArguments();

		$this->lock();

		$orders = $this->getPendingOrderComments();

		$this->debug(
			sprintf(
				"%s pending orders\n",
				count($orders)
			)
		);


		if (count($orders) > 0) {
			$message = $this->getMailMessage();
			$message->html_body = $this->getHtmlContent($orders);

			$transaction = new SwatDBTransaction($this->db);

			try {
				$this->debug('sending email ... ');
				$message->send();
				$this->updateOrdersCommentStatus($orders);
				$transaction->commit();
			} catch (SwatDBException $e) {
				$e = new SiteCommandLineException($e);
				$e->processAndContinue();

				$this->debug('error updating orders ... ');
				$transaction->rollback();
			} catch (SiteMailException $e) {
				$e = new SiteCommandLineException($e);
				$e->processAndContinue();

				$this->debug('error sending email ... ');
				$transaction->rollback();
			}
		}

		$this->unlock();
		$this->debug("done\n", true);
	}

	// }}}
	// {{{ protected function getMailMessage()

	protected function getMailMessage()
	{
		$message = new SiteMultipartMailMessage($this);

		$message->smtp_server  = $this->config->email->smtp_server;
		$message->from_address = $this->config->email->website_address;
		$message->from_name    = $this->config->site->title;
		$message->to_address   = $this->getToAddress();
		$message->cc_list      = $this->getCcList();
		$message->subject      = sprintf(
			Store::_('Order Comments Digest: %s'),
			$this->config->site->title
		);

		return $message;
	}

	// }}}
	// {{{ protected function getToAddress()

	protected function getToAddress()
	{
		// always return the first address in the list as the to address
		$to_address = array_shift(
			explode(
				';',
				$this->config->email->order_comments_digest_list
			)
		);

		return $to_address;
	}

	// }}}
	// {{{ protected function getCcList()

	protected function getCcList()
	{
		// return everything but the first address as the cc list.
		$list = array_slice(
			explode(
				';',
				$this->config->email->order_comments_digest_list
			),
			1
		);

		return $list;
	}

	// }}}
	// {{{ protected function getHtmlContent()

	protected function getHtmlContent(StoreOrderWrapper $orders)
	{
		ob_start();

		foreach ($orders as $order) {
			if ($order->comments !== null) {
				$date = clone $order->createdate;
				$date->convertTZ($this->default_time_zone);

				$p_tag = new SwatHtmlTag('p');
				$p_tag->setContent(
					sprintf(
						'<p><a href="%1$sadmin/Order/Details?id=%2$s">'.
						'Order %2$s</a><br />%3$s (%4$s)<br />%5$s</p>',
						$this->config->uri->absolute_base,
						$order->id,
						SwatString::minimizeEntities(
							$order->account->getFullname()
						),
						SwatString::minimizeEntities(
							$order->account->email
						),
						$date->format(
							SwatDate::DF_DATE_TIME
						)
					),
					'text/xml'
				);

				$p_tag->display();

				$p_tag->style = 'padding-bottom: 10px;';
				$p_tag->setContent($order->comments);
				$p_tag->display();
			}
		}

		return ob_get_clean();
	}

	// }}}
	// {{{ protected function getPendingOrderComments()

	protected function getPendingOrderComments()
	{
		$sql = 'select Orders.*
			from Orders
			where Orders.comments_sent = %s
			order by createdate';

		$sql = sprintf(
			$sql,
			$this->db->quote(false, 'boolean')
		);

		$orders = SwatDB::query(
			$this->db,
			$sql,
			SwatDBClassMap::get('StoreOrderWrapper')
		);

		return $orders;
	}

	// }}}
	// {{{ protected function updateOrdersCommentStatus()

	protected function updateOrdersCommentStatus(StoreOrderWrapper $orders)
	{
		foreach ($orders as $order) {
			$order->comments_sent = true;
			$order->save();
		}
	}

	// }}}

	// boilerplate
	// {{{ protected function getDefaultModuleList()

	/**
	 * Gets the list of modules to load for this search indexer
	 *
	 * @return array the list of modules to load for this application.
	 *
	 * @see SiteApplication::getDefaultModuleList()
	 */
	protected function getDefaultModuleList()
	{
		return array(
			'config'   => 'SiteConfigModule',
			'database' => 'SiteDatabaseModule',
		);
	}

	// }}}
	// {{{ protected function addConfigDefinitions()

	/**
	 * Adds configuration definitions to the config module of this application
	 *
	 * @param SiteConfigModule $config the config module of this application to
	 *                                  witch to add the config definitions.
	 */
	protected function addConfigDefinitions(SiteConfigModule $config)
	{
		parent::addConfigDefinitions($config);
		$config->addDefinitions(Store::getConfigDefinitions());
	}

	// }}}
	// {{{ protected function configure()

	protected function configure(SiteConfigModule $config)
	{
		parent::configure($config);
		$this->database->dsn = $config->database->dsn;
	}

	// }}}
}

?>
