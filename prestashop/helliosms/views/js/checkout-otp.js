/**
 * Hellio Messaging checkout OTP script.
 *
 * Drives the "Send code" and "Verify" buttons. The API token never touches the
 * browser: every call goes to the module front controller, which talks to
 * Hellio server-side. On success the checkout can proceed; the server also
 * enforces verification independently.
 *
 * @author Hellio Solutions
 */
(function () {
	'use strict';

	if (typeof helliosmsOtp === 'undefined') {
		return;
	}

	function $(id) {
		return document.getElementById(id);
	}

	function setMessage(text, isError) {
		var el = $('helliosms-otp-message');
		if (!el) {
			return;
		}
		el.textContent = text || '';
		el.className = 'helliosms-otp__message' + (isError ? ' helliosms-otp__message--error' : ' helliosms-otp__message--ok');
	}

	function disable(el, state) {
		if (el) {
			el.disabled = !!state;
		}
	}

	function post(url, data, done) {
		var body = 'token=' + encodeURIComponent(helliosmsOtp.token);
		Object.keys(data).forEach(function (key) {
			body += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
		});

		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) {
				return;
			}
			var payload = { success: false, message: '' };
			try {
				payload = JSON.parse(xhr.responseText);
			} catch (e) {
				payload.message = 'Unexpected response. Please try again.';
			}
			done(payload);
		};
		xhr.send(body);
	}

	function markVerified() {
		var root = $('helliosms-otp');
		if (root) {
			root.setAttribute('data-verified', '1');
			root.classList.add('helliosms-otp--verified');
		}
		var body = document.querySelector('.helliosms-otp__body');
		if (body) {
			body.style.display = 'none';
		}
		var ok = document.querySelector('.helliosms-otp__ok');
		if (ok) {
			ok.style.display = 'block';
		}
	}

	function onSend() {
		var phoneEl = $('helliosms-otp-phone');
		var btn = $('helliosms-otp-send');
		disable(btn, true);
		setMessage('', false);

		post(helliosmsOtp.sendUrl, { phone: phoneEl ? phoneEl.value : '' }, function (res) {
			disable(btn, false);
			setMessage(res.message, !res.success);
			if (res.success) {
				var row = $('helliosms-otp-verify-row');
				if (row) {
					row.style.display = 'block';
				}
				var code = $('helliosms-otp-code');
				if (code) {
					code.focus();
				}
			}
		});
	}

	function onVerify() {
		var phoneEl = $('helliosms-otp-phone');
		var codeEl = $('helliosms-otp-code');
		var btn = $('helliosms-otp-verify');
		disable(btn, true);
		setMessage('', false);

		post(
			helliosmsOtp.verifyUrl,
			{ phone: phoneEl ? phoneEl.value : '', code: codeEl ? codeEl.value : '' },
			function (res) {
				disable(btn, false);
				setMessage(res.message, !res.success);
				if (res.success) {
					markVerified();
				}
			}
		);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var sendBtn = $('helliosms-otp-send');
		var verifyBtn = $('helliosms-otp-verify');
		if (sendBtn) {
			sendBtn.addEventListener('click', onSend);
		}
		if (verifyBtn) {
			verifyBtn.addEventListener('click', onVerify);
		}
	});
})();
