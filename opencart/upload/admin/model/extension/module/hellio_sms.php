<?php
/**
 * Hellio Messaging admin model.
 *
 * Owns install/uninstall (event registration) and the audience queries used
 * by the bulk SMS sender.
 */
class ModelExtensionModuleHellioSms extends Model {
	const EVENT_CODE = 'hellio_sms';

	/**
	 * Register the order events. Called when the extension is installed.
	 */
	public function install() {
		$this->uninstall();

		$this->load->model('setting/event');

		// Storefront order confirmation: initial customer SMS + admin new-order alert.
		$this->model_setting_event->addEvent(
			self::EVENT_CODE,
			'catalog/model/checkout/order/addOrderHistory/after',
			'extension/module/hellio_sms_event/orderHistory'
		);

		// Admin driven status changes: customer status SMS.
		$this->model_setting_event->addEvent(
			self::EVENT_CODE,
			'admin/model/sale/order/addOrderHistory/after',
			'extension/module/hellio_sms_event/orderHistory'
		);
	}

	/**
	 * Remove the registered events. Called when the extension is uninstalled.
	 */
	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
	}

	/**
	 * Count of customers with a telephone number.
	 */
	public function getTotalCustomers() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer` WHERE `telephone` != '' AND `status` = '1'");

		return (int)$query->row['total'];
	}

	/**
	 * All active customers that have a telephone number.
	 *
	 * @return array List of ['name' => ..., 'telephone' => ...].
	 */
	public function getCustomers() {
		$query = $this->db->query("SELECT CONCAT(`firstname`, ' ', `lastname`) AS name, `telephone` FROM `" . DB_PREFIX . "customer` WHERE `telephone` != '' AND `status` = '1'");

		return $query->rows;
	}

	/**
	 * Distinct customer telephones for orders in a given status.
	 *
	 * @param int $order_status_id
	 * @return array List of ['name' => ..., 'telephone' => ...].
	 */
	public function getCustomersByOrderStatus($order_status_id) {
		$query = $this->db->query("SELECT DISTINCT CONCAT(`firstname`, ' ', `lastname`) AS name, `telephone` FROM `" . DB_PREFIX . "order` WHERE `order_status_id` = '" . (int)$order_status_id . "' AND `telephone` != ''");

		return $query->rows;
	}
}
