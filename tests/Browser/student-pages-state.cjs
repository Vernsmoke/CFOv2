const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Exercise the delivered module's real event handlers without changing its assets.
class Element {
  constructor(value = '') {
    this.value = value;
    this.innerHTML = '';
    this.textContent = '';
    this.hidden = false;
    this.style = {};
    this.dataset = {};
    this.attributes = {};
    this.listeners = {};
    this.nodes = {};
  }
  addEventListener(type, handler) { (this.listeners[type] ||= []).push(handler); }
  emit(type, event = {}) { return this.listeners[type]?.map(handler=>handler({target: this, currentTarget: this, preventDefault() {}, ...event})).at(-1); }
  querySelector(selector) { return this.nodes[selector] || null; }
  setAttribute(name, value) { this.attributes[name] = value; }
  focus() { this.focused = true; }
  remove() { this.removed = true; }
  scrollIntoView() { this.scrolled = true; }
  closest(selector) { return this.selector === selector ? this : null; }
}

function setup() {
  const modals = [], document = new Element(), notices = [];
  document.activeElement = new Element();
  document.body = {style: {}, append: modal => modals.push(modal)};
  document.createElement = () => {
    const modal = new Element(), form = new Element(), close = new Element();
    modal.nodes = {'[data-close]': close, '[data-full]': new Element(), '#history-detail': new Element(), form};
    Object.defineProperty(modal, 'innerHTML', {
      get() { return this.markup || ''; },
      set(markup) {
        this.markup = markup;
        for (const match of markup.matchAll(/<input\b[^>]*id="([^"]+)"[^>]*value="([^"]*)"/g)) {
          const input = new Element(match[2]);
          form.nodes['#' + match[1]] = input;
          if (!this.nodes.input) this.nodes.input = input;
        }
      },
    });
    return modal;
  };
  const window = {
    CFO_BACKEND: {},
    cfoHistoryDetails: data => '<article>Full record ' + data.registration.id + '</article>',
    studentEmailDisplay: value => '<span class="masked-email">masked:' + value + '</span>',
    protectStudentEmailInput: input => { input.protected = true; },
    studentEmailValue: input => input.value,
  };
  const location = {hash: '', href: ''};
  const sandbox = {window, document, location, URLSearchParams, matchMedia: () => ({matches: true}), module: {exports: {}}};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/admin-history-documents.js'), 'utf8'), sandbox);
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/student/student-pages.js'), 'utf8'), sandbox);
  const page = ids => {
    const element = new Element();
    for (const id of ids) element.nodes[id] = new Element();
    return element;
  };
  const click = (element, selector, dataset = {}) => {
    const target = new Element(); target.selector = selector; target.dataset = dataset;
    element.emit('click', {target});
    return target;
  };
  return {C: sandbox.module.exports, document, window, location, modals, notices, page, click,
    showNotice: (...args) => notices.push(args)};
}

