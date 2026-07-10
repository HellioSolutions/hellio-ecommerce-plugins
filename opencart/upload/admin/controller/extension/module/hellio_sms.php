<?php
/**
 * Hellio Messaging settings controller (admin).
 *
 * Routes:
 *   index()   Render + is the settings form.
 *   save()    AJAX save (JSON).
 *   test()    AJAX "Test connection" (GET /v1/balance).
 *   bulk()    AJAX bulk / marketing send.
 *   install() Register events (called by the extension installer).
 *   uninstall()
 */
class ControllerExtensionModuleHellioSms extends Controller {
	private $error = array();

	const CODE = 'module_hellio_sms';

	public function index() {
		$this->load->language('extension/module/hellio_sms');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Breadcrumbs.
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/hellio_sms', 'user_token=' . $this->session->data['user_token'], true)
		);

		// Action URLs (all carry the admin user_token).
		$data['action']         = $this->url->link('extension/module/hellio_sms/save', 'user_token=' . $this->session->data['user_token'], true);
		$data['test_action']    = $this->url->link('extension/module/hellio_sms/test', 'user_token=' . $this->session->data['user_token'], true);
		$data['bulk_action']    = $this->url->link('extension/module/hellio_sms/bulk', 'user_token=' . $this->session->data['user_token'], true);
		$data['connect_action']    = $this->url->link('extension/module/hellio_sms/connect', 'user_token=' . $this->session->data['user_token'], true);
		$data['disconnect_action'] = $this->url->link('extension/module/hellio_sms/disconnect', 'user_token=' . $this->session->data['user_token'], true);
		$data['test_send_action']  = $this->url->link('extension/module/hellio_sms/testSend', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel']      = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['user_token'] = $this->session->data['user_token'];

		// Current values (fall back to sensible defaults).
		$data['module_hellio_sms_status']            = (int)$this->config->get(self::CODE . '_status');
		$data['module_hellio_sms_api_base_url']      = $this->config->get(self::CODE . '_api_base_url') ? $this->config->get(self::CODE . '_api_base_url') : 'https://api.helliomessaging.com';
		$data['module_hellio_sms_sender_id']         = $this->config->get(self::CODE . '_sender_id');
		$data['module_hellio_sms_default_dial_code'] = $this->config->get(self::CODE . '_default_dial_code');

		// The token is never echoed back. We only report whether one is stored.
		$data['api_token_set']  = (bool)$this->config->get(self::CODE . '_api_token');
		$data['connected_email'] = $this->config->get(self::CODE . '_connected_email');

		$order_status_settings = $this->config->get(self::CODE . '_order_status');
		$data['module_hellio_sms_order_status'] = is_array($order_status_settings) ? $order_status_settings : array();

		$data['module_hellio_sms_admin_alert_enabled']  = (int)$this->config->get(self::CODE . '_admin_alert_enabled');
		$data['module_hellio_sms_admin_alert_numbers']  = $this->config->get(self::CODE . '_admin_alert_numbers');
		$data['module_hellio_sms_admin_alert_template'] = $this->config->get(self::CODE . '_admin_alert_template') ? $this->config->get(self::CODE . '_admin_alert_template') : $this->language->get('placeholder_admin_alert');

		$data['module_hellio_sms_otp_enabled'] = (int)$this->config->get(self::CODE . '_otp_enabled');
		$data['module_hellio_sms_otp_length']  = $this->config->get(self::CODE . '_otp_length') ? (int)$this->config->get(self::CODE . '_otp_length') : 6;
		$data['module_hellio_sms_otp_expiry']  = $this->config->get(self::CODE . '_otp_expiry') ? (int)$this->config->get(self::CODE . '_otp_expiry') : 5;

		$data['default_status_template'] = $this->language->get('placeholder_order_status');

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/hellio_sms', $data));
	}

