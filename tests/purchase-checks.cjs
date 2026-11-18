const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');

const source = readFileSync(join(__dirname, '../assets/guest-key.js'), 'utf8').replace(
 /\}\)\(\);\s*$/, 'globalThis.ui = { refreshPurchase, request, expiryMessage, durationLabel, state: () => licensing }; })();'
);
const trial = { kind: 'trial', active: false, expired: true };
const paid = { kind: 'paid', active: true, canManageAccess: true };
const settle = () => new Promise(resolve => setImmediate(resolve));
function browser(license = trial, hidden = false, settings = false, clipboard = {}) {
 let now = 100000;
 const requests = [], redirects = [], elements = [];
 const timers = new Map(); let timerId = 0, clock = 0;
 function target() {
  const events = {};
  const node = { events, children: [], style: {}, textContent: '', attributes: {},
   addEventListener: (name, fn) => { events[name] = fn; },
   setAttribute(name, value) { this.attributes[name] = value; }, removeAttribute(name) { delete this.attributes[name]; }, hasAttribute(name) { return name in this.attributes; },
   classList: { toggle() {}, add() {}, remove() {} },
   append(...children) { this.children.push(...children); }, replaceChildren(...children) { this.children = children; },
   closest() { return this; }, querySelectorAll(selector) {
    return this.children.filter(child => typeof child === 'object').flatMap(child => [
     ...(selector.split(',').map(tag => tag.trim()).includes(child.tag) ? [child] : []), ...child.querySelectorAll(selector)
    ]);
   },
   focus() { document.activeElement = this; }, scrollIntoView() { this.scrolled = true; },
   getBoundingClientRect() { return {left: 10, bottom: 32}; }
  };
  elements.push(node); return node;
 }
 const toolbar = target(), link = target(), window = target(), document = target();
 const panel = settings ? target() : null, create = target();
 const diagnostics = target(), report = target();
 report.value = "old report"; report.select = () => { report.selected = true; };
 toolbar.querySelector = () => link;
 document.createElement = tag => Object.assign(target(), {tag}); document.body = target();
 document.hidden = hidden;
 document.querySelector = selector => selector === '#wp-admin-bar-guest-key-access' ? toolbar : selector === '[data-guest-key-license-settings]' ? panel : selector === '[data-guest-key-diagnostics]' ? diagnostics : null;
 document.querySelectorAll = selector => selector.includes('[data-guest-key-create]') ? [create] : [];
 document.getElementById = id => id === 'guest-key-diagnostic-report' ? report : null;
 const context = vm.createContext({
  guestKey: { strings: { durationHour: '%s hour', durationHours: '%s hours', durationMinute: '%s minute', durationMinutes: '%s minutes', expires: 'Access expires at %s.', expiresTomorrow: 'Access expires tomorrow at %s.', expiresDay: 'Access expires %s.',
   licenseRequired: 'Guest Key is not licensed for your account. Enter your license key or email address to continue.',
   trialExpiredEmail: 'Your free trial for %s has ended. Add a license to keep connecting your agent.', trialExpired: 'Your free trial has ended.',
   licenseExpired: 'Your license has expired or is inactive. Renew it, then check again.', licenseCheckNeeded: 'Your license needs to be checked before connecting. Please try again.',
   diagnosticsCopied: 'Diagnostics copied.', diagnosticsSelect: 'Select and copy diagnostics.',
   codeSent: 'We sent a six-digit code to %s. Your trial starts after verification.', working: 'Working…',
   automaticLicense: 'Purchasing with %s? Your license will connect automatically.',
   opensNewTab: '%s (opens in a new tab)', manageBilling: 'Manage billing', renewLicense: 'Renew license', checkLicense: 'Check again', removeLicense: 'Remove my license',
   shareLicense: 'Share your license', visibilitySaved: 'Toolbar visibility updated.', sharingSaved: 'License sharing updated.',
   accessNotEnabled: 'Guest Key access is not currently enabled for your account.', accessContact: 'To request access, contact your administrator at %s.', accessPurchase: 'Purchase or activate your own license.', purchaseLicense: 'Purchase your own license', activateLicense: 'Activate license',
   licenseKey: 'License key', email: 'Email address', code: 'Six-digit email code', error: 'Could not prepare access.'
  }, licensing: license, settingsUrl: '/settings', ajaxUrl: '/ajax' },
  document, window, location: { assign: url => redirects.push(url), reload: () => redirects.push('reload') }, URLSearchParams,
  innerWidth: 1200, innerHeight: 900, navigator: {clipboard},
  Date: class extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } },
  setInterval() {}, setTimeout(fn, delay) { const id = ++timerId; timers.set(id, {fn, at: clock + delay}); return id; }, clearTimeout(id) { timers.delete(id); },
  fetch: (url, options) => new Promise((resolve, reject) => requests.push({
   action: options.body.get('action'), users: options.body.get('users'), user: options.body.get('user'), hidden: options.body.get('hidden'), reject,
   finish: (licensing, success = true, message) => resolve({ ok: success, json: async () => ({ success, data: { licensing, message } }) })
  }))
 });
 vm.runInContext(source, context);
 return { ...context, toolbar, link, panel, create, diagnostics, report, elements, requests, redirects, tick(ms = 1500) {
  clock += ms;
  for (const [id, timer] of [...timers]) if (timer.at <= clock) { timers.delete(id); timer.fn(); }
 }, advance: () => { now += 15000; }, at: time => { now = time; } };
}

