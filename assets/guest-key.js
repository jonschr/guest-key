(() => {
	'use strict';
	const { strings: s } = guestKey;
	let busy = false;
	let notice;
	let dialog;
	let dismissTimer;
	let returnFocus;
	let access = guestKey.access;
	let copiedTimer;
	const toolbarItem = document.querySelector('#wp-admin-bar-guest-key-access');
	const toolbarLink = toolbarItem?.querySelector(':scope > .ab-item');
	const accessSettings = document.querySelector('[data-guest-key-access-settings]');
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
		const response = await fetch(guestKey.ajaxUrl, {
			method: 'POST', credentials: 'same-origin', cache: 'no-store',
			body: new URLSearchParams({ ...values, action, nonce: guestKey.nonce })
		});
		let result;
		try { result = await response.json(); } catch { throw new Error(s.error); }
		if (result.data?.access) access = result.data.access;
		if (!response.ok || !result.success) {
			const error = new Error(result.data?.message || s.error);
			throw error;
		}
		return result.data;
	}

	function renderAccess() {
		if (!accessSettings) return;
		const heading = document.createElement('h2'); heading.textContent = s.accessTitle;
		heading.id = 'guest-key-access-settings-title';
		const intro = document.createElement('p');
		const feedback = document.createElement('p'); feedback.className = 'guest-key-access-feedback';
		feedback.setAttribute('role', 'status'); feedback.setAttribute('aria-live', 'polite');
		accessSettings.replaceChildren(heading, intro, feedback);
		if (access.needsSetup) {
			intro.remove();
			const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = s.claimControl;
			button.addEventListener('click', async () => {
				if (button.disabled) return;
				button.disabled = true;
				try { await request('guest_key_claim_control'); location.reload(); }
				catch (error) { feedback.textContent = error.message; button.disabled = false; }
			});
			accessSettings.append(button); return;
		}
		if (access.restricted) {
			heading.textContent = s.accessNotEnabled;
			intro.textContent = access.contactEmail ? s.accessContact.replace('%s', access.contactEmail) : s.accessContactFallback;
			return;
		}
		intro.remove();
		const choices = [];
		let timer, saving = false, transferButton;
		function sync() {
			if (transferButton) transferButton.disabled = saving;
			choices.forEach(({id, enable, hide}) => {
				const admin = access.administrators.find(admin => admin.id === id);
				enable.checked = !!(admin?.installer || admin?.enabled); hide.checked = !enable.checked;
				enable.disabled = hide.disabled = !access.canManageAccess || !!admin?.installer;
			});
		}
		async function save() {
			if (saving) return false;
			saving = true;
			if (transferButton) transferButton.disabled = true;
			const changes = choices.filter(({id, enable}) => enable.checked !== !!access.administrators.find(admin => admin.id === id)?.enabled)
				.map(({id, enable}) => ({id, enabled: enable.checked}))
				.sort((a, b) => Number(a.id === access.userId) - Number(b.id === access.userId));
			// Keep the focused radio in place while locking edits until the queue finishes.
			accessSettings.setAttribute('aria-busy', 'true');
			try {
				for (const {id, enabled} of changes) {
					await request('guest_key_set_access', {user: String(id), enabled: enabled ? '1' : '0'});
					if (access.restricted || !access.canManageAccess) { clearTimeout(timer); location.reload(); return; }
				}
				feedback.textContent = s.accessSaved;
				return true;
			} catch (error) { feedback.textContent = error.message || s.error; return false; }
			finally { saving = false; accessSettings.setAttribute('aria-busy', 'false'); sync(); }
		}
		function changed(enable, hide, enabled) {
			if (saving) { sync(); return; }
			enable.checked = enabled; hide.checked = !enabled;
			clearTimeout(timer); timer = setTimeout(save, 1500);
		}
		(access.administrators || []).forEach(admin => {
			const row = document.createElement('div'); row.className = 'guest-key-admin-row';
			row.setAttribute('role', 'radiogroup'); row.setAttribute('aria-label', admin.name);
			const name = document.createElement('strong'); name.textContent = admin.name; row.append(name);
			function choice(label, attribute) {
				const field = document.createElement('label'); const input = document.createElement('input');
				input.type = 'radio'; input.name = `guest-key-access-${admin.id}`; input.setAttribute(attribute, '');
				field.append(input, ' ', label); row.append(field); return input;
			}
			const enable = choice(s.shareAccess, 'data-guest-key-enable'); const hide = choice(s.hideToolbar, 'data-guest-key-hide');
			enable.addEventListener('change', () => changed(enable, hide, true)); hide.addEventListener('change', () => changed(enable, hide, false));
			choices.push({id: admin.id, enable, hide}); accessSettings.append(row);
		});
		const candidates = (access.administrators || []).filter(admin => !admin.installer && admin.canOwn);
		if (access.isInstaller && candidates.length) {
			const form = document.createElement('form'); form.className = 'guest-key-owner-form';
			const label = document.createElement('label'); label.textContent = s.owner; label.htmlFor = 'guest-key-owner';
			const select = document.createElement('select'); select.id = 'guest-key-owner'; select.required = true;
			const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = s.selectAdministrator; select.append(placeholder);
			candidates.forEach(admin => { const option = document.createElement('option'); option.value = String(admin.id); option.textContent = admin.name; select.append(option); });
			transferButton = document.createElement('button'); transferButton.type = 'submit'; transferButton.className = 'button'; transferButton.textContent = s.changeOwner;
			form.append(label, select, transferButton); accessSettings.append(form);
			form.addEventListener('submit', async event => {
				event.preventDefault(); if (saving || !select.value) return;
				clearTimeout(timer);
				if (!await save()) return;
				saving = true; sync(); select.disabled = true;
				try { await request('guest_key_change_owner', {user: select.value}); location.reload(); }
				catch (error) { feedback.textContent = error.message || s.error; }
				finally { saving = false; select.disabled = false; sync(); }
			});
		}
		sync();
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
			if (copied) confirmCopy();
			status(`${copied ? s.copied : s.created} ${expiryMessage(data.expires)}${data.mcp.ready ? '' : ` ${s.mcpUnavailable}`}`, false);
			if (!copied) fallback(data.bundle, data.expires);
		} catch (error) {
			setBusy(false);
			status(error.message || s.error, true);
		}
	}

	async function revokeAccess(event) {
		event?.preventDefault();
		if (busy) return;
		setBusy(true);
		try {
			await request('guest_key_revoke');
			closeDialog();
			const grantStatus = document.getElementById('guest-key-grant-status');
			if (grantStatus) grantStatus.textContent = s.revoked;
			updateRevokeState(false);
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
			await navigator.clipboard.writeText(field.value);
			status(s.diagnosticsCopied);
		} catch {
			field.focus();
			field.select();
			status(s.diagnosticsSelect);
		} finally {
			button.disabled = false;
		}
	});

	toolbarLink?.setAttribute('aria-controls', 'wp-admin-bar-guest-key-access-default');
	toolbarLink?.setAttribute('aria-expanded', 'false');
	toolbarLink?.addEventListener('click', createAccess);
	toolbarRevoke?.querySelector('.ab-item')?.addEventListener('click', revokeAccess);
	toolbarItem?.addEventListener('mouseenter', () => { if (notice) notice.hidden = true; });
	toolbarLink?.addEventListener('keydown', event => {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			if (notice) notice.hidden = true;
			toolbarItem.classList.add('hover'); toolbarLink.setAttribute('aria-expanded', 'true'); settingsLink?.focus();
		}
	});
	window.addEventListener('resize', positionStatus);
	window.addEventListener('scroll', positionStatus, {passive: true});
	if (accessSettings) renderAccess();
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