	/**
	 * Persist the settings. Responds with JSON for the AJAX form.
	 */
	public function save() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/hellio_sms')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		// Validation.
		if (empty($json['error'])) {
			$errors = array();

			$sender = isset($post[self::CODE . '_sender_id']) ? trim($post[self::CODE . '_sender_id']) : '';

			if ($sender === '' || strlen($sender) > 11) {
				$errors[] = $this->language->get('error_sender_id');
			}

			$otp_length = isset($post[self::CODE . '_otp_length']) ? (int)$post[self::CODE . '_otp_length'] : 6;

			if ($otp_length < 4 || $otp_length > 10) {
				$errors[] = $this->language->get('error_otp_length');
			}

			$otp_expiry = isset($post[self::CODE . '_otp_expiry']) ? (int)$post[self::CODE . '_otp_expiry'] : 5;

			if ($otp_expiry < 1 || $otp_expiry > 1440) {
				$errors[] = $this->language->get('error_otp_expiry');
			}

			if ($errors) {
				$json['error'] = implode(' ', $errors);
			}
		}

		if (empty($json['error'])) {
			$this->load->model('setting/setting');

			$settings = $this->model_setting_setting->getSetting(self::CODE);

			// Keep the existing token if the field was left blank.
			$token = isset($post[self::CODE . '_api_token']) ? trim($post[self::CODE . '_api_token']) : '';

			if ($token === '' && isset($settings[self::CODE . '_api_token'])) {
				$token = $settings[self::CODE . '_api_token'];
			}

			$order_status = array();

			if (isset($post[self::CODE . '_order_status']) && is_array($post[self::CODE . '_order_status'])) {
				foreach ($post[self::CODE . '_order_status'] as $status_id => $row) {
					$order_status[(int)$status_id] = array(
						'enabled'  => !empty($row['enabled']) ? 1 : 0,
						'template' => isset($row['template']) ? $row['template'] : ''
					);
				}
			}

			$data = array(
				self::CODE . '_status'               => !empty($post[self::CODE . '_status']) ? 1 : 0,
				self::CODE . '_api_base_url'         => isset($post[self::CODE . '_api_base_url']) ? trim($post[self::CODE . '_api_base_url']) : 'https://api.helliomessaging.com',
				self::CODE . '_api_token'            => $token,
				self::CODE . '_sender_id'            => isset($post[self::CODE . '_sender_id']) ? trim($post[self::CODE . '_sender_id']) : '',
				self::CODE . '_default_dial_code'    => isset($post[self::CODE . '_default_dial_code']) ? preg_replace('/[^0-9]/', '', $post[self::CODE . '_default_dial_code']) : '',
				self::CODE . '_order_status'         => $order_status,
				self::CODE . '_admin_alert_enabled'  => !empty($post[self::CODE . '_admin_alert_enabled']) ? 1 : 0,
				self::CODE . '_admin_alert_numbers'  => isset($post[self::CODE . '_admin_alert_numbers']) ? trim($post[self::CODE . '_admin_alert_numbers']) : '',
				self::CODE . '_admin_alert_template' => isset($post[self::CODE . '_admin_alert_template']) ? $post[self::CODE . '_admin_alert_template'] : '',
				self::CODE . '_otp_enabled'          => !empty($post[self::CODE . '_otp_enabled']) ? 1 : 0,
				self::CODE . '_otp_length'           => isset($post[self::CODE . '_otp_length']) ? (int)$post[self::CODE . '_otp_length'] : 6,
				self::CODE . '_otp_expiry'           => isset($post[self::CODE . '_otp_expiry']) ? (int)$post[self::CODE . '_otp_expiry'] : 5,
				// Preserve the connected account email set by the Connect action.
				self::CODE . '_connected_email'      => isset($settings[self::CODE . '_connected_email']) ? $settings[self::CODE . '_connected_email'] : ''
			);

			$this->model_setting_setting->editSetting(self::CODE, $data);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Test connection: GET /v1/balance.
	 */
	public function test() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/hellio_sms')) {
			$json['error'] = $this->language->get('error_permission');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		// Prefer the token typed in the form (unsaved), else the stored one.
		$post   = $this->request->post;
		$token  = isset($post[self::CODE . '_api_token']) && trim($post[self::CODE . '_api_token']) !== '' ? trim($post[self::CODE . '_api_token']) : $this->config->get(self::CODE . '_api_token');
		$base   = isset($post[self::CODE . '_api_base_url']) && trim($post[self::CODE . '_api_base_url']) !== '' ? trim($post[self::CODE . '_api_base_url']) : $this->config->get(self::CODE . '_api_base_url');

		if (!$token) {
			$json['error'] = $this->language->get('error_not_configured');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$client = $this->makeClient(array('token' => $token, 'base_url' => $base));

		$result = $client->getBalance();

		if ($result['success']) {
			$json['success'] = $this->language->get('text_test_ok');
			$json['balance'] = $result['data'];
		} else {
			$json['error'] = $this->language->get('text_test_connection') . ': ' . $result['message'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Connect with a Hellio login: POST /v1/auth/token, then store the returned
	 * token (and the account email) via editSetting. The password is only ever
	 * forwarded to the API, never stored.
	 */
	public function connect() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/hellio_sms')) {
			return $this->json(array('error' => $this->language->get('error_permission')));
		}

		$post = $this->request->post;

		$email    = isset($post['email']) ? trim($post['email']) : '';
		$password = isset($post['password']) ? (string)$post['password'] : '';
		$twoFactor = isset($post['two_factor_code']) ? trim($post['two_factor_code']) : '';
		$base     = isset($post[self::CODE . '_api_base_url']) && trim($post[self::CODE . '_api_base_url']) !== '' ? trim($post[self::CODE . '_api_base_url']) : $this->config->get(self::CODE . '_api_base_url');

		if ($email === '' || $password === '') {
			return $this->json(array('error' => $this->language->get('error_credentials')));
		}

		try {
			$client = $this->makeClient(array('base_url' => $base, 'token' => ''));

			$result = $client->createToken($email, $password, 'OpenCart', $twoFactor !== '' ? $twoFactor : null);

			if ($result['success'] && is_array($result['data']) && !empty($result['data']['token'])) {
				$this->load->model('setting/setting');

				$settings = $this->model_setting_setting->getSetting(self::CODE);

				$settings[self::CODE . '_api_token']       = $result['data']['token'];
				$settings[self::CODE . '_connected_email'] = $email;

				if (empty($settings[self::CODE . '_api_base_url'])) {
					$settings[self::CODE . '_api_base_url'] = $base;
				}

				$this->model_setting_setting->editSetting(self::CODE, $settings);

				$json['success']   = $this->language->get('text_connected');
				$json['connected'] = $email;
			} elseif ($result['error'] === 'two_factor_required') {
				// Reveal the 2FA field and let the merchant retry.
				$json['two_factor_required'] = true;
				$json['error'] = $result['message'] ? $result['message'] : $this->language->get('text_two_factor');
			} else {
				$json['error'] = $result['message'] ? $result['message'] : $this->language->get('error_connect');
			}
		} catch (Exception $e) {
			$this->log->write('Hellio connect error: ' . $e->getMessage());

			$json['error'] = $this->language->get('error_connect');
		}

		return $this->json($json);
	}

	/**
	 * Disconnect: clear the stored token and connected email.
	 */
	public function disconnect() {
		$this->load->language('extension/module/hellio_sms');

		if (!$this->user->hasPermission('modify', 'extension/module/hellio_sms')) {
			return $this->json(array('error' => $this->language->get('error_permission')));
		}

		$this->load->model('setting/setting');

		$settings = $this->model_setting_setting->getSetting(self::CODE);

		$settings[self::CODE . '_api_token']       = '';
		$settings[self::CODE . '_connected_email'] = '';

		$this->model_setting_setting->editSetting(self::CODE, $settings);

		return $this->json(array('success' => $this->language->get('text_disconnected')));
	}

	/**
	 * Send an SMS to one or many numbers. Accepts a pasted list separated by
	 * comma, space, or newline. Renders template placeholders once against the
	 * store's most recent order (blank when there is none), then sends via the
	 * client, chunked at 500. Reports the accepted count with status/reference.
	 */
	public function testSend() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/hellio_sms')) {
			return $this->json(array('error' => $this->language->get('error_permission')));
		}

		$post = $this->request->post;

		$recipientsRaw = isset($post['test_recipient']) ? (string)$post['test_recipient'] : '';
		$sender        = isset($post['test_sender']) ? trim($post['test_sender']) : '';
		$message       = isset($post['test_message']) ? (string)$post['test_message'] : '';

		// One number or many, separated by comma, space, semicolon, or newline.
		$numbers = array();

		foreach (preg_split('/[\s,;]+/', $recipientsRaw) as $number) {
			$number = trim($number);

			if ($number !== '') {
				$numbers[] = $number;
			}
		}

		$numbers = array_values(array_unique($numbers));

		if (empty($numbers)) {
			return $this->json(array('error' => $this->language->get('error_test_recipient')));
		}

		if (trim($message) === '') {
			return $this->json(array('error' => $this->language->get('error_test_message')));
		}

		if (!$this->config->get(self::CODE . '_api_token')) {
			return $this->json(array('error' => $this->language->get('error_not_configured')));
		}

		try {
			require_once(DIR_SYSTEM . 'library/hellio/client.php');

			// Render placeholders once against the most recent order, if any.
			$tokens = $this->sampleTokens();

			$rendered = HellioMessage::render($message, $tokens);

			$client = $this->makeClient();

			$accepted  = 0;
			$failed    = 0;
			$reference = '';
			$status    = '';
			$lastError = '';

			foreach (array_chunk($numbers, 500) as $chunk) {
				$result = $client->sendSms($chunk, $rendered, $sender !== '' ? $sender : null);

				if ($result['success'] && is_array($result['data'])) {
					$accepted += isset($result['data']['accepted_recipients']) ? (int)$result['data']['accepted_recipients'] : count($chunk);
					$failed   += isset($result['data']['invalid_recipients']) ? (int)$result['data']['invalid_recipients'] : 0;

					if ($reference === '' && isset($result['data']['reference'])) {
						$reference = $result['data']['reference'];
					}

					if ($status === '' && isset($result['data']['status'])) {
						$status = $result['data']['status'];
					}
				} else {
					$failed   += count($chunk);
					$lastError = $result['message'] ? $result['message'] : $this->language->get('error_test_failed');
				}
			}

			if ($accepted > 0) {
				$json['success']  = sprintf($this->language->get('text_test_sent'), $accepted, $status, $reference);
				$json['accepted'] = $accepted;
				$json['failed']   = $failed;
				$json['preview']  = $rendered;
			} else {
				$json['error'] = $lastError ? $lastError : $this->language->get('error_test_failed');
			}
		} catch (Exception $e) {
			$this->log->write('Hellio send SMS error: ' . $e->getMessage());

			$json['error'] = $this->language->get('error_test_failed');
		}

		return $this->json($json);
	}

