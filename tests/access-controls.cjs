const assert = require('node:assert/strict');
const { test } = require('node:test');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');

const source = readFileSync(join(__dirname, '../assets/guest-key.js'), 'utf8').replace(
 /\}\)\(\);\s*$/, 'globalThis.ui = { request, expiryMessage, durationLabel, state: () => access }; })();'
);
const enabled = {active: true, canManageAccess: true, administrators: []};
const settle = () => new Promise(resolve => setImmediate(resolve));
function browser(state = enabled, hidden = false, settings = false, clipboard = {}) {
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
   remove() { this.removed = true; }, focus() { document.activeElement = this; }, scrollIntoView() { this.scrolled = true; },
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
 document.querySelector = selector => selector === '#wp-admin-bar-guest-key-access' ? toolbar : selector === '[data-guest-key-access-settings]' ? panel : selector === '[data-guest-key-diagnostics]' ? diagnostics : null;
 document.querySelectorAll = selector => selector.includes('[data-guest-key-create]') ? [create] : [];
 document.getElementById = id => id === 'guest-key-diagnostic-report' ? report : null;
 const context = vm.createContext({
  guestKey: { strings: { durationHour: '%s hour', durationHours: '%s hours', durationMinute: '%s minute', durationMinutes: '%s minutes', expires: 'Access expires at %s.', expiresTomorrow: 'Access expires tomorrow at %s.', expiresDay: 'Access expires %s.',
   accessTitle: 'Administrator access', accessNotEnabled: 'Guest Key access is not currently enabled for your account.',
   accessContact: 'Contact installer at %s.', accessContactFallback: 'Contact installer.',
   owner: 'Owner', changeOwner: 'Change owner', selectAdministrator: 'Select administrator', shareAccess: 'Enable Guest Key', hideToolbar: 'Hide Guest Key',
   claimControl: 'Set up Guest Key', accessSaved: 'Access saved.',
   diagnosticsCopied: 'Diagnostics copied.', diagnosticsSelect: 'Select and copy diagnostics.', error: 'Could not prepare access.'
  }, access: state, settingsUrl: '/settings', ajaxUrl: '/ajax' },
  document, window, location: { assign: url => redirects.push(url), reload: () => redirects.push('reload') }, URLSearchParams,
  innerWidth: 1200, innerHeight: 900, navigator: {clipboard},
  Date: class extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } },
  setInterval() {}, setTimeout(fn, delay) { const id = ++timerId; timers.set(id, {fn, at: clock + delay}); return id; }, clearTimeout(id) { timers.delete(id); },
  fetch: (url, options) => new Promise((resolve, reject) => requests.push({
   action: options.body.get('action'), users: options.body.get('users'), user: options.body.get('user'), enabled: options.body.get('enabled'), reject,
   finish: (licensing, success = true, message) => resolve({ ok: success, json: async () => ({ success, data: { access: licensing, message } }) })
  }))
 });
 vm.runInContext(source, context);
 return { ...context, toolbar, link, panel, create, diagnostics, report, elements, requests, redirects, tick(ms = 1500) {
  clock += ms;
  for (const [id, timer] of [...timers]) if (timer.at <= clock) { timers.delete(id); timer.fn(); }
 }, advance: () => { now += 15000; }, at: time => { now = time; } };
}

test('expiration uses local time without a date and identifies the next day', () => {
 const b = browser(enabled);
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
 const b = browser(enabled);
 for (const [minutes, label] of [[30, '30 minutes'], [60, '1 hour'], [75, '1 hour 15 minutes'], [120, '2 hours'], [135, '2 hours 15 minutes'], [1440, '24 hours']]) {
  assert.equal(b.ui.durationLabel(minutes), label);
 }
});

test('diagnostics copies the displayed text during the click without a server request', async () => {
 let copied;
 const b = browser(enabled, false, false, {writeText: async text => { copied = text; }});
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
  const b = browser(enabled, false, false, clipboard);
  await b.diagnostics.events.click({currentTarget: b.diagnostics});
  assert.equal(b.requests.length, 0);
  assert.equal(b.report.value, 'old report');
  assert.equal(b.report.selected, true);
  assert.equal(b.document.activeElement, b.report);
  assert.equal(b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent, 'Select and copy diagnostics.');
  assert.equal(b.diagnostics.disabled, false);
 }
});


