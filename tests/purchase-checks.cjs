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
function browser(license = trial, hidden = false) {
 let now = 100000;
 const requests = [], redirects = [];
 function target() {
  const events = {};
  return { events, addEventListener: (name, fn) => { events[name] = fn; }, setAttribute() {}, classList: { toggle() {} } };
 }
 const toolbar = target(), link = target(), window = target(), document = target();
 toolbar.querySelector = () => link;
 document.hidden = hidden;
 document.querySelector = selector => selector === '#wp-admin-bar-guest-key-access' ? toolbar : null;
 document.querySelectorAll = () => [];
 document.getElementById = () => null;
 const context = vm.createContext({
  guestKey: { strings: { durationHour: '%s hour', durationHours: '%s hours', durationMinute: '%s minute', durationMinutes: '%s minutes', expires: 'Access expires at %s.', expiresTomorrow: 'Access expires tomorrow at %s.', expiresDay: 'Access expires %s.' }, licensing: license, settingsUrl: '/settings', ajaxUrl: '/ajax' },
  document, window, location: { assign: url => redirects.push(url) }, URLSearchParams,
  Date: class extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } },
  setInterval() {}, setTimeout() {}, clearTimeout() {},
  fetch: (url, options) => new Promise((resolve, reject) => requests.push({
   action: options.body.get('action'), reject,
   finish: licensing => resolve({ ok: true, json: async () => ({ success: true, data: { licensing } }) })
  }))
 });
 vm.runInContext(source, context);
 return { ...context, toolbar, link, requests, redirects, advance: () => { now += 15000; }, at: time => { now = time; } };
}

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
