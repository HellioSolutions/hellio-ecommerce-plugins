<?php
/**
 * Hellio Messaging for PrestaShop.
 *
 * Send order-status SMS, admin new-order alerts, checkout phone OTP
 * verification, and bulk marketing SMS through the Hellio Messaging API.
 *
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/HellioClient.php';

class HellioSms extends Module
{
    /** @var string Prefix for template placeholder configuration keys. */
    const STATE_ENABLED_PREFIX = 'HELLIOSMS_STATE_EN_';

    /** @var string */
    const STATE_TEMPLATE_PREFIX = 'HELLIOSMS_STATE_TPL_';

    /** @var bool Whether to reveal the 2FA field after a connect attempt. */
    private $showTwoFactor = false;

    /** @var string Email to keep in the connect form after a failed attempt. */
    private $connectEmailPrefill = '';

    /** @var array Values to keep in the Send SMS panel after a send. */
    private $sendSmsValues = array('recipients' => '', 'sender' => '', 'message' => '');

    public function __construct()
    {
        $this->name = 'helliosms';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Hellio Solutions';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Hellio Messaging');
        $this->description = $this->l('Send order-status SMS, admin new-order alerts, checkout phone OTP verification, and bulk marketing SMS through the Hellio Messaging API.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Hellio Messaging? Your settings will be removed.');
    }

    /**
     * Install the module: register hooks, seed configuration, add the bulk tab.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $hooks = array(
            'actionOrderStatusPostUpdate',
            'actionValidateOrder',
            'actionFrontControllerSetMedia',
            'displayBeforeCarrier',
            'actionValidateStepComplete',
        );
        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        $defaults = array(
            'HELLIOSMS_ENABLED' => '1',
            'HELLIOSMS_API_BASE_URL' => 'https://api.helliomessaging.com',
            'HELLIOSMS_API_TOKEN' => '',
            'HELLIOSMS_CONNECTED_EMAIL' => '',
            'HELLIOSMS_SENDER_ID' => '',
            'HELLIOSMS_DEFAULT_DIAL_CODE' => '233',
            'HELLIOSMS_ADMIN_ALERT_EN' => '0',
            'HELLIOSMS_ADMIN_ALERT_NUMBERS' => '',
            'HELLIOSMS_ADMIN_ALERT_TPL' => $this->l('New order {order_number} for {order_total} {currency} from {customer_name} on {store_name}.'),
            'HELLIOSMS_OTP_EN' => '0',
            'HELLIOSMS_OTP_LENGTH' => '6',
            'HELLIOSMS_OTP_EXPIRY' => '5',
        );
        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        // Seed a sensible default template for each order state.
        $defaultStateTemplate = $this->l('Hi {customer_first_name}, your {store_name} order {order_number} is now {order_status}.');
        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            Configuration::updateValue(self::STATE_ENABLED_PREFIX . (int) $state['id_order_state'], '0');
            Configuration::updateValue(self::STATE_TEMPLATE_PREFIX . (int) $state['id_order_state'], $defaultStateTemplate);
        }

        return $this->installTab();
    }

    /**
     * Uninstall the module: drop configuration and the bulk tab.
     *
     * @return bool
     */
    public function uninstall()
    {
        $keys = array(
            'HELLIOSMS_ENABLED',
            'HELLIOSMS_API_BASE_URL',
            'HELLIOSMS_API_TOKEN',
            'HELLIOSMS_CONNECTED_EMAIL',
            'HELLIOSMS_SENDER_ID',
            'HELLIOSMS_DEFAULT_DIAL_CODE',
            'HELLIOSMS_ADMIN_ALERT_EN',
            'HELLIOSMS_ADMIN_ALERT_NUMBERS',
            'HELLIOSMS_ADMIN_ALERT_TPL',
            'HELLIOSMS_OTP_EN',
            'HELLIOSMS_OTP_LENGTH',
            'HELLIOSMS_OTP_EXPIRY',
        );
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            Configuration::deleteByName(self::STATE_ENABLED_PREFIX . (int) $state['id_order_state']);
            Configuration::deleteByName(self::STATE_TEMPLATE_PREFIX . (int) $state['id_order_state']);
        }

        $this->uninstallTab();

        return parent::uninstall();
    }

    /**
     * Create the admin controller tab for the bulk SMS page.
     *
     * @return bool
     */
    private function installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminHellioBulk';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        if (!$tab->id_parent) {
            $tab->id_parent = (int) Tab::getIdFromClassName('CONFIGURE');
        }
        if (!$tab->id_parent) {
            $tab->id_parent = 0;
        }
        $tab->active = 1;
        $tab->name = array();
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Hellio Bulk SMS';
        }

        return (bool) $tab->add();
    }

    /**
     * Remove the admin controller tab.
     *
     * @return bool
     */
    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminHellioBulk');
        if ($idTab) {
            $tab = new Tab($idTab);

            return (bool) $tab->delete();
        }

        return true;
    }

    /**
     * Build a configured API client.
     *
     * @return HellioClient
     */
    public function getClient()
    {
        return new HellioClient(
            Configuration::get('HELLIOSMS_API_BASE_URL'),
            Configuration::get('HELLIOSMS_API_TOKEN'),
            Configuration::get('HELLIOSMS_DEFAULT_DIAL_CODE')
        );
    }

    /**
     * Whether the module is switched on globally.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (bool) Configuration::get('HELLIOSMS_ENABLED');
    }

    /* ---------------------------------------------------------------------
     * Admin settings page
     * ------------------------------------------------------------------- */

    /**
     * Render (and process) the configuration page.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitHellioConnect')) {
            $output .= $this->processConnect();
        } elseif (Tools::isSubmit('submitHellioDisconnect')) {
            $output .= $this->processDisconnect();
        } elseif (Tools::isSubmit('submitHellioSendSms')) {
            $output .= $this->processSendSms();
        } elseif (Tools::isSubmit('submitHellioTest')) {
            $output .= $this->processTestConnection();
        } elseif (Tools::isSubmit('submitHellioSms')) {
            $output .= $this->processConfiguration();
        }

        return $output . $this->renderPanels() . $this->renderForm();
    }

    /**
     * Connect the plugin using the merchant's Hellio login.
     *
     * POSTs credentials to /v1/auth/token server-side and stores only the
     * returned token plus the account email. The password is never stored.
     *
     * @return string HTML notice.
     */
    private function processConnect()
    {
        $email = trim((string) Tools::getValue('hellio_email'));
        $password = (string) Tools::getValue('hellio_password');
        $twoFactor = trim((string) Tools::getValue('hellio_two_factor_code'));
        $this->connectEmailPrefill = $email;

        if ($email === '' || $password === '') {
            $this->showTwoFactor = ($twoFactor !== '');

            return $this->displayError($this->l('Enter your Hellio email and password.'));
        }

        try {
            $result = $this->getClient()->createToken(
                $email,
                $password,
                'PrestaShop',
                $twoFactor !== '' ? $twoFactor : null
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms connect error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);

            return $this->displayError($this->l('We could not reach Hellio. Please try again.'));
        }

        if (!empty($result['success']) && !empty($result['data']['token'])) {
            Configuration::updateValue('HELLIOSMS_API_TOKEN', (string) $result['data']['token']);
            $storedEmail = isset($result['data']['user']['email']) ? (string) $result['data']['user']['email'] : $email;
            Configuration::updateValue('HELLIOSMS_CONNECTED_EMAIL', $storedEmail);
            Configuration::updateValue('HELLIOSMS_ENABLED', 1);

            return $this->displayConfirmation(
                $this->l('Connected as') . ' ' . Tools::safeOutput($storedEmail) . '.'
            );
        }

        $error = isset($result['error']) ? (string) $result['error'] : '';
        if ($error === 'two_factor_required') {
            $this->showTwoFactor = true;

            return $this->displayWarning($this->l('Two-factor is enabled on this account. Re-enter your password and add the 2FA code, then connect again.'));
        }

        $this->showTwoFactor = ($twoFactor !== '');

        return $this->displayError($this->l('Could not connect:') . ' ' . Tools::safeOutput($this->connectErrorMessage($result)));
    }

    /**
     * Clear the stored token and connected email.
     *
     * @return string HTML notice.
     */
    private function processDisconnect()
    {
        Configuration::updateValue('HELLIOSMS_API_TOKEN', '');
        Configuration::updateValue('HELLIOSMS_CONNECTED_EMAIL', '');

        return $this->displayConfirmation($this->l('Disconnected. Your API token has been cleared.'));
    }

    /**
     * Map an auth error to a friendly message.
     *
     * @param array $result
     *
     * @return string
     */
    private function connectErrorMessage(array $result)
    {
        $error = isset($result['error']) ? (string) $result['error'] : '';
        switch ($error) {
            case 'invalid_credentials':
                return $this->l('The email or password is incorrect.');
            case 'email_unverified':
                return $this->l('Verify your Hellio email address first.');
            case 'account_locked':
                return $this->l('This account is locked. Contact Hellio support.');
            case 'throttled':
                return $this->l('Too many attempts. Please wait and try again.');
        }

        return isset($result['message']) && $result['message'] !== ''
            ? (string) $result['message']
            : $this->l('Please check your details and try again.');
    }

    /**
     * Send an SMS to one or many pasted numbers.
     *
     * Doubles as a test send and a quick send-to-list. Placeholders are
     * rendered once against the most recent order (or blank), then the message
     * goes to every number via /v1/sms/send, chunked at 500 for long lists.
     *
     * @return string HTML notice.
     */
    private function processSendSms()
    {
        $recipientsRaw = (string) Tools::getValue('hellio_send_recipients');
        $sender = Tools::substr(trim((string) Tools::getValue('hellio_send_sender')), 0, 11);
        $message = (string) Tools::getValue('hellio_send_message');
        $this->sendSmsValues = array(
            'recipients' => $recipientsRaw,
            'sender' => $sender,
            'message' => $message,
        );

        // Split on commas, spaces, and new lines, then trim and dedupe.
        $parts = preg_split('/[\s,;]+/', $recipientsRaw);
        $numbers = is_array($parts) ? array_filter(array_map('trim', $parts)) : array();
        $numbers = array_values(array_unique($numbers));

        if (empty($numbers) || trim($message) === '') {
            return $this->displayError($this->l('Enter at least one recipient number and a message.'));
        }

        // Render placeholders once against the most recent order, if any.
        $order = $this->getMostRecentOrder();
        if ($order !== null) {
            $customer = new Customer((int) $order->id_customer);
            $address = new Address((int) $order->id_address_delivery);
            $rendered = $this->renderTemplate($message, $order, $customer, $address);
        } else {
            $rendered = $this->applyPlaceholders($message, array());
        }

        $accepted = 0;
        $failed = 0;
        $lastReference = '';
        $lastStatus = '';
        $lastError = '';
        $client = $this->getClient();

        foreach (array_chunk($numbers, 500) as $chunk) {
            try {
                $result = $client->sendSms($chunk, $rendered, $sender !== '' ? $sender : null);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('HellioSms send SMS error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
                $failed += count($chunk);
                $lastError = $e->getMessage();
                continue;
            }

            if (!empty($result['success'])) {
                $chunkAccepted = isset($result['data']['accepted_recipients'])
                    ? (int) $result['data']['accepted_recipients']
                    : count($chunk);
                $accepted += $chunkAccepted;
                $failed += max(0, count($chunk) - $chunkAccepted);
                if (isset($result['data']['reference'])) {
                    $lastReference = (string) $result['data']['reference'];
                }
                if (isset($result['data']['status'])) {
                    $lastStatus = (string) $result['data']['status'];
                }
            } else {
                $failed += count($chunk);
                $lastError = (string) $result['message'];
            }
        }

        if ($accepted > 0) {
            $summary = sprintf($this->l('SMS sent. Accepted recipients: %1$d. Failed: %2$d.'), $accepted, $failed);
            if ($lastStatus !== '') {
                $summary .= ' ' . $this->l('Status:') . ' ' . Tools::safeOutput($lastStatus);
            }
            if ($lastReference !== '') {
                $summary .= ' ' . $this->l('Reference:') . ' ' . Tools::safeOutput($lastReference);
            }

            return $this->displayConfirmation($summary);
        }

        return $this->displayError(
            $this->l('Send failed.') . ' '
            . ($lastError !== '' ? Tools::safeOutput($lastError) : $this->l('No recipients were accepted.'))
        );
    }

    /**
     * The shop's most recent valid order, or null.
     *
     * @return Order|null
     */
    private function getMostRecentOrder()
    {
        $row = Db::getInstance()->getRow(
            'SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` ORDER BY id_order DESC'
        );
        if (empty($row['id_order'])) {
            return null;
        }
        $order = new Order((int) $row['id_order']);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    /**
     * Render the Connect and Send-test panels shown above the settings form.
     *
     * @return string
     */
    private function renderPanels()
    {
        $connectedEmail = (string) Configuration::get('HELLIOSMS_CONNECTED_EMAIL');
        $hasToken = ((string) Configuration::get('HELLIOSMS_API_TOKEN')) !== '';
        $isConnected = ($connectedEmail !== '' && $hasToken);

        $senderDefault = $this->sendSmsValues['sender'] !== ''
            ? $this->sendSmsValues['sender']
            : (string) Configuration::get('HELLIOSMS_SENDER_ID');

        $this->context->smarty->assign(array(
            'hellio_form_action' => AdminController::$currentIndex . '&configure=' . $this->name
                . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'hellio_is_connected' => $isConnected,
            'hellio_connected_email' => $connectedEmail,
            'hellio_show_two_factor' => $this->showTwoFactor,
            'hellio_email_prefill' => $this->connectEmailPrefill,
            'hellio_placeholders' => $this->l('Placeholders: {order_id} {order_number} {order_status} {order_total} {currency} {customer_name} {customer_first_name} {store_name} {shop_url} {tracking_url} {date}.'),
            'hellio_send_recipients' => $this->sendSmsValues['recipients'],
            'hellio_send_sender' => $senderDefault,
            'hellio_send_message' => $this->sendSmsValues['message'],
        ));

        return $this->display(__FILE__, 'views/templates/admin/settings-panels.tpl');
    }

    /**
     * Persist the submitted settings.
     *
     * @return string HTML notice.
     */
    private function processConfiguration()
    {
        $token = Tools::getValue('HELLIOSMS_API_TOKEN');
        // Keep the stored token when the masked placeholder came back unchanged.
        if ($token === $this->maskedToken() || $token === '') {
            $token = Configuration::get('HELLIOSMS_API_TOKEN');
        }

        Configuration::updateValue('HELLIOSMS_ENABLED', (int) Tools::getValue('HELLIOSMS_ENABLED'));
        Configuration::updateValue('HELLIOSMS_API_BASE_URL', trim((string) Tools::getValue('HELLIOSMS_API_BASE_URL')));
        Configuration::updateValue('HELLIOSMS_API_TOKEN', $token);
        Configuration::updateValue('HELLIOSMS_SENDER_ID', Tools::substr((string) Tools::getValue('HELLIOSMS_SENDER_ID'), 0, 11));
        Configuration::updateValue('HELLIOSMS_DEFAULT_DIAL_CODE', preg_replace('/\D+/', '', (string) Tools::getValue('HELLIOSMS_DEFAULT_DIAL_CODE')));

        Configuration::updateValue('HELLIOSMS_ADMIN_ALERT_EN', (int) Tools::getValue('HELLIOSMS_ADMIN_ALERT_EN'));
        Configuration::updateValue('HELLIOSMS_ADMIN_ALERT_NUMBERS', trim((string) Tools::getValue('HELLIOSMS_ADMIN_ALERT_NUMBERS')));
        Configuration::updateValue('HELLIOSMS_ADMIN_ALERT_TPL', (string) Tools::getValue('HELLIOSMS_ADMIN_ALERT_TPL'), true);

        Configuration::updateValue('HELLIOSMS_OTP_EN', (int) Tools::getValue('HELLIOSMS_OTP_EN'));
        $length = (int) Tools::getValue('HELLIOSMS_OTP_LENGTH');
        $length = max(4, min(10, $length));
        Configuration::updateValue('HELLIOSMS_OTP_LENGTH', $length);
        $expiry = (int) Tools::getValue('HELLIOSMS_OTP_EXPIRY');
        $expiry = max(1, min(1440, $expiry));
        Configuration::updateValue('HELLIOSMS_OTP_EXPIRY', $expiry);

        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $id = (int) $state['id_order_state'];
            Configuration::updateValue(self::STATE_ENABLED_PREFIX . $id, (int) Tools::getValue('HELLIOSMS_STATE_EN_' . $id));
            Configuration::updateValue(self::STATE_TEMPLATE_PREFIX . $id, (string) Tools::getValue('HELLIOSMS_STATE_TPL_' . $id), true);
        }

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    /**
     * Call GET /v1/balance and surface the result.
     *
     * @return string HTML notice.
     */
    private function processTestConnection()
    {
        $result = $this->getClient()->getBalance();
        if (!empty($result['success'])) {
            $data = $result['data'];
            $summary = is_array($data) ? Tools::safeOutput(json_encode($data)) : Tools::safeOutput((string) $data);

            return $this->displayConfirmation($this->l('Connection succeeded. Balance:') . ' ' . $summary);
        }

        return $this->displayError(
            $this->l('Connection failed:') . ' '
            . Tools::safeOutput((string) $result['message'])
            . ' (' . Tools::safeOutput((string) $result['error']) . ')'
        );
    }

    /**
     * The masked placeholder shown in place of a stored token.
     *
     * @return string
     */
    private function maskedToken()
    {
        $token = (string) Configuration::get('HELLIOSMS_API_TOKEN');
        if ($token === '') {
            return '';
        }

        return str_repeat('*', 12) . Tools::substr($token, -4);
    }

    /**
     * Render the HelperForm settings screen.
     *
     * @return string
     */
    private function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitHellioSms';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getFormStructure());
    }

    /**
     * Current values for every form field.
     *
     * @return array
     */
    private function getConfigFieldsValues()
    {
        $values = array(
            'HELLIOSMS_ENABLED' => Configuration::get('HELLIOSMS_ENABLED'),
            'HELLIOSMS_API_BASE_URL' => Configuration::get('HELLIOSMS_API_BASE_URL'),
            'HELLIOSMS_API_TOKEN' => $this->maskedToken(),
            'HELLIOSMS_SENDER_ID' => Configuration::get('HELLIOSMS_SENDER_ID'),
            'HELLIOSMS_DEFAULT_DIAL_CODE' => Configuration::get('HELLIOSMS_DEFAULT_DIAL_CODE'),
            'HELLIOSMS_ADMIN_ALERT_EN' => Configuration::get('HELLIOSMS_ADMIN_ALERT_EN'),
            'HELLIOSMS_ADMIN_ALERT_NUMBERS' => Configuration::get('HELLIOSMS_ADMIN_ALERT_NUMBERS'),
            'HELLIOSMS_ADMIN_ALERT_TPL' => Configuration::get('HELLIOSMS_ADMIN_ALERT_TPL'),
            'HELLIOSMS_OTP_EN' => Configuration::get('HELLIOSMS_OTP_EN'),
            'HELLIOSMS_OTP_LENGTH' => Configuration::get('HELLIOSMS_OTP_LENGTH'),
            'HELLIOSMS_OTP_EXPIRY' => Configuration::get('HELLIOSMS_OTP_EXPIRY'),
        );

        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $id = (int) $state['id_order_state'];
            $values['HELLIOSMS_STATE_EN_' . $id] = Configuration::get(self::STATE_ENABLED_PREFIX . $id);
            $values['HELLIOSMS_STATE_TPL_' . $id] = Configuration::get(self::STATE_TEMPLATE_PREFIX . $id);
        }

        return $values;
    }

    /**
     * HelperForm field definition.
     *
     * @return array
     */
    private function getFormStructure()
    {
        $switch = array(
            array('id' => 'on', 'value' => 1, 'label' => $this->l('Yes')),
            array('id' => 'off', 'value' => 0, 'label' => $this->l('No')),
        );

        $placeholderHint = $this->l('Placeholders: {order_id} {order_number} {order_status} {order_total} {currency} {customer_name} {customer_first_name} {store_name} {shop_url} {tracking_url} {date}.');

        // Connection section.
        $connection = array(
            'form' => array(
                'legend' => array('title' => $this->l('Connection'), 'icon' => 'icon-cogs'),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Hellio Messaging'),
                        'name' => 'HELLIOSMS_ENABLED',
                        'is_bool' => true,
                        'values' => $switch,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API base URL'),
                        'name' => 'HELLIOSMS_API_BASE_URL',
                        'desc' => $this->l('Default: https://api.helliomessaging.com'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API token'),
                        'name' => 'HELLIOSMS_API_TOKEN',
                        'desc' => $this->l('Bearer token from your Hellio dashboard. Leave the masked value to keep the current token.'),
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Default Sender ID'),
                        'name' => 'HELLIOSMS_SENDER_ID',
                        'desc' => $this->l('Approved Sender ID, max 11 characters.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Default dial code'),
                        'name' => 'HELLIOSMS_DEFAULT_DIAL_CODE',
                        'desc' => $this->l('Applied to local numbers, for example 233 for Ghana.'),
                    ),
                ),
                'submit' => array('title' => $this->l('Save'), 'name' => 'submitHellioSms'),
                'buttons' => array(
                    array(
                        'title' => $this->l('Test connection'),
                        'name' => 'submitHellioTest',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-refresh',
                    ),
                ),
            ),
        );

        // Admin alert section.
        $adminAlert = array(
            'form' => array(
                'legend' => array('title' => $this->l('Admin new-order alert'), 'icon' => 'icon-bell'),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable admin alert'),
                        'name' => 'HELLIOSMS_ADMIN_ALERT_EN',
                        'is_bool' => true,
                        'values' => $switch,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Admin numbers'),
                        'name' => 'HELLIOSMS_ADMIN_ALERT_NUMBERS',
                        'desc' => $this->l('Comma separated recipient numbers.'),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Alert template'),
                        'name' => 'HELLIOSMS_ADMIN_ALERT_TPL',
                        'rows' => 3,
                        'desc' => $placeholderHint,
                    ),
                ),
                'submit' => array('title' => $this->l('Save'), 'name' => 'submitHellioSms'),
            ),
        );

        // OTP section.
        $otp = array(
            'form' => array(
                'legend' => array('title' => $this->l('Checkout OTP'), 'icon' => 'icon-mobile'),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable checkout OTP'),
                        'name' => 'HELLIOSMS_OTP_EN',
                        'is_bool' => true,
                        'values' => $switch,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Code length'),
                        'name' => 'HELLIOSMS_OTP_LENGTH',
                        'desc' => $this->l('Digits, 4 to 10.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Expiry (minutes)'),
                        'name' => 'HELLIOSMS_OTP_EXPIRY',
                        'desc' => $this->l('1 to 1440 minutes.'),
                    ),
                ),
                'submit' => array('title' => $this->l('Save'), 'name' => 'submitHellioSms'),
            ),
        );

        // Order-status section: a toggle + template per order state.
        $stateInputs = array();
        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $id = (int) $state['id_order_state'];
            $name = $state['name'];
            $stateInputs[] = array(
                'type' => 'switch',
                'label' => $name,
                'name' => 'HELLIOSMS_STATE_EN_' . $id,
                'is_bool' => true,
                'values' => $switch,
            );
            $stateInputs[] = array(
                'type' => 'textarea',
                'label' => $this->l('Template') . ' (' . $name . ')',
                'name' => 'HELLIOSMS_STATE_TPL_' . $id,
                'rows' => 2,
                'desc' => $placeholderHint,
            );
        }
        $orderStatus = array(
            'form' => array(
                'legend' => array('title' => $this->l('Customer order-status SMS'), 'icon' => 'icon-envelope'),
                'input' => $stateInputs,
                'submit' => array('title' => $this->l('Save'), 'name' => 'submitHellioSms'),
            ),
        );

        return array($connection, $adminAlert, $otp, $orderStatus);
    }

    /* ---------------------------------------------------------------------
     * Templating
     * ------------------------------------------------------------------- */

    /**
     * Replace template placeholders using an order and its customer/address.
     *
     * @param string        $template
     * @param Order         $order
     * @param Customer|null $customer
     * @param Address|null  $address
     *
     * @return string
     */
    public function renderTemplate($template, Order $order, $customer = null, $address = null)
    {
        $currency = new Currency((int) $order->id_currency);
        $stateName = '';
        $currentState = new OrderState((int) $order->getCurrentState(), (int) $this->context->language->id);
        if (Validate::isLoadedObject($currentState)) {
            $stateName = $currentState->name;
        }

        $firstName = '';
        $lastName = '';
        if ($customer instanceof Customer && Validate::isLoadedObject($customer)) {
            $firstName = $customer->firstname;
            $lastName = $customer->lastname;
        } elseif ($address instanceof Address && Validate::isLoadedObject($address)) {
            $firstName = $address->firstname;
            $lastName = $address->lastname;
        }

        $shopUrl = Tools::getShopDomainSsl(true);
        $trackingUrl = $shopUrl . __PS_BASE_URI__ . 'index.php?controller=guest-tracking&id_order=' . (int) $order->id;

        $map = array(
            '{order_id}' => (int) $order->id,
            '{order_number}' => $order->reference,
            '{order_status}' => $stateName,
            '{order_total}' => Tools::displayPrice($order->total_paid, $currency),
            '{currency}' => $currency->iso_code,
            '{customer_name}' => trim($firstName . ' ' . $lastName),
            '{customer_first_name}' => $firstName,
            '{store_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => $shopUrl,
            '{tracking_url}' => $trackingUrl,
            '{date}' => Tools::displayDate($order->date_add),
        );

        return $this->applyPlaceholders($template, $map);
    }

    /**
     * Replace a placeholder map in a string. Unknown placeholders render empty.
     *
     * @param string $template
     * @param array  $map
     *
     * @return string
     */
    public function applyPlaceholders($template, array $map)
    {
        $out = str_replace(array_keys($map), array_values($map), (string) $template);
        // Strip any leftover unknown placeholders.
        $out = preg_replace('/\{[a-z_]+\}/i', '', $out);

        return trim($out);
    }

    /**
     * Resolve the best phone number for an order (delivery then invoice).
     *
     * @param Order $order
     *
     * @return string
     */
    private function getOrderPhone(Order $order)
    {
        $addressIds = array((int) $order->id_address_delivery, (int) $order->id_address_invoice);
        foreach ($addressIds as $id) {
            if (!$id) {
                continue;
            }
            $address = new Address($id);
            if (Validate::isLoadedObject($address)) {
                if (!empty($address->phone_mobile)) {
                    return $address->phone_mobile;
                }
                if (!empty($address->phone)) {
                    return $address->phone;
                }
            }
        }

        return '';
    }

    /* ---------------------------------------------------------------------
     * Feature 1: customer order-status SMS
     * ------------------------------------------------------------------- */

    /**
     * On an order status change, SMS the customer if that status is enabled.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            if (empty($params['newOrderStatus']) || empty($params['id_order'])) {
                return;
            }
            $newState = $params['newOrderStatus'];
            $idState = (int) (is_object($newState) ? $newState->id : $newState);
            if (!$idState || !(int) Configuration::get(self::STATE_ENABLED_PREFIX . $idState)) {
                return;
            }

            $order = new Order((int) $params['id_order']);
            if (!Validate::isLoadedObject($order)) {
                return;
            }

            $customer = new Customer((int) $order->id_customer);
            $address = new Address((int) $order->id_address_delivery);
            $template = Configuration::get(self::STATE_TEMPLATE_PREFIX . $idState);
            $message = $this->renderTemplate($template, $order, $customer, $address);

            $phone = $this->getOrderPhone($order);
            if ($phone === '' || $message === '') {
                return;
            }

            $this->getClient()->sendSms(array($phone), $message);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms order-status SMS error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
        }
    }

    /* ---------------------------------------------------------------------
     * Feature 2: admin new-order alert
     * ------------------------------------------------------------------- */

    /**
     * On a validated order, alert the configured admin numbers.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionValidateOrder($params)
    {
        if (!$this->isEnabled() || !(int) Configuration::get('HELLIOSMS_ADMIN_ALERT_EN')) {
            return;
        }

        try {
            $numbersRaw = (string) Configuration::get('HELLIOSMS_ADMIN_ALERT_NUMBERS');
            $numbers = array_filter(array_map('trim', explode(',', $numbersRaw)));
            if (empty($numbers) || empty($params['order'])) {
                return;
            }

            $order = $params['order'];
            if (!($order instanceof Order) || !Validate::isLoadedObject($order)) {
                return;
            }

            $customer = isset($params['customer']) ? $params['customer'] : new Customer((int) $order->id_customer);
            $address = new Address((int) $order->id_address_delivery);
            $template = Configuration::get('HELLIOSMS_ADMIN_ALERT_TPL');
            $message = $this->renderTemplate($template, $order, $customer, $address);
            if ($message === '') {
                return;
            }

            $this->getClient()->sendSms(array_values($numbers), $message);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms admin alert error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
        }
    }

    /* ---------------------------------------------------------------------
     * Feature 3: checkout OTP
     * ------------------------------------------------------------------- */

    /**
     * Enqueue the OTP checkout assets on the checkout page.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if (!$this->isEnabled() || !(int) Configuration::get('HELLIOSMS_OTP_EN')) {
            return;
        }
        if (!$this->isCheckoutPage()) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'helliosms-otp',
            'modules/' . $this->name . '/views/css/checkout-otp.css',
            array('media' => 'all', 'priority' => 200)
        );
        $this->context->controller->registerJavascript(
            'helliosms-otp',
            'modules/' . $this->name . '/views/js/checkout-otp.js',
            array('position' => 'bottom', 'priority' => 200)
        );
        Media::addJsDef(array(
            'helliosmsOtp' => array(
                'sendUrl' => $this->context->link->getModuleLink($this->name, 'otp', array('action' => 'send')),
                'verifyUrl' => $this->context->link->getModuleLink($this->name, 'otp', array('action' => 'verify')),
                'token' => Tools::getToken(false),
                'length' => (int) Configuration::get('HELLIOSMS_OTP_LENGTH'),
            ),
        ));
    }

    /**
     * Render the OTP field on the checkout, before the carrier step.
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayBeforeCarrier($params)
    {
        if (!$this->isEnabled() || !(int) Configuration::get('HELLIOSMS_OTP_EN')) {
            return '';
        }

        $verified = $this->isOtpVerified();
        $this->context->smarty->assign(array(
            'helliosms_verified' => $verified,
            'helliosms_length' => (int) Configuration::get('HELLIOSMS_OTP_LENGTH'),
            'helliosms_phone' => $this->guessCheckoutPhone(),
        ));

        return $this->display(__FILE__, 'views/templates/hook/otp-field.tpl');
    }

    /**
     * Block the checkout step until the phone is verified.
     *
     * PrestaShop passes the step-complete flag by reference, so setting
     * completed to false halts progression cleanly. Never throws.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionValidateStepComplete($params)
    {
        if (!$this->isEnabled() || !(int) Configuration::get('HELLIOSMS_OTP_EN')) {
            return;
        }

        try {
            // Only guard the address/personal step where the field is shown.
            $step = isset($params['step']) ? $params['step'] : null;
            $stepClass = is_object($step) ? get_class($step) : '';
            if ($stepClass !== '' && strpos($stepClass, 'CheckoutPersonalInformationStep') === false
                && strpos($stepClass, 'CheckoutAddressesStep') === false) {
                return;
            }

            if ($this->isOtpVerified()) {
                return;
            }

            if (isset($params['completed'])) {
                $params['completed'] = false;
            }
            if (isset($this->context->controller) && is_object($this->context->controller)) {
                $this->context->controller->errors[] = $this->l('Please verify your phone number with the code we sent before continuing.');
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms OTP step guard error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
        }
    }

    /**
     * Whether the current cart's phone has been verified this session.
     *
     * @return bool
     */
    public function isOtpVerified()
    {
        $cookie = $this->context->cookie;
        if (!$cookie) {
            return false;
        }
        if ((int) $cookie->helliosms_otp_verified !== 1) {
            return false;
        }
        // Tie the verification to the phone that was actually verified.
        $verifiedPhone = (string) $cookie->helliosms_otp_phone;
        $current = $this->getClient()->normalizePhone($this->guessCheckoutPhone());
        if ($current !== '' && $verifiedPhone !== '' && $current !== $verifiedPhone) {
            return false;
        }

        return true;
    }

    /**
     * Best guess of the customer's checkout phone from their addresses.
     *
     * @return string
     */
    public function guessCheckoutPhone()
    {
        $cart = $this->context->cart;
        if (!$cart || !Validate::isLoadedObject($cart)) {
            return '';
        }
        foreach (array((int) $cart->id_address_delivery, (int) $cart->id_address_invoice) as $id) {
            if (!$id) {
                continue;
            }
            $address = new Address($id);
            if (Validate::isLoadedObject($address)) {
                if (!empty($address->phone_mobile)) {
                    return $address->phone_mobile;
                }
                if (!empty($address->phone)) {
                    return $address->phone;
                }
            }
        }

        return '';
    }

    /**
     * Whether the current request is the checkout controller.
     *
     * @return bool
     */
    private function isCheckoutPage()
    {
        $controller = $this->context->controller;
        if (!$controller) {
            return false;
        }

        return isset($controller->php_self) && $controller->php_self === 'order';
    }
}