const admins = [{id: 1, name: 'Installer', enabled: true, installer: true}, {id: 2, name: 'Shared', enabled: false}, {id: 3, name: 'Other', enabled: true}];
const accessView = {...enabled, userId: 3, administrators: admins};
function radios(b) { return b.panel.querySelectorAll('input'); }
function select(input) { input.checked = true; input.events.change(); }
test('installer stays enabled and disabled; shared administrators can enable and hide others', () => {
 const b = browser(accessView, false, true), fields = radios(b);
 assert.equal(fields.length, 6); assert.equal(fields[0].checked, true); assert.equal(fields[0].disabled, true); assert.equal(fields[1].disabled, true);
 assert.equal(fields[3].checked, true); assert.equal(fields[2].disabled, false);
 assert.equal(b.requests.length, 0);
});
test('choices debounce, remain mutually exclusive, preserve focus and save numeric-string IDs', async () => {
 const b = browser(accessView, false, true), fields = radios(b);
 fields[2].focus(); select(fields[2]); b.tick(1000); select(fields[3]); b.tick(1000);
 assert.equal(b.requests.length, 0); select(fields[2]); b.tick(1499); assert.equal(b.requests.length, 0);
 b.tick(1); assert.equal(b.requests.length, 1); assert.equal(b.requests[0].action, 'guest_key_set_access');
 assert.equal(b.requests[0].user, '2'); assert.equal(b.requests[0].enabled, '1'); assert.equal(fields[3].checked, false);
 b.requests[0].finish({...accessView, administrators: admins.map(a => a.id === 2 ? {...a, enabled: true} : a)}); await settle();
 assert.equal(b.document.activeElement, fields[2]); assert.equal(radios(b)[2], fields[2]); assert.equal(fields[2].checked, true);
});
test('failed save rolls choices back and exposes the error', async () => {
 const b = browser(accessView, false, true), fields = radios(b);
 select(fields[2]); b.tick(); b.requests[0].reject(new Error('Cannot save')); await settle();
 assert.equal(fields[2].checked, false); assert.equal(fields[3].checked, true); assert.equal(b.panel.children[2].textContent, 'Cannot save');
});
test('self-revocation saves other changes first and reloads when access ends', async () => {
 const b = browser(accessView, false, true), fields = radios(b);
 select(fields[2]); select(fields[5]); b.tick(); assert.equal(b.requests.length, 1);
 b.requests[0].finish({...accessView, administrators: admins.map(a => a.id === 2 ? {...a, enabled: true} : a)}); await settle();
 assert.equal(b.requests.length, 2); assert.equal(b.requests[1].user, '3');
 b.requests[1].finish({...accessView, restricted: true, canManageAccess: false, administrators: []}); await settle();
 assert.deepEqual(b.redirects, ['reload']);
});
test('successful multi-user saves serialize and retain the last successful state on error', async () => {
 const b = browser(accessView, false, true), fields = radios(b);
 select(fields[2]); select(fields[5]); b.tick(); assert.equal(b.requests.length, 1);
 b.requests[0].finish({...accessView, administrators: admins.map(a => a.id === 2 ? {...a, enabled: true} : a)}); await settle();
 assert.equal(b.requests.length, 2); assert.equal(b.requests[1].user, '3'); assert.equal(b.requests[1].enabled, '0');
 b.requests[1].reject(new Error('Denied')); await settle(); assert.equal(fields[2].checked, true); assert.equal(fields[4].checked, true);
});
test('restricted accounts see contact information with no controls or background polling', () => {
 const b = browser({restricted: true, contactEmail: 'installer@example.test'}, false, true);
 assert.equal(b.panel.children[0].textContent, b.guestKey.strings.accessNotEnabled);
 assert.equal(b.panel.children[1].textContent, 'Contact installer at installer@example.test.');
 assert.equal(radios(b).length, 0); assert.equal(b.panel.querySelectorAll('button').length, 0); b.tick(120000); assert.equal(b.requests.length, 0);
});
test('unconfigured installs require an explicit claim and reload', async () => {
 const b = browser({restricted: true, needsSetup: true}, false, true);
 assert.equal(b.requests.length, 0); const button = b.panel.querySelectorAll('button')[0];
 const click = button.events.click(); assert.equal(b.requests[0].action, 'guest_key_claim_control');
 b.requests[0].finish(enabled); await click; assert.deepEqual(b.redirects, ['reload']);
});
test('creating a connection sends create directly without access preflight', async () => {
 const b = browser({active: false}); const click = b.create.events.click({preventDefault() {}});
 assert.equal(b.requests[0].action, 'guest_key_create'); b.requests[0].reject(new Error('Access denied')); await click;
 assert.equal(b.requests.length, 1); assert.equal(b.elements.find(e => e.id === 'guest-key-status-panel').children[0].textContent, 'Access denied');
});


test('only the owner sees Change owner and pending access edits finish first', async () => {
 const own = {...accessView, userId: 1, isInstaller: true, administrators: admins.map(a => ({...a, canOwn: true}))};
 const b = browser(own, false, true), form = b.panel.querySelectorAll('form')[0];
 assert.ok(form); const selectOwner = form.querySelectorAll('select')[0]; selectOwner.value = '2';
 select(radios(b)[2]);
 const submit = form.events.submit({preventDefault() {}});
 assert.equal(b.requests[0].action, 'guest_key_set_access');
 b.requests[0].finish({...own, administrators: own.administrators.map(a => a.id === 2 ? {...a, enabled: true} : a)}); await settle();
 assert.equal(b.requests[1].action, 'guest_key_change_owner'); assert.equal(b.requests[1].user, '2');
 b.requests[1].finish({...own, isInstaller: false}); await submit;
 assert.deepEqual(b.redirects, ['reload']);
 assert.equal(browser({...own, isInstaller: false}, false, true).panel.querySelectorAll('form').length, 0);
});

test('failed ownership change leaves a retryable form and shows the error', async () => {
 const own = {...accessView, isInstaller: true, administrators: admins.map(a => ({...a, canOwn: true}))};
 const b = browser(own, false, true), form = b.panel.querySelectorAll('form')[0], dropdown = form.querySelectorAll('select')[0]; dropdown.value = '2';
 const submit = form.events.submit({preventDefault() {}}); await settle();
 b.requests[0].reject(new Error('Only the owner can change ownership.')); await submit;
 assert.equal(dropdown.disabled, false); assert.equal(form.querySelectorAll('button')[0].disabled, false);
 assert.equal(b.panel.children[2].textContent, 'Only the owner can change ownership.'); assert.deepEqual(b.redirects, []);
});