test('unlicensed Copy Guest Key clicks explain the requirement and focus the email field', async () => {
 const b = browser({kind: '', active: false}, false, true);
 assert.equal(b.link.events.click, b.create.events.click);
 const click = b.create.events.click({preventDefault() {}});
 assert.equal(b.requests[0].action, 'guest_key_license_status');
 assert.equal(b.create.disabled, true);
 b.requests[0].finish({kind: '', active: false}); await click;
 const message = b.guestKey.strings.licenseRequired;
 assert.equal(b.panel.children[2].textContent, message);
 assert.equal(b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent, message);
 assert.equal(b.document.activeElement.type, 'email');
 assert.equal(b.panel.scrolled, true);
 assert.equal(b.create.disabled, false);
 assert.equal(b.requests.length, 1);
});

test('active owned licenses group billing with removal and give sharing a heading', () => {
 const b = browser({...paid, ownActive: true, renew: 'https://billing.example.test', administrators: [{id: 2, name: 'Brian', shared: false}]}, false, true);
 const actions = b.panel.children.find(node => node.tag === 'p' && node.children.some(child => child.tag === 'a'));
 assert.deepEqual(actions.children.map(node => node.textContent), ['Remove my license', 'Manage billing']);
 const billing = actions.children[1];
 assert.equal(billing.target, '_blank');
 assert.equal(billing.rel, 'noopener noreferrer');
 assert.equal(billing.href, 'https://billing.example.test');
 assert.equal(billing.attributes['aria-label'], 'Manage billing (opens in a new tab)');
 assert.equal(billing.children[1].textContent, '↗');
 assert.equal(billing.children[1].attributes['aria-hidden'], 'true');
 assert.ok(b.panel.children.some(node => node.tag === 'section' && node.children[0].tag === 'h3' && node.children[0].textContent === 'Share your license'));
 assert.ok(!b.panel.querySelectorAll('button').some(node => ['Check again', 'Save sharing'].includes(node.textContent)));
 const inactive = browser({kind: 'paid', active: false, ownActive: false, expired: true}, false, true);
 assert.ok(inactive.panel.querySelectorAll('button').some(node => node.textContent === 'Check again'));
});

