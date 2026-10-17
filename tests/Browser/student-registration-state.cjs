const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/student/index.blade.php','utf8');
const start=source.indexOf('const registrationFieldLabels ='),end=source.indexOf('function recollectionRegistration()',start);
assert.ok(start>0&&end>start);
const records=[{id:1,year:2024,details:{name:'Oldest'}},{id:3,year:2026,status:'Pending',details:{name:'Latest',course:'BSN',yearSection:'3-B',medical:'Health info',phone:'123',participation:true,photos:true}},{id:2,year:2025,details:{name:'Middle'}}];
const registrationSchedules=[{courses:[{id:1,name:'BSIT'},{id:2,name:'BSN'}],year_levels:['2','3'],course_sections_by_year:{1:{2:['A'],3:['A']},2:{3:['B']}}}];
const context=vm.createContext({window:{CFO_BACKEND:{user:{name:'Profile',email:'profile@example.test',course:'BSIT',year_level:'2',section:'A'}}},cfoStudent:{current_year:2027,registrations:records,registration_courses:['BSIT','BSN'],registration_schedules:registrationSchedules},URL:{revokeObjectURL(){}},document:{},escapeHTML:String});
vm.runInContext(source.slice(start,end),context);
vm.runInContext(fs.readFileSync('public/assets/student/student-receipts.js','utf8'),context);
context.document.addEventListener=()=>{};
vm.runInContext(fs.readFileSync('public/assets/student/student-security.js','utf8'),context);
const run=code=>vm.runInContext(code,context);
run('initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.name'),'Latest');
assert.equal(run('recollectionState.values.course'),'BSIT','The account course overrides a previous year with another course.');
assert.equal(run('recollectionState.values.yearSection'),'2A');
assert.equal(run('recollectionState.values.yearLevel'),'2');
assert.equal(run('recollectionState.values.section'),'A');
assert.equal(records[1].details.course,'BSN','The historical course remains unchanged.');
assert.equal(run('recollectionState.values.medical'),'Health info');
assert.equal(run('recollectionState.values.phone'),'');
assert.equal(records[1].details.phone,'123');
assert.equal(run('recollectionState.values.email'),'profile@example.test');
assert.equal(run('recollectionState.values.participation'),false);
assert.equal(run('recollectionState.values.photos'),false);
assert.equal(run('recollectionState.receipt'),null);
run("recollectionState.values.name='Unsaved name';initializeRecollectionDraft()");
assert.equal(run('recollectionState.values.name'),'Unsaved name');
// Reopening a draft resolves a configured course, retaining unrelated work in progress.
run("recollectionState.values.course='bsit';recollectionState.values.yearLevel='3';recollectionState.values.section='a';recollectionState.values.photos=true;recollectionState.receipt={name:'new.png'};initializeRecollectionDraft()");
assert.equal(run('recollectionState.values.course'),'BSIT');
assert.equal(run('recollectionState.values.yearSection'),'3A');
assert.equal(run('recollectionState.values.photos'),true);
assert.equal(run('recollectionState.receipt.name'),'new.png');
run("recollectionState.values.yearLevel='5';recollectionState.values.section='A';initializeRecollectionDraft()");
assert.equal(run('recollectionState.values.yearSection'),'');
run('clearRecollection();initializeRecollectionDraft(cfoStudent.registrations[1])');
assert.equal(run('recollectionState.registrationId'),3);
assert.equal(run('recollectionState.values.name'),'Latest');
assert.equal(run('recollectionState.values.participation'),true);
run("recollectionState.values.name='<script>Changed</script>'");
const review=run('registrationReview(recollectionState.values,recollectionState.baseline)');
assert.equal(review.length,3);assert.equal(review[0].before,'Latest');assert.equal(review[0].after,'<script>Changed</script>');
assert.equal(review[1].before,'3-B');assert.equal(review[1].after,'3 / B');
assert.equal(review[2].before,'123');assert.equal(review[2].after,'(Not provided)');
assert.equal(records[1].details.name,'Latest');
assert.equal(records[1].details.yearSection,'3-B');
assert.equal(run('recollectionState.baseline.yearSection'),'3-B');
assert.equal(run('recollectionState.baseline.phone'),'123');
context.cfoStudent.registrations=[];
run('clearRecollection();initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.name'),'Profile');
assert.equal(run('recollectionState.values.yearSection'),'2A');

