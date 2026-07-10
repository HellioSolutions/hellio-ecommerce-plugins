<?php
// Heading
$_['heading_title']              = 'Hellio Messaging';

// Tabs
$_['tab_general']                = 'General';
$_['tab_order_status']           = 'Order Status SMS';
$_['tab_admin_alert']            = 'Admin Alerts';
$_['tab_otp']                    = 'Checkout OTP';
$_['tab_bulk']                   = 'Bulk SMS';
$_['tab_test_send']              = 'Send SMS';

// Text
$_['text_extension']             = 'Extensions';
$_['text_success']               = 'Success: You have saved the Hellio Messaging settings.';
$_['text_edit']                  = 'Edit Hellio Messaging';
$_['text_enabled']               = 'Enabled';
$_['text_disabled']              = 'Disabled';
$_['text_general_intro']         = 'Connect your store to the Hellio Messaging API. Generate a personal access token in your Hellio dashboard, then paste it below.';
$_['text_test_connection']       = 'Test connection';
$_['text_testing']               = 'Contacting Hellio...';
$_['text_test_ok']               = 'Connection OK.';
$_['text_balance']               = 'Wallet balance';
$_['text_order_status_intro']    = 'Pick the order statuses that should text the customer, and edit each message. The customer is texted on the order telephone number.';
$_['text_admin_alert_intro']     = 'Text one or more staff numbers whenever a new order is placed.';
$_['text_otp_intro']             = 'Ask customers to verify their phone with a one time code before the order is placed. The order is blocked server side until the code is verified.';
$_['text_bulk_intro']           = 'Compose a message, choose an audience, and send. Recipients are sent in batches of 500.';
$_['text_placeholders']          = 'Placeholders';
$_['text_placeholder_help']      = 'Available placeholders: {order_id} {order_number} {order_status} {order_total} {currency} {customer_name} {customer_first_name} {store_name} {shop_url} {tracking_url} {date}. Unknown placeholders render empty.';
$_['text_audience_all']          = 'All customers';
$_['text_audience_status']       = 'Customers with an order in status';
$_['text_audience_list']         = 'Pasted list of numbers';
$_['text_send_bulk']             = 'Send bulk SMS';
$_['text_sending']               = 'Sending...';
$_['text_bulk_result']           = 'Sent %s of %s. Failed: %s.';
$_['text_confirm_bulk']          = 'Send this message now?';
$_['text_none']                  = '--- None ---';
$_['text_loading']               = 'Loading...';
$_['text_connect_intro']         = 'Connect with your Hellio login and we store a token for you. No need to paste one by hand.';
$_['text_connect']               = 'Connect';
$_['text_connecting']            = 'Connecting...';
$_['text_connected']             = 'Connected.';
$_['text_connected_as']          = 'Connected as %s';
$_['text_disconnect']            = 'Disconnect';
$_['text_disconnected']          = 'Disconnected. The stored token was cleared.';
$_['text_two_factor']            = 'Enter the two factor code from your authenticator, then connect again.';
$_['text_or_paste_token']        = 'Or paste an API token by hand:';
$_['text_test_send_intro']       = 'Send a message to one number or to many pasted numbers. Placeholders are rendered against your most recent order, or left blank if you have none yet.';
$_['text_test_send']             = 'Send SMS';
$_['text_test_sent']             = 'Sent to %s recipient(s). Status: %s. Reference: %s.';
$_['text_preview']               = 'Preview';

// Entry
$_['entry_email']                = 'Hellio email';
$_['entry_password']             = 'Password';
$_['entry_two_factor']           = 'Two factor code';
$_['entry_test_recipient']       = 'Recipients';
$_['entry_test_sender']          = 'Sender ID';
$_['entry_test_message']         = 'Message';
$_['help_test_recipient']        = 'One number, or many separated by comma, space, or a new line.';
$_['entry_status']               = 'Enable extension';
$_['entry_api_base_url']         = 'API base URL';
$_['entry_api_token']            = 'API token';
$_['entry_sender_id']            = 'Default Sender ID';
$_['entry_default_dial_code']    = 'Default dial code';
$_['entry_status_enabled']       = 'Text customer';
$_['entry_status_template']      = 'Message';
$_['entry_admin_alert_enabled']  = 'Enable admin alerts';
$_['entry_admin_alert_numbers']  = 'Admin numbers';
$_['entry_admin_alert_template'] = 'Alert message';
$_['entry_otp_enabled']          = 'Enable checkout OTP';
$_['entry_otp_length']           = 'Code length';
$_['entry_otp_expiry']           = 'Expiry (minutes)';
$_['entry_bulk_message']         = 'Message';
$_['entry_bulk_audience']        = 'Audience';
$_['entry_bulk_status']          = 'Order status';
$_['entry_bulk_list']            = 'Numbers';

// Help
$_['help_api_base_url']          = 'Default: https://api.helliomessaging.com';
$_['help_api_token']             = 'A Bearer token from your Hellio dashboard. Leave blank to keep the saved token.';
$_['help_sender_id']             = 'Up to 11 characters. Must be approved on your Hellio account.';
$_['help_default_dial_code']     = 'For example 233. Applied to local numbers that have no plus sign or country code.';
$_['help_admin_alert_numbers']   = 'Comma separated. For example 233241111111, 233209999999.';
$_['help_otp_length']            = 'Between 4 and 10 digits.';
$_['help_otp_expiry']            = 'Between 1 and 1440 minutes.';
$_['help_bulk_list']             = 'One number per line or comma separated. Used only for the pasted list audience.';

// Button
$_['button_save']                = 'Save';
$_['button_cancel']              = 'Back';

// Placeholder token labels
$_['placeholder_order_status']   = 'Hi {customer_first_name}, order {order_number} at {store_name} is now {order_status}. Total: {order_total} {currency}.';
$_['placeholder_admin_alert']    = 'New order {order_number} for {order_total} {currency} from {customer_name}.';

// Error
$_['error_permission']           = 'Warning: You do not have permission to modify Hellio Messaging.';
$_['error_api_token']            = 'An API token is required.';
$_['error_sender_id']            = 'A Sender ID is required (max 11 characters).';
$_['error_otp_length']           = 'Code length must be between 4 and 10.';
$_['error_otp_expiry']           = 'Expiry must be between 1 and 1440 minutes.';
$_['error_bulk_message']         = 'A message is required.';
$_['error_bulk_audience']        = 'No recipients matched the chosen audience.';
$_['error_not_configured']       = 'Set the API token and Sender ID first.';
$_['error_credentials']          = 'Enter your Hellio email and password.';
$_['error_connect']              = 'Could not connect. Check your email and password and try again.';
$_['error_test_recipient']       = 'Enter at least one recipient number.';
$_['error_test_message']         = 'Enter a message.';
$_['error_test_failed']          = 'The test message could not be sent.';
