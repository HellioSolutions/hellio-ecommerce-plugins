<?php
/**
 * OTP front controller.
 *
 * Server-side endpoints for the checkout OTP flow. The API token stays on the
 * server: the browser only ever calls send and verify here, and this
 * controller talks to Hellio. Responses are JSON. Requests are guarded by the
 * front controller token.
 *
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class HellioSmsOtpModuleFrontController extends ModuleFrontController
{
    /** @var bool No template, JSON only. */
    public $ajax = true;

    /**
     * Route the request to the send or verify action.
     *
     * @return void
     */
    public function postProcess()
    {
        if (!(int) Configuration::get('HELLIOSMS_ENABLED') || !(int) Configuration::get('HELLIOSMS_OTP_EN')) {
            $this->respond(false, $this->module->l('OTP verification is not enabled.', 'otp'), 403);
        }

        if (!$this->isTokenValid()) {
            $this->respond(false, $this->module->l('Invalid security token. Please refresh and try again.', 'otp'), 403);
        }

        $action = Tools::getValue('action');
        if ($action === 'send') {
            $this->processSend();
        } elseif ($action === 'verify') {
            $this->processVerify();
        } else {
            $this->respond(false, $this->module->l('Unknown action.', 'otp'), 400);
        }
    }

    /**
     * Request an OTP for the submitted mobile number.
     *
     * @return void
     */
    private function processSend()
    {
        $phone = trim((string) Tools::getValue('phone'));
        if ($phone === '') {
            $phone = $this->module->guessCheckoutPhone();
        }
        if ($phone === '') {
            $this->respond(false, $this->module->l('Please enter your mobile number first.', 'otp'), 422);
        }

        try {
            $result = $this->module->getClient()->sendOtp(
                $phone,
                (int) Configuration::get('HELLIOSMS_OTP_LENGTH'),
                (int) Configuration::get('HELLIOSMS_OTP_EXPIRY'),
                'checkout'
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms OTP send error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
            $this->respond(false, $this->module->l('We could not send the code. Please try again.', 'otp'), 502);

            return;
        }

        if (!empty($result['success'])) {
            // Remember which phone the pending code belongs to.
            $this->context->cookie->helliosms_otp_pending = $this->module->getClient()->normalizePhone($phone);
            $this->context->cookie->helliosms_otp_verified = 0;
            $this->context->cookie->write();

            $expiry = isset($result['data']['expires_in_minutes'])
                ? (int) $result['data']['expires_in_minutes']
                : (int) Configuration::get('HELLIOSMS_OTP_EXPIRY');

            $this->respond(true, sprintf($this->module->l('We sent a code. It expires in %d minutes.', 'otp'), $expiry), 200);

            return;
        }

        $status = (int) $result['status'] === 429
            ? 429
            : 422;
        $this->respond(false, $this->friendlyError($result), $status);
    }

    /**
     * Verify a submitted code against the pending mobile number.
     *
     * @return void
     */
    private function processVerify()
    {
        $code = preg_replace('/\D+/', '', (string) Tools::getValue('code'));
        $phone = trim((string) Tools::getValue('phone'));
        if ($phone === '') {
            $phone = (string) $this->context->cookie->helliosms_otp_pending;
        }
        if ($phone === '') {
            $phone = $this->module->guessCheckoutPhone();
        }
        if ($phone === '' || $code === '') {
            $this->respond(false, $this->module->l('Enter the code we sent to your phone.', 'otp'), 422);
        }

        try {
            $result = $this->module->getClient()->verifyOtp($phone, $code);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms OTP verify error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
            $this->respond(false, $this->module->l('We could not verify the code. Please try again.', 'otp'), 502);

            return;
        }

        $verified = !empty($result['success'])
            && isset($result['data']['verified'])
            && $result['data']['verified'];

        if ($verified) {
            $this->context->cookie->helliosms_otp_verified = 1;
            $this->context->cookie->helliosms_otp_phone = $this->module->getClient()->normalizePhone($phone);
            $this->context->cookie->write();

            $this->respond(true, $this->module->l('Your phone number is verified.', 'otp'), 200);

            return;
        }

        $this->context->cookie->helliosms_otp_verified = 0;
        $this->context->cookie->write();
        $this->respond(false, $this->friendlyError($result, $this->module->l('That code is not valid. Please try again.', 'otp')), 422);
    }

    /**
     * Turn an API error result into a customer-facing message.
     *
     * @param array  $result
     * @param string $fallback
     *
     * @return string
     */
    private function friendlyError(array $result, $fallback = '')
    {
        $error = isset($result['error']) ? (string) $result['error'] : '';
        switch ($error) {
            case 'throttled':
                $retry = isset($result['retry_after']) ? (int) $result['retry_after'] : 0;

                return $retry > 0
                    ? sprintf($this->module->l('Too many attempts. Try again in %d seconds.', 'otp'), $retry)
                    : $this->module->l('Too many attempts. Please try again shortly.', 'otp');
            case 'insufficient_balance':
            case 'spend_limit_exceeded':
            case 'sender_not_approved':
            case 'traffic_not_routable':
                return $this->module->l('We could not send the code right now. Please contact the store.', 'otp');
        }

        if ($fallback !== '') {
            return $fallback;
        }

        return isset($result['message']) && $result['message'] !== ''
            ? (string) $result['message']
            : $this->module->l('Something went wrong. Please try again.', 'otp');
    }

    /**
     * Emit a JSON response and stop.
     *
     * @param bool   $success
     * @param string $message
     * @param int    $httpCode
     *
     * @return void
     */
    private function respond($success, $message, $httpCode = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $httpCode);
            header('Content-Type: application/json');
        }
        $this->ajaxRender(json_encode(array(
            'success' => (bool) $success,
            'message' => (string) $message,
        )));
        exit;
    }
}
