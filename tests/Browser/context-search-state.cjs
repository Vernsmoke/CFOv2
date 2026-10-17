// Actual search implementation; isolated fixtures only. This is not a visual browser test.
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/cfo-search.js'), 'utf8');
const sandbox = {URLSearchParams}; vm.runInNewContext(source, sandbox);
const search = sandbox.CfoSearch;
const admin = {id:1, role:'Admin'}, student = {id:2, role:'Student', name:'José Santos', student_id:'ID-2', course:'BSIT', year_level:'3', section:'A'};
const other = {...student, id:3, name:'Other Student', student_id:'ID-3'};
const schedule = {id:10, title:'Formation Day', date:'2026-10-05', time:'08:00 - 12:00', venue:'Chapel', courses:[{name:'BSIT'}], year_level:'3', section:'A', active:true, year:2026};
const registration = {id:20, user_id:2, user:student, year:2026, is_archived:false, status:'Approved', receipt_status:'Approved', attendance:'Present', recollection_schedule_id:10, schedule,
  created_at:'2026-10-03T10:00:00Z', certificate:{id:30, certificate_number:'CFO-2026-0030'}, details:{medical:'medical-secret', religion:'faith-secret'}, receipt_path:'receipt-secret'};
const unscheduled = {...registration, id:21, schedule:null, recollection_schedule_id:null, status:'Pending', attendance:'Not Yet Recorded', certificate:null};
const archived = {...registration, id:22, year:2025, is_archived:true, student_snapshot:{...student, name:'Historical Name'}};
const foreign = {...registration, id:23, user_id:3, user:other, recollection_schedule_id:11, schedule:{...schedule, id:11, title:'Second Batch'}};
const request = {id:40, registration_id:20, registration, requested_schedule:{...schedule, title:'Alternative Formation'}, status:'Pending', created_at:'2026-10-03'};
const reset = {id:50, user:student, display_status:'Pending', status:'Pending', created_at:'2026-10-03', recovery_hash:'recovery-secret', temp_password_hash:'password-secret'};
const state = {current_year:2026, server_date:'2026-10-03', users:[admin, student, other], registrations:[registration, unscheduled, foreign, archived], history:[archived], schedules:[schedule, {...schedule,id:12,finalized_at:'2026-10-01'}],
  requests:[request], resets:[reset], academic_options:[{id:1,name:'BSIT',type:'course'}],
  announcements:[{id:60,title:'Office Hours',preview:'Visit the office',body:'Bring your student identification',published_at:'2026-10-03'}],
  logs:[{action:'registration.created',actor:'CFO Admin',created_at:'2026-10-03',record_type:'registration',record_id:20,metadata:'metadata-secret'}]};
