{*
 * Hellio Messaging checkout OTP field.
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 *}
<section class="helliosms-otp{if $helliosms_verified} helliosms-otp--verified{/if}" id="helliosms-otp"
	data-verified="{if $helliosms_verified}1{else}0{/if}">
	<h3 class="helliosms-otp__title">{l s='Verify your phone number' mod='helliosms'}</h3>

	<div class="helliosms-otp__body"{if $helliosms_verified} style="display:none"{/if}>
		<div class="form-group">
			<label for="helliosms-otp-phone">{l s='Mobile number' mod='helliosms'}</label>
			<input type="tel" id="helliosms-otp-phone" class="form-control" autocomplete="tel"
				value="{$helliosms_phone|escape:'html':'UTF-8'}" placeholder="{l s='e.g. 0241111111' mod='helliosms'}">
		</div>
		<button type="button" class="btn btn-primary" id="helliosms-otp-send">
			{l s='Send code' mod='helliosms'}
		</button>

		<div class="helliosms-otp__verify" id="helliosms-otp-verify-row" style="display:none; margin-top:12px;">
			<div class="form-group">
				<label for="helliosms-otp-code">{l s='Enter the code' mod='helliosms'}</label>
				<input type="text" id="helliosms-otp-code" class="form-control" inputmode="numeric"
					maxlength="{$helliosms_length|intval}" autocomplete="one-time-code">
			</div>
			<button type="button" class="btn btn-primary" id="helliosms-otp-verify">
				{l s='Verify' mod='helliosms'}
			</button>
		</div>

		<p class="helliosms-otp__message" id="helliosms-otp-message" aria-live="polite"></p>
	</div>

	<p class="helliosms-otp__ok"{if !$helliosms_verified} style="display:none"{/if}>
		{l s='Your phone number is verified.' mod='helliosms'}
	</p>
</section>
