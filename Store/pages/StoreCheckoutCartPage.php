<?php

require_once 'Store/pages/StoreCheckoutUIPage.php';
require_once 'Swat/SwatString.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';

/**
 * Cart edit page of checkout
 *
 * @package   Store
 * @copyright 2006 silverorange
 */
class StoreCheckoutCartPage extends StoreCheckoutUIPage
{
	// {{{ protected properties

	protected $updated_entry_ids = array();

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, SiteLayout $layout)
	{
		parent::__construct($app, $layout);
		$this->ui_xml = 'Store/pages/checkout-cart.xml';
	}

	// }}}

	// init phase
	// {{{ protected function getProgressDependencies()

	protected function getProgressDependencies()
	{
		return array('checkout/first');
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		parent::process();

		$form = $this->ui->getWidget('form');
		$form->process();

		if ($form->isProcessed()) {
			if ($form->hasMessage()) {
				$message = new SwatMessage(Store::_('There is a problem with '.
					'the information submitted.'), SwatMessage::ERROR);

				$message->secondary_content = Store::_('Please address the '.
					'fields highlighted below and re-submit the form.');

				$this->ui->getWidget('message_display')->add($message);
			} else {
				$this->processEntries();

				$continue_button_ids =
					array('header_continue_button', 'footer_continue_button');

				$continue_button_clicked = false;
				foreach ($continue_button_ids as $id) {
					$button = $this->ui->getWidget($id);
					if ($button->hasBeenClicked()) {
						$continue_button_clicked = true;
						break;
					}
				}

				if (!$form->hasMessage() && $continue_button_clicked) {
					$this->app->cart->save();
					$this->app->relocate('checkout/confirmation');
				}
			}
		}
	}

	// }}}
	// {{{ protected function getMoveRenderer()

	protected function getMoveRenderer()
	{
		$view = $this->ui->getWidget('cart_view');
		$column = $view->getColumn('move_column');
		return $column->getRendererByPosition(); 
	}

	// }}}
	// {{{ protected function getRemoveRenderer()

	protected function getRemoveRenderer()
	{
		$view = $this->ui->getWidget('cart_view');
		$column = $view->getColumn('remove_column');
		return $column->getRendererByPosition(); 
	}

	// }}}
	// {{{ protected function getQuantityRenderer()

	protected function getQuantityRenderer()
	{
		$view = $this->ui->getWidget('cart_view');
		$column = $view->getColumn('quantity_column');
		return $column->getRendererByPosition(); 
	}

	// }}}
	// {{{ protected function processEntries()

	protected function processEntries()
	{
		$message_display = $this->ui->getWidget('message_display');

		$num_moved_entries   = 0;
		$num_removed_entries = 0;
		$num_updated_entries = 0;

		$num_removed_entries += $this->processRemovedEntries();

		if ($num_removed_entries == 0)
			$num_moved_entries += $this->processMovedEntries();

		if ($num_removed_entries == 0 && $num_moved_entries == 0) {
			$result = $this->processUpdatedEntries();
			$num_removed_entries += $reuslt['num_removed_entries'];
			$num_updated_entries += $reuslt['num_updated_entries'];
		}

		if ($num_entries_removed > 0) {
			$message_display->add(new SwatMessage(sprintf(Store::ngettext(
				'One item has been removed from shopping cart.',
				'%s items have been removed form shopping cart.',
				$num_entries_removed),
				SwatString::numberFormat($num_entries_removed)),
				StoreMessage::CART_NOTIFICATION));
		}

		if ($num_entries_moved > 0) {
			$message_display = $this->ui->getWidget('message_display');
			$message_display->add(new SwatMessage(
				Store::_('One item has been saved for later.'),
				StoreMessage::CART_NOTIFICATION));
		}

		if ($num_entries_updated > 0) {
			$message_display->add(new SwatMessage(sprintf(Store::ngettext(
				'One item quantity has been updated.',
				'%s item quantities have been updated.',
				$num_entries_updated),
				SwatString::numberFormat($num_entries_removed)),
				StoreMessage::CART_NOTIFICATION));
		}

		foreach ($this->app->cart->checkout->getMessages() as $message)
			$message_display->add($message);
	}

	// }}}
	// {{{ protected function processRemovedEntries()

	protected function processRemovedEntries()
	{
		$message_display = $this->ui->getWidget('message_display');
		$remove_renderer = $this->getRemoveRenderer();

		$num_entries_removed = 0;

		foreach ($remove_renderer->getClonedWidgets() as $id => $widget) {
			if ($widget->hasBeenClicked()) {
				$num_entries_removed++;
				$this->app->cart->checkout->removeEntryById($id);
				break;
			}
		}

		return $num_entries_removed;
	}

	// }}}
	// {{{ protected function processMovedEntries()

	protected function processMovedEntries()
	{
		$message_display = $this->ui->getWidget('message_display');
		$quantity_renderer = $this->getQuantityRenderer();
		$move_renderer = $this->getMoveRenderer();

		$num_entries_moved = 0;

		foreach ($move_renderer->getClonedWidgets() as $id => $widget) {
			if ($widget->hasBeenClicked()) {
				$entry = $this->app->cart->checkout->getEntryById($id);

				// make sure entry wasn't already moved
				// (i.e. a page resubmit)
				if ($entry === null)
					break;

				$quantity = $quantity_renderer->getWidget($id)->value;
				$entry->setQuantity($quantity);
				$this->app->cart->checkout->removeEntry($entry);
				$this->app->cart->saved->addEntry($entry);
				$num_entries_moved++;
				break;
			}
		}

		return $num_entries_moved;
	}

	// }}}
	// {{{ protected function processUpdatedEntries()

	protected function processUpdatedEntries()
	{
		$message_display = $this->ui->getWidget('message_display');
		$quantity_renderer = $this->getQuantityRenderer();

		$num_entries_removed = 0;
		$num_entries_updated = 0;

		foreach ($quantity_renderer->getClonedWidgets() as $id => $widget) {
			if (!$widget->hasMessage()) {
				$entry = $this->app->cart->checkout->getEntryById($id);
				if ($entry !== null &&
					$entry->getQuantity() !== $widget->value) {
					$this->updated_entry_ids[] = $id;
					$this->app->cart->checkout->setEntryQuantity($entry,
						$widget->value);
					
					if ($widget->value > 0)
						$num_entries_updated++;
					else
						$num_entries_removed++;

					$widget->value = $entry->getQuantity();
				}
			}
		}

		return array(
			'num_entries_updated' => $num_entries_updated,
			'num_entries_removed' => $num_entries_removed,
		);
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		$this->buildTableView();

		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/styles/store-checkout-cart-page.css',
			Store::PACKAGE_ID));

		parent::build();
	}

	// }}}
	// {{{ protected function buildTableView()

	protected function buildTableView()
	{
		$cart = $this->app->cart->checkout;
		$order = $this->app->session->order;

		$view = $this->ui->getWidget('cart_view');
		$view->model = $this->getTableStore();

		$view->getRow('subtotal')->value = $cart->getSubtotal();

		$view->getRow('shipping')->value = $cart->getShippingTotal(
			new StoreOrderAddress(), new StoreOrderAddress());

		$view->getRow('total')->value = $cart->getTotal(
			$order->billing_address, $order->shipping_address);
	}

	// }}}
	// {{{ protected function getTableStore()

	protected function getTableStore()
	{
		$store = new SwatTableStore();

		$entries = $this->app->cart->checkout->getAvailableEntries();
		foreach ($entries as $entry) {
			$ds = $this->getDetailsStore($entry);
			$store->addRow($ds);
		}

		return $store;
	}

	// }}}
	// {{{ protected function getDetailsStore()

	protected function getDetailsStore($entry)
	{
		$ds = new SwatDetailsStore($entry);

		$ds->quantity = $entry->getQuantity();
		$ds->description = $entry->item->getDescription();
		$ds->price = $entry->getCalculatedItemPrice();
		$ds->extension = $entry->getExtension();

		return $ds;
	}

	// }}}
}

?>
