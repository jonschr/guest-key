(() => {
	'use strict';
	const { strings: s } = guestKey;
	let busy = false;
	let notice;
	let dialog;
	let dismissTimer;
	let returnFocus;

	function status(message, error = false, revoke = false) {
		clearTimeout(dismissTimer);
		if (!notice) {
			notice = document.createElement('div');
			notice.className = 'guest-key-toast';
			notice.setAttribute('role', 'status');
			notice.setAttribute('aria-live', 'polite');
			document.body.append(notice);
		}
		notice.replaceChildren();
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
		if (!busy) dismissTimer = setTimeout(() => { notice.hidden = true; }, 15000);
	}

	async function request(action) {
		const response = await fetch(guestKey.ajaxUrl, {
			method: 'POST', credentials: 'same-origin', cache: 'no-store',
			body: new URLSearchParams({ action, nonce: guestKey.nonce })
		});
		let result;
		try { result = await response.json(); } catch { throw new Error(s.error); }
		if (!response.ok || !result.success) throw new Error(result.data?.message || s.error);
		return result.data;
	}

	function setBusy(value) {
		busy = value;
		document.querySelectorAll('[data-guest-key-create], [data-guest-key-revoke]').forEach(button => { button.disabled = value; });
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
		const template = button.closest('[data-guest-key-ability]').querySelector('[data-guest-key-ability-content]');
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
			if (grantStatus) grantStatus.textContent = `${s.created} ${s.expires}${data.expires_label}`;
			const adapterStatus = document.getElementById('guest-key-adapter-status');
			if (adapterStatus) adapterStatus.textContent = s.active;
			status(`${copied ? s.copied : s.created} ${s.expires}${data.expires_label}`, false, true);
			if (!copied) fallback(data.bundle, data.expires_label);
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
			const data = await request('guest_key_revoke');
			closeDialog();
			const grantStatus = document.getElementById('guest-key-grant-status');
			if (grantStatus) grantStatus.textContent = s.revoked;
			const adapterStatus = document.getElementById('guest-key-adapter-status');
			if (adapterStatus) adapterStatus.textContent = data.adapter_active ? s.active : s.inactive;
			setBusy(false);
			status(s.revoked);
		} catch (error) {
			setBusy(false);
			status(error.message || s.error, true);
		}
	}

	document.querySelector('#wp-admin-bar-guest-key-access > .ab-item')?.addEventListener('click', createAccess);
	document.querySelector('#wp-admin-bar-guest-key-revoke > .ab-item')?.addEventListener('click', revokeAccess);
	document.querySelectorAll('[data-guest-key-create]').forEach(button => button.addEventListener('click', createAccess));
	document.querySelectorAll('[data-guest-key-revoke]').forEach(button => button.addEventListener('click', revokeAccess));
	document.querySelectorAll('[data-guest-key-inspect]').forEach(button => button.addEventListener('click', inspectAbility));
	document.getElementById('guest-key-search')?.addEventListener('input', event => {
		const query = event.target.value.toLocaleLowerCase().trim();
		let visible = 0;
		document.querySelectorAll('[data-guest-key-ability]').forEach(row => {
			const details = row.querySelector('[data-guest-key-ability-content]');
			row.hidden = !(row.textContent + (details?.content.textContent || '')).toLocaleLowerCase().includes(query);
			if (!row.hidden) visible++;
		});
		document.getElementById('guest-key-empty').hidden = visible !== 0;
		document.getElementById('guest-key-search-count').textContent = `${visible} shown`;
	});
})();
