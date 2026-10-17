// Executes the production request module and dialog/live-refresh wiring with isolated data.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/admin-dashboard.js','utf8');
const moduleContext={};vm.runInNewContext(fs.readFileSync('public/admin-change-requests.js','utf8'),moduleContext);
const requests=moduleContext.CfoChangeRequests;
const original={id:10,title:'Original day',date:'2026-10-20',time:'08:00 – 16:00',venue:'Chapel'};
const assigned={...original,id:11,title:'New day',date:'2026-10-22',slots_left:2};
const user={name:'Student <img src=x>',student_id:'ID-12',course:'Profile course',year_level:'4',section:'Z'};
const registration={id:12,user,details:{course:'BSIT',yearLevel:'2',section:'B'},schedule:assigned};
const pending={id:4,created_at:'2026-10-10T03:00:00Z',status:'Pending',reason:'Exam <script>conflict</script>',registration,current_schedule:original,requested_schedule:null,assigned_schedule:null,eligible_schedules:[assigned]};
const approved={...pending,id:5,status:'Approved',assigned_schedule:assigned,review_reason:'Verified <b>conflict</b>'};
const rejected={...pending,id:6,status:'Rejected',review_reason:'Visit the CFO office.'};
const closed={...pending,id:7,status:'Closed',eligible_schedules:[]};
const cancelled={...pending,id:8,status:'Cancelled',student_status:'Cancelled',student_message:'You cancelled this request.',eligible_schedules:[]};
const rows=[pending,approved,rejected,closed];
const escaped=html=>{assert.doesNotMatch(html,/<(?:img|script|b)\b/,'Stored student/schedule/note fields must be escaped.');};
function between(start,end){const a=source.indexOf(start),b=source.indexOf(end,a);assert.ok(a>=0&&b>a,start);return source.slice(a,b);}
const column=(columns,label)=>columns.find(c=>c.label===label);
const columns=requests.columns();
assert.deepEqual(Array.from(columns,c=>c.label),['Student','Current schedule','Reason','Request date','Status']);
assert.equal(requests.requestId(pending),'SCR-2026-004');
const student=column(columns,'Student').html(pending);escaped(student);assert.match(student,/BSIT · 2 B/);assert.doesNotMatch(student,/Profile course/);
assert.match(student,/Request ID: SCR-2026-004/);
assert.equal(column(columns,'Current schedule').get(approved),'Original day · 2026-10-20','The original assignment is retained after the registration moves.');
assert.match(column(columns,'Status').html(approved),/Assigned: New day · 2026-10-22/);
escaped(column(columns,'Reason').html(pending));
const courseFilter=requests.courseFilter(rows,values=>[...new Set(values)]);
assert.deepEqual(Array.from(courseFilter.values),['BSIT']);assert.equal(courseFilter.get(pending),'BSIT');
const legacyProfile={...pending,registration:{...registration,details:{}}};
assert.equal(courseFilter.get(legacyProfile),'Profile course','Old requests retain their profile fallback.');
const metrics=[];const stats=requests.statsHtml(rows,(...args)=>{metrics.push(args);return args[0]+':'+args[1];});
assert.deepEqual(metrics.map(([label,value])=>[label,value]),[['Total requests',4],['Pending review',1],['Approved',1],['Rejected',1]]);assert.match(stats,/Request summary/);
const exportColumns=requests.exportColumns();
assert.equal(column(exportColumns,'Current schedule').get(approved),'Original day');
assert.equal(column(exportColumns,'Assigned schedule').get(approved),'New day');
assert.equal(column(exportColumns,'Decision note').get(approved),'Verified <b>conflict</b>');
assert.equal(column(exportColumns,'Year').get(approved),'2');assert.equal(column(exportColumns,'Section').get(approved),'B');
let html=requests.reviewHtml(pending);escaped(html);
assert.match(html,/name="assigned_schedule_id"[^>]*required/);assert.match(html,/<option value="11"[^>]*>New day/);
assert.doesNotMatch(html,/Requested schedule/,'Reason-only requests do not invent a requested schedule.');
assert.match(html,/Original day/);assert.doesNotMatch(html,/name="status"/);
const legacy={...pending,requested_schedule:assigned};html=requests.reviewHtml(legacy);
assert.match(html,/<option value="11"[^>]*selected/);assert.match(html,/older request asked for New day/);
html=requests.reviewHtml({...legacy,eligible_schedules:[]});assert.match(html,/no longer available/);assert.doesNotMatch(html,/<select/);
assert.match(html,/No Open upcoming schedule/,'An unavailable target still allows rejection without an assignment.');
for(const row of [approved,rejected,closed,cancelled]){
  html=requests.reviewHtml(row);escaped(html);assert.doesNotMatch(html,/<form|<select|<textarea/,'Reviewed requests are read-only.');
}
assert.match(requests.reviewHtml(approved),/Assigned schedule/);assert.match(requests.reviewHtml(approved),/New day/);
assert.match(requests.reviewHtml(rejected),/Reason for not approving/);assert.match(requests.reviewHtml(closed),/closed automatically/);
assert.match(requests.reviewHtml(cancelled),/Cancelled/);assert.match(column(columns,'Status').html(cancelled),/Cancelled/);
const form=(schedule='',note='',options)=>({elements:{assigned_schedule_id:{value:schedule,...(options?{options}: {})},review_reason:{value:note}}});
assert.equal(requests.decision(form('','  '),'Rejected').ok,false);
assert.equal(requests.decision(form('','  '),'Rejected').field,'review_reason');
assert.deepEqual({...requests.decision(form('','  Visit the office.  '),'Rejected').values},{status:'Rejected',review_reason:'Visit the office.'});
assert.equal(requests.decision(form('','Note'),'Approved').field,'assigned_schedule_id');
assert.deepEqual({...requests.decision(form('11','  Verified conflict  '),'Approved').values},{status:'Approved',assigned_schedule_id:'11',review_reason:'Verified conflict'});
assert.equal(requests.decision(form('11','x'.repeat(2001)),'Approved').ok,false,'The note maxlength is enforced before confirmation.');
assert.equal(requests.decision(form('11','ok'),'Closed').ok,false,'Only an approval or rejection can be submitted.');
for(const value of ['0','-1','1.5','bad'])assert.equal(requests.decision(form(value),'Approved').ok,false,'Invalid assignment id '+value);
assert.equal(requests.decision(form('11','',[{value:'11',disabled:true}]),'Approved').ok,false,'A disabled option cannot be approved.');
assert.equal(requests.decision(form('12','',[{value:'11',disabled:false}]),'Approved').ok,false,'A stale selection outside the picker cannot be approved.');
assert.equal(requests.decision(form('11','',[{value:'11',disabled:false}]),'Approved').ok,true);