test('sharing radio changes save automatically without rebuilding or moving focus', async () => {
 const license = {...paid, ownActive: true, administrators: [{id: 2, name: 'Brian', shared: false}, {id: 3, name: 'Alex', shared: true}]};
 const b = browser(license, false, true);
 const choices = b.panel.querySelectorAll('input').filter(node => node.hasAttribute('data-guest-key-share'));
 b.document.activeElement = choices[0]; choices[0].checked = true;
 choices[0].events.change();
 assert.ok(choices.every(node => !node.disabled));
 assert.equal(b.requests.length, 0);
 b.tick();
 assert.ok(choices.every(node => node.disabled));
 await settle();
 assert.equal(b.requests[0].action, 'guest_key_license_share');
 assert.deepEqual(JSON.parse(b.requests[0].users), [2, 3]);
 const updated = {...license, administrators: license.administrators.map(admin => ({...admin, shared: true}))};
 b.requests[0].finish(updated); await settle();
 assert.equal(b.panel.children[2].textContent, 'License sharing updated.');
 assert.equal(b.document.activeElement, choices[0]);
 assert.ok(choices.every(node => !node.disabled));
 assert.equal(b.panel.querySelectorAll('input').filter(node => node.hasAttribute('data-guest-key-share'))[0], choices[0]);
 choices[0].checked = false;
 choices[0].events.change(); b.tick(); await settle();
 assert.deepEqual(JSON.parse(b.requests[1].users), [3]);
 b.requests[1].finish(license); await settle();
 assert.equal(b.ui.state().administrators[0].shared, false);
});

test('failed sharing saves restore the saved selection and report the error', async () => {
 const b = browser({...paid, ownActive: true, administrators: [{id: 2, name: 'Brian', shared: true}]}, false, true);
 const checkbox = b.panel.querySelectorAll('input').filter(node => node.hasAttribute('data-guest-key-share'))[0];
 checkbox.checked = false;
 checkbox.events.change(); b.tick(); await settle();
 b.requests[0].reject(new Error('Could not save sharing. Please retry.')); await settle();
 assert.equal(checkbox.checked, true);
 assert.equal(checkbox.disabled, false);
 assert.equal(b.create.disabled, false);
 assert.equal(b.panel.children[2].textContent, 'Could not save sharing. Please retry.');
 checkbox.checked = false;
 checkbox.events.change(); b.tick(); await settle();
 assert.deepEqual(JSON.parse(b.requests[1].users), []);
 b.requests[1].finish(b.ui.state()); await settle();
});

test('an unlicensed toolbar click elsewhere opens the licensing settings', async () => {
 const b = browser({kind: '', active: false});
 const click = b.link.events.click({preventDefault() {}});
 b.requests[0].finish({kind: '', active: false}); await click;
 assert.deepEqual(b.redirects, ['/settings#guest-key-license-settings']);
 assert.equal(b.requests.length, 1);
});

test('expired trials and pending verification get actionable feedback on click', async () => {
 for (const license of [{...trial, email: 'trial@example.test'}, {kind: '', active: false, pending: true, email: 'pending@example.test'}, {kind: 'paid', active: false, expired: true}]) {
  const b = browser(license, true, true);
  const click = b.create.events.click({preventDefault() {}});
  b.requests[0].finish(license); await click;
  const feedback = b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent;
  assert.ok(feedback.length);
  assert.notEqual(feedback, b.guestKey.strings.licenseRequired);
  if (license.email) assert.ok(feedback.includes(license.email));
  if (license.expired && license.kind === 'trial') assert.equal(b.document.activeElement.type, 'text');
  if (license.pending) assert.equal(b.document.activeElement.inputMode, 'numeric');
 }
});

test('a licensing outage still explains the next step and leaves manual entry usable', async () => {
 const b = browser({kind: '', active: false}, false, true);
 const click = b.create.events.click({preventDefault() {}});
 b.requests[0].reject(new Error('offline')); await click;
 assert.equal(b.panel.children[2].textContent, b.guestKey.strings.licenseRequired);
 assert.equal(b.create.disabled, false);
 assert.equal(b.document.activeElement.type, 'email');
});