// Legacy data may be normalized for an editable draft, never written back to the source.
context.cfoStudent.registrations=[{id:9,year:2026,details:{course:'bsit',yearSection:'3-a'}}];
run('clearRecollection();initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.course'),'BSIT');
assert.equal(run('recollectionState.values.yearSection'),'3A');
assert.equal(context.cfoStudent.registrations[0].details.course,'bsit');
assert.equal(context.cfoStudent.registrations[0].details.yearSection,'3-a');
// A legacy selection remains visible when its schedule is no longer available.
context.cfoStudent.registration_schedules=[];
run('clearRecollection();initializeRecollectionDraft(cfoStudent.registrations[0])');
assert.equal(run('recollectionState.values.course'),'BSIT');
assert.equal(run('recollectionState.values.yearLevel'),'3');
assert.equal(run('recollectionState.values.section'),'A');
assert.equal(run('recollectionState.values.yearSection'),'3A');
assert.equal(context.cfoStudent.registrations[0].details.yearSection,'3-a');
context.cfoStudent.registration_schedules=registrationSchedules;
for(const invalid of ['5A','3AB','A3','12A','',null,123]) {
  context.cfoStudent.registrations[0].details.yearSection=invalid;
  run('clearRecollection();initializeRecollectionDraft()');
  assert.equal(run('recollectionState.values.yearSection'),'',String(invalid));
}
context.cfoStudent.registrations[0].details.course='123-';
run('clearRecollection();initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.course'),'BSIT');
assert.equal(run('recollectionState.values.yearSection'),'2A');
context.cfoStudent.registrations[0].details={course:'BSN',yearSection:'2A'};
run('clearRecollection();initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.yearSection'),'2A','A historical foreign course uses the saved account group instead.');

// Legacy phone numbers are normalized only in the editable copy, including reopened drafts.
const legacyPhones={phone:'+63 912 345 6789',emergencyPhone:'(0998) 765-4321'};
context.cfoStudent.registrations=[{id:10,year:2026,details:{course:'BSIT',yearSection:'3A',...legacyPhones}}];
run('clearRecollection();initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.phone'),'09123456789');
assert.equal(run('recollectionState.values.emergencyPhone'),'09987654321');
assert.equal(context.cfoStudent.registrations[0].details.phone,legacyPhones.phone);
assert.equal(context.cfoStudent.registrations[0].details.emergencyPhone,legacyPhones.emergencyPhone);
run("recollectionState.values.name='Unsaved phone draft';recollectionState.values.phone='9123456789';recollectionState.values.emergencyPhone='63 998 765 4321';initializeRecollectionDraft()");
assert.equal(run('recollectionState.values.phone'),'09123456789');
assert.equal(run('recollectionState.values.emergencyPhone'),'09987654321');
assert.equal(run('recollectionState.values.name'),'Unsaved phone draft');
const pendingPhoneRecord={id:11,year:2027,status:'Pending',details:{course:'BSIT',yearSection:'3A',phone:'+639123456789',emergencyPhone:'9987654321'}};
context.pendingPhoneRecord=pendingPhoneRecord;
run('clearRecollection();initializeRecollectionDraft(pendingPhoneRecord)');
assert.equal(run('recollectionState.values.phone'),'09123456789');
assert.equal(run('recollectionState.values.emergencyPhone'),'09987654321');
assert.equal(run('recollectionState.baseline.phone'),'+639123456789');
assert.equal(run('recollectionState.baseline.emergencyPhone'),'9987654321');
assert.equal(pendingPhoneRecord.details.phone,'+639123456789');
assert.equal(pendingPhoneRecord.details.emergencyPhone,'9987654321');
run('clearRecollection();initializeRecollectionDraft()');
run("recollectionState.values.phone='09111111111';recollectionState.values.emergencyPhone='123';initializeRecollectionDraft()");
assert.equal(run('recollectionState.values.phone'),'');
assert.equal(run('recollectionState.values.emergencyPhone'),'');
for(const invalid of ['09111111111','09000000000','123','08123456789','091234567890','',null]) {
  context.cfoStudent.registrations[0].details.phone=invalid;
  context.cfoStudent.registrations[0].details.emergencyPhone=invalid;
  run('clearRecollection();initializeRecollectionDraft()');
  assert.equal(run('recollectionState.values.phone'),'',String(invalid));
  assert.equal(run('recollectionState.values.emergencyPhone'),'',String(invalid));
  assert.equal(context.cfoStudent.registrations[0].details.phone,invalid);
  assert.equal(context.cfoStudent.registrations[0].details.emergencyPhone,invalid);
}
for(const [input,expected] of [
  ['a09b123c456789!','09123456789'],
  ['+639123456789','09123456789'],
  ['+63 912 345 6789','09123456789'],
  ['639123456789','09123456789'],
  ['9123456789','09123456789'],
  ['0912-345-6789','09123456789'],
  ['(0912).345.6789','09123456789'],
]) {
  context.raw=input;
  assert.equal(run('normalizeRecollectionPhone(raw)'),expected);
  assert.equal(run('isValidRecollectionPhone(normalizeRecollectionPhone(raw))'),true);
}
context.raw='091234567890';
assert.equal(run('normalizeRecollectionPhone(raw)'),'091234567890','Validation normalization must retain overlength digits.');
assert.equal(run('sanitizeRecollectionPhoneInput(raw)'),'09123456789');
for(const invalid of ['09111111111','09000000000','09999999999','0912','08123456789','091234567890']) {
  context.raw=invalid;
  assert.equal(run('isValidRecollectionPhone(raw)'),false,invalid);
}