// Real dialog wiring validates before handing values to the existing save-confirmation flow.
let dialogCall,gridConfig,saveCalls=[],toastCalls=[],activeForm;
const field=value=>({value,focus(){this.focused=true;}});
const context=vm.createContext({
  window:{CfoChangeRequests:requests},cfoState:{requests:[...rows,cancelled]},
  $:selector=>selector==='#connected-request-form'?activeForm:null,
  showDialog:(...args)=>{dialogCall=args;if(!args[1].includes('<form'))activeForm=null;},button:(label,action,id)=>`<button data-action="${action}" data-id="${id}">${label}</button>`,
  connectedGrid:config=>{gridConfig=config;},objectFilter:(key,label,items)=>({key,label,items}),unique:values=>[...new Set(values)],metric:()=>'',
  toast:(...args)=>toastCalls.push(args),saveConnected:(...args)=>saveCalls.push(args),
});
vm.runInContext(between('    function connectedRequests()','    function connectedAnnouncements()'),context);
context.connectedRequests();assert.equal(gridConfig.noun,'requests');assert.equal(typeof gridConfig.exportColumns,'object');
assert.match(gridConfig.rowActions(pending),/>Review</);assert.match(gridConfig.rowActions(approved),/>View</);
assert.match(gridConfig.rowActions(cancelled),/>View</);assert.doesNotMatch(gridConfig.rowActions(cancelled),/>Review</);
let csv;
context.activeGrid={config:gridConfig,filtered:()=>[approved]};context.csvDownload=(...args)=>{csv=args;};
vm.runInContext("const exportRequests=({"+between("        'export-grid':","        'logout':")+"})['export-grid'];exportRequests();",context);
assert.equal(csv[0],'connected-requests.csv');assert.equal(csv[2].length,1,'Export uses the filtered request rows.');
assert.equal(csv[1][csv[1].indexOf('Assigned schedule')],'Assigned schedule');
assert.equal(csv[2][0][csv[1].indexOf('Assigned schedule')],'New day');assert.equal(csv[2][0][csv[1].indexOf('Current schedule')],'Original day');
assert.equal(csv[2][0][csv[1].indexOf('Decision note')],'Verified <b>conflict</b>');
activeForm={elements:{assigned_schedule_id:field(''),review_reason:field('')}};
context.connectedRequest(4);assert.match(dialogCall[0],/Review/);assert.match(dialogCall[2],/cr-reject/);assert.match(dialogCall[2],/cr-approve/);
assert.equal(typeof activeForm.onsubmit,'function','Enter submits the review through the confirmation flow.');
context.decideChangeRequest(4,'Approved');assert.equal(saveCalls.length,0);assert.equal(activeForm.elements.assigned_schedule_id.focused,true);assert.equal(toastCalls.length,1);
context.decideChangeRequest(4,'Rejected');assert.equal(saveCalls.length,0);assert.equal(activeForm.elements.review_reason.focused,true);
activeForm.elements.assigned_schedule_id.value='11';activeForm.elements.review_reason.value=' Checked ';
let prevented=false;activeForm.onsubmit({preventDefault(){prevented=true;}});
assert.equal(prevented,true);assert.equal(saveCalls.length,1);assert.equal(saveCalls[0][0],'/api/admin/change-requests/4');assert.equal(saveCalls[0][1],'PATCH');
assert.deepEqual({...saveCalls[0][2]},{status:'Approved',assigned_schedule_id:'11',review_reason:'Checked'});
context.connectedRequest(5);assert.doesNotMatch(dialogCall[2],/cr-reject|cr-approve/);assert.match(dialogCall[0],/details/);
context.decideChangeRequest(5,'Approved');assert.equal(saveCalls.length,1,'A reviewed request cannot be resubmitted from stale UI.');
context.connectedRequest(8);assert.doesNotMatch(dialogCall[2],/cr-reject|cr-approve/,'Cancelled requests offer no review actions.');
context.decideChangeRequest(8,'Approved');context.decideChangeRequest(8,'Rejected');assert.equal(saveCalls.length,1,'Cancelled requests cannot be reviewed from stale UI.');

