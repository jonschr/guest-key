(() => {
	'use strict';
	const { strings: s } = guestKey;
	let busy = false;
	let notice;
	let dialog;
	let dismissTimer;
	let returnFocus;
	let licensing = guestKey.licensing;
	let licensePanel;
	let copiedTimer;
	let purchaseCheck;
	let purchaseCheckedAt = 0;
	const toolbarItem = document.querySelector('#wp-admin-bar-guest-key-access');
	const toolbarLink = toolbarItem?.querySelector(':scope > .ab-item');
	const licenseSettings = document.querySelector('[data-guest-key-license-settings]');
	const settingsLink = document.querySelector('#wp-admin-bar-guest-key-settings > .ab-item');
	const toolbarRevoke = document.querySelector('#wp-admin-bar-guest-key-revoke');
	const initialGrantStatus = document.getElementById('guest-key-grant-status');
	if (initialGrantStatus?.dataset.guestKeyExpires) initialGrantStatus.textContent = expiryMessage(initialGrantStatus.dataset.guestKeyExpires);

	function expiryMessage(value) {
		const expires = new Date(value);
		const now = new Date();
		const today = expires.toDateString() === now.toDateString();
		const tomorrow = expires.toDateString() === new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1).toDateString();
		const time = expires.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', timeZoneName: 'short', ...(!today && !tomorrow ? { weekday: 'long' } : {}) });
		return (today ? s.expires : tomorrow ? s.expiresTomorrow : s.expiresDay).replace('%s', time);
	}
	function positionStatus() {
		if (!notice || notice.hidden) return;
		const rect = (toolbarLink || document.querySelector('[data-guest-key-create]'))?.getBoundingClientRect();
		const left = Math.max(8, Math.min(rect?.left || 8, innerWidth - notice.offsetWidth - 8));
		const top = Math.max(8, rect?.bottom || 32);
		notice.style.left = `${left}px`; notice.style.top = `${top}px`;
		notice.style.maxHeight = `${Math.max(100, innerHeight - top - 8)}px`;
	}

	function updateRevokeState(active) {
		document.querySelectorAll('[data-guest-key-revoke]').forEach(button => { button.hidden = !active; });
		toolbarRevoke?.classList.toggle('guest-key-no-access', !active);
	}

	function confirmCopy() {
		if (!toolbarLink) return;
		clearTimeout(copiedTimer);
		toolbarItem.classList.add('guest-key-copied');
		toolbarLink.querySelector('.ab-label').textContent = s.copiedLabel;
		copiedTimer = setTimeout(() => {
			toolbarItem.classList.remove('guest-key-copied');
			toolbarLink.querySelector('.ab-label').textContent = s.copyLabel;
		}, 2500);
	}

	function status(message, error = false) {
		clearTimeout(dismissTimer);
		if (!notice) {
			notice = document.createElement('div');
			notice.className = 'guest-key-toast';
			notice.id = 'guest-key-status-panel';
			notice.setAttribute('role', 'status');
			notice.setAttribute('aria-live', 'polite');
			document.body.append(notice);
		}
		notice.replaceChildren();
		notice.removeAttribute('aria-label');
		notice.setAttribute('role', 'status');
		notice.setAttribute('aria-live', 'polite');
		notice.classList.toggle('guest-key-error', error);
		notice.hidden = false;
		const text = document.createElement('span');
		text.textContent = message;
		notice.append(text);

		positionStatus();
		if (!busy) dismissTimer = setTimeout(() => { notice.hidden = true; positionStatus(); }, 15000);
	}

	async function request(action, values = {}) {
		// Finish an outstanding purchase claim before changing the saved license.
		if (action.startsWith('guest_key_license_') && action !== 'guest_key_license_status') await purchaseCheck;
		const response = await fetch(guestKey.ajaxUrl, {
			method: 'POST', credentials: 'same-origin', cache: 'no-store',
			body: new URLSearchParams({ ...values, action, nonce: guestKey.nonce })
		});
		let result;
		try { result = await response.json(); } catch { throw new Error(s.error); }
		if (result.data?.licensing) licensing = result.data.licensing;
		if (!response.ok || !result.success) {
			const error = new Error(result.data?.message || s.error);
			error.licensing = result.data?.licensing;
			throw error;
		}
		return result.data;
	}

	function licenseNotice(message = '', editEmail = false, takeFocus = true) {
		if (!licenseSettings) { location.assign(guestKey.settingsUrl + '#guest-key-license-settings'); return; }
		clearTimeout(dismissTimer);
		if (notice) notice.hidden = true;
		licensePanel = licenseSettings;
		licensePanel.closest('#guest-key-license-settings').classList.toggle('guest-key-license-attention', !licensing?.active);

		if (message === s.codeSent) message = s.codeSent.replace('%s', licensing.email);
		const title = licensing?.kind === 'paid' || licensing?.kind === 'shared' ? s.licenseSettings : licensing?.active ? s.trialTitle : s.licenseTitle;
		licensePanel.removeAttribute('aria-live');
		const heading = document.createElement('h2'); heading.textContent = title;
		heading.id = 'guest-key-license-settings-title';
		const intro = document.createElement('p');
		intro.textContent = licensing?.active ? (licensing.kind === 'paid' ? s.licenseActive : s.trialActive)
			: licensing?.kind === 'paid' ? (licensing.expired ? s.licenseExpired : s.licenseCheckNeeded)
				: licensing?.expired ? (licensing.email ? s.trialExpiredEmail.replace('%s', licensing.email) : s.trialExpired) : s.trialIntro;
		if (licensing?.sharedBy?.length && !licensing.ownActive) intro.textContent = s.sharedAccess.replace('%s', licensing.sharedBy.join(', '));
		const feedback = document.createElement('p'); feedback.className = 'guest-key-license-feedback';
		feedback.setAttribute('role', 'status'); feedback.setAttribute('aria-live', 'polite'); feedback.textContent = message === intro.textContent ? '' : message;
		licensePanel.replaceChildren(heading, intro, feedback);
		async function run(callback) {
			if (busy) return;
			setBusy(true); licensePanel.querySelectorAll('button, input').forEach(item => { item.disabled = true; }); feedback.textContent = s.working;
			try { await callback(); }
			catch (error) { feedback.textContent = error.message || s.error; }
			finally { setBusy(false); licensePanel.querySelectorAll('button, input').forEach(item => { item.disabled = false; }); }
		}
		function action(label, callback, parent = licensePanel) {
			const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
			button.className = 'button';
			button.addEventListener('click', () => run(callback)); parent.append(button); return button;
		}
		function form(label, type, submit, callback, parent = licensePanel) {
			const form = document.createElement('form'); const fieldLabel = document.createElement('label'); fieldLabel.textContent = label;
			const input = document.createElement('input'); input.type = type === 'code' ? 'text' : type; input.required = true; input.setAttribute('aria-label', label);
			input.autocomplete = type === 'email' ? 'email' : type === 'code' ? 'one-time-code' : 'off';
			if (type === 'code') { input.inputMode = 'numeric'; input.pattern = '[0-9]{6}'; input.maxLength = 6; }
			if (type === 'email') input.value = licensing?.email || '';
			if (type === 'text') {
				input.maxLength = 200; input.spellcheck = false; input.autocapitalize = 'off'; input.setAttribute('autocorrect', 'off');
			}
			input.className = 'regular-text'; fieldLabel.append(document.createElement('br'));
			fieldLabel.append(input);
			const button = document.createElement('button'); button.type = 'submit'; button.textContent = submit;
			button.className = 'button button-primary';
			const fieldRow = document.createElement('p'); fieldRow.append(fieldLabel);
			const submitRow = document.createElement('p'); submitRow.append(button); form.append(fieldRow, submitRow);
			form.addEventListener('submit', event => { event.preventDefault(); run(() => callback(input.value)); }); parent.append(form); return input;
		}
		const keyColumn = document.createElement('div');
		const emailColumn = document.createElement('div');
		const showTrial = !licensing?.active && licensing?.kind !== 'paid' && !licensing?.expired;
		if (licensing?.kind !== 'paid') {
			const columns = document.createElement('div'); columns.className = 'guest-key-license-columns';
			columns.append(keyColumn);
			if (showTrial) columns.append(emailColumn);
			licensePanel.append(columns);
		}
		let focus;
		if (showTrial) {
			if (licensing?.pending && !editEmail) {
				if (!message) feedback.textContent = s.codeSent.replace('%s', licensing.email);
				focus = form(s.code, 'code', s.verifyCode, async code => { await request('guest_key_license_verify', {code}); licenseNotice(s.trialActive); }, emailColumn);
				action(s.changeEmail, async () => { licenseNotice('', true); }, emailColumn);
			} else {
				focus = form(s.email, 'email', s.sendCode, async email => { await request('guest_key_license_email', {email, fresh: editEmail ? '1' : '0'}); licenseNotice(s.codeSent); }, emailColumn);
			}
		}
		if (licensing?.kind === 'paid') {
			action(s.checkLicense, async () => { await request('guest_key_license_status'); licenseNotice(); });
			action(s.removeLicense, async () => { await request('guest_key_license_remove'); licenseNotice(); });
		} else {
			const input = form(s.licenseKey, 'text', s.activateLicense, async key => { await request('guest_key_license_activate', {key}); licenseNotice(s.licenseActive); }, keyColumn);
			focus ||= input;
		}
		if (licensing?.kind === 'paid' && licensing.administrators?.length) {
			const fieldset = document.createElement('fieldset');
			const legend = document.createElement('legend'); legend.textContent = s.shareLicense;
			const description = document.createElement('p'); description.textContent = s.shareIntro;
			fieldset.append(legend, description);
			const choices = [];
			licensing.administrators.forEach(admin => {
				const label = document.createElement('label');
				const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.value = admin.id; checkbox.checked = admin.shared;
				choices.push(checkbox); label.append(checkbox, ' ', admin.name);
				const row = document.createElement('p'); row.append(label); fieldset.append(row);
			});
			action(s.saveSharing, async () => {
				const users = choices.filter(input => input.checked).map(input => Number(input.value));
				await request('guest_key_license_share', {users: JSON.stringify(users)}); licenseNotice(s.sharingSaved, false, false);
			}, fieldset);
			licensePanel.append(fieldset);
		}
		if (licensing?.legacyClaim) {
			const explanation = document.createElement('p'); explanation.textContent = s.claimIntro; licensePanel.append(explanation);
			action(s.claimExisting, async () => { await request('guest_key_license_claim_existing'); licenseNotice(); });
		}
		const checkout = document.createElement('a'); checkout.textContent = licensing?.kind === 'paid' ? (licensing.ownActive ? s.manageBilling : s.renewLicense) : s.buyLicense;
		checkout.href = licensing?.kind === 'paid' ? licensing.renew : licensing.checkout;
		checkout.className = 'button';
		if (licensing?.kind !== 'paid') {
			const icon = document.createElement('span'); icon.textContent = '↗'; icon.setAttribute('aria-hidden', 'true');
			checkout.append(' ', icon);
		}
		checkout.target = '_blank'; checkout.rel = 'noopener noreferrer'; (licensing?.kind === 'paid' ? licensePanel : keyColumn).append(checkout);
		if (licensing?.email && licensing?.kind === 'trial') {
			const match = document.createElement('p'); match.textContent = s.automaticLicense.replace('%s', licensing.email); licensePanel.append(match);
		}
		positionStatus();
		if (takeFocus) focus?.focus();
	}

	function setBusy(value) {
		busy = value;
		document.querySelectorAll('[data-guest-key-create], [data-guest-key-revoke]').forEach(button => { button.disabled = value || button.hasAttribute('data-guest-key-unavailable'); });
		toolbarLink?.setAttribute('aria-busy', String(value));
		toolbarRevoke?.querySelector('.ab-item')?.setAttribute('aria-disabled', String(value));
		toolbarItem?.classList.toggle('guest-key-copying', value);
	}

	function closeDialog() {
		if (!dialog) return;
		dialog.remove();
		dialog = null;
		returnFocus?.focus();
	}

	function fallback(bundle, expires) {
		closeDialog();
		returnFocus = document.activeElement;
		dialog = document.createElement('dialog');
		dialog.className = 'guest-key-dialog';
		dialog.setAttribute('aria-labelledby', 'guest-key-dialog-title');
		const heading = document.createElement('h2');
		heading.id = 'guest-key-dialog-title';
		heading.textContent = s.title;
		const explanation = document.createElement('p');
		explanation.textContent = `${s.fallback} ${expiryMessage(expires)}`;
		const field = document.createElement('textarea');
		field.readOnly = true;
		field.value = bundle;
		field.setAttribute('aria-label', s.copy);
		field.spellcheck = false;
		const copy = document.createElement('button');
		copy.type = 'button';
		copy.className = 'button button-primary';
		copy.textContent = s.copy;
		copy.addEventListener('click', async () => {
			try {
				await navigator.clipboard.writeText(field.value);
				closeDialog(); confirmCopy();
				status(`${s.copied} ${expiryMessage(expires)}`, false);
			} catch {
				field.focus();
				field.select();
				explanation.textContent = s.manualCopy;
			}
		});
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'button';
		close.textContent = s.close;
		close.addEventListener('click', closeDialog);
		dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(); });
		dialog.append(heading, explanation, field, copy, close);
		document.body.append(dialog);
		dialog.showModal();
		field.focus();
		field.select();
	}

	function inspectAbility(event) {
		const button = event.currentTarget;
		const template = button.closest('[data-guest-key-item]').querySelector('[data-guest-key-ability-content]');
		closeDialog();
		returnFocus = button;
		dialog = document.createElement('dialog');
		dialog.className = 'guest-key-dialog guest-key-ability-dialog';
		dialog.setAttribute('aria-labelledby', 'guest-key-dialog-title');
		const content = template.content.cloneNode(true);
		content.querySelector('h2').id = 'guest-key-dialog-title';
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'button';
		close.textContent = s.close;
		close.autofocus = true;
		close.addEventListener('click', closeDialog);
		dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(); });
		const header = document.createElement('div');
		header.className = 'guest-key-dialog-header';
		header.append(content.querySelector('h2'), close);
		dialog.append(header, content);
		document.body.append(dialog);
		dialog.showModal();
	}

	async function createAccess(event) {
		event.preventDefault();
		if (busy) return;
		if (!licensing?.active) {
			setBusy(true);
			try { await request('guest_key_license_status'); } catch { /* Manual entry remains available. */ }
			setBusy(false); licenseNotice(); return;
		}
		closeDialog();
		setBusy(true);
		status(s.creating);
		const pending = request('guest_key_create');
		// Start the clipboard write during the user gesture; resolve its contents after
		// credential creation. This preserves one-click copying on browsers such as Safari.
		let clipboard;
		if (navigator.clipboard?.write && window.ClipboardItem) {
			try {
				clipboard = navigator.clipboard.write([new ClipboardItem({
					'text/plain': pending.then(data => new Blob([data.bundle], { type: 'text/plain' }))
				})]).then(() => true, () => false);
			} catch { /* Use writeText or the explicit-copy dialog below. */ }
		}
		try {
			const data = await pending;
			let copied = clipboard ? await clipboard : false;
			if (!copied) {
				try { await navigator.clipboard.writeText(data.bundle); copied = true; } catch { /* Show a manual fallback. */ }
			}
			setBusy(false);
			const grantStatus = document.getElementById('guest-key-grant-status');
			if (grantStatus) grantStatus.textContent = expiryMessage(data.expires);
			updateRevokeState(true);
			const adapterStatus = document.getElementById('guest-key-adapter-status');
			if (adapterStatus) adapterStatus.textContent = data.mcp.ready ? s.active : s.mcpUnavailable;
			const endpoint = document.getElementById('guest-key-mcp-endpoint');
			if (endpoint) endpoint.hidden = !data.mcp.ready;
			if (copied) confirmCopy();
			status(`${copied ? s.copied : s.created} ${expiryMessage(data.expires)}${data.mcp.ready ? '' : ` ${s.mcpUnavailable}`}`, false);
			if (!copied) fallback(data.bundle, data.expires);
		} catch (error) {
			setBusy(false);
			if (error.licensing) licenseNotice(error.message);
			else status(error.message || s.error, true);
		}
	}

	async function revokeAccess(event) {
		event?.preventDefault();
		if (busy) return;
		setBusy(true);
		try {
			const data = await request('guest_key_revoke');
			closeDialog();
			const grantStatus = document.getElementById('guest-key-grant-status');
			if (grantStatus) grantStatus.textContent = s.revoked;
			updateRevokeState(false);
			const adapterStatus = document.getElementById('guest-key-adapter-status');
			if (adapterStatus) adapterStatus.textContent = data.adapter_active ? s.active : s.inactive;
			const endpoint = document.getElementById('guest-key-mcp-endpoint');
			if (endpoint) endpoint.hidden = !data.adapter_active;
			setBusy(false);
			status(s.revoked);
		} catch (error) {
			setBusy(false);
			status(error.message || s.error, true);
		}
	}

	document.querySelector('[data-guest-key-diagnostics]')?.addEventListener('click', async event => {
		const button = event.currentTarget;
		const field = document.getElementById('guest-key-diagnostic-report');
		button.disabled = true;
		try {
			field.value = JSON.stringify(await request('guest_key_diagnostics'), null, 2);
			try {
				await navigator.clipboard.writeText(field.value);
				status(s.diagnosticsCopied);
			} catch {
				field.focus();
				field.select();
				status(s.diagnosticsSelect);
			}
		} catch (error) {
			status(error.message || s.error, true);
		} finally {
			button.disabled = false;
		}
	});

	toolbarLink?.setAttribute('aria-controls', 'wp-admin-bar-guest-key-access-default');
	toolbarLink?.setAttribute('aria-expanded', 'false');
	toolbarLink?.addEventListener('click', createAccess);
	toolbarRevoke?.querySelector('.ab-item')?.addEventListener('click', revokeAccess);
	toolbarItem?.addEventListener('mouseenter', () => { if (notice) notice.hidden = true; refreshPurchase(); });
	toolbarLink?.addEventListener('focus', refreshPurchase);
	toolbarLink?.addEventListener('keydown', event => {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			if (notice) notice.hidden = true;
			toolbarItem.classList.add('hover'); toolbarLink.setAttribute('aria-expanded', 'true'); settingsLink?.focus();
		}
	});
	window.addEventListener('resize', positionStatus);
	window.addEventListener('scroll', positionStatus, {passive: true});
	if (licenseSettings) licenseNotice('', false, false);
	function refreshPurchase() {
		if (purchaseCheck) return purchaseCheck;
		if (busy || licensing?.kind !== 'trial' || document.hidden || Date.now() - purchaseCheckedAt < 15000) return;
		purchaseCheckedAt = Date.now();
		purchaseCheck = (async () => {
			try {
				const wasActive = licensing.active;
				await request('guest_key_license_status');
				if (licensing.kind === 'paid' || wasActive !== licensing.active) {
					if (!busy && licensePanel && !licensePanel.hidden) licenseNotice('', false, false);
				}
			} catch { /* Keep the saved trial and manual activation available during an outage. */ }
		})().finally(() => { purchaseCheck = null; });
		return purchaseCheck;
	}
	refreshPurchase();
	setInterval(refreshPurchase, 60000);
	document.addEventListener('visibilitychange', refreshPurchase);
	window.addEventListener('focus', refreshPurchase);
	document.querySelectorAll('[data-guest-key-create]').forEach(button => button.addEventListener('click', createAccess));
	document.querySelectorAll('[data-guest-key-revoke]').forEach(button => button.addEventListener('click', revokeAccess));
	document.querySelectorAll('[data-guest-key-inspect]').forEach(button => button.addEventListener('click', inspectAbility));
	function durationLabel(minutes) {
		const hours = Math.floor(minutes / 60);
		const remainder = minutes % 60;
		return [
			hours ? (hours === 1 ? s.durationHour : s.durationHours).replace('%s', hours) : '',
			remainder ? (remainder === 1 ? s.durationMinute : s.durationMinutes).replace('%s', remainder) : ''
		].filter(Boolean).join(' ');
	}
	const durationSlider = document.getElementById('guest-key-duration-minutes');
	if (durationSlider) {
		const updateDuration = () => {
			const minutes = Number(durationSlider.value);
			const text = durationLabel(minutes);
			document.getElementById('guest-key-duration-value').textContent = text;
			durationSlider.setAttribute('aria-valuetext', text);
		};
		durationSlider.addEventListener('input', updateDuration);
		updateDuration();
	}

	document.getElementById('guest-key-search')?.addEventListener('input', event => {
		const query = event.target.value.toLocaleLowerCase().trim();
		let visible = 0;
		document.querySelectorAll('[data-guest-key-item]').forEach(row => {
			const details = row.querySelector('[data-guest-key-ability-content]');
			row.hidden = !(row.textContent + (details?.content.textContent || '')).toLocaleLowerCase().includes(query);
			if (!row.hidden) visible++;
		});
		document.querySelectorAll('[data-guest-key-section]').forEach(section => {
			section.hidden = !!query && !section.querySelector('[data-guest-key-item]:not([hidden])');
		});
		document.getElementById('guest-key-empty').hidden = visible !== 0;
		document.getElementById('guest-key-search-count').textContent = `${visible} shown`;
	});
})();