// Shared field mock for production phone/religion handlers.
context.Event=class {constructor(type,options){this.type=type;Object.assign(this,options);}};
function academicInput(name) {
  const listeners={},error={textContent:''};
  return {name,value:'',selectionStart:0,selectionEnd:0,attributes:{},error,
    addEventListener(type,callback){listeners[type]=callback;},
    dispatchEvent(event){listeners[event.type]?.(event);},
    closest(){return {querySelector:()=>error};},
    setCustomValidity(message){this.validationMessage=message;},
    setAttribute(name,value){this.attributes[name]=value;},
    setSelectionRange(start,end){this.selectionStart=start;this.selectionEnd=end;}};
}
// Exercise production phone event handlers before native maxlength can truncate a country-code paste.
const phoneFields={phone:academicInput('phone'),emergencyPhone:academicInput('emergencyPhone')};
context.phoneForm={elements:{namedItem:name=>phoneFields[name]}};
run('bindRecollectionPhoneFields(phoneForm)');
const phoneMessage='Enter a valid mobile number like 09123456789.';
const emergencyMessage='Emergency contact number must be different from your own number.';
function typePhone(name,value) {
  const input=phoneFields[name];input.value=value;input.setSelectionRange(value.length,value.length);input.dispatchEvent({type:'input'});return input;
}
function pastePhone(name,value) {
  const input=phoneFields[name];input.setSelectionRange(0,input.value.length);
  let prevented=false;
  input.dispatchEvent({type:'paste',clipboardData:{getData:()=>value},preventDefault(){prevented=true;}});
  assert.equal(prevented,true,'Paste is cleaned before maxlength.');return input;
}
assert.equal(typePhone('phone','a09b123c456789!').value,'09123456789');
for(const value of ['+639123456789','+63 912 345 6789','639123456789','9123456789','0912-345-6789']) {
  assert.equal(pastePhone('phone',value).value,'09123456789',value);
}
for(const value of ['+639987654321','+63 998 765 4321','639987654321','9987654321','0998-765-4321']) {
  assert.equal(pastePhone('emergencyPhone',value).value,'09987654321',value);
}
assert.equal(typePhone('phone','091234567890').value,'09123456789');
assert.equal(pastePhone('phone','091234567890').value,'09123456789');
phoneFields.phone.setSelectionRange(11,11);
let twelfthPrevented=false;
phoneFields.phone.dispatchEvent({type:'beforeinput',inputType:'insertText',cancelable:true,isComposing:false,data:'0',preventDefault(){twelfthPrevented=true;}});
assert.equal(twelfthPrevented,true);
assert.equal(phoneFields.phone.value,'09123456789','The twelfth digit cannot enter the field.');
phoneFields.phone.setSelectionRange(5,5);
phoneFields.phone.dispatchEvent({type:'beforeinput',inputType:'insertText',cancelable:true,isComposing:false,data:'0',preventDefault(){}});
assert.equal(phoneFields.phone.value,'09123456789','Inserting into a full number must preserve all existing digits.');
assert.equal(phoneFields.phone.selectionStart,5);
phoneFields.phone.setSelectionRange(0,11);
let mobileReplacementPrevented=false;
phoneFields.phone.dispatchEvent({type:'beforeinput',inputType:'insertReplacementText',cancelable:true,isComposing:false,data:'+63 912 345 6789',preventDefault(){mobileReplacementPrevented=true;}});
assert.equal(mobileReplacementPrevented,true);
assert.equal(phoneFields.phone.value,'09123456789');
typePhone('phone','0912');
phoneFields.phone.dispatchEvent({type:'blur'});
assert.equal(phoneFields.phone.validationMessage,phoneMessage);
assert.equal(phoneFields.phone.error.textContent,phoneMessage);
assert.equal(phoneFields.phone.attributes['aria-invalid'],'true');
assert.equal(typePhone('phone','09123456789').validationMessage,'');
assert.equal(phoneFields.phone.error.textContent,'');
assert.equal(phoneFields.phone.attributes['aria-invalid'],'false');
for(const name of ['phone','emergencyPhone']) {
  typePhone(name,'09111111111');
  phoneFields[name].dispatchEvent({type:'invalid'});
  assert.equal(phoneFields[name].validationMessage,phoneMessage);
  assert.equal(phoneFields[name].error.textContent,phoneMessage);
}
typePhone('phone','09123456789');
pastePhone('emergencyPhone','+639123456789');
phoneFields.emergencyPhone.dispatchEvent({type:'blur'});
assert.equal(phoneFields.emergencyPhone.validationMessage,emergencyMessage);
assert.equal(phoneFields.emergencyPhone.error.textContent,emergencyMessage);
assert.equal(phoneFields.emergencyPhone.attributes['aria-invalid'],'true');
typePhone('phone','09987654321');
assert.equal(phoneFields.emergencyPhone.validationMessage,'','Changing the own number refreshes emergency-number equality.');
assert.equal(phoneFields.emergencyPhone.error.textContent,'');
assert.equal(phoneFields.emergencyPhone.attributes['aria-invalid'],'false');
typePhone('phone','09123456789');
phoneFields.emergencyPhone.dispatchEvent({type:'invalid'});
assert.equal(phoneFields.emergencyPhone.error.textContent,emergencyMessage);
pastePhone('emergencyPhone','09987654321');
assert.equal(phoneFields.emergencyPhone.validationMessage,'');
assert.equal(phoneFields.emergencyPhone.error.textContent,'');