test('a newly matched license continues creation and server-side license rejection is visible', async () => {
 const b = browser({kind: '', active: false}, false, true);
 const click = b.create.events.click({preventDefault() {}});
 b.requests[0].finish(paid); await settle();
 assert.equal(b.requests[1].action, 'guest_key_create');
 b.requests[1].finish(trial, false, 'Your free trial has ended.'); await click;
 assert.equal(b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent, 'Your free trial has ended.');
 assert.equal(b.create.disabled, false);
});

test('expired trials check automatically, coalesce hovers and observe the cooldown', async () => {
 const b = browser();
 assert.equal(b.requests.length, 1);
 b.toolbar.events.mouseenter(); b.link.events.focus();
 assert.equal(b.requests.length, 1);
 b.requests[0].finish(trial); await settle();
 b.toolbar.events.mouseenter();
 assert.equal(b.requests.length, 1);
 b.advance(); b.toolbar.events.mouseenter();
 assert.equal(b.requests.length, 2);
 b.window.events.focus();
 assert.equal(b.requests.length, 2);
 b.requests[1].finish(paid); await settle();
 assert.equal(b.ui.state().kind, 'paid');
 b.advance(); b.toolbar.events.mouseenter();
 assert.equal(b.requests.length, 2);
 assert.deepEqual(b.redirects, []);
});

test('manual activation waits for an outstanding background check', async () => {
 const b = browser();
 const activation = b.ui.request('guest_key_license_activate', { key: 'public-fixture' });
 await settle(); assert.equal(b.requests.length, 1);
 b.requests[0].finish(trial); await settle();
 assert.equal(b.requests[1].action, 'guest_key_license_activate');
 b.requests[1].finish(paid); await activation;
 assert.equal(b.ui.state().kind, 'paid');
});

test('an outage stays quiet and permits a later retry', async () => {
 const b = browser();
 b.requests[0].reject(new Error('offline')); await settle();
 assert.equal(b.ui.state().kind, 'trial');
 assert.deepEqual(b.redirects, []);
 b.advance(); b.toolbar.events.mouseenter();
 assert.equal(b.requests.length, 2);
 b.requests[1].finish(trial); await settle();
});

test('hidden tabs and paid or unregistered sites do not check for a purchase', async () => {
 const b = browser(trial, true);
 assert.equal(b.requests.length, 0);
 b.document.hidden = false; b.window.events.focus();
 assert.equal(b.requests.length, 1);
 b.requests[0].finish(trial); await settle();
 assert.equal(browser(paid).requests.length, 0);
 assert.equal(browser({ kind: '', active: false }).requests.length, 0);
});