const context = (route, extra={}) => search.buildContext({role:'Admin', user:admin, state, route, ...extra});
const mine = (route, extra={}) => context(route, {role:'Student', user:student, ...extra});
const ids = c => Array.from(c.entries, e=>e.id);
assert.equal(context('reports'), null); assert.equal(context('sessions'), null);
assert.equal(context('admin-dashboard', {role:'Visitor'}), null);
assert.equal(mine('admin-all-student'), null);
assert.deepEqual(ids(context('admin-all-student')), [2,3]);
assert.deepEqual(ids(context('admin-register-this-year')), [21]);
assert.deepEqual(ids(context('admin-register-this-year', {rows:[]})), []);
assert.deepEqual(ids(context('admin-register-this-year', {rows:[registration,unscheduled,archived]})), [21], 'supplied rows cannot leak assigned or archived students into the registration queue');
assert.deepEqual(ids(context('admin-student-attendance',{params:new URLSearchParams('batch=10')})), [20]);
assert.equal(context('admin-student-attendance').entries.length, 2);
assert.deepEqual(ids(context('admin-student-attendance',{rows:[registration,unscheduled,archived,{...foreign,year:2027},{...foreign,status:'Pending'}]})), [20], 'supplied attendance rows retain current-year approval and archive protections');
assert.equal(context('admin-student-attendance').entries[1].href,'#admin-student-attendance?batch=11');
assert.equal(context('admin-recollection-schedule').entries.length,1);
assert.equal(search.find(context('admin-recollection-schedule').entries,'Chapel Upcoming').length,1);
const multiSchedule = {...schedule, year_levels: ['1', '4'], sections: ['A', 'Z'], year_level: '1', section: 'A'};
assert.equal(search.matches(search.gridFields('connected-schedules', multiSchedule), '4 Z'), true, 'Schedule search includes secondary years and sections');
assert.equal(search.find(context('admin-recollection-schedule', {state: {...state, schedules: [multiSchedule]}}).entries, '4 Z').length, 1);
assert.equal(search.find(context('admin-history').entries,'Historical Name').length,1);
assert.equal(search.find(context('admin-history').entries,'José Santos').length,0, 'history uses saved identity');
assert.equal(search.find(context('admin-student-reciept').entries,'Approved').length,3);
assert.equal(search.find(context('admin-certificate').entries,'CFO-2026-0030').length,2);
assert.equal(search.find(context('admin-schedule-change-requests').entries,'Alternative').length,1);
const assignedSchedule={...schedule,id:13,title:'Assigned Renewal',date:'2026-10-22'};
const selectedRequestRegistration={...registration,user:{...student,course:'Outdated request profile'},details:{...registration.details,course:'BSECE',yearLevel:'1',section:'Z'},schedule:assignedSchedule};
const assignedRequest={...request,registration:selectedRequestRegistration,current_schedule:{...schedule,title:'Original Formation'},requested_schedule:null,assigned_schedule:assignedSchedule,reason:'Exam timetable conflict',status:'Approved'};
const assignedState={...state,requests:[assignedRequest]};
for(const [route,role] of [['admin-schedule-change-requests','Admin'],['schedule-change-request','Student']]){
  const requestContext=context(route,{state:assignedState,role,user:role==='Admin'?admin:student});
  assert.equal(search.find(requestContext.entries,'Assigned Renewal').length,1,'Assigned schedule is discoverable: '+role);
  assert.equal(search.find(requestContext.entries,'Exam timetable').length,1,'Student reason is discoverable: '+role);
  assert.equal(search.find(requestContext.entries,'SCR-2026-040').length,1,'Displayed request identifier is discoverable: '+role);
  assert.equal(search.find(requestContext.entries,'Original Formation').length,1,'Immutable original assignment remains discoverable after the registration moves: '+role);
  assert.equal(search.find(requestContext.entries,'BSECE 1Z').length,1,'Submitted academic group is discoverable: '+role);
  assert.equal(search.find(requestContext.entries,'Outdated request profile').length,0,'Outdated academic profile is excluded: '+role);
  assert.match(requestContext.entries[0].detail,/Assigned Renewal/);
  assert.equal(search.find(requestContext.entries,'Alternative Formation').length,0,'A reason-only request does not invent a legacy target.');
}
assert.equal(search.matches(search.gridFields('connected-requests',assignedRequest),'Assigned Renewal Exam timetable SCR-2026-040'),true);
for(const [status,display] of [['Rejected','Not Approved'],['Closed','Not Approved'],['Cancelled','Cancelled']]){
  const statusState={...assignedState,requests:[{...assignedRequest,status,student_status:display,student_message:status==='Cancelled'?'You cancelled this request.':'Visit the CFO office.'}]};
  const studentRequest=context('schedule-change-request',{state:statusState,role:'Student',user:student});
  assert.match(studentRequest.entries[0].detail,new RegExp(display),'Students discover the same status shown on their page: '+status);
  assert.doesNotMatch(studentRequest.entries[0].detail,/Rejected|Closed/,'Internal review status is not shown to students.');
  assert.equal(search.find(studentRequest.entries,display).length,1,'Student-facing status remains searchable.');
  assert.match(context('admin-schedule-change-requests',{state:statusState}).entries[0].detail,new RegExp(status),'Admin status remains the real value.');
}
for(const details of [{},{course:'BSECE',yearSection:'1Z'},{course:'BSECE',yearLevel:'1'}]){
  const fallbackRequest={...assignedRequest,registration:{...registration,details}};
  assert.equal(search.matches(search.gridFields('connected-requests',fallbackRequest),'BSIT 3A'),true,'Incomplete/legacy academic choices retain the same profile fallback as request assignment.');
  assert.equal(search.matches(search.gridFields('connected-requests',fallbackRequest),'BSECE 1Z'),false,'An incomplete selected group is not treated as a submitted assignment identity.');
}
assert.equal(mine('schedule-change-request',{state:{...assignedState,requests:[{...assignedRequest,registration_id:23}]}}).entries.length,0,'Assigned requests retain student ownership protection.');
assert.equal(search.find(context('admin-announcements').entries,'identification').length,1);
assert.equal(search.find(context('admin-course-year').entries,'course BSIT').length,1);
assert.equal(search.find(context('admin-activity').entries,'CFO Admin 20').length,1);
assert.equal(context('admin-password-resets').entries.length,0,'recovery is restricted to the current tab rows');
const recovery = context('admin-password-resets',{rows:[reset],recoveryStatus:()=> 'Expired'});
assert.equal(search.find(recovery.entries,'Expired').length,1);assert.equal(search.find(recovery.entries,'Pending').length,0);
for (const route of ['admin-dashboard','admin-settings','admin-lan-access','admin-certificate-template','admin-add-new-recollection-schedule']) {
  assert.ok(context(route).entries.every(e=>e.group==='Pages'));
}
assert.equal(context('admin-certificate-template').entries.length,2);
assert.deepEqual(ids(mine('recollection-registration')), [20,21]);
assert.deepEqual(ids(mine('recollection-history')), [22]);
assert.equal(mine('certificate').entries.length,2);
assert.equal(search.find(mine('dashboard').entries,'Other Student').length,0);
assert.equal(search.find(mine('dashboard').entries,'Password reset requests').length,0);
assert.equal(mine('schedule-change-request').entries.length,1);
assert.equal(mine('announcements').entries.length,1);
assert.ok(mine('my-profile').entries.every(e=>e.group==='Pages'));
assert.equal(mine('schedule-change-request',{state:{...state, requests:[{...request,registration_id:23}]}}).entries.length,0);
assert.equal(search.matches('José Santos BSIT-3A', '  jose  bsit-3a '),true);
assert.equal(search.matches('José Santos', '.*'), false, 'query is literal, not a regexp');
assert.equal(search.matches('José Santos', 'santos jose'),true);
assert.equal(search.matches('Historical Student 0 2026-09-01', 'Historical Student 0'),true);
assert.equal(search.matches('Historical Student 2 2026-09-01', 'Historical Student 0'),false);
assert.equal(search.gridFields('unknown-module',registration),'');
for (const key of ['connected-registrations','connected-history','connected-receipts','certificates','batch-attendance-10']) {
  const words = search.gridFields(key,registration);
  for (const secret of ['medical-secret','receipt-secret','user_id','receipt_path',...(key.startsWith('batch-attendance-')?[]:['faith-secret'])]) assert.ok(!words.includes(secret),key+' excludes '+secret);
}
assert.ok(search.gridFields('batch-attendance-10',registration).includes('faith-secret'),'Attendance can search the Religion field displayed in its table');
const healthWords=search.gridFields('batch-health-10',{...registration,details:{...registration.details,allergies:'allergy-note',reflection:'private-reflection'}});
assert.ok(healthWords.includes('medical-secret'));assert.ok(healthWords.includes('allergy-note'));assert.ok(healthWords.includes('faith-secret'));
assert.ok(!healthWords.includes('private-reflection'));assert.ok(!healthWords.includes('receipt-secret'));
const selectedAcademic={...registration,user:{...student,course:'Outdated Program'},details:{...registration.details,course:' BSECE ',yearLevel:' 1 ',section:' Z ',yearSection:'1Z'}};
assert.deepEqual({...search.registrationAcademicGroup(selectedAcademic)},{course:'BSECE',year_level:'1',section:'Z'},'Submitted choices override the profile and are trimmed.');
assert.deepEqual({...search.registrationAcademicGroup({...registration,details:{course:'BSN',yearSection:'4-Y'}})},
  {course:'BSN',year_level:'4',section:'Y'},'Legacy combined year/section values stay searchable.');