async function historyChecks() {
  const t = setup(), {C, modals} = t;
  const record = (id, extra = {}) => ({id, year: 2025, is_archived: true, status: 'Approved', attendance: 'Present',
    schedule: {title: 'Annual Retreat', date: '2025-10-12', time: '8:00 AM', venue: 'Chapel'},
    student_snapshot: {course: 'BSIT', year_level: '2', section: 'A'}, ...extra});
  const state = {registrations: [record(1, {has_receipt:true,certificate: {id: 30, certificate_number: 'ACTIVE-1'}}),
    record(2, {year: 2024, attendance: 'Absent', certificate_record: {certificate_number: 'REVOKED-2', revoked_at: '2025-01-01'}}),
    record(3, {is_archived: false, schedule: {title: 'Active registration'}}),
    record(4, {attendance: 'Not Yet Recorded', schedule: null, student_snapshot: null, details: {course: 'BSN', yearLevel: '3', section: 'B'}})]};
  let html = C.pageHtml('history', state);
  assert.match(html, /3 records/);
  assert.doesNotMatch(html, /Active registration/);
  assert.match(html, /Withdrawn/);
  assert.match(html, /Not recorded/);
  assert.match(html, /No schedule assigned/);
  const page = t.page(['#historyRows', '#historyEmpty', '#historyCount', '#historyShown', '#historySearch', '#historyYear', '#historyAttendance', '#clearHistory']);
  const requests = [];
  const cleanup = C.mount('history', page, {state, showNotice: t.showNotice, request: async url => {
    requests.push(url); return {registration: {id: 1}};
  }});
  page.nodes['#historyYear'].value = '2024'; page.nodes['#historyYear'].emit('change');
  assert.equal(page.nodes['#historyCount'].textContent, '1 record');
  assert.match(page.nodes['#historyRows'].innerHTML, /REVOKED-2/);
  page.nodes['#historyAttendance'].value = 'Present'; page.nodes['#historyAttendance'].emit('change');
  assert.equal(page.nodes['#historyEmpty'].style.display, 'block');
  page.nodes['#clearHistory'].emit('click');
  assert.equal(page.nodes['#historyCount'].textContent, '3 records');
  assert.equal(page.nodes['#historyYear'].value, '');
  page.nodes['#historySearch'].value = 'chapel'; page.nodes['#historySearch'].emit('input');
  assert.equal(page.nodes['#historyCount'].textContent, '2 records');
  assert.match(C.pageHtml('history', state), /value="chapel"/);
  t.click(page, '[data-history-id]', {historyId: '1'});
  let modal = modals.at(-1);
  assert.equal(modal.attributes.role, 'dialog');
  assert.equal(modal.attributes['aria-modal'], 'true');
  assert.match(modal.innerHTML, /BSIT · 2 · A/);
  assert.match(modal.innerHTML, /\/certificates\/30\?download=1/);
  assert.match(modal.innerHTML, /data-action="history-certificate-preview" data-id="30"/);
  assert.match(modal.innerHTML, /data-action="history-receipt-preview" data-id="1"/);
  assert.doesNotMatch(modal.innerHTML, /target="_blank"/);
  assert.equal(t.document.body.style.overflow, 'hidden');
  await modal.nodes['[data-full]'].emit('click');
  assert.deepEqual(requests, ['/api/history/1']);
  assert.match(modal.nodes['#history-detail'].innerHTML, /Full record 1/);
  assert.equal(modal.nodes['#history-detail'].hidden, false);
  cleanup(); assert.equal(modal.removed, true);
  assert.equal(t.document.activeElement.focused, true);
  t.click(page, '[data-history-id]', {historyId: '2'});
  modal = modals.at(-1);
  assert.doesNotMatch(modal.innerHTML, /href="\/certificates\//);
  assert.match(modal.innerHTML, /Withdrawn by the CFO Office/);
  t.document.emit('keydown', {key: 'Escape'}); assert.equal(modal.removed, true);
  t.click(page, '[data-history-id]', {historyId: '4'});
  modal = modals.at(-1); assert.match(modal.innerHTML, /BSN · 3 · B/);
  modal.emit('click'); assert.equal(modal.removed, true);
  const failPage = t.page(Object.keys(page.nodes));
  C.mount('history', failPage, {state, showNotice: t.showNotice, request: async () => { throw new Error('Access denied'); }});
  t.click(failPage, '[data-history-id]', {historyId: '1'});
  modal = modals.at(-1);
  await modal.nodes['[data-full]'].emit('click');
  assert.equal(modal.nodes['[data-full]'].disabled, false);
  assert.deepEqual(t.notices.at(-1), ['Unable to open record', 'Access denied']);
  console.log('History: archive filtering, search/year/attendance, live refresh, details, full record, certificates and modal cleanup passed.');
}

function announcementChecks() {
  const t = setup(), {C} = t;
  const state = {announcements: [
    {id: 1, title: 'Older announcement', preview: 'Bring your ID', body: 'Old full body', published_at: '2025-01-01'},
    {id: 2, title: 'Newer <img src=x>', preview: 'Chapel details', body: '<script>alert(1)</script>', published_at: new Date().toISOString()},
  ]};
  t.location.hash = '#announcements?announcement=1';
  let html = C.pageHtml('announcements', state);
  assert.match(html, /data-announcement="1" id="announcement-focus"/);
  assert.match(html, /<h3>Older announcement<\/h3>/);
  assert.ok(html.indexOf('data-announcement="2"') < html.indexOf('data-announcement="1"'));
  assert.doesNotMatch(html, /<img src=x>/);
  const page = t.page(['#announcementList', '#announcementEmpty', '#announcementDetail', '#announcementSearch', '#announcementSort']);
  C.mount('announcements', page, {state});
  page.nodes['#announcementSort'].value = 'oldest'; page.nodes['#announcementSort'].emit('change');
  assert.ok(page.nodes['#announcementList'].innerHTML.indexOf('data-announcement="1"') < page.nodes['#announcementList'].innerHTML.indexOf('data-announcement="2"'));
  t.click(page, '[data-announcement]', {announcement: '2'});
  assert.match(page.nodes['#announcementDetail'].innerHTML, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
  assert.equal(page.nodes['#announcementDetail'].scrolled, true);
  t.click(page, '[data-announcement-close]');
  assert.match(page.nodes['#announcementDetail'].innerHTML, /Select an announcement/);
  page.nodes['#announcementSearch'].value = 'Bring your ID'; page.nodes['#announcementSearch'].emit('input');
  assert.match(page.nodes['#announcementList'].innerHTML, /Older announcement/);
  assert.doesNotMatch(page.nodes['#announcementList'].innerHTML, /Newer/);
  html = C.pageHtml('announcements', state);
  assert.match(html, /value="Bring your ID"/); assert.match(html, /value="oldest" selected/);
  page.nodes['#announcementSearch'].value = 'no matching announcement'; page.nodes['#announcementSearch'].emit('input');
  assert.equal(page.nodes['#announcementEmpty'].style.display, 'block');
  assert.equal(page.nodes['#announcementList'].style.display, 'none');
  assert.match(C.pageHtml('announcements', {announcements: []}), /No announcements have been published/);
  console.log('Announcements: deep link, search, sort, selection, Close, escaping and persistent filters passed.');
}

async function profileChecks() {
  const t = setup(), {C} = t;
  const user = {name: 'Juan Cruz', first_name: 'Juan', middle_name: '', last_name: 'Cruz', email: 'juan@gmail.com', login_email: 'juan@cfo.office.com',
    course: 'BSIT', year_level: '2', section: 'A', student_id: 'ST-1', status: 'Approved'};
  t.window.CFO_BACKEND.user = user;
  const html = C.pageHtml('profile', {}, user);
  assert.match(html, /BSIT · 2 · A/); assert.match(html, /masked:juan@gmail.com/); assert.match(html, /Student ID: ST-1/);
  const page = t.page(['#profileEditBtn']), requests = [], reviews = [];
  let error, result = {user: {...user, name: 'Maria Cruz', first_name: 'Maria'}}, refreshes = 0, task;
  const context = {user, showNotice: t.showNotice, refresh: () => { refreshes++; }, request: async (...args) => {
    requests.push(args); if (error) throw new Error(error); return result;
  }, submit: (form, action, skip, review) => {
    reviews.push({skip, review}); task = action().catch(e => t.showNotice('Unable to save', e.message));
  }};
  const cleanup = C.mount('profile', page, context);
  page.nodes['#profileEditBtn'].emit('click');
  let modal = t.modals.at(-1), form = modal.nodes.form;
  assert.equal(form.nodes['#profile-email'].protected, true);
  assert.match(modal.innerHTML, /Current Password \*/); assert.match(modal.innerHTML, /autocomplete="current-password" required/);
  form.emit('submit'); assert.deepEqual(t.notices.at(-1), ['Nothing to save', 'You have not changed any information.']);
  assert.equal(requests.length, 0);
  form.nodes['#profile-first-name'].value = 'Maria'; form.nodes['#profile-current'].value = 'WrongPassword';
  error = 'The password is incorrect.'; form.emit('submit'); await task;
  assert.notEqual(modal.removed, true); assert.equal(refreshes, 0);
  assert.deepEqual(t.notices.at(-1), ['Unable to save', 'The password is incorrect.']);
  error = null; form.nodes['#profile-current'].value = 'CorrectPassword!1'; form.emit('submit'); await task;
  assert.equal(requests.at(-1)[0], '/api/profile'); assert.equal(requests.at(-1)[1], 'PUT');
  assert.equal(requests.at(-1)[2].first_name, 'Maria'); assert.equal(requests.at(-1)[2].current_password, 'CorrectPassword!1');
  assert.equal(reviews.at(-1).skip, false); assert.equal(reviews.at(-1).review[0].after, 'Maria');
  assert.equal(modal.removed, true); assert.equal(t.window.CFO_BACKEND.user.name, 'Maria Cruz'); assert.equal(refreshes, 1);
  assert.match(C.pageHtml('profile', {}, t.window.CFO_BACKEND.user), /<h2>Maria Cruz<\/h2>/);
  page.nodes['#profileEditBtn'].emit('click'); modal = t.modals.at(-1); cleanup(); assert.equal(modal.removed, true);
  page.nodes['#profileEditBtn'].emit('click'); modal = t.modals.at(-1); form = modal.nodes.form;
  form.nodes['#profile-password'].value = 'NewPassword!2'; form.nodes['#profile-confirm'].value = 'NewPassword!2';
  result = {requires_login: true, redirect: '/login?password_changed=1'};
  form.emit('submit'); await task;
  assert.equal(t.location.href, '/login?password_changed=1');
  assert.match(reviews.at(-1).review.at(-1).after, /sign out/);
  console.log('My Profile: summary, email masking, edit review, save/error retention, refresh, password redirect and cleanup passed.');
}

(async () => { await historyChecks(); announcementChecks(); await profileChecks(); })().catch(error => {
  console.error(error.stack); process.exitCode = 1;
});