	/**
	 * Placeholder map from the store's most recent order. Empty strings when the
	 * store has no orders yet.
	 */
	private function sampleTokens() {
		require_once(DIR_SYSTEM . 'library/hellio/client.php');

		$query = $this->db->query("SELECT `order_id` FROM `" . DB_PREFIX . "order` WHERE `order_status_id` > 0 ORDER BY `order_id` DESC LIMIT 1");

		if ($query->num_rows) {
			$this->load->model('sale/order');

			$order = $this->model_sale_order->getOrder((int)$query->row['order_id']);

			if ($order) {
				$store_url = isset($order['store_url']) ? $order['store_url'] : '';

				return HellioMessage::fromOrder($order, isset($order['store_name']) ? $order['store_name'] : '', $store_url, $store_url);
			}
		}

		return array(
			'order_id'            => '',
			'order_number'        => '',
			'order_status'        => '',
			'order_total'         => '',
			'currency'            => '',
			'customer_name'       => '',
			'customer_first_name' => '',
			'store_name'          => $this->config->get('config_name'),
			'shop_url'            => HTTP_CATALOG,
			'tracking_url'        => '',
			'date'                => date('Y-m-d')
		);
	}

	/**
	 * Bulk / marketing send. Chunks recipients at 500 and reports counts.
	 */
	public function bulk() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/hellio_sms')) {
			$json['error'] = $this->language->get('error_permission');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$post = $this->request->post;