// Religion uses the existing inline error area and clears errors after a real selection.
const religionField=academicInput('religion');
context.religionForm={elements:{namedItem:name=>name==='religion'?religionField:null}};
run('bindRecollectionReligionField(religionForm)');
const religionMessage='Please select your religion.';
assert.equal(religionField.validationMessage,religionMessage);
assert.equal(religionField.error.textContent,'','Initial placeholder should not show an error before submission.');
religionField.dispatchEvent({type:'invalid'});
assert.equal(religionField.validationMessage,religionMessage);
assert.equal(religionField.error.textContent,religionMessage);
assert.equal(religionField.attributes['aria-invalid'],'true');
religionField.value='   ';
assert.equal(run('validateRecollectionReligionField(religionForm.elements.namedItem("religion"),true)'),false);
assert.equal(religionField.error.textContent,religionMessage);
religionField.value='Roman Catholic';
religionField.dispatchEvent({type:'change'});
assert.equal(religionField.validationMessage,'');
assert.equal(religionField.error.textContent,'');
assert.equal(religionField.attributes['aria-invalid'],'false');
assert.equal(run('recollectionState.values.religion'),'Roman Catholic');
context.firstStepForm={elements:{namedItem:()=>null}};
assert.doesNotThrow(()=>run('bindRecollectionReligionField(firstStepForm)'),'Religion is only visible on the second step.');

