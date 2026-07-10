<?php
/**
 * Hellio Messaging checkout OTP endpoints (storefront).
 *
 * send()   POST /v1/otp/send for the customer's telephone.
 * verify() POST /v1/otp/verify, and on success records the verified phone in
 *          the session. The order confirm step is blocked server side until
 *          this flag is present (see the OCMOD guard in confirm.php).
 *
 * The API token never leaves the server: the browser only calls these two
 * routes. Both respond with JSON and never throw.
 */
class ControllerExtensionModuleHellioOtp extends Controller {
	const CODE = 'module_hellio_sms';

	public function send() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->enabled()) {
			$json['error'] = $this->language->get('text_otp_disabled');

			return $this->respond($json);
		}

		$phone = isset($this->request->post['telephone']) ? trim($this->request->post['telephone']) : '';

		if ($phone === '') {
			$json['error'] = $this->language->get('error_phone');

			return $this->respond($json);
		}

		try {
			$client = $this->makeClient();

			$length = (int)$this->config->get(self::CODE . '_otp_length');
			$expiry = (int)$this->config->get(self::CODE . '_otp_expiry');

			$result = $client->sendOtp($phone, $length ? $length : 6, $expiry ? $expiry : 5, 'checkout');

			if ($result['success']) {
				// Bind verification to the number we actually sent to.
				$this->session->data['hellio_otp_pending'] = $client->normalize($phone);
				unset($this->session->data['hellio_otp_verified']);

				$json['success'] = $this->language->get('text_otp_sent');
			} else {
				$json['error'] = $result['message'] ? $result['message'] : $this->language->get('error_send');
			}
		} catch (Exception $e) {
			$this->log->write('Hellio OTP send error: ' . $e->getMessage());

			$json['error'] = $this->language->get('error_send');
		}

		return $this->respond($json);
	}

	public function verify() {
		$this->load->language('extension/module/hellio_sms');

		$json = array();

		if (!$this->enabled()) {
			// Nothing to verify: treat as satisfied so checkout is not blocked.
			$json['success'] = $this->language->get('text_otp_disabled');

			return $this->respond($json);
		}

		$code = isset($this->request->post['code']) ? trim($this->request->post['code']) : '';

		$phone = isset($this->session->data['hellio_otp_pending']) ? $this->session->data['hellio_otp_pending'] : (isset($this->request->post['telephone']) ? trim($this->request->post['telephone']) : '');

		if ($code === '') {
			$json['error'] = $this->language->get('error_code');

			return $this->respond($json);
		}

		try {
			$client = $this->makeClient();

			$result = $client->verifyOtp($phone, $code);

			if ($result['success'] && is_array($result['data']) && !empty($result['data']['verified'])) {
				$this->session->data['hellio_otp_verified'] = $client->normalize($phone);

				$json['success'] = $this->language->get('text_otp_verified');
			} else {
				$json['error'] = $this->language->get('error_invalid_code');
			}
		} catch (Exception $e) {
			$this->log->write('Hellio OTP verify error: ' . $e->getMessage());

			$json['error'] = $this->language->get('error_invalid_code');
		}

		return $this->respond($json);
	}

	private function enabled() {
		return $this->config->get(self::CODE . '_status') && $this->config->get(self::CODE . '_otp_enabled');
	}

	private function makeClient() {
		require_once(DIR_SYSTEM . 'library/hellio/client.php');

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

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