assert.deepEqual({...search.registrationAcademicGroup({...registration,details:{},student_snapshot:{course:'BSED',year_level:'2',section:'C'}})},
  {course:'BSED',year_level:'2',section:'C'},'Saved snapshots supply academic information before a subsequently changed profile.');
assert.deepEqual({...search.registrationAcademicGroup(registration)},
  {course:'BSIT',year_level:'3',section:'A'},'Registrations without saved academic choices retain the profile fallback.');
for(const key of ['batch-attendance-10','batch-health-10']){
  const words=search.gridFields(key,selectedAcademic);
  assert.ok(search.matches(words,'BSECE 1Z'),key+' searches the selected academic group.');
  assert.equal(search.matches(words,'Outdated Program'),false,key+' does not search an outdated profile course.');
}
const selectedCertificate={...selectedAcademic,
  user:{...selectedAcademic.user,year_level:'9',section:'X',email:'certificate-email-secret'},
  student_snapshot:{name:'Saved certificate name',student_id:'OLD-CERT-ID',course:'Old snapshot program',year_level:'2',section:'Y'},
  details:{...selectedAcademic.details,allergies:'certificate-allergy-secret',emergencyPhone:'certificate-phone-secret'}};
const certificateWords=search.gridFields('certificates',selectedCertificate);
assert.ok(search.matches(certificateWords,'José ID-2 BSECE 1Z CFO-2026-0030'),'Certificate table search follows the displayed identity and registration academic choices.');
for(const hidden of ['Outdated Program','9X','Old snapshot program','2Y','Saved certificate name','OLD-CERT-ID','medical-secret','faith-secret','certificate-allergy-secret','certificate-phone-secret','certificate-email-secret','receipt-secret']){
  assert.equal(search.matches(certificateWords,hidden),false,'Certificate table excludes stale identity or private data: '+hidden);
}
const certificateContext=context('admin-certificate',{state:{...state,registrations:[selectedCertificate,foreign]},rows:[selectedCertificate]});
assert.deepEqual(Array.from(search.find(certificateContext.entries,'BSECE 1Z'),entry=>entry.id),[20],'Admin certificate discovery uses the submitted course/year/section.');
assert.equal(search.find(certificateContext.entries,'José ID-2 CFO-2026-0030').length,1,'Certificate identity and certificate number remain discoverable.');
assert.equal(certificateContext.entries[0].href,'/certificates/30','Certificate discovery preserves the private certificate endpoint.');
for(const hidden of ['Outdated Program','9X','Old snapshot program','2Y','medical-secret','faith-secret','certificate-allergy-secret','certificate-phone-secret','certificate-email-secret','receipt-secret']){
  assert.equal(search.find(certificateContext.entries,hidden).length,0,'Admin certificate discovery excludes stale academics or private data: '+hidden);
}
const studentCertificateContext=mine('certificate',{state:{...state,registrations:[selectedCertificate,foreign]}});
assert.deepEqual(ids(studentCertificateContext),[20],'Student certificate discovery retains existing ownership filtering.');
assert.equal(search.find(studentCertificateContext.entries,'José ID-2').length,1,'Student certificate discovery retains its existing account identity.');
for(const hidden of ['Other Student','medical-secret','faith-secret','certificate-allergy-secret','certificate-phone-secret','certificate-email-secret','receipt-secret']){
  assert.equal(search.find(studentCertificateContext.entries,hidden).length,0,'Student certificate discovery retains its private-data allowlist: '+hidden);
}
// Student certificate discovery uses one academic group even when its schedule serves multiple groups.
const sharedCertificateSchedule={...schedule,title:'BSECE-1Z and BSN-4Y Recollection',
  courses:[{name:'BSECE'},{name:'BSN'}],year_levels:['1','4'],sections:['Z','Y'],start_time:'08:15',end_time:'12:15'};
