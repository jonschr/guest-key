(() => {
	'use strict';
	const { strings: s } = guestKey;
	let busy = false;
	let notice;
	let dialog;
	let dismissTimer;
	let returnFocus;
	let licensing = guestKey.licensing;
	let noticeReturnFocus;
	let licensePanel;
	let hoverTimer;
	let panelPinned = false;
	const toolbarItem = document.querySelector('#wp-admin-bar-guest-key-access');
	const toolbarLink = toolbarItem?.querySelector(':scope > .ab-item');
	const licenseSettings = document.querySelector('[data-guest-key-license-settings]');
	const settingsLink = document.querySelector('#wp-admin-bar-guest-key-settings > .ab-item');
	const dismissalKey = `guest-key-dismissed:${guestKey.noticeKey}`;
	const dismissed = () => { try { return Number(localStorage.getItem(dismissalKey)) > Date.now(); } catch { return false; } };
	function positionLicensePanel() {
		const panels = [licensePanel, notice].filter(panel => panel && panel !== licenseSettings && !panel.hidden);
		const open = panels.length > 0;
		toolbarLink?.setAttribute('aria-controls', open ? panels.map(panel => panel.id).join(' ') : 'wp-admin-bar-guest-key-access-default');
		toolbarLink?.setAttribute('aria-expanded', String(open || toolbarItem?.classList.contains('hover')));
		toolbarItem?.classList.toggle('guest-key-panel-open', open);
		for (const panel of panels) {
			const anchor = toolbarLink || document.querySelector('[data-guest-key-create]');
			const rect = anchor?.getBoundingClientRect();
			const left = Math.max(8, Math.min(rect?.left || 8, innerWidth - panel.offsetWidth - 8));
			const top = Math.max(8, rect?.bottom || 32);
			panel.style.left = `${left}px`;
			panel.style.top = `${top}px`;
			panel.style.maxHeight = `${Math.max(100, innerHeight - top - 8)}px`;
		}
	}

	function hideLicensePanel(remember = false) {
		if (!licensePanel || licenseSettings) return;
		licensePanel.hidden = true;
		panelPinned = false;
		positionLicensePanel();
		if (remember) { try { localStorage.setItem(dismissalKey, String(Date.now() + 86400000)); } catch { /* Storage may be disabled. */ } }
	}
	function leaveLicensePanel() {
		if (licenseSettings) return;
		clearTimeout(hoverTimer);
		if (!panelPinned) hoverTimer = setTimeout(() => {
			if (!licensePanel?.contains(document.activeElement)) hideLicensePanel();
		}, 200);
	}

	function status(message, error = false, revoke = false) {
		hideLicensePanel();
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
		notice.classList.remove('guest-key-license-notice');
		notice.removeAttribute('aria-label');
		notice.setAttribute('role', 'status');
		notice.setAttribute('aria-live', 'polite');
		notice.classList.toggle('guest-key-error', error);
		notice.hidden = false;
		const text = document.createElement('span');
		text.textContent = message;
		notice.append(text);
		if (revoke) {
			const button = document.createElement('button');
			button.type = 'button';
			button.textContent = s.revoke;
			button.addEventListener('click', revokeAccess);
			notice.append(button);
		}
		positionLicensePanel();
		if (!busy) dismissTimer = setTimeout(() => { notice.hidden = true; positionLicensePanel(); }, 15000);
	}

	async function request(action, values = {}) {
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
		if (licensing?.kind === 'paid' && licensing.active && !licenseSettings) {
			const restoreFocus = licensePanel?.contains(document.activeElement);
			hideLicensePanel();
			licensePanel?.replaceChildren();
			if (restoreFocus) toolbarLink?.focus();
			if (message) status(message);
			return;
		}
		clearTimeout(dismissTimer);
		if (notice) notice.hidden = true;
		if (!licensePanel?.contains(document.activeElement)) noticeReturnFocus = document.activeElement;
		if (licenseSettings) {
			licensePanel = licenseSettings;
		} else if (!licensePanel) {
			licensePanel = document.createElement('div');
			licensePanel.id = 'guest-key-license-panel';
			licensePanel.className = 'guest-key-license-notice';
			licensePanel.setAttribute('role', 'region');
			licensePanel.addEventListener('mouseenter', () => clearTimeout(hoverTimer));
			licensePanel.addEventListener('mouseleave', leaveLicensePanel);
			licensePanel.addEventListener('focusout', leaveLicensePanel);
			licensePanel.addEventListener('keydown', event => {
				if (event.key === 'Escape') { event.preventDefault(); hideLicensePanel(true); noticeReturnFocus?.focus(); }
			});
			document.body.append(licensePanel);
		}
		licensePanel.hidden = false;
		panelPinned = takeFocus || panelPinned;

		if (message === s.codeSent) message = s.codeSent.replace('%s', licensing.email);
		const title = licensing?.kind === 'paid' ? s.licenseSettings : licensing?.active ? s.trialTitle : s.licenseTitle;
		if (!licenseSettings) { licensePanel.setAttribute('role', 'region'); licensePanel.setAttribute('aria-label', title); }
		licensePanel.removeAttribute('aria-live');
		const heading = document.createElement(licenseSettings ? 'h2' : 'strong'); heading.textContent = title;
		if (licenseSettings) heading.id = 'guest-key-license-settings-title';
		const close = document.createElement('button'); close.type = 'button'; close.className = 'guest-key-license-close'; close.textContent = s.close;
		close.addEventListener('click', () => { hideLicensePanel(true); noticeReturnFocus?.focus(); });
		const intro = document.createElement('p');
		intro.textContent = licensing?.active ? (licensing.kind === 'paid' ? s.licenseActive : s.trialActive)
			: licensing?.kind === 'paid' ? (licensing.expired ? s.licenseExpired : s.licenseCheckNeeded)
				: licensing?.expired ? s.trialExpired : s.trialIntro;
		const feedback = document.createElement('p'); feedback.className = 'guest-key-license-feedback';
		feedback.setAttribute('role', 'status'); feedback.setAttribute('aria-live', 'polite'); feedback.textContent = message === intro.textContent ? '' : message;
		licensePanel.replaceChildren(heading, ...(licenseSettings ? [] : [close]), intro, feedback);
		async function run(callback) {
			if (busy) return;
			setBusy(true); licensePanel.querySelectorAll('button, input').forEach(item => { item.disabled = true; }); feedback.textContent = s.working;
			try { await callback(); }
			catch (error) { feedback.textContent = error.message || s.error; }
			finally { setBusy(false); licensePanel.querySelectorAll('button, input').forEach(item => { item.disabled = false; }); }
		}
		function action(label, callback) {
			const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
			if (licenseSettings) button.className = 'button';
			button.addEventListener('click', () => run(callback)); licensePanel.append(button); return button;
		}
		function form(label, type, submit, callback) {
			const form = document.createElement('form'); const fieldLabel = document.createElement('label'); fieldLabel.textContent = label;
			let input = document.createElement('input'); input.type = type; input.required = true; input.setAttribute('aria-label', label);
			input.autocomplete = type === 'email' ? 'email' : type === 'text' ? 'one-time-code' : 'off';
			if (type === 'text') { input.inputMode = 'numeric'; input.pattern = '[0-9]{6}'; input.maxLength = 6; }
			if (type === 'email') input.value = licensing?.email || '';
			if (type === 'password') {
				input.maxLength = 200; input.spellcheck = false; input.autocapitalize = 'off'; input.setAttribute('autocorrect', 'off');
			}
			if (licenseSettings) { input.className = 'regular-text'; fieldLabel.append(document.createElement('br')); }
			fieldLabel.append(input);
			const button = document.createElement('button'); button.type = 'submit'; button.textContent = submit;
			if (licenseSettings) {
				button.className = 'button button-primary';
				const fieldRow = document.createElement('p'); fieldRow.append(fieldLabel);
				const submitRow = document.createElement('p'); submitRow.append(button); form.append(fieldRow, submitRow);
			} else form.append(fieldLabel, button);
			if (type === 'password') {
				const reveal = document.createElement('button'); reveal.type = 'button'; reveal.textContent = s.showKey;
				reveal.setAttribute('aria-pressed', 'false'); reveal.title = s.showKeyHelp;
				if (licenseSettings) reveal.className = 'button';
				reveal.addEventListener('click', () => {
					const show = input.type === 'password';
					// Chrome remembers password fields; a fresh text input lets TextExpander work.
					const replacement = document.createElement('input');
					for (const attribute of input.attributes) if (attribute.name !== 'type') replacement.setAttribute(attribute.name, attribute.value);
					replacement.type = show ? 'text' : 'password'; replacement.value = input.value;
					input.replaceWith(replacement); input = replacement;
					reveal.textContent = show ? s.hideKey : s.showKey; reveal.setAttribute('aria-pressed', String(show));
					input.focus(); input.setSelectionRange(input.value.length, input.value.length);
				});
				fieldLabel.after(document.createTextNode(' '), reveal);
			}
			form.addEventListener('submit', event => { event.preventDefault(); run(() => callback(input.value)); }); licensePanel.append(form); return input;
		}
		let focus;
		if (licensing?.active && !licenseSettings) {
			focus = action(s.continueConnect, async () => { hideLicensePanel(); setBusy(false); await createAccess({preventDefault() {}}); });
		} else if (!licensing?.active && licensing?.kind !== 'paid' && !licensing?.expired) {
			if (licensing?.pending && !editEmail) {
				if (!message) feedback.textContent = s.codeSent.replace('%s', licensing.email);
				focus = form(s.code, 'text', s.verifyCode, async code => { await request('guest_key_license_verify', {code}); licenseNotice(s.trialActive); });
				action(s.changeEmail, async () => { licenseNotice('', true); });
			} else {
				focus = form(s.email, 'email', s.sendCode, async email => { await request('guest_key_license_email', {email, fresh: editEmail ? '1' : '0'}); licenseNotice(s.codeSent); });
			}
		}
		if (licensing?.kind === 'paid') {
			action(s.checkLicense, async () => { await request('guest_key_license_status'); licenseNotice(); });
			action(s.removeLicense, async () => { await request('guest_key_license_remove'); licenseNotice(); });
		} else {
			const input = form(s.licenseKey, 'password', s.activateLicense, async key => { await request('guest_key_license_activate', {key}); licenseNotice(s.licenseActive); });
			focus ||= input;
		}
		const checkout = document.createElement('a'); checkout.textContent = licensing?.kind === 'paid' ? (licensing.active ? s.manageBilling : s.renewLicense) : s.buyLicense;
		checkout.href = licensing?.kind === 'paid' ? licensing.renew : licensing.checkout;
		if (licenseSettings) checkout.className = 'button';
		checkout.target = '_blank'; checkout.rel = 'noopener noreferrer'; licensePanel.append(checkout);
		if (licensing?.email && licensing?.kind === 'trial') {
			const match = document.createElement('p'); match.textContent = s.automaticLicense.replace('%s', licensing.email); licensePanel.append(match);
		}
		if (licensing?.kind === 'trial') action(s.checkPurchase, async () => { await request('guest_key_license_status'); licenseNotice(licensing.kind === 'paid' ? s.licenseActive : s.purchasePending); });
		if (!licenseSettings) {
			const settings = document.createElement('a'); settings.href = guestKey.settingsUrl; settings.textContent = s.settings; licensePanel.append(settings);
		}
		positionLicensePanel();
		if (takeFocus) focus?.focus();
	}

	function setBusy(value) {
		busy = value;
		document.querySelectorAll('[data-guest-key-create], [data-guest-key-revoke]').forEach(button => { button.disabled = value || button.hasAttribute('data-guest-key-unavailable'); });
		const toolbar = document.querySelector('#wp-admin-bar-guest-key-access > .ab-item');
		if (toolbar) toolbar.setAttribute('aria-busy', String(value));
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
		explanation.textContent = `${s.fallback} ${s.expires}${expires}`;
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
				closeDialog();
				status(`${s.copied} ${s.expires}${expires}`, false, true);
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
		hideLicensePanel();
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
			if (grantStatus) grantStatus.textContent = `${s.created} ${s.expires}${data.expires_label}`;
			document.querySelectorAll('[data-guest-key-revoke]').forEach(button => { button.hidden = false; });
			const adapterStatus = document.getElementById('guest-key-adapter-status');
			if (adapterStatus) adapterStatus.textContent = data.mcp.ready ? s.active : s.mcpUnavailable;
			const endpoint = document.getElementById('guest-key-mcp-endpoint');
			if (endpoint) endpoint.hidden = !data.mcp.ready;
			status(`${copied ? s.copied : s.created} ${s.expires}${data.expires_label}${data.mcp.ready ? '' : ` ${s.mcpUnavailable}`}`, false, true);
			if (!copied) fallback(data.bundle, data.expires_label);
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
			document.querySelectorAll('[data-guest-key-revoke]').forEach(button => { button.hidden = true; });
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
	toolbarItem?.addEventListener('mouseenter', () => {
		clearTimeout(hoverTimer);
		if (licensing?.active && notice && !notice.hidden) { notice.hidden = true; positionLicensePanel(); }
		if (!busy && !licensing?.active && (!licensePanel || licensePanel.hidden)) licenseNotice('', false, false);
	});
	toolbarItem?.addEventListener('mouseleave', leaveLicensePanel);
	toolbarLink?.addEventListener('keydown', event => {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			if (licensing?.active) {
				if (notice) notice.hidden = true;
				positionLicensePanel();
				toolbarItem.classList.add('hover'); toolbarLink.setAttribute('aria-expanded', 'true'); settingsLink?.focus();
			} else licenseNotice();
		}
	});
	window.addEventListener('resize', positionLicensePanel);
	window.addEventListener('scroll', positionLicensePanel, {passive: true});
	document.addEventListener('pointerdown', event => {
		if (licensePanel && !licensePanel.hidden && !licensePanel.contains(event.target) && !toolbarItem?.contains(event.target)) hideLicensePanel();
	});
	if (licenseSettings) licenseNotice('', false, false);
	else if (!licensing?.active && !dismissed()) { licenseNotice('', false, false); panelPinned = true; }
	async function refreshPurchase() {
		if (busy || licensing?.kind !== 'trial' || document.hidden) return;
		try {
			const wasActive = licensing.active;
			await request('guest_key_license_status');
			if (licensing.kind === 'paid' || wasActive !== licensing.active) {
				if (licensePanel && !licensePanel.hidden) licenseNotice('', false, false);
			}
		} catch { /* Keep the saved trial and manual activation available during an outage. */ }
	}
	refreshPurchase();
	setInterval(refreshPurchase, 60000);
	document.addEventListener('visibilitychange', refreshPurchase);
	window.addEventListener('focus', refreshPurchase);
	document.querySelectorAll('[data-guest-key-create]').forEach(button => button.addEventListener('click', createAccess));
	document.querySelectorAll('[data-guest-key-revoke]').forEach(button => button.addEventListener('click', revokeAccess));
	document.querySelectorAll('[data-guest-key-inspect]').forEach(button => button.addEventListener('click', inspectAbility));
	function openInventory() {
		const inventory = document.getElementById('guest-key-inventory');
		if (inventory && location.hash === '#guest-key-inventory') inventory.open = true;
	}
	openInventory();
	window.addEventListener('hashchange', openInventory);
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
