{*
 * Hellio Messaging bulk SMS admin page.
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 *}
<div class="panel">
	<div class="panel-heading">
		<i class="icon-bullhorn"></i> {l s='Bulk SMS' mod='helliosms'}
	</div>
	<form id="hellio-bulk-form" class="form-horizontal" action="{$hellio_form_action|escape:'html':'UTF-8'}" method="post">
		<div class="form-group">
			<label class="control-label col-lg-3">{l s='Message' mod='helliosms'}</label>
			<div class="col-lg-9">
				<textarea name="hellio_message" rows="4" maxlength="1600" class="form-control">{$hellio_message|escape:'html':'UTF-8'}</textarea>
				<p class="help-block">{l s='Up to 1600 characters. Sent in chunks of' mod='helliosms'} {$hellio_chunk|intval} {l s='recipients per request.' mod='helliosms'}</p>
			</div>
		</div>

		<div class="form-group">
			<label class="control-label col-lg-3">{l s='Audience' mod='helliosms'}</label>
			<div class="col-lg-9">
				<div class="radio">
					<label><input type="radio" name="hellio_audience" value="all" checked="checked" class="hellio-audience"> {l s='All customers' mod='helliosms'}</label>
				</div>
				<div class="radio">
					<label><input type="radio" name="hellio_audience" value="state" class="hellio-audience"> {l s='Customers with an order in status' mod='helliosms'}</label>
				</div>
				<div class="hellio-audience-state" style="margin: 8px 0 12px 20px; display: none;">
					<select name="hellio_order_state" class="form-control fixed-width-xxl">
						<option value="">{l s='Choose a status' mod='helliosms'}</option>
						{foreach from=$hellio_states item=state}
							<option value="{$state.id|intval}">{$state.name|escape:'html':'UTF-8'}</option>
						{/foreach}
					</select>
				</div>
				<div class="radio">
					<label><input type="radio" name="hellio_audience" value="list" class="hellio-audience"> {l s='Pasted list' mod='helliosms'}</label>
				</div>
				<div class="hellio-audience-list" style="margin: 8px 0 0 20px; display: none;">
					<textarea name="hellio_list" rows="4" class="form-control" placeholder="{l s='One number per line, or comma separated' mod='helliosms'}"></textarea>
				</div>
			</div>
		</div>

		<div class="panel-footer">
			<button type="submit" name="submitHellioBulk" value="1" class="btn btn-default pull-right">
				<i class="process-icon-envelope"></i> {l s='Send bulk SMS' mod='helliosms'}
			</button>
		</div>
	</form>
</div>

<script type="text/javascript">
	(function () {
		function sync() {
			var value = document.querySelector('input[name="hellio_audience"]:checked');
			value = value ? value.value : 'all';
			var stateBox = document.querySelector('.hellio-audience-state');
			var listBox = document.querySelector('.hellio-audience-list');
			if (stateBox) { stateBox.style.display = (value === 'state') ? 'block' : 'none'; }
			if (listBox) { listBox.style.display = (value === 'list') ? 'block' : 'none'; }
		}
		var radios = document.querySelectorAll('.hellio-audience');
		for (var i = 0; i < radios.length; i++) {
			radios[i].addEventListener('change', sync);
		}
		sync();
	})();
</script>
