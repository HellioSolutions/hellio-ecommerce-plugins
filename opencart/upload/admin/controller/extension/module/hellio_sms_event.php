<?php
/**
 * Hellio Messaging admin event handler.
 *
 * Wired to: admin/model/sale/order/addOrderHistory/after
 * This fires when a staff member changes an order status from the admin. It
 * sends the customer order-status SMS for the enabled statuses. The new-order
 * alert is handled on the storefront side (hellio_sms_event in catalog).
 *
 * Note the admin model signature differs from the catalog one:
 *   addOrderHistory($order_id, $data)   where $data['order_status_id'] holds the status.
 *
 * Defensive throughout: a failure logs and returns, never throwing into the
 * admin order save.
 */
class ControllerExtensionModuleHellioSmsEvent extends Controller {
	const CODE = 'module_hellio_sms';

	/**
	 * @param string $route
	 * @param array  $args  [order_id, data]
	 * @param mixed  $output
	 */
	public function orderHistory(&$route, &$args, &$output) {
		try {
			if (!$this->config->get(self::CODE . '_status')) {
				return;
			}

			$order_id = isset($args[0]) ? (int)$args[0] : 0;

			$order_status_id = 0;

			if (isset($args[1]) && is_array($args[1]) && isset($args[1]['order_status_id'])) {
				$order_status_id = (int)$args[1]['order_status_id'];
			} elseif (isset($args[1])) {
				$order_status_id = (int)$args[1];
			}

			if (!$order_id || !$order_status_id) {
				return;
			}

			$statuses = $this->config->get(self::CODE . '_order_status');

			if (!is_array($statuses) || empty($statuses[$order_status_id]['enabled'])) {
				return;
			}

			$this->load->model('sale/order');

			$order = $this->model_sale_order->getOrder($order_id);

			if (!$order || empty($order['telephone'])) {
				return;
			}

			require_once(DIR_SYSTEM . 'library/hellio/client.php');

			$store_url = isset($order['store_url']) ? $order['store_url'] : '';

			$tokens = HellioMessage::fromOrder($order, isset($order['store_name']) ? $order['store_name'] : '', $store_url, $store_url);

			$template = isset($statuses[$order_status_id]['template']) ? trim($statuses[$order_status_id]['template']) : '';

			if ($template === '') {
				$template = 'Order {order_number} at {store_name} is now {order_status}.';
			}

			$message = HellioMessage::render($template, $tokens);

			if (trim($message) === '') {
				return;
			}

			$log = $this->log;

			$client = new HellioClient(array(
				'base_url'  => $this->config->get(self::CODE . '_api_base_url'),
				'token'     => $this->config->get(self::CODE . '_api_token'),
				'sender'    => $this->config->get(self::CODE . '_sender_id'),
				'dial_code' => $this->config->get(self::CODE . '_default_dial_code'),
				'timeout'   => 15
			), function ($line) use ($log) {
				$log->write($line);
			});

			$client->sendSms(array($order['telephone']), $message);
		} catch (Exception $e) {
			$this->log->write('Hellio admin order event error: ' . $e->getMessage());
		}
	}
}