test('expiration uses local time without a date and identifies the next day', () => {
 const b = browser(paid);
 b.at(new Date(2026, 9, 28, 20, 0).getTime());
 for (const [expiry, prefix] of [[new Date(2026, 9, 28, 22, 50), 'Access expires at '], [new Date(2026, 9, 29, 1, 50), 'Access expires tomorrow at ']]) {
  const time = expiry.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' });
  assert.equal(b.ui.expiryMessage(expiry.toISOString()), `${prefix}${time}.`);
 }
 // A 24-hour key can span two calendar dates when daylight saving time starts.
 b.at(new Date(2026, 2, 7, 23, 30).getTime());
 const later = new Date(2026, 2, 9, 0, 30);
 const time = later.toLocaleTimeString(undefined, { weekday: 'long', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' });
 assert.equal(b.ui.expiryMessage(later.toISOString()), `Access expires ${time}.`);
});


test('duration uses readable hours and minutes', () => {
 const b = browser(paid);
 for (const [minutes, label] of [[30, '30 minutes'], [60, '1 hour'], [75, '1 hour 15 minutes'], [120, '2 hours'], [135, '2 hours 15 minutes'], [1440, '24 hours']]) {
  assert.equal(b.ui.durationLabel(minutes), label);
 }
});

test('hiding an administrator saves after a delay and clears shared access', async () => {
 const license={...paid, ownActive:true, administrators:[{id:2,name:'Brian',shared:true,hidden:false}]};
 const b=browser(license,false,true);
 const [share,hide]=b.panel.querySelectorAll('input');
 hide.checked=true; hide.events.change(); b.tick(); await settle();
 assert.equal(b.requests[0].action,'guest_key_license_visibility');
 assert.equal(b.requests[0].user,'2'); assert.equal(b.requests[0].hidden,'1');
 b.requests[0].finish({...license,administrators:[{id:2,name:'Brian',shared:false,hidden:true}]}); await settle();
 assert.equal(share.checked,false); assert.equal(share.disabled,false); assert.equal(hide.disabled,false);
 assert.equal(b.panel.children[2].textContent,'Toolbar visibility updated.');
 hide.checked=false; hide.events.change(); b.tick(); await settle();
 assert.equal(b.requests[1].hidden,'0');
 b.requests[1].finish({...license,administrators:[{id:2,name:'Brian',shared:false,hidden:false}]}); await settle();
 assert.equal(share.disabled,false); assert.equal(share.checked,false);
});

test('failed visibility saves restore both controls and permit retry', async () => {
 const b=browser({...paid, ownActive:true,administrators:[{id:2,name:'Brian',shared:true,hidden:false}]},false,true);
 const [share,hide]=b.panel.querySelectorAll('input'); hide.checked=true;
 hide.events.change(); b.tick(); await settle(); b.requests[0].reject(new Error('Could not save.')); await settle();
 assert.equal(hide.checked,false); assert.equal(share.checked,true); assert.equal(share.disabled,false);
 assert.equal(b.panel.children[2].textContent,'Could not save.');
});

test('unlicensed administrators cannot manage visibility or sharing', async () => {
 const license={kind:'',active:false,administrators:[{id:2,name:'Brian',shared:false,hidden:false}]};
 const b=browser(license,false,true);
 const inputs=b.panel.querySelectorAll('input'); const share=inputs.find(n=>n.hasAttribute('data-guest-key-share')); const hide=inputs.find(n=>n.hasAttribute('data-guest-key-hide'));
 assert.equal(share.disabled,true); assert.equal(hide.disabled,true);

});

test('rapid sharing changes restart the debounce and save only the final selection', async () => {
 const license = {...paid, ownActive: true, administrators: [{id: 2, shared: false}, {id: 3, shared: false}]};
 const b = browser(license, false, true);
 const shares = b.panel.querySelectorAll('input').filter(n => n.hasAttribute('data-guest-key-share'));
 shares[0].checked = true; shares[0].events.change(); b.tick(1000);
 shares[1].checked = true; shares[1].events.change(); b.tick(1499);
 assert.equal(b.requests.length, 0);
 assert.ok(shares.every(n => !n.disabled));
 b.tick(1); await settle();
 assert.equal(b.requests.length, 1);
 assert.deepEqual(JSON.parse(b.requests[0].users), [2, 3]);
 b.requests[0].finish({...license, administrators: license.administrators.map(a => ({...a, shared: true}))}); await settle();
 b.tick(1000); assert.equal(b.requests.length, 1);
});

test('reversing sharing and visibility within the debounce makes no requests', async () => {
 const b = browser({...paid, ownActive: true, administrators: [{id: 2, shared: true, hidden: false}]}, false, true);
 const [share, hide] = b.panel.querySelectorAll('input');
 share.checked = false; share.events.change();
 hide.checked = true; hide.events.change(); assert.equal(share.disabled, false);
 hide.checked = false; hide.events.change(); assert.equal(share.disabled, false);
 share.checked = true; share.events.change();
 b.tick(); await settle();
 assert.equal(b.requests.length, 0);
 assert.equal(share.checked, true); assert.equal(hide.checked, false);
});

test('mixed changes save visibility sequentially before sharing without losing other selections', async () => {
 const license = {...paid, ownActive: true, administrators: [{id: 2, shared: true, hidden: false}, {id: 3, shared: false, hidden: false}, {id: 4, shared: false, hidden: false}]};
 const b = browser(license, false, true);
 const [share2, hide2, share3, hide3, share4] = b.panel.querySelectorAll('input');
 hide2.checked = true; hide2.events.change();
 hide3.checked = true; hide3.events.change();
 share4.checked = true; share4.events.change();
 b.tick(); await settle();
 assert.equal(b.requests.length, 1); assert.equal(b.requests[0].user, '2');
 const first = {...license, administrators: license.administrators.map(a => a.id === 2 ? {...a, hidden: true, shared: false} : a)};
 b.requests[0].finish(first); await settle();
 assert.equal(b.requests.length, 2); assert.equal(b.requests[1].user, '3');
 const second = {...first, administrators: first.administrators.map(a => a.id === 3 ? {...a, hidden: true} : a)};
 b.requests[1].finish(second); await settle();
 assert.equal(b.requests.length, 3); assert.equal(b.requests[2].action, 'guest_key_license_share');
 assert.deepEqual(JSON.parse(b.requests[2].users), [4]);
 b.requests[2].reject(new Error('Sharing failed.')); await settle();
 assert.equal(hide2.checked, true); assert.equal(hide3.checked, true);
 assert.equal(share2.checked, false); assert.equal(share2.disabled, false);
 assert.equal(share3.disabled, false); assert.equal(share4.checked, false);
 assert.equal(b.panel.children[2].textContent, 'Sharing failed.');
});

test('another license action flushes pending selections before running and cancels the timer', async () => {
 const license = {...paid, ownActive: true, administrators: [{id: 2, shared: false}]};
 const b = browser(license, false, true);
 const share = b.panel.querySelectorAll('input')[0];
 share.checked = true; share.events.change();
 const removal = b.panel.querySelectorAll('button').find(n => n.textContent === 'Remove my license').events.click();
 await settle(); assert.equal(b.requests[0].action, 'guest_key_license_share');
 b.requests[0].finish({...license, administrators: [{id: 2, shared: true}]}); await settle();
 assert.equal(b.requests[1].action, 'guest_key_license_remove');
 b.requests[1].finish({kind: '', active: false}); await removal;
 b.tick(); await settle(); assert.equal(b.requests.length, 2);
});

test('diagnostics copies the displayed text during the click without a server request', async () => {
 let copied;
 const b = browser(paid, false, false, {writeText: async text => { copied = text; }});
 const displayed = JSON.stringify({guest_key: '0.2.9', adapter: {installed: false}}, null, 2);
 b.report.value = displayed;
 const click = b.diagnostics.events.click({currentTarget: b.diagnostics});
 assert.equal(copied, displayed);
 assert.equal(b.requests.length, 0);
 await click;
 assert.equal(b.report.value, displayed);
 assert.equal(b.diagnostics.disabled, false);
 assert.equal(b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent, 'Diagnostics copied.');
});

test('blocked or unavailable clipboard selects displayed diagnostics for manual copying', async () => {
 for (const clipboard of [{}, {writeText: async () => { throw new Error('denied'); }}]) {
  const b = browser(paid, false, false, clipboard);
  await b.diagnostics.events.click({currentTarget: b.diagnostics});
  assert.equal(b.requests.length, 0);
  assert.equal(b.report.value, 'old report');
  assert.equal(b.report.selected, true);
  assert.equal(b.document.activeElement, b.report);
  assert.equal(b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent, 'Select and copy diagnostics.');
  assert.equal(b.diagnostics.disabled, false);
 }
});


test('restricted settings show contact, purchase and license entry without sharing or trials', async () => {
 const restricted = {kind: '', active: false, restricted: true, canManageAccess: false, contactEmail: 'owner@example.com', checkout: 'https://checkout.example.test'};
 const b = browser(restricted, false, true);
 assert.equal(b.panel.children[0].textContent, 'Guest Key access is not currently enabled for your account.');
 assert.equal(b.panel.children[1].textContent, 'To request access, contact your administrator at owner@example.com.');
 const purchase = b.panel.children.find(n => n.tag === 'a');
 assert.equal(purchase.textContent, 'Purchase your own license');
 assert.equal(purchase.href, restricted.checkout);
 const inputs = b.panel.querySelectorAll('input');
 assert.equal(inputs.length, 1); assert.equal(inputs[0].type, 'text');
 assert.equal(b.panel.querySelectorAll('button').length, 1);
 inputs[0].value = 'fixture-own-key';
 const form = b.panel.children.find(n => n.tag === 'form');
 form.events.submit({preventDefault() {}}); await settle();
 assert.equal(b.requests[0].action, 'guest_key_license_activate');
 b.requests[0].finish(paid); await settle();
 assert.deepEqual(b.redirects, ['reload']);
});

test('failed activation leaves restricted license entry usable without unlocking settings', async () => {
 const b = browser({kind: '', active: false, restricted: true}, false, true);
 const input = b.panel.querySelectorAll('input')[0]; input.value = 'invalid-key';
 b.panel.children.find(n => n.tag === 'form').events.submit({preventDefault() {}}); await settle();
 b.requests[0].reject(new Error('Invalid license.')); await settle();
 assert.equal(b.panel.children[2].textContent, 'Invalid license.');
 assert.equal(input.disabled, false);
 assert.equal(b.ui.state().restricted, true);
 assert.deepEqual(b.redirects, []);
});

test('purchased-license owners cannot be hidden or have access unchecked', () => {
 const b = browser({...paid, ownActive: true, administrators: [{id: 2, name: 'Other owner', ownsLicense: true, shared: true, hidden: false}]}, false, true);
 const [share, hide] = b.panel.querySelectorAll('input');
 assert.equal(share.checked, true); assert.equal(share.disabled, true);
 assert.equal(hide.checked, false); assert.equal(hide.disabled, true);
});

test('each administrator has an independent radio group with mutually exclusive choices', () => {
 const b = browser({...paid, administrators: [{id: 2, shared: true, hidden: false}, {id: 3, shared: false, hidden: true}]}, false, true);
 const [share2, hide2, share3, hide3] = b.panel.querySelectorAll('input');
 assert.equal(share2.type, 'radio'); assert.equal(hide2.type, 'radio');
 assert.equal(share2.name, hide2.name); assert.notEqual(share2.name, share3.name);
 hide2.checked = true; hide2.events.change();
 assert.equal(share2.checked, false); assert.equal(hide2.checked, true);
 assert.equal(hide3.checked, true);
 share3.checked = true; share3.events.change();
 assert.equal(hide3.checked, false); assert.equal(share3.checked, true);
 assert.equal(hide2.checked, true);
});

test('switching a hidden administrator to shared access unhides before sharing', async () => {
 const license = {...paid, administrators: [{id: 2, shared: false, hidden: true}]};
 const b = browser(license, false, true);
 const [share, hide] = b.panel.querySelectorAll('input');
 assert.equal(share.disabled, false);
 share.checked = true; share.events.change();
 assert.equal(hide.checked, false);
 b.tick(); await settle();
 assert.equal(b.requests.length, 1);
 assert.equal(b.requests[0].action, 'guest_key_license_visibility');
 assert.equal(b.requests[0].hidden, '0');
 b.requests[0].finish({...license, administrators: [{id: 2, shared: false, hidden: false}]}); await settle();
 assert.equal(b.requests[1].action, 'guest_key_license_share');
 assert.deepEqual(JSON.parse(b.requests[1].users), [2]);
 b.requests[1].finish({...license, administrators: [{id: 2, shared: true, hidden: false}]}); await settle();
 assert.equal(share.checked, true); assert.equal(hide.checked, false);
});
