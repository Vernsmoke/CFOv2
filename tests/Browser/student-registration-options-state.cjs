const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/student/index.blade.php','utf8');
const start=source.indexOf('const registrationFieldLabels ='),end=source.indexOf('function recollectionRegistration()',start);
assert.ok(start>0&&end>start);
const escapeHTML=value=>String(value??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
const decode=value=>value.replaceAll('&quot;','"').replaceAll('&#39;',"'").replaceAll('&gt;','>').replaceAll('&lt;','<').replaceAll('&amp;','&');
const configured=[
  {id:1,courses:[{id:1,name:'BSIT'},{id:2,name:'BSN'},{id:3,name:'B.S. Architecture (5-year)'}],year_levels:['1','3'],course_sections_by_year:{1:{1:['A','B'],3:['C']},2:{3:['B']},3:{3:['North 2']}}},
  {id:2,courses:[{id:1,name:'BSIT'}],year_levels:['3','4'],course_sections_by_year:{1:{3:['C','D'],4:['Z']}}},
];
let form,note={textContent:''};
const context=vm.createContext({window:{CFO_BACKEND:{user:{name:'Student',course:'Unscheduled profile course'}}},cfoStudent:{current_year:2027,registrations:[],schedules:[],registration_courses:['BSIT','BSN','B.S. Architecture (5-year)','BSA'],registration_schedules:configured},URL:{revokeObjectURL(){}},document:{activeElement:null,querySelector:selector=>selector==='#recollection-form'?form:selector==='#recollection-academic-update'?note:null},escapeHTML});
vm.runInContext(source.slice(start,end),context);
const run=code=>vm.runInContext(code,context),plain=code=>JSON.parse(JSON.stringify(run(code)));
function academicForm(markup=run('recollectionAcademicFieldsHtml()')){
  const listeners={},fields=new Map(),container={html:''};
  const self={dataset:{},fields,listeners,querySelector(selector){if(selector==='#recollection-academic-options')return container;const name=selector.match(/^\[data-recollection-academic-field="([^"]+)"\]$/)?.[1];return fields.get(name)||null;},addEventListener(type,callback){listeners[type]=callback;}};
  Object.defineProperty(container,'innerHTML',{get(){return this.html;},set(html){
    this.html=html;fields.clear();
    for(const match of html.matchAll(/<select\b([^>]*)>([\s\S]*?)<\/select>/g)){
      const attrs=match[1],name=attrs.match(/\bdata-recollection-academic="([^"]+)"/)?.[1];
      if(!name)continue;
      const error={textContent:''},options=[];
      for(const optionMatch of match[2].matchAll(/<option\b([^>]*)>([\s\S]*?)<\/option>/g)){
        const optionAttrs=optionMatch[1];options.push({value:decode(optionAttrs.match(/\bvalue="([^"]*)"/)?.[1]??optionMatch[2]),textContent:decode(optionMatch[2]),selected:/\bselected\b/.test(optionAttrs),disabled:/\bdisabled\b/.test(optionAttrs)});
      }
      const select={name,type:'select-one',dataset:{recollectionAcademic:name},options,attributes:{},disabled:/\bdisabled\b/.test(attrs),required:/\brequired\b/.test(attrs),validationMessage:'',setAttribute(key,value){this.attributes[key]=value;},setCustomValidity(message){this.validationMessage=message;},focus(){context.document.activeElement=this;}};
      Object.defineProperty(select,'value',{get(){return options.find(option=>option.selected)?.value??options[0]?.value??'';},set(value){for(const option of options)option.selected=option.value===value;}});
      const field={attributes:{},error,select,querySelector:selector=>selector==='.error'?error:selector==='select'?select:null,querySelectorAll:selector=>selector==='select'?[select]:[],setAttribute(key,value){this.attributes[key]=value;},focus(){context.document.activeElement=this;}};
      fields.set(name,field);
    }
  }});
  container.innerHTML=markup;
  return self;
}
const yearOptions=['1','2','3','4'],sectionOptions=Array.from({length:26},(_,index)=>String.fromCharCode(65+index));
const values=name=>form.fields.get(name).select.options.map(option=>option.value).filter(Boolean);
const enabledValues=name=>form.fields.get(name).select.options.filter(option=>option.value&&!option.disabled).map(option=>option.value);
const selected=name=>{const value=form.fields.get(name).select.value;return value?[value]:[];};
function choose(name,value){const select=form.fields.get(name).select,option=select.options.find(option=>option.value===value);assert.ok(option,'Expected a '+name+' dropdown option: '+value);assert.equal(option.disabled,false,'Expected an available '+name+' option: '+value);select.value=value;form.listeners.change({target:select});}
const expected=[
  {course:'BSIT',yearLevel:'1',section:'A'},
  {course:'BSIT',yearLevel:'1',section:'B'},
  {course:'BSIT',yearLevel:'3',section:'C'},
  {course:'BSN',yearLevel:'3',section:'B'},
  {course:'B.S. Architecture (5-year)',yearLevel:'3',section:'North 2'},
  {course:'BSIT',yearLevel:'3',section:'D'},
  {course:'BSIT',yearLevel:'4',section:'Z'},
];
assert.deepEqual(plain('recollectionAcademicGroups()'),expected,'Only selected admin course/year/section combinations are offered, with duplicates removed.');
assert.deepEqual(plain('recollectionAcademicChoices().course'),['B.S. Architecture (5-year)','BSA','BSIT','BSN']);
context.window.CFO_BACKEND.user.course=' bSiT ';
run('initializeRecollectionDraft()');
assert.equal(run('recollectionState.values.course'),'BSIT');
form=academicForm();context.form=form;run('bindRecollectionAcademicFields(form)');
assert.ok(enabledValues('section').includes('North 2'),'Custom configured sections are selectable even for another course/year.');
choose('course','BSA');choose('yearLevel','2');choose('section','B');
assert.equal(run('recollectionState.values.course'),'BSA','A course without a schedule remains selectable.');
assert.equal(run('validateRecollectionAcademicSelection(form,true)'),'section');
assert.match(form.fields.get('section').error.textContent,/No recollection schedule is available yet for BSA 2-B/);
assert.ok(form.fields.get('section').select.validationMessage);
choose('course','BSN');choose('yearLevel','3');choose('section','B');
assert.equal(run('validateRecollectionAcademicSelection(form,true)'),null,'Another course can register with a matching group.');
assert.deepEqual(enabledValues('yearLevel'),yearOptions);
assert.deepEqual(enabledValues('section'),[...sectionOptions,'North 2']);
run("recollectionState.values.course=' bSn ';recollectionState.values.section='b';cleanRecollectionAcademicValues(recollectionState.values)");
assert.equal(run('recollectionState.values.course'),'BSN');
run("recollectionState.values.name='Unsaved Student';recollectionState.values.participation=true;recollectionState.values.photos=true;recollectionState.receipt={name:'receipt.png'};recollectionState.step=2");
const draftReceipt=run('recollectionState.receipt');
context.cfoStudent.registration_courses.push('New Course');
run('refreshRecollectionAcademicChoices()');
assert.ok(values('course').includes('New Course'),'Live course additions appear in the open form.');
assert.equal(run('recollectionState.values.course'),'BSN');
context.cfoStudent.registration_schedules=[];
run('refreshRecollectionAcademicChoices()');
assert.equal(run('recollectionState.values.section'),'B','Schedule removal preserves the selection for an inline error.');
assert.equal(run('validateRecollectionAcademicSelection(form,true)'),'section');
assert.equal(run('recollectionState.receipt'),draftReceipt);

context.cfoStudent.academic_options=[{type:'year_level',name:'5'},{type:'section',name:'Evening'}];
run('refreshRecollectionAcademicChoices()');
assert.ok(values('yearLevel').includes('5'));
assert.ok(values('section').includes('Evening'));
const unsafeCourse='BS <img src=x> & "Tech"';
context.cfoStudent.registration_courses.push(unsafeCourse);
run('refreshRecollectionAcademicChoices()');
const escaped=run('recollectionAcademicFieldsHtml()');
assert.doesNotMatch(escaped,/<img\b/);
assert.match(escaped,/&lt;img src=x&gt; &amp; &quot;Tech&quot;/);

// The supplied catalog adds a department > course > optional major dependency over the schedule choices.
context.window.CFO_BACKEND.academic_catalog={departments:[
  {name:'College of Information Technology',courses:[{name:'BSIT',majors:[]}]},
  {name:'College of Business Education',courses:[{name:'BSA',majors:['Marketing Management','Financial Management']}]},
],year_levels:yearOptions,sections:sectionOptions};
run("recollectionState.values={...recollectionState.values,department:'College of Business Education',course:'BSA',major:'Marketing Management',yearLevel:'2',section:'B',yearSection:'2B'}");
assert.deepEqual(plain('recollectionAcademicFieldNames()'),['department','course','major','yearLevel','section']);
assert.deepEqual(plain('recollectionAcademicChoices().course'),['BSA']);
assert.deepEqual(plain('recollectionAcademicChoices().major'),['Marketing Management','Financial Management']);
form=academicForm();context.form=form;run('bindRecollectionAcademicFields(form)');
choose('department','College of Information Technology');
assert.equal(run('recollectionState.values.course'),'','Changing department clears the course.');
assert.equal(run('recollectionState.values.major'),'','Changing department clears the major.');
assert.deepEqual(plain('recollectionAcademicChoices().course'),['BSIT']);
context.window.CFO_BACKEND.academic_catalog=null;

// Run the live-refresh entry point with a dirty form; options update independently of route rendering.
const refreshStart=source.indexOf('let studentLiveVersions='),refreshEnd=source.indexOf("document.addEventListener('focusout'",refreshStart);
assert.ok(refreshStart>0&&refreshEnd>refreshStart);
context.document.addEventListener=()=>{};context.document.hidden=false;context.document.querySelectorAll=()=>[];
let reroutes=0;context.route=async()=>{reroutes++;};context.routeVersion=1;context.studentRouteLoading=false;context.location={hash:'#recollection-registration'};
vm.runInContext(source.slice(refreshStart,refreshEnd),context);
const freshSchedules=[{courses:[{id:10,name:'Live course'}],year_levels:['2'],course_sections_by_year:{10:{2:['Blue']}}}];
let requests=[];context.cfoRequest=async url=>{requests.push(url);return url==='/api/live/versions'?{schedules:2,academic_options:1,year:2027}:{registration_schedules:freshSchedules,registration_courses:['BSIT','BSN','Live course'],registration_course:'Live course'};};
run("recollectionState.step=2;recollectionState.dirty=true;studentLiveVersions={schedules:2,academic_options:0,year:2027};document.activeElement={matches:()=>true}");
(async()=>{
  await run('pollStudentVersions()');
  assert.ok(requests.some(url=>url.includes('registration_options')),'Schedule changes fetch registration options even while another field is dirty.');
  assert.deepEqual(plain('cfoStudent.registration_schedules'),freshSchedules);
  assert.equal(run('recollectionStudentCourse()'),'Live course','A live course update overrides the stale initial page account.');
  assert.deepEqual(plain('recollectionAcademicChoices().course'),['BSIT','BSN','Live course']);
  assert.equal(reroutes,0,'Updating options must not reroute or replace a dirty registration form.');
  assert.equal(run('recollectionState.step'),2);assert.equal(run('recollectionState.values.name'),'Unsaved Student');assert.equal(run('recollectionState.receipt'),draftReceipt);

  // Render the actual form templates and run its real input, step, and submit handlers.
  // Phone/religion/receipt binding is covered by the companion registration state test.
  context.window.CFO_BACKEND.user.course='BSIT';context.cfoStudent.registration_course='BSIT';
  context.cfoStudent.registration_schedules=configured;context.cfoStudent.registration_courses=['BSIT','BSN','BSA'];
  run('clearRecollection();initializeRecollectionDraft()');
  context.studentTemplate=(id,args=[])=>{
    const template=source.match(new RegExp('<template id="'+id+'"><!--([\\s\\S]*?)--></template>'));
    assert.ok(template,'Missing production template '+id);
    return template[1].replace(/@\{\{(\d+)\}\}/g,(_,index)=>args[Number(index)]??'');
  };
  context.icon=()=>'';context.window.scrollTo=()=>{};context.protectStudentEmailInput=()=>{};
  let renderedForm,renderedMarkup='',confirmation=null,submission=null;
  const localNote={textContent:''},heading={focus(){}},submitButton={textContent:'Submit Registration'};
  const receiptDialog={querySelector:()=>({}),addEventListener(){},close(){}};
  const renderRoot={className:'',get innerHTML(){return renderedMarkup;},set innerHTML(html){
    renderedMarkup=html;renderedForm=academicForm(html);renderedForm.prepend=()=>{};renderedForm.elements={namedItem:()=>null};
    const academicQuery=renderedForm.querySelector.bind(renderedForm);
    renderedForm.querySelector=selector=>selector==='#recollection-academic-options'&&!html.includes('id="recollection-academic-options"')?null:selector==='[type=submit]'?submitButton:academicQuery(selector);
  },querySelector(selector){return selector==='#recollection-form'?renderedForm:selector==='.recollection-local-note'?localNote:selector==='#receipt-dialog'?receiptDialog:selector==='h1'?heading:null;},querySelectorAll(){return [];}};
  context.document.querySelector=selector=>selector==='#recollection-root'?renderRoot:selector==='#recollection-form'?renderedForm:null;
  const renderStart=source.indexOf('function recollectionField('),renderEnd=source.indexOf('function bindRecollectionReceipt()',renderStart);
  assert.ok(renderStart>0&&renderEnd>renderStart);vm.runInContext(source.slice(renderStart,renderEnd),context);
  context.bindRecollectionPhoneFields=()=>{};context.bindRecollectionReligionField=()=>{};context.bindRecollectionReceipt=()=>{};
  context.FormData=class{constructor(){this.entries=[];}append(key,value){this.entries.push([key,value]);}};
  context.cfoSubmit=(submittedForm,action,automatic,review)=>{confirmation={submittedForm,action,automatic,review};};
  context.cfoRequest=async(url,method,payload)=>{submission={url,method,payload};return {};};
  context.setTimeout=()=>0;context.showNotice=()=>{};
  run("recollectionState.values.phone='09123456789';recollectionState.values.emergencyPhone='09987654321';recollectionState.values.religion='Roman Catholic';renderRecollectionStep()");
  for(const name of ['course','yearLevel','section'])assert.match(renderedMarkup,new RegExp('data-recollection-academic-field="'+name+'"'),'The first-step production template includes '+name+'.');
  assert.doesNotMatch(renderedMarkup,/<input\b(?=[^>]*name="(?:course|yearSection)")(?=[^>]*type="text")/,'Obsolete typed academic fields do not reappear in the full form.');
  assert.match(renderedMarkup,/<input\b[^>]*type="email"/);assert.match(renderedMarkup,/<input\b[^>]*name="phone"/,'Personal information remains in the same section after the academic template change.');
  form=renderedForm;choose('course','BSIT');choose('yearLevel','3');choose('section','D');
  run('renderRecollectionStep()');form=renderedForm;
  for(const name of ['course','yearLevel','section'])assert.match(renderedMarkup,new RegExp('<select\\b[^>]*name="'+name+'"'),'The full production form renders '+name+' as a native single-select dropdown.');
  assert.doesNotMatch(renderedMarkup,/<input\b(?=[^>]*type="checkbox")(?=[^>]*name="(?:course|yearLevel|section)")/,'Obsolete academic checkboxes do not reappear in the production form.');
  // Native select input precedes change; the generic handler leaves cascading academic changes to their delegated handler.
  renderedForm.listeners.input({target:form.fields.get('course').select});
  assert.equal(run('recollectionState.values.course'),'BSIT');
  renderedForm.listeners.input({target:{name:'name',type:'text',value:'Updated Student',dataset:{}}});
  let prevented=false;renderedForm.onsubmit({preventDefault(){prevented=true;}});
  assert.equal(prevented,true);assert.equal(run('recollectionState.step'),2);assert.match(renderedMarkup,/Faith Background to Consent/);assert.match(renderedMarkup,/name="participation"/);
  renderedForm.listeners.input({target:{name:'participation',type:'checkbox',checked:true,dataset:{}}});
  renderedForm.listeners.input({target:{name:'photos',type:'checkbox',checked:true,dataset:{}}});
  run("recollectionState.receipt={name:'registration-receipt.png',type:'image/png'}");
  renderedForm.onsubmit({preventDefault(){}});
  assert.ok(confirmation,'The real submit handler opens confirmation.');
  // The actual final submit handler rejects an unscheduled course before confirmation.
  confirmation=null;
  run("recollectionState.values.course='BSA';recollectionState.values.yearLevel='2';recollectionState.values.section='B';renderRecollectionStep()");
  renderedForm.onsubmit({preventDefault(){}});
  assert.equal(confirmation,null);
  assert.equal(submission,null);
  assert.equal(run('recollectionState.step'),1,'Invalid final submission returns to the academic fields.');
  assert.match(renderedForm.fields.get('section').error.textContent,/No recollection schedule is available yet/);
  run("recollectionState.values.course='BSIT';recollectionState.values.yearLevel='3';recollectionState.values.section='D';recollectionState.step=2;renderRecollectionStep()");
  renderedForm.onsubmit({preventDefault(){}});
  assert.ok(confirmation);
  assert.equal(confirmation.submittedForm,renderedForm);assert.equal(confirmation.automatic,false);assert.equal(submission,null,'No registration is sent before confirmation.');
  assert.ok(confirmation.review.some(row=>row.label==='Course / program'&&row.after==='BSIT'));assert.ok(confirmation.review.some(row=>row.label==='Year / section'&&row.after==='3 / D'));
  await confirmation.action();
  assert.equal(submission.url,'/api/registrations');assert.equal(submission.method,'POST');
  const submitted=Object.fromEntries(submission.payload.entries);
  assert.equal(submitted['details[course]'],'BSIT');assert.equal(submitted['details[yearLevel]'],'3');assert.equal(submitted['details[section]'],'D');assert.equal(submitted['details[yearSection]'],'3D');
  assert.equal(submitted['details[name]'],'Updated Student');assert.equal(submitted['details[participation]'],'1');assert.equal(submitted['details[photos]'],'1');assert.equal(submitted.receipt.name,'registration-receipt.png');
  assert.equal(context.location.hash,'dashboard');
  for(const mode of ['edit','retry']) {
    const record={id:44,year:2027,status:'Pending',can_reregister:mode==='retry',details:{
      name:'Saved Student',course:'BSN',yearLevel:'3',section:'B',yearSection:'3B',
      phone:'09123456789',emergencyPhone:'09987654321',religion:'Roman Catholic',participation:true,photos:true,
    }};
    context.flowRecord=record;context.cfoStudent.registrations=[record];
    run('clearRecollection();initializeRecollectionDraft(flowRecord)');
    assert.equal(run('recollectionState.values.course'),'BSN','Edit/retry retains the selected course instead of the profile course.');
    run("recollectionState.values.name='Updated Student';recollectionState.values.participation=true;recollectionState.values.photos=true;recollectionState.values.course='BSA';recollectionState.values.yearLevel='2';recollectionState.values.section='B';recollectionState.step=2;renderRecollectionStep()");
    confirmation=null;submission=null;
    renderedForm.onsubmit({preventDefault(){}});
    assert.equal(confirmation,null,mode+' blocks unscheduled choices before confirmation.');
    assert.equal(submission,null);
    assert.match(renderedForm.fields.get('section').error.textContent,/No recollection schedule is available yet for BSA 2-B/);
    run("recollectionState.values.course='BSN';recollectionState.values.yearLevel='3';recollectionState.values.section='B';recollectionState.step=2;renderRecollectionStep()");
    renderedForm.onsubmit({preventDefault(){}});
    assert.ok(confirmation,mode+' permits a matching schedule group.');
    await confirmation.action();
    assert.equal(submission.url,'/api/registrations/44'+(mode==='retry'?'/retry':''));
    assert.equal(submission.method,'POST');
    const details=Object.fromEntries(submission.payload.entries);
    assert.equal(details['details[course]'],'BSN');
    assert.equal(details['details[yearSection]'],'3B');
    assert.equal(details._method,mode==='edit'?'PATCH':undefined);
  }
  console.log('PASS: all admin courses, unrestricted standard academic choices, missing-schedule inline and submit gates, case normalization, dirty live course updates, preserved receipt/consent, production rendering, and confirmed scalar submission.');
})().catch(error=>{console.error(error);process.exitCode=1;});