const fieldStart=source.indexOf('function recollectionField('),fieldEnd=source.indexOf('function recollectionGroup(',fieldStart);
context.studentTemplate=(id,args)=>{
  const template=source.match(new RegExp('<template id="'+id+'"><!--([\\s\\S]*?)--></template>'));
  return template?template[1].replace(/@\{\{(\d+)\}\}/g,(_,index)=>args[Number(index)]??''):args.join('');
};
vm.runInContext(source.slice(fieldStart,fieldEnd),context);
const ownPhoneMarkup=run('recollectionField("phone","Contact Number","09123456789","tel",true)');
const emergencyPhoneMarkup=run('recollectionField("emergencyPhone","Emergency Contact Number","09987654321","tel",true)');
for(const markup of [ownPhoneMarkup,emergencyPhoneMarkup]) {
  assert.match(markup,/type="tel"/);
  assert.match(markup,/inputmode="numeric"/);
  assert.match(markup,/maxlength="11"/);
  assert.match(markup,/pattern="09\[0-9\]\{9\}"/);
  assert.match(markup,/aria-describedby="recollection-(?:phone|emergencyPhone)-error"/);
  assert.match(markup,/class="error" role="alert"/);
}
assert.match(ownPhoneMarkup,/autocomplete="tel-national"/);
assert.doesNotMatch(ownPhoneMarkup,/autocomplete="off"/);
assert.match(emergencyPhoneMarkup,/autocomplete="off"/);

// Render actual production templates to check the Religion-only markup change.
run('recollectionState.values.religion=""');
const religionMarkup=run('recollectionField("religion","Religion:","Select your religion","text",true,["Roman Catholic","Other"])');
assert.match(religionMarkup,/<select\b[^>]*\brequired\b/);
assert.match(religionMarkup,/aria-describedby="recollection-religion-error"/);
assert.match(religionMarkup,/<option(?=[^>]*value="")(?=[^>]*\bdisabled\b)(?=[^>]*\bselected\b)[^>]*>Select your religion<\/option>/);
assert.match(religionMarkup,/<span[^>]*class="cfo-required"[^>]*>\*<\/span>/);
assert.match(religionMarkup,/<p[^>]*id="recollection-religion-error"[^>]*class="error"[^>]*role="alert"[^>]*><\/p>/);
run('recollectionState.values.religion="Roman Catholic"');
const selectedReligionMarkup=run('recollectionField("religion","Religion:","Select your religion","text",true,["Roman Catholic","Other"])');
assert.match(selectedReligionMarkup,/<option(?=[^>]*value="")(?=[^>]*\bdisabled\b)[^>]*>Select your religion<\/option>/);
assert.doesNotMatch(selectedReligionMarkup,/<option(?=[^>]*value="")(?=[^>]*\bselected\b)[^>]*>/);
assert.match(selectedReligionMarkup,/<option[^>]*value="Roman Catholic"[^>]*selected[^>]*>Roman Catholic<\/option>/);
const optionalDropdownMarkup=run('recollectionField("baptism","Baptism","Select","text",false,["Yes","No"])');
assert.match(optionalDropdownMarkup,/<option value="">Select<\/option>/);
assert.doesNotMatch(optionalDropdownMarkup,/required|disabled|class="error"/);

const recordView={};context.document={querySelector:()=>recordView};
run("showSubmittedRegistration({year:2027,status:'Pending',details:{name:'Student'}})");
assert.match(recordView.innerHTML,/Edit registration/);
run("showSubmittedRegistration({year:2027,status:'Approved',details:{name:'Student'}})");
assert.doesNotMatch(recordView.innerHTML,/Edit registration/);
assert.match(recordView.innerHTML,/locked/);
run("showSubmittedRegistration({year:2027,status:'Rejected',details:{name:'Student'}})");
assert.doesNotMatch(recordView.innerHTML,/Edit registration/);

