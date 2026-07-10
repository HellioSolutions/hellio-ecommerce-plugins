<?php
/**
 * Bulk / marketing SMS admin page.
 *
 * Compose a message, pick an audience (all customers, customers filtered by
 * order status, or a pasted list), and send through /v1/sms/send in chunks of
 * 500. Reports sent and failed counts. CSRF is handled by the admin token.
 *
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminHellioBulkController extends ModuleAdminController
{
    /** @var int Recipients per API call. */
    const CHUNK_SIZE = 500;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();

        if (!$this->module || !$this->module->active) {
            $this->errors[] = $this->l('The Hellio Messaging module is not active.');
        }
    }

    /**
     * Process a bulk send request.
     *
     * @return void
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitHellioBulk')) {
            if (!$this->isTokenValid()) {
                $this->errors[] = $this->l('Invalid security token. Please retry.');

                return;
            }
            $this->processBulkSend();
        }

        parent::postProcess();
    }

    /**
     * Gather recipients for the chosen audience and send in chunks.
     *
     * @return void
     */
    private function processBulkSend()
    {
        $message = trim((string) Tools::getValue('hellio_message'));
        if ($message === '') {
            $this->errors[] = $this->l('Please write a message.');

            return;
        }

        $audience = Tools::getValue('hellio_audience');
        $recipients = array();

        try {
            if ($audience === 'all') {
                $recipients = $this->getAllCustomerPhones();
            } elseif ($audience === 'state') {
                $idState = (int) Tools::getValue('hellio_order_state');
                if (!$idState) {
                    $this->errors[] = $this->l('Please choose an order status.');

                    return;
                }
                $recipients = $this->getPhonesByOrderState($idState);
            } elseif ($audience === 'list') {
                $pasted = (string) Tools::getValue('hellio_list');
                $recipients = $this->parsePastedList($pasted);
            } else {
                $this->errors[] = $this->l('Please choose an audience.');

                return;
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('HellioSms bulk audience error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
            $this->errors[] = $this->l('Could not build the recipient list.');

            return;
        }

        $recipients = array_values(array_unique(array_filter(array_map('trim', $recipients))));
        if (empty($recipients)) {
            $this->errors[] = $this->l('No recipients matched this audience.');

            return;
        }

        $sent = 0;
        $failed = 0;
        $lastError = '';
        $client = $this->module->getClient();

        foreach (array_chunk($recipients, self::CHUNK_SIZE) as $chunk) {
            try {
                $result = $client->sendSms($chunk, $message);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('HellioSms bulk send error: ' . $e->getMessage(), 3, null, 'HellioSms', null, true);
                $failed += count($chunk);
                $lastError = $e->getMessage();
                continue;
            }

            if (!empty($result['success'])) {
                $accepted = isset($result['data']['accepted_recipients'])
                    ? (int) $result['data']['accepted_recipients']
                    : count($chunk);
                $sent += $accepted;
                $failed += max(0, count($chunk) - $accepted);
            } else {
                $failed += count($chunk);
                $lastError = (string) $result['message'];
            }
        }

        $summary = sprintf(
            $this->l('Bulk SMS finished. Sent: %1$d. Failed: %2$d.'),
            $sent,
            $failed
        );
        if ($lastError !== '') {
            $summary .= ' ' . $this->l('Last error:') . ' ' . Tools::safeOutput($lastError);
        }

        if ($failed > 0 && $sent === 0) {
            $this->errors[] = $summary;
        } else {
            $this->confirmations[] = $summary;
        }
    }

    /**
     * Every customer mobile or phone from their default addresses.
     *
     * @return array
     */
    private function getAllCustomerPhones()
    {
        $sql = 'SELECT DISTINCT a.phone_mobile, a.phone
                FROM `' . _DB_PREFIX_ . 'address` a
                INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = a.id_customer
                WHERE a.deleted = 0 AND a.id_customer > 0
                  AND c.deleted = 0 AND c.active = 1
                  AND (a.phone_mobile <> \'\' OR a.phone <> \'\')';

        return $this->collectPhones(Db::getInstance()->executeS($sql));
    }

    /**
     * Customer phones for everyone who has an order in the given state.
     *
     * @param int $idState
     *
     * @return array
     */
    private function getPhonesByOrderState($idState)
    {
        $sql = 'SELECT DISTINCT a.phone_mobile, a.phone
                FROM `' . _DB_PREFIX_ . 'orders` o
                INNER JOIN `' . _DB_PREFIX_ . 'address` a ON a.id_address = o.id_address_delivery
                WHERE o.current_state = ' . (int) $idState . '
                  AND a.deleted = 0
                  AND (a.phone_mobile <> \'\' OR a.phone <> \'\')';

        return $this->collectPhones(Db::getInstance()->executeS($sql));
    }

    /**
     * Reduce address rows to a single best phone each.
     *
     * @param array|false $rows
     *
     * @return array
     */
    private function collectPhones($rows)
    {
        $phones = array();
        if (!is_array($rows)) {
            return $phones;
        }
        foreach ($rows as $row) {
            if (!empty($row['phone_mobile'])) {
                $phones[] = $row['phone_mobile'];
            } elseif (!empty($row['phone'])) {
                $phones[] = $row['phone'];
            }
        }

        return $phones;
    }

    /**
     * Split a pasted list on commas, semicolons, and new lines.
     *
     * @param string $pasted
     *
     * @return array
     */
    private function parsePastedList($pasted)
    {
        $parts = preg_split('/[\s,;]+/', $pasted);
        if (!is_array($parts)) {
            return array();
        }

        return array_filter(array_map('trim', $parts));
    }

    /**
     * Render the compose form.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        $states = OrderState::getOrderStates((int) $this->context->language->id);
        $stateOptions = array();
        foreach ($states as $state) {
            $stateOptions[] = array(
                'id' => (int) $state['id_order_state'],
                'name' => $state['name'],
            );
        }

        $this->context->smarty->assign(array(
            'hellio_form_action' => self::$currentIndex . '&token=' . $this->token,
            'hellio_states' => $stateOptions,
            'hellio_message' => Tools::getValue('hellio_message', ''),
            'hellio_chunk' => self::CHUNK_SIZE,
        ));

        $tpl = _PS_MODULE_DIR_ . 'helliosms/views/templates/admin/bulk.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }
}
