{*
 * Hellio Messaging admin settings panels: Connect and Send test SMS.
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 *}
<div class="panel">
	<div class="panel-heading">
		<i class="icon-user"></i> {l s='Connect with your Hellio login' mod='helliosms'}
	</div>
	{if $hellio_is_connected}
		<div class="alert alert-success" style="margin-bottom:15px;">
			{l s='Connected as' mod='helliosms'} <strong>{$hellio_connected_email|escape:'html':'UTF-8'}</strong>
		</div>
		<form action="{$hellio_form_action|escape:'html':'UTF-8'}" method="post">
			<button type="submit" name="submitHellioDisconnect" value="1" class="btn btn-default">
				<i class="icon-sign-out"></i> {l s='Disconnect' mod='helliosms'}
			</button>
		</form>
	{else}
		<p class="help-block">
			{l s='Sign in once with your Hellio account and we will store the API token for you. You can also paste a token by hand in the Connection section below.' mod='helliosms'}
		</p>
		<form class="form-horizontal" action="{$hellio_form_action|escape:'html':'UTF-8'}" method="post" autocomplete="off">
			<div class="form-group">
				<label class="control-label col-lg-3">{l s='Hellio email' mod='helliosms'}</label>
				<div class="col-lg-6">
					<input type="email" name="hellio_email" class="form-control" autocomplete="username"
						value="{$hellio_email_prefill|escape:'html':'UTF-8'}">
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-3">{l s='Password' mod='helliosms'}</label>
				<div class="col-lg-6">
					<input type="password" name="hellio_password" class="form-control" autocomplete="new-password" value="">
				</div>
			</div>
			{if $hellio_show_two_factor}
				<div class="form-group">
					<label class="control-label col-lg-3">{l s='Two-factor code' mod='helliosms'}</label>
					<div class="col-lg-6">
						<input type="text" name="hellio_two_factor_code" class="form-control" inputmode="numeric"
							autocomplete="one-time-code" value="">
						<p class="help-block">{l s='Enter the code from your authenticator app.' mod='helliosms'}</p>
					</div>
				</div>
			{/if}
			<div class="form-group">
				<div class="col-lg-6 col-lg-offset-3">
					<button type="submit" name="submitHellioConnect" value="1" class="btn btn-primary">
						<i class="icon-sign-in"></i> {l s='Connect' mod='helliosms'}
					</button>
				</div>
			</div>
		</form>
	{/if}
</div>

<div class="panel">
	<div class="panel-heading">
		<i class="icon-paper-plane"></i> {l s='Send SMS' mod='helliosms'}
	</div>
	<p class="help-block">{l s='Send a test message or a quick blast. Accepts one number or many, separated by comma, space, or new line.' mod='helliosms'}</p>
	<p class="help-block">{$hellio_placeholders|escape:'html':'UTF-8'}</p>
	<form class="form-horizontal" action="{$hellio_form_action|escape:'html':'UTF-8'}" method="post">
		<div class="form-group">
			<label class="control-label col-lg-3">{l s='Recipients' mod='helliosms'}</label>
			<div class="col-lg-6">
				<textarea name="hellio_send_recipients" rows="3" class="form-control"
					placeholder="{l s='One number, or many separated by comma, space, or new line' mod='helliosms'}">{$hellio_send_recipients|escape:'html':'UTF-8'}</textarea>
			</div>
		</div>
		<div class="form-group">
			<label class="control-label col-lg-3">{l s='Sender ID' mod='helliosms'}</label>
			<div class="col-lg-6">
				<input type="text" name="hellio_send_sender" class="form-control" maxlength="11"
					value="{$hellio_send_sender|escape:'html':'UTF-8'}">
			</div>
		</div>
		<div class="form-group">
			<label class="control-label col-lg-3">{l s='Message' mod='helliosms'}</label>
			<div class="col-lg-6">
				<textarea name="hellio_send_message" rows="3" maxlength="1600" class="form-control">{$hellio_send_message|escape:'html':'UTF-8'}</textarea>
				<p class="help-block">{l s='Placeholders render against your most recent order, or blank if you have none yet. Long lists are sent in chunks of 500.' mod='helliosms'}</p>
			</div>
		</div>
		<div class="form-group">
			<div class="col-lg-6 col-lg-offset-3">
				<button type="submit" name="submitHellioSendSms" value="1" class="btn btn-primary">
					<i class="process-icon-envelope"></i> {l s='Send' mod='helliosms'}
				</button>
			</div>
		</div>
	</form>
</div>