// The shared modal must display review text, cancel without saving, and save only on confirm.
context.escapeHTML=value=>String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
run("showSubmittedRegistration({year:2027,status:'Pending',receipt_status:'Rejected',receipt_reason:'Upload a clearer photo <script>',details:{name:'Student'}})");
assert.match(recordView.innerHTML,/Not Yet Scheduled/);
assert.match(recordView.innerHTML,/Upload a clearer photo &lt;script&gt;/);
assert.match(recordView.innerHTML,/Upload or replace receipt/);
assert.doesNotMatch(recordView.innerHTML,/<script>/);
run("showSubmittedRegistration({year:2027,status:'Approved',receipt_status:'Approved',schedule:{title:'Assigned Recollection',date:'2027-10-05',time:'08:00 - 12:00',venue:'Chapel'},details:{name:'Student'}})");
for(const text of ['Scheduled','Assigned Recollection','2027-10-05','08:00 - 12:00','Chapel'])assert.ok(recordView.innerHTML.includes(text),text);
assert.doesNotMatch(recordView.innerHTML,/Not Yet Scheduled|Upload or replace receipt|Edit registration/);
// Persisted receipts remain visible after submission and rejection, independently of review status.
for(const receiptStatus of ['Pending','Approved','Rejected']) {
  context.savedReceipt={id:27,year:2027,status:receiptStatus,receipt_status:receiptStatus,has_receipt:true,details:{name:'Student'}};
  run('showSubmittedRegistration(savedReceipt)');
  assert.ok(recordView.innerHTML.includes('<img src="/api/receipts/27"'));
  assert.match(recordView.innerHTML,/View uploaded receipt/);
  assert.ok(recordView.innerHTML.includes('Receipt status: '+receiptStatus));
}
assert.equal(context.window.CfoStudentReceipt.markup({id:27,has_receipt:false}),'');
assert.equal(context.window.CfoStudentReceipt.markup({id:'27" onerror="bad',has_receipt:true}),'');
assert.ok(context.window.CfoStudentReceipt.markup({id:27,has_receipt:true,receipt_status:'<script>bad</script>'}).includes('&lt;script&gt;bad&lt;/script&gt;'));
const receiptNodes=new Map();
const receiptNode=selector=>{if(!receiptNodes.has(selector))receiptNodes.set(selector,{innerHTML:'',textContent:'',value:'',classList:{toggle(){}},click(){},focus(){},querySelector:()=>receiptNode('thumb')});return receiptNodes.get(selector);};
context.document={querySelector:receiptNode};context.icon=()=>'';
const previewStart=source.indexOf('function updateRecollectionReceipt()'),previewEnd=source.indexOf('/* 7.3.8',previewStart);
assert.ok(previewStart>0&&previewEnd>previewStart);vm.runInContext(source.slice(previewStart,previewEnd),context);
context.cfoStudent.registrations=[{id:27,year:2027,status:'Pending',receipt_status:'Rejected',has_receipt:true,details:{name:'Student'}}];
const receiptBefore=JSON.stringify(context.cfoStudent.registrations[0]);
run('recollectionState.registrationId=27;recollectionState.receipt=null;updateRecollectionReceipt()');
assert.ok(receiptNode('#receipt-preview').innerHTML.includes('<img src="/api/receipts/27"'));
assert.equal(receiptNode('#receipt-status').textContent,'Rejected');
run("recollectionState.receipt={name:'replacement.png',type:'image/png'};recollectionState.receiptUrl='blob:replacement';updateRecollectionReceipt()");
assert.match(receiptNode('#receipt-preview').innerHTML,/blob:replacement/);
assert.equal(receiptNode('#receipt-status').textContent,'Pending Verification');
receiptNode('#receipt-remove').onclick();
assert.ok(receiptNode('#receipt-preview').innerHTML.includes('<img src="/api/receipts/27"'));
assert.equal(receiptNode('#receipt-status').textContent,'Rejected');
assert.equal(JSON.stringify(context.cfoStudent.registrations[0]),receiptBefore);
run('recollectionState.registrationId=null;updateRecollectionReceipt()');
assert.match(receiptNode('#receipt-preview').innerHTML,/No receipt selected/);
assert.ok(!receiptNode('#receipt-preview').innerHTML.includes('/api/receipts/'));
const dashboardNote={inserted:[],insertAdjacentHTML(position,html){this.inserted.push(html);}};
context.document={querySelector:selector=>selector==='.dash-preview-note'?dashboardNote:{}};
const dashboardStart=source.indexOf('function connectedRegistrationDetails()'),dashboardEnd=source.indexOf('let studentLiveVersions=',dashboardStart);
assert.ok(dashboardStart>0&&dashboardEnd>dashboardStart);vm.runInContext(source.slice(dashboardStart,dashboardEnd),context);
for(const receiptStatus of ['Pending','Approved','Rejected']) {
  context.cfoStudent.registrations[0].receipt_status=receiptStatus;dashboardNote.inserted=[];
  run('connectedRegistrationDetails()');
  assert.equal(dashboardNote.hidden,true,receiptStatus);assert.equal(dashboardNote.textContent,'',receiptStatus);
  assert.doesNotMatch(dashboardNote.inserted.join(''),/Payment receipt|View uploaded receipt|Receipt status:|Edit registration/,receiptStatus);
}