// The real live guard defers schedule/request updates while the review and its note are open.
let rendered=[],editing=false;
const dialog={open:true},note={value:'Unsaved conflict review'};
const live=vm.createContext({
  Set,Number,Object,window:{},dialog,batchSaveBusy:false,mySchedulesController:null,
  document:{hidden:false,activeElement:{matches:()=>editing},addEventListener(){},querySelector:()=>null},
  $:()=>null,setTimeout(){},clearInterval(){},setInterval(){},backend:async()=>({}),
  renderConnectedAdmin:async resources=>{rendered.push(Array.from(resources));note.value='refreshed';},
});
vm.runInContext(between('    function setLiveStatus(','    function connectAdminEvents()'),live);
(async()=>{
  await live.applyLiveVersions({year:2026,schedules:1,schedule_changes:1});
  await live.applyLiveVersions({year:2026,schedules:2,schedule_changes:2});
  assert.equal(rendered.length,0);assert.equal(note.value,'Unsaved conflict review');
  assert.equal(vm.runInContext('pendingLiveResources.has("schedules")&&pendingLiveResources.has("schedule_changes")',live),true);
  dialog.open=false;await live.refreshAdminWhenSafe();assert.equal(rendered.length,1);assert.ok(rendered[0].includes('schedules'));assert.ok(rendered[0].includes('schedule_changes'));
  editing=true;await live.applyLiveVersions({year:2026,schedules:3,schedule_changes:2});assert.equal(rendered.length,1);
  editing=false;await live.refreshAdminWhenSafe();assert.equal(rendered.length,2);
  console.log('PASS: request summaries, submitted academic group, original/current assignment, CSV, reason-only/legacy review, escaped fields, assignment/rejection validation, dialog confirmation wiring and deferred live review refresh.');
})().catch(error=>{console.error(error);process.exitCode=1;});