const sharedCertificate={...selectedCertificate,schedule:sharedCertificateSchedule};
const ownCertificateContext=mine('certificate',{state:{...state,registrations:[sharedCertificate,foreign]}});
assert.deepEqual(ids(ownCertificateContext),[20],'Shared schedule groups do not alter student ownership.');
assert.match(ownCertificateContext.entries[0].detail,/BSECE 1Z/,'Student certificate discovery displays the registration academic group.');
assert.equal(search.find(ownCertificateContext.entries,'BSECE 1Z ID-2 CFO-2026-0030').length,1,'The own group, account identity and certificate remain searchable.');
assert.equal(ownCertificateContext.entries[0].href,'/certificates/30','Student certificate discovery keeps the private certificate endpoint.');
for(const metadata of [schedule.date,schedule.time,schedule.venue,'08:15','12:15']){
  assert.equal(search.find(ownCertificateContext.entries,metadata).length,1,'Certificate schedule metadata remains searchable: '+metadata);
  assert.ok(ownCertificateContext.entries[0].detail.includes(metadata),'Certificate schedule metadata remains visible: '+metadata);
}
for(const hidden of [sharedCertificateSchedule.title,'BSN','4Y','BSIT 3A','Outdated Program','Old snapshot program','9X','2Y']){
  assert.equal(search.find(ownCertificateContext.entries,hidden).length,0,'Student certificate discovery excludes shared groups and stale academics: '+hidden);
}
assert.doesNotMatch(ownCertificateContext.entries[0].terms,/\b4\b|\bY\b/,'Shared schedule year and section arrays are excluded from certificate terms.');
const sharedAdminCertificateContext=context('admin-certificate',{state:{...state,registrations:[sharedCertificate]},rows:[sharedCertificate]});
assert.equal(search.find(sharedAdminCertificateContext.entries,sharedCertificateSchedule.title).length,1,'Admin certificate search retains its shared schedule title.');
assert.equal(search.matches(search.gridFields('certificates',sharedCertificate),'BSN 4 Y'),true,'Admin certificate table search retains shared schedule groups.');
assert.equal(search.find(mine('recollection-registration',{state:{...state,registrations:[sharedCertificate]}}).entries,sharedCertificateSchedule.title).length,1,'Student registration discovery retains the shared schedule title.');
for(const [saved,query,staleQuery] of [[{details:{course:'BSN',yearSection:'4-y'},student_snapshot:null},'BSN 4Y'],
  [{details:{},student_snapshot:{course:'BSED',year_level:'2',section:'C'}},'BSED 2C'],
  [{details:{},student_snapshot:{course:'BSED',year_level:'2',section:'C',submitted_year_section:'4-y'}},'BSED 4Y','BSED 2C'],
  [{details:{},student_snapshot:{course:'BSED',year_level:'2',section:'C',submitted_year_section:'3/A'}},'BSED 3A','BSED 2C'],
  [{details:{},student_snapshot:null},'BSIT 3A']]){
  const legacyContext=mine('certificate',{state:{...state,registrations:[{...registration,...saved,user:undefined}]}});
  assert.equal(search.find(legacyContext.entries,query).length,1,'Student certificate group follows legacy, snapshot and account fallback: '+query);
  if(staleQuery) assert.equal(search.find(legacyContext.entries,staleQuery).length,0,'Saved combined year/section takes precedence over stale snapshot values.');
}
const selectedContext=context('admin-student-attendance',{state:{...state,registrations:[selectedAcademic]},params:new URLSearchParams('batch=10')});
assert.deepEqual(Array.from(search.find(selectedContext.entries,'BSECE 1Z'),entry=>entry.id),[20]);
assert.equal(search.find(selectedContext.entries,'Outdated Program').length,0,'Attendance discovery uses the same submitted academic choices as its table.');
assert.ok(!search.gridFields('activity',state.logs[0]).includes('metadata-secret'));
assert.ok(!search.gridFields('recovery-Pending',reset).includes('recovery-secret'));
assert.ok(!search.gridFields('recovery-Pending',reset).includes('password-secret'));
const sorted = search.sortRows([{id:10},{id:2},{id:1}], [{get:r=>r.id}], 0, 'asc');
assert.deepEqual(Array.from(sorted,r=>r.id),[1,2,10]);
assert.deepEqual(Array.from(search.sortRows(sorted,[{get:r=>r.id}],0,'desc'),r=>r.id),[10,2,1]);
assert.equal(search.gridOptions('connected-schedules').searchLabel,'Filter this table');
const ranked = search.find([{title:'Other Student',detail:'José',terms:'',group:'Students'}, {title:'José',detail:'',terms:'',group:'Students'}], 'jose');
assert.equal(ranked[0].title,'José');
assert.equal(search.find(mine('dashboard').entries,'','Pages').every(e=>e.group==='Pages'),true);

