const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');

const source = readFileSync(join(__dirname, '../assets/guest-key.js'), 'utf8').replace(
 /\}\)\(\);\s*$/, 'globalThis.ui = { refreshPurchase, request, expiryMessage, durationLabel, state: () => licensing }; })();'
);
const trial = { kind: 'trial', active: false, expired: true };
const paid = { kind: 'paid', active: true };
const settle = () => new Promise(resolve => setImmediate(resolve));
function browser(license = trial, hidden = false, settings = false) {
 let now = 100000;
 const requests = [], redirects = [], elements = [];
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
 toolbar.querySelector = () => link;
 document.createElement = tag => Object.assign(target(), {tag}); document.body = target();
 document.hidden = hidden;
 document.querySelector = selector => selector === '#wp-admin-bar-guest-key-access' ? toolbar : selector === '[data-guest-key-license-settings]' ? panel : null;
 document.querySelectorAll = selector => selector.includes('[data-guest-key-create]') ? [create] : [];
 document.getElementById = () => null;
 const context = vm.createContext({
  guestKey: { strings: { durationHour: '%s hour', durationHours: '%s hours', durationMinute: '%s minute', durationMinutes: '%s minutes', expires: 'Access expires at %s.', expiresTomorrow: 'Access expires tomorrow at %s.', expiresDay: 'Access expires %s.',
   licenseRequired: 'Guest Key is not licensed for your account. Enter your license key or email address to continue.',
   trialExpiredEmail: 'Your free trial for %s has ended. Add a license to keep connecting your agent.', trialExpired: 'Your free trial has ended.',
   licenseExpired: 'Your license has expired or is inactive. Renew it, then check again.', licenseCheckNeeded: 'Your license needs to be checked before connecting. Please try again.',
   codeSent: 'We sent a six-digit code to %s. Your trial starts after verification.', working: 'Working…',
   automaticLicense: 'Purchasing with %s? Your license will connect automatically.',
   opensNewTab: '%s (opens in a new tab)', manageBilling: 'Manage billing', renewLicense: 'Renew license', checkLicense: 'Check again', removeLicense: 'Remove my license',
   shareLicense: 'Share your license', visibilitySaved: 'Toolbar visibility updated.', sharingSaved: 'License sharing updated.',
   licenseKey: 'License key', email: 'Email address', code: 'Six-digit email code', error: 'Could not prepare access.'
  }, licensing: license, settingsUrl: '/settings', ajaxUrl: '/ajax' },
  document, window, location: { assign: url => redirects.push(url) }, URLSearchParams,
  innerWidth: 1200, innerHeight: 900, navigator: {clipboard: {}},
  Date: class extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } },
  setInterval() {}, setTimeout() {}, clearTimeout() {},
  fetch: (url, options) => new Promise((resolve, reject) => requests.push({
   action: options.body.get('action'), users: options.body.get('users'), user: options.body.get('user'), hidden: options.body.get('hidden'), reject,
   finish: (licensing, success = true, message) => resolve({ ok: success, json: async () => ({ success, data: { licensing, message } }) })
  }))
 });
 vm.runInContext(source, context);
 return { ...context, toolbar, link, panel, create, elements, requests, redirects, advance: () => { now += 15000; }, at: time => { now = time; } };
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

test('sharing checkbox changes save automatically without rebuilding or moving focus', async () => {
 const license = {...paid, ownActive: true, administrators: [{id: 2, name: 'Brian', shared: false}, {id: 3, name: 'Alex', shared: true}]};
 const b = browser(license, false, true);
 const choices = b.panel.querySelectorAll('input').filter(node => node.hasAttribute('data-guest-key-share'));
 b.document.activeElement = choices[0]; choices[0].checked = true;
 const save = choices[0].events.change();
 assert.ok(choices.every(node => node.disabled));
 await settle();
 assert.equal(b.requests[0].action, 'guest_key_license_share');
 assert.deepEqual(JSON.parse(b.requests[0].users), [2, 3]);
 const updated = {...license, administrators: license.administrators.map(admin => ({...admin, shared: true}))};
 b.requests[0].finish(updated); await save;
 assert.equal(b.panel.children[2].textContent, 'License sharing updated.');
 assert.equal(b.document.activeElement, choices[0]);
 assert.ok(choices.every(node => !node.disabled));
 assert.equal(b.panel.querySelectorAll('input').filter(node => node.hasAttribute('data-guest-key-share'))[0], choices[0]);
 choices[0].checked = false;
 const remove = choices[0].events.change(); await settle();
 assert.deepEqual(JSON.parse(b.requests[1].users), [3]);
 b.requests[1].finish(license); await remove;
 assert.equal(b.ui.state().administrators[0].shared, false);
});