		$message  = isset($post['bulk_message']) ? trim($post['bulk_message']) : '';
		$audience = isset($post['bulk_audience']) ? $post['bulk_audience'] : 'all';

		if ($message === '') {
			$json['error'] = $this->language->get('error_bulk_message');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!$this->config->get(self::CODE . '_api_token') || !$this->config->get(self::CODE . '_sender_id')) {
			$json['error'] = $this->language->get('error_not_configured');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		// Resolve the audience into a flat list of raw phone strings.
		$recipients = array();

		$this->load->model('extension/module/hellio_sms');

		if ($audience === 'status') {
			$status_id = isset($post['bulk_status']) ? (int)$post['bulk_status'] : 0;

			foreach ($this->model_extension_module_hellio_sms->getCustomersByOrderStatus($status_id) as $row) {
				$recipients[] = $row['telephone'];
			}
		} elseif ($audience === 'list') {
			$list = isset($post['bulk_list']) ? $post['bulk_list'] : '';

			foreach (preg_split('/[\s,;]+/', $list) as $number) {
				$number = trim($number);

				if ($number !== '') {
					$recipients[] = $number;
				}
			}
		} else {
			foreach ($this->model_extension_module_hellio_sms->getCustomers() as $row) {
				$recipients[] = $row['telephone'];
			}
		}

		$recipients = array_values(array_filter(array_map('trim', $recipients)));

		if (empty($recipients)) {
			$json['error'] = $this->language->get('error_bulk_audience');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$client = $this->makeClient();

		$sent   = 0;
		$failed = 0;

		foreach (array_chunk($recipients, 500) as $chunk) {
			$result = $client->sendSms($chunk, $message);

			if ($result['success'] && is_array($result['data'])) {
				$accepted = isset($result['data']['accepted_recipients']) ? (int)$result['data']['accepted_recipients'] : count($chunk);
				$invalid  = isset($result['data']['invalid_recipients']) ? (int)$result['data']['invalid_recipients'] : 0;

				$sent   += $accepted;
				$failed += $invalid;
			} else {
				$failed += count($chunk);
			}
		}

		$json['success'] = sprintf($this->language->get('text_bulk_result'), $sent, count($recipients), $failed);
		$json['sent']    = $sent;
		$json['failed']  = $failed;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Emit a JSON response.
	 */
	private function json($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Build a configured Hellio client. Optional overrides let the test action
	 * use an unsaved token.
	 */
	private function makeClient(array $overrides = array()) {
		require_once(DIR_SYSTEM . 'library/hellio/client.php');

		$log = $this->log;

		$config = array(
			'base_url'  => isset($overrides['base_url']) ? $overrides['base_url'] : $this->config->get(self::CODE . '_api_base_url'),
			'token'     => isset($overrides['token']) ? $overrides['token'] : $this->config->get(self::CODE . '_api_token'),
			'sender'    => $this->config->get(self::CODE . '_sender_id'),
			'dial_code' => $this->config->get(self::CODE . '_default_dial_code'),
			'timeout'   => 15
		);

		return new HellioClient($config, function ($line) use ($log) {
			$log->write($line);
		});
	}

	/**
	 * Called by the extension installer. Registers the order events.
	 */
	public function install() {
		$this->load->model('extension/module/hellio_sms');
		$this->model_extension_module_hellio_sms->install();
	}

	/**
	 * Called by the extension installer. Removes the order events.
	 */
	public function uninstall() {
		$this->load->model('extension/module/hellio_sms');
		$this->model_extension_module_hellio_sms->uninstall();
	}
}