// Small DOM model exercises mount, focus, escape, pagination, failure and refresh behavior.
let document;
class Element {
  constructor(tag) { this.tagName=tag; this.children=[]; this.attrs={}; this.listeners={}; this.value=''; this.hidden=false; this.className=''; this.textContent=''; }
  append(...nodes) { for (const node of nodes) { node.parentElement=this; this.children.push(node); } }
  replaceChildren(...nodes) { this.children=[]; this.append(...nodes); }
  setAttribute(k,v) { this.attrs[k]=String(v); }
  removeAttribute(k) { delete this.attrs[k]; }
  addEventListener(k,fn) { (this.listeners[k] ||= []).push(fn); }
  emit(k,event={}) { for(const fn of [...(this.listeners[k]||[]),...(this['on'+k]?[this['on'+k]]:[])])fn({target:this,preventDefault(){},stopPropagation(){},...event}); }
  focus() { document.activeElement=this; this.emit('focus'); }
  contains(node) { return node===this || this.children.some(child=>child.contains(node)); }
  matches(selector) { return selector.startsWith('.') ? this.className.split(' ').includes(selector.slice(1)) : selector==='[role=status]' ? this.attrs.role==='status' : this.tagName===selector; }
  closest(selector) { return this.matches(selector)?this:this.parentElement?.closest(selector); }
  querySelectorAll(selector) { return this.children.flatMap(child=>[...(child.matches(selector)?[child]:[]),...child.querySelectorAll(selector)]); }
  querySelector(selector) { return this.querySelectorAll(selector)[0]||null; }
  set innerHTML(html) {
    // Only static component scaffolding is assigned with innerHTML; records must use textContent.
    assert.ok(!html.includes('Unsafe title'));
    const stack=[this];this.children=[];
    for(const token of html.matchAll(/<\/?([a-z0-9]+)([^>]*)>/gi)) {
      if(token[0].startsWith('</')) {stack.pop();continue;}
      const node=new Element(token[1]);
      for(const attr of token[2].matchAll(/([\w-]+)="([^"]*)"/g)) {node.setAttribute(attr[1],attr[2]);if(attr[1]==='class')node.className=attr[2];}
      node.hidden=/\bhidden\b/.test(token[2]);stack.at(-1).append(node);
      if(!['input','br'].includes(token[1]))stack.push(node);
    }
  }
}
document={activeElement:null,createElement:tag=>new Element(tag),addEventListener(){},querySelectorAll:()=>[]};
function Option(label,value){const node=new Element('option');node.textContent=label;node.value=value;return node;}
const ui={document,Option,URLSearchParams,setTimeout:fn=>fn()};vm.runInNewContext(source,ui);
const host=new Element('div');
const entry=(i)=>({group:i%2?'Registrations':'Attendance',title:'Student '+i,detail:'Saved record',terms:String(i),kind:'registration',id:i});
const view={key:'Admin:1:records',title:'Registration',hint:'Current page',placeholder:'Search saved records',suggestions:['Registrations','Attendance'],entries:Array.from({length:25},(_,i)=>entry(i))};
let selected;
ui.CfoSearch.mount(host,view,e=>selected=e);
const input=host.querySelector('input'),resultPanel=host.querySelector('.cfo-search-results');
input.focus();assert.equal(resultPanel.hidden,false);assert.equal(host.querySelectorAll('.cfo-search-result').length,12);
host.emit('keydown',{key:'ArrowUp',target:input});assert.equal(document.activeElement,host.querySelectorAll('.cfo-search-result').at(-1));
host.emit('keydown',{key:'Escape',target:document.activeElement});assert.equal(resultPanel.hidden,true);assert.equal(document.activeElement,input);
input.value='Student 24';input.emit('input');assert.equal(host.querySelectorAll('.cfo-search-result').length,1);
host.querySelector('.cfo-search-result').emit('click');assert.equal(selected.id,24);assert.equal(resultPanel.hidden,true);
assert.equal(document.activeElement,input,'record dialogs capture the visible search input as their return-focus target');
input.value='no-matches';input.emit('input');assert.match(host.querySelector('[role=status]').textContent,/No matches/);
ui.CfoSearch.mount(host,{...view,entries:[...view.entries,entry(99)]},e=>selected=e);assert.equal(input.value,'no-matches','query survives live state updates');
host.querySelector('.cfo-search-clear').onclick();assert.equal(input.value,'');assert.equal(host.querySelectorAll('.cfo-search-result').length,12);
host.querySelector('.cfo-search-more').onclick();assert.equal(host.querySelectorAll('.cfo-search-result').length,24);
assert.equal(host.querySelector('select'),null,'Admin search has no category selector.');
assert.equal(host.querySelectorAll('h3').length,0,'Admin results have no category headings.');
assert.equal(host.querySelectorAll('.cfo-search-result').length,24,'Admin search includes every result category.');
input.value='Student 2';input.emit('input');
ui.CfoSearch.pending(host);assert.equal(host.querySelector('input'),null);assert.equal(host.attrs['aria-busy'],'true');
ui.CfoSearch.pending(host,'Unable to load. Retry this page.',true);assert.equal(host.children[0].attrs.role,'alert');
ui.CfoSearch.mount(host,view);assert.equal(host.querySelector('input').value,'Student 2','draft survives navigation retry');
ui.CfoSearch.mount(host,{...view,key:'Admin:1:other'});assert.equal(host.querySelector('input').value,'','new module has its own query');
ui.CfoSearch.mount(host,{...view,key:'Student:2:other',entries:[{...entry(1),title:'Unsafe title <img src=x onerror=alert(1)>'}]});
assert.ok(host.querySelector('select'),'Student search still supports its category selector.');
host.querySelector('input').focus();assert.equal(host.querySelector('.cfo-search-result').children[0].textContent,'Unsafe title <img src=x onerror=alert(1)>');
ui.CfoSearch.mount(host,null);assert.equal(host.hidden,true);assert.equal(host.children.length,0);
assert.equal(host.onkeydown,null);assert.equal(host._cfoSearch,null);
ui.CfoSearch.mount(host,view);assert.ok(host.querySelector('input'),'a hidden/unsupported page can return to a working search');
console.log('PASS: all module scopes, ownership defense, allowed fields, history, batch/tab filters, literal matching, sorting, suggestions, keyboard, pagination, escaping, loading/error and query preservation.');