context.cfoStudent.registrations=[{id:8,year:2027,status:'Approved',attendance:'Absent',can_reregister:true,details:{name:'Retry Student',phone:'0912-345-6789',emergencyPhone:'+63 998 765 4321',participation:true,photos:true}}];
run('clearRecollection();initializeRecollectionDraft(cfoStudent.registrations[0])');
assert.equal(run('recollectionState.retrying'),true);
assert.equal(run('recollectionState.values.name'),'Retry Student');
assert.equal(run('recollectionState.values.phone'),'09123456789');
assert.equal(run('recollectionState.values.emergencyPhone'),'09987654321');
assert.equal(context.cfoStudent.registrations[0].details.phone,'0912-345-6789');
assert.equal(context.cfoStudent.registrations[0].details.emergencyPhone,'+63 998 765 4321');
assert.equal(run('recollectionState.values.participation'),false);
assert.equal(run('recollectionState.values.photos'),false);
run("recollectionState.values.name='Retry draft';initializeRecollectionDraft(cfoStudent.registrations[0])");
assert.equal(run('recollectionState.values.name'),'Retry draft');
run('clearRecollection()');
assert.equal(run('recollectionState.retrying'),false);

const nodes=new Map();let actionCount=0;
const element=()=>({children:[],style:{},append(...nodes){this.children.push(...nodes);},replaceChildren(){this.children=[];}});
const dialog={showModal(){this.open=true;},close(){this.open=false;},querySelector(selector){if(!nodes.has(selector))nodes.set(selector,element());return nodes.get(selector);}};
context.document={querySelector:()=>dialog,createElement:element};
context.showNotice=()=>{};
const button={textContent:'Save Changes',disabled:false};context.form={querySelector:()=>button};context.action=async()=>{actionCount++;};context.review=review;
const a=source.indexOf('async function cfoSubmit('),b=source.indexOf('// Consistent button contrast',a);
vm.runInContext(source.slice(a,b),context);
(async()=>{
  await run('cfoSubmit(form,action,false,review)');
  assert.equal(actionCount,0);assert.equal(dialog.open,true);
  const rows=nodes.get('#action-confirm-details').children;
  assert.equal(rows[0].children[1].textContent,'Before: Latest');
  assert.equal(rows[0].children[2].textContent,'After: <script>Changed</script>');
  nodes.get('#action-confirm-cancel').onclick();assert.equal(actionCount,0);
  await run('cfoSubmit(form,action,false,review)');
  nodes.get('#action-confirm-accept').onclick();await new Promise(setImmediate);
  assert.equal(actionCount,1);assert.equal(button.disabled,false);
  console.log('PASS: latest prefill, configured academic options and legacy drafts, mobile-number conversion/max11/fake/equality checks, phone input, inline validation, required Religion/disabled placeholder/error clearing, consent/receipt reset, unchanged source records, edit/retry preload, cancel and confirm.');
})().catch(error=>{console.error(error);process.exitCode=1;});
