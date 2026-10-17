const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs'), vm = require('node:vm');

// Minimal DOM: only what student-certificates.js touches.
const listeners = {}, opened = [], clicks = [], closeTasks = [];
const select = {value: '', hidden: false, addEventListener(type, fn) { listeners['select:' + type] = fn; }};
const box = {innerHTML: '', hidden: true}, empty = {hidden: false};
const page = {
  querySelector: sel => ({'#certificate-schedule': select, '#certificate-selected': box, '#certificate-empty': empty}[sel]),
  addEventListener(type, fn) { listeners['page:' + type] = fn; },
};
global.window = {open: url => { const w = {url, listeners: {}, addEventListener(t, f) { this.listeners[t] = f; }, focus() {}, print() { this.printed = true; }}; opened.push(w); return w; }};
global.document = {getElementById: () => null, body: {style:{overflow:'scroll'}, appendChild: n => {n.isConnected=true;clicks.push(n);}}, createElement: tag => {
  if(tag!=='dialog')return {click() { this.clicked = true; }, remove() {this.removed=true;}};
  return {open:false,isConnected:false,listeners:{},attributes:{},
    setAttribute(name,value){this.attributes[name]=value;},
    addEventListener(type,fn){this.listeners[type]=fn;},
    querySelector(){return {focus:()=>{this.focused=true;}};},
    closest(){return null;},getBoundingClientRect(){return {left:10,right:100,top:10,bottom:100};},
    showModal(){assert.equal(this.open,false);this.open=true;},
    close(){this.open=false;closeTasks.push(()=>this.listeners.close?.());},
    remove(){this.removed=true;this.isConnected=false;}};
}};
// The project uses type=module: load the actual browser script rather than an ESM namespace.
const sandbox={window,document,module:{exports:{}}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/student/student-certificates.js'),'utf8'),sandbox);
const C=sandbox.module.exports;

const cert = (id, year, number, date, venue) => ({year, attempt_no: 1, attendance: 'Present', attendance_verified_at: 'x', certificate: {id, certificate_number: number}, schedule: {date, venue, title: 'T'}});
const state = {current_year: 2026, certificate_revision: 'rev1', registrations: [
  cert(7, 2026, 'CFO-2026-000005', '2026-10-24', 'CPD <b>BUILDING</b>'), cert(3, 2025, 'CFO-2025-000048', '2025-10-18', 'MAIN HALL'),
  {year: 2024, certificate: null, schedule: null},
]};

// Page: one option per issued certificate, nothing selected, venue escaped, no certificate drawn client-side.
let html = C.pageHtml(state);
assert.equal((html.match(/<option value="\d+"/g) || []).length, 2);
assert.match(html, /2026 Recollection — October 24, 2026 · CFO-2026-000005/);
assert.match(html, /id="certificate-selected" class="cert-selected"[^>]* hidden/);
assert.ok(!html.includes('<img'), 'no preview before a schedule is chosen');
assert.equal(C.url({id: 'a/b'}), '/certificates/a%2Fb');
assert.equal(C.url({id: 5}, true), '/certificates/5?download=1');

// Selecting shows the server SVG (with the revision cache-buster), escaped details, and the three actions.
const notices = [];
const cleanup=C.mount(page, {state, showNotice: (...a) => notices.push(a)});
select.value = '7'; listeners['select:change']();
assert.match(box.innerHTML, /src="\/certificates\/7\?v=rev1"/);
assert.match(box.innerHTML, /CPD &lt;b&gt;BUILDING&lt;\/b&gt;/);
assert.ok(!box.innerHTML.includes('<b>BUILDING'), 'venue is escaped');
for (const a of ['view', 'print', 'download']) assert.ok(box.innerHTML.includes(`data-cert-action="${a}"`));
assert.equal(box.hidden, false); assert.equal(empty.hidden, true);

// Selection survives the live re-render; unselecting clears it.
html = C.pageHtml(state);
assert.match(html, /<option value="7" selected>/);
assert.match(html, /src="\/certificates\/7\?v=rev1"/);
select.value = ''; listeners['select:change']();
assert.equal(box.hidden, true); assert.equal(empty.hidden, false);
assert.ok(!C.pageHtml(state).includes('<img'));

// A certificate that disappears (revoked) clears a stale selection instead of throwing.
select.value = '3'; listeners['select:change']();
assert.doesNotThrow(() => C.pageHtml({...state, registrations: [state.registrations[0]]}));
assert.ok(!C.pageHtml({...state, registrations: [state.registrations[0]]}).includes('selected>'));

// Print opens the private endpoint, prints once loaded, and reports a blocked pop-up. Download uses ?download=1.
select.value = '7'; listeners['select:change']();
let focusReturns=0;
const trigger={isConnected:true,focus(){focusReturns++;}};
const press = action => listeners['page:click']({target: {closest: () => Object.assign(trigger,{dataset: {certAction: action}})}});
press('print');
assert.equal(opened.at(-1).url, '/certificates/7'); assert.equal(opened.at(-1).opener, null);
opened.at(-1).listeners.load(); assert.equal(opened.at(-1).printed, true);
const realOpen = window.open; window.open = () => null; press('print'); assert.equal(notices.length, 1); window.open = realOpen;
press('download'); assert.equal(clicks.at(-1).href, '/certificates/7?download=1'); assert.equal(clicks.at(-1).rel, 'noopener'); assert.equal(clicks.at(-1).clicked, true);

// Empty states explain eligibility and keep the schedule selector disabled.
const emptyState = s => C.pageHtml({current_year: 2026, registrations: s});
assert.match(emptyState([]), /No recollection registration for the current year yet\./);
assert.match(emptyState([{year: 2026, attendance: 'Absent'}]), /Not available\. Attendance must be marked Present and verified\./);
assert.match(emptyState([{year: 2026, attendance: 'Present', attendance_verified_at: 'x'}]), /being prepared automatically/);
assert.match(emptyState([]), /id="certificate-schedule" disabled/);
console.log('student certificates state: ok');

// Template revisions refresh the image while preserving selection; ineligible records cannot be selected.
select.value='7';listeners['select:change']();
assert.match(C.pageHtml({...state,certificate_revision:'rev2&updated'}),/\/certificates\/7\?v=rev2%26updated/);
const ineligible=[{...state.registrations[0],attendance:'Absent'},
  {...state.registrations[0],attendance_verified_at:null},
  {...state.registrations[0],certificate:{...state.registrations[0].certificate,revoked_at:'2026-10-11'}}];
assert.equal(C.issued({registrations:ineligible}).length,0);
assert.doesNotMatch(C.pageHtml({current_year:2026,registrations:ineligible}),/<img/);
assert.match(C.pageHtml(state),/id="certificate-selected"[^>]* hidden/,'Revocation clears the remembered selection.');

// Native modal: labelled, versioned image, focus, Escape, backdrop and immediate navigation cleanup.
select.value='7';listeners['select:change']();press('view');
let dialog=clicks.at(-1);
assert.equal(dialog.attributes['aria-labelledby'],'certificate-view-title');
assert.equal(dialog.focused,true);assert.equal(dialog.open,true);
assert.match(dialog.innerHTML,/\/certificates\/7\?v=rev1/);
assert.equal(document.body.style.overflow,'hidden');
dialog.listeners.click({target:dialog,clientX:50,clientY:50});assert.equal(dialog.open,true,'Dialog padding is not its backdrop.');
dialog.listeners.click({target:dialog,clientX:0,clientY:0});
assert.equal(dialog.open,false);assert.equal(dialog.removed,true);assert.equal(focusReturns,1);
assert.equal(document.body.style.overflow,'scroll');
press('view');dialog=clicks.at(-1);
let prevented=false;dialog.listeners.cancel({preventDefault(){prevented=true;}});
assert.equal(prevented,true);assert.equal(dialog.open,false);assert.equal(document.body.style.overflow,'scroll');
press('view');const oldDialog=clicks.at(-1);
press('view');dialog=clicks.at(-1);
while(closeTasks.length)closeTasks.shift()();
assert.equal(oldDialog.removed,true);assert.equal(dialog.open,true);
assert.equal(document.body.style.overflow,'hidden','Queued close events cannot unlock a replacement modal.');
cleanup();assert.equal(dialog.removed,true);assert.equal(document.body.style.overflow,'scroll');

// Dynamic details stay escaped even inside the preview dialog.
const hostile={...state,registrations:[{...state.registrations[0],certificate:{id:7,certificate_number:'<img src=x onerror=alert(1)>'}}]};
C.pageHtml(hostile);C.mount(page,{state:hostile,showNotice:(...args)=>notices.push(args)});
select.value='7';listeners['select:change']();press('view');dialog=clicks.at(-1);
assert.match(dialog.innerHTML,/&lt;img src=x onerror=alert\(1\)&gt;/);
assert.doesNotMatch(dialog.innerHTML,/<img src=x/);
dialog.listeners.click({target:{closest:()=>({dataset:{certAction:'close'}})}});
assert.equal(dialog.removed,true);

// The actual Blade wrapper mounts the module and chains navigation cleanup inside the existing shell.
const blade=fs.readFileSync(path.join(__dirname,'../../resources/views/student/index.blade.php'),'utf8');
const start=blade.indexOf('function connectedStudentCertificates() {'),end=blade.indexOf('function connectedRegistrationDetails() {',start);
assert.ok(start>=0&&end>start);
let priorCleanups=0,dashboardOptions;
const wrapper=vm.createContext({CfoStudentCertificates:C,cfoStudent:state,document:{querySelector:()=>page},
  showNotice:(...args)=>notices.push(args),dashboard:options=>{dashboardOptions=options;},dashboardCleanup:()=>{priorCleanups++;}});
vm.runInContext(blade.slice(start,end),wrapper);wrapper.connectedStudentCertificates();
assert.equal(dashboardOptions.active,'certificate');assert.equal(dashboardOptions.titleId,'certificate-title');
select.value='7';listeners['select:change']();press('view');dialog=clicks.at(-1);
wrapper.dashboardCleanup();assert.equal(dialog.removed,true);assert.equal(priorCleanups,1);
assert.equal(document.body.style.overflow,'scroll');
console.log('student certificates extended checks: ok');
