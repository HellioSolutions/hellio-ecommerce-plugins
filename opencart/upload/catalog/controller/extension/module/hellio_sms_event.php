<?php
/**
 * Hellio Messaging storefront event handler.
 *
 * Wired to: catalog/model/checkout/order/addOrderHistory/after
 * This fires when an order reaches a status from the storefront (typically the
 * initial confirmation by the payment extension). It sends the customer
 * order-status SMS and, on the first confirmed status, the admin new-order
 * alert.
 *
 * Every path is defensive: a failure logs and returns, it never throws back
 * into order processing.
 */
class ControllerExtensionModuleHellioSmsEvent extends Controller {
	const CODE = 'module_hellio_sms';

	/**
	 * @param string $route
	 * @param array  $args   [order_id, order_status_id, comment, notify, override]
	 * @param mixed  $output
	 */
	public function orderHistory(&$route, &$args, &$output) {
		try {
			if (!$this->config->get(self::CODE . '_status')) {
				return;
			}

			$order_id        = isset($args[0]) ? (int)$args[0] : 0;
			$order_status_id = isset($args[1]) ? (int)$args[1] : 0;

			if (!$order_id || !$order_status_id) {
				return;
			}

			$this->load->model('checkout/order');

			$order = $this->model_checkout_order->getOrder($order_id);

			if (!$order) {
				return;
			}

			require_once(DIR_SYSTEM . 'library/hellio/client.php');

			$client = $this->makeClient();

			$store_url = isset($order['store_url']) ? $order['store_url'] : '';

			$tokens = HellioMessage::fromOrder($order, isset($order['store_name']) ? $order['store_name'] : '', $store_url, $store_url);

			// Feature 1: customer order-status SMS.
			$statuses = $this->config->get(self::CODE . '_order_status');

			if (is_array($statuses) && !empty($statuses[$order_status_id]['enabled'])) {
				$template = isset($statuses[$order_status_id]['template']) ? trim($statuses[$order_status_id]['template']) : '';

				if ($template === '') {
					$template = 'Order {order_number} at {store_name} is now {order_status}.';
				}

				$message = HellioMessage::render($template, $tokens);

				if (!empty($order['telephone']) && trim($message) !== '') {
					$client->sendSms(array($order['telephone']), $message);
				}
			}

			// Feature 2: admin new-order alert (only on the first confirmed status).
			if ($this->config->get(self::CODE . '_admin_alert_enabled') && $this->isNewOrder($order_id)) {
				$numbers = $this->parseNumbers($this->config->get(self::CODE . '_admin_alert_numbers'));

				if ($numbers) {
					$template = $this->config->get(self::CODE . '_admin_alert_template');

					if (trim((string)$template) === '') {
						$template = 'New order {order_number} for {order_total} {currency} from {customer_name}.';
					}

					$message = HellioMessage::render($template, $tokens);

					if (trim($message) !== '') {
						$client->sendSms($numbers, $message);
					}
				}
			}
		} catch (Exception $e) {
			$this->log->write('Hellio order event error: ' . $e->getMessage());
		}
	}

	/**
	 * True when this is the order's first history row with a real status, which
	 * marks the transition from an incomplete order to a confirmed one.
	 */
	private function isNewOrder($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order_history` WHERE `order_id` = '" . (int)$order_id . "' AND `order_status_id` > 0");

		return (int)$query->row['total'] <= 1;
	}

	private function parseNumbers($raw) {
		$numbers = array();

		foreach (preg_split('/[\s,;]+/', (string)$raw) as $number) {
			$number = trim($number);

			if ($number !== '') {
				$numbers[] = $number;
			}
		}

		return $numbers;
	}

	private function makeClient() {
		$log = $this->log;

		return new HellioClient(array(
			'base_url'  => $this->config->get(self::CODE . '_api_base_url'),
			'token'     => $this->config->get(self::CODE . '_api_token'),
			'sender'    => $this->config->get(self::CODE . '_sender_id'),
			'dial_code' => $this->config->get(self::CODE . '_default_dial_code'),
			'timeout'   => 15
		), function ($line) use ($log) {
			$log->write($line);
		});
	}
}