test('failed sharing saves restore the saved selection and report the error', async () => {
 const b = browser({...paid, ownActive: true, administrators: [{id: 2, name: 'Brian', shared: true}]}, false, true);
 const checkbox = b.panel.querySelectorAll('input').filter(node => node.hasAttribute('data-guest-key-share'))[0];
 checkbox.checked = false;
 const save = checkbox.events.change(); await settle();
 b.requests[0].reject(new Error('Could not save sharing. Please retry.')); await save;
 assert.equal(checkbox.checked, true);
 assert.equal(checkbox.disabled, false);
 assert.equal(b.create.disabled, false);
 assert.equal(b.panel.children[2].textContent, 'Could not save sharing. Please retry.');
 const retry = checkbox.events.change(); await settle();
 assert.deepEqual(JSON.parse(b.requests[1].users), [2]);
 b.requests[1].finish(b.ui.state()); await retry;
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

test('hiding an administrator saves immediately and clears/disables sharing', async () => {
 const license={...paid, ownActive:true, administrators:[{id:2,name:'Brian',shared:true,hidden:false}]};
 const b=browser(license,false,true);
 const [share,hide]=b.panel.querySelectorAll('input');
 hide.checked=true; const save=hide.events.change(); await settle();
 assert.equal(b.requests[0].action,'guest_key_license_visibility');
 assert.equal(b.requests[0].user,'2'); assert.equal(b.requests[0].hidden,'1');
 b.requests[0].finish({...license,administrators:[{id:2,name:'Brian',shared:false,hidden:true}]}); await save;
 assert.equal(share.checked,false); assert.equal(share.disabled,true); assert.equal(hide.disabled,false);
 assert.equal(b.panel.children[2].textContent,'Toolbar visibility updated.');
 hide.checked=false; const show=hide.events.change(); await settle();
 assert.equal(b.requests[1].hidden,'0');
 b.requests[1].finish({...license,administrators:[{id:2,name:'Brian',shared:false,hidden:false}]}); await show;
 assert.equal(share.disabled,false); assert.equal(share.checked,false);
});

test('failed visibility saves restore both controls and permit retry', async () => {
 const b=browser({...paid, ownActive:true,administrators:[{id:2,name:'Brian',shared:true,hidden:false}]},false,true);
 const [share,hide]=b.panel.querySelectorAll('input'); hide.checked=true;
 const save=hide.events.change(); await settle(); b.requests[0].reject(new Error('Could not save.')); await save;
 assert.equal(hide.checked,false); assert.equal(share.checked,true); assert.equal(share.disabled,false);
 assert.equal(b.panel.children[2].textContent,'Could not save.');
});

test('unlicensed administrators can manage visibility without granting sharing', async () => {
 const license={kind:'',active:false,administrators:[{id:2,name:'Brian',shared:false,hidden:false}]};
 const b=browser(license,false,true);
 const inputs=b.panel.querySelectorAll('input'); const share=inputs.find(n=>n.hasAttribute('data-guest-key-share')); const hide=inputs.find(n=>n.hasAttribute('data-guest-key-hide'));
 assert.equal(share.disabled,true); assert.equal(hide.disabled,undefined);
 hide.checked=true; const save=hide.events.change(); await settle();
 b.requests[0].finish({...license,administrators:[{id:2,name:'Brian',shared:false,hidden:true}]}); await save;
 assert.equal(share.disabled,true); assert.equal(hide.checked,true);
});
