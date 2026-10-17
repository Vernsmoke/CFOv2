const fs=require('node:fs'), vm=require('node:vm'), assert=require('node:assert/strict'), path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/partials/admin-batch-workflow.blade.php'),'utf8');
let rendered,grid,batch=null,release,requests=[],refreshes=0;
const elements=new Map();
const el=key=>{if(!elements.has(key))elements.set(key,{innerHTML:'',textContent:'',setAttribute(){},removeAttribute(){},classList:{add(){}},insertAdjacentHTML(_,html){this.innerHTML+=html;}});return elements.get(key);};
const schedule={id:10,title:'Batch A',date:'2026-10-01',year:2026,time:'08:00',active:true,capacity:10,registrations_count:2};
const rows=[1,2].map(id=>({id,user:{name:'Student '+id,course:'BSIT',year_level:'3',section:'A'},details:{},year:2026,status:'Approved',attendance:'Not Yet Recorded',is_archived:false,recollection_schedule_id:10,schedule,has_receipt:true}));
const context=vm.createContext({
    Set,Number,String,Boolean,URLSearchParams,
    $:el,dialog:{open:true,querySelectorAll:()=>[],querySelector:()=>null,setAttribute(){},removeAttribute(){}},actions:{},document:{querySelectorAll:()=>[],addEventListener(){}},window:{},icon:()=>'',go(){},
    cfoState:{current_year:2026,registrations:rows,schedules:[schedule],server_date:'2026-10-01'},
    findRegistration:id=>rows.find(row=>String(row.id)===String(id)),
    routeDetails:()=>({params:new URLSearchParams(batch?'batch='+batch:'')}),
    button:(label,action,id)=>`<button data-action="${action}" data-id="${id}">${label}</button>`,
    link:(label,route)=>`<a href="#${route}">${label}</a>`,
    escapeHTML:String,details:values=>JSON.stringify(values),panel:(title,body,controls='')=>title+body+controls,
    empty:(title,body)=>title+body,badge:String,objectFilter:()=>({}),
    connectedGrid:config=>{grid=config;},
    showDialog:(title,body,controls)=>{rendered={title,body,controls};},closeDialog(){},toast(){},
    backend:async(url,method,payload)=>{requests.push({url,method,payload});return new Promise(resolve=>{release=resolve;});},
    renderConnectedAdmin:async()=>{refreshes++;},
});
vm.runInContext(fs.readFileSync(path.join(__dirname,'../../public/cfo-search.js'),'utf8'),context);
vm.runInContext(source,context);
async function checkSharedAdminControls(){
    const admin=fs.readFileSync(path.join(__dirname,'../../public/admin-dashboard.js'),'utf8');
    const between=(start,end)=>{const a=admin.indexOf(start),b=admin.indexOf(end,a);assert.ok(a>=0&&b>a);return admin.slice(a,b);};
    const draft={value:'Unsaved student correction',onsubmit:()=>{}},confirmButton={disabled:false};
    const modal={open:true,childNodes:[draft],classList:{large:true,contains(){return this.large;},toggle(_,value){this.large=value;}},
        querySelectorAll:()=>[confirmButton],querySelector:()=>null,
        replaceChildren(...nodes){this.childNodes=nodes;},showModal(){this.open=true;}};
    let saves=0,refreshes=0,resolveSave,rejectSave,onSavedCalls=0,savedResult;
    const savedDrafts=new Map([['schedule-draft','Unsaved schedule']]);
    const onSaved=result=>{onSavedCalls++;savedResult=result;savedDrafts.delete('schedule-draft');};
    const errors=[];
    const shared=vm.createContext({
        actions:{},dialog:modal,dialogOpener:{},location:{hash:'#admin-all-student'},FormData:class {},URLSearchParams,batchSaveBusy:false,
        window:{CFO_BACKEND:{}},escapeHTML:String,button:(label,action)=>`<button data-action="${action}">${label}</button>`,
        showDialog:()=>{modal.open=true;modal.childNodes=[confirmButton];},closeDialog:()=>{modal.open=false;},
        toast:message=>errors.push(message),renderConnectedAdmin:async()=>{refreshes++;},
        backend:()=>{saves++;return new Promise((resolve,reject)=>{resolveSave=resolve;rejectSave=reject;});},
    });
    vm.runInContext(between('    let pendingConnectedSave =','    async function backend('),shared);
    vm.runInContext(between('    async function saveConnected(','    const connectedStudent ='),shared);
    const handler=admin.match(/^\s*'connected-confirm-save':(.+),\r?$/m);assert.ok(handler);
    vm.runInContext("actions['connected-confirm-save']="+handler[1],shared);
    const payload={first_name:'Corrected'};
    await shared.saveConnected('/api/admin/users/1','PATCH',payload,false,onSaved);
    assert.equal(onSavedCalls,0,'Opening confirmation cannot run the success callback');
    assert.equal(savedDrafts.has('schedule-draft'),true,'Opening confirmation preserves a saved draft');
    shared.actions['close-dialog']();assert.equal(modal.childNodes[0],draft,'Cancel preserves the form nodes and handlers');assert.equal(saves,0);
    assert.equal(onSavedCalls,0,'Cancelling confirmation cannot run the success callback');
    assert.equal(savedDrafts.has('schedule-draft'),true);
    await shared.saveConnected('/api/admin/users/1','PATCH',payload,false,onSaved);
    const pending=shared.actions['connected-confirm-save']();
    await shared.actions['connected-confirm-save']();assert.equal(saves,1);assert.equal(confirmButton.disabled,true);
    shared.actions['close-dialog']();assert.equal(modal.childNodes[0],confirmButton,'submission cannot be dismissed while in flight');
    rejectSave(Error('Choose a valid course'));await pending;
    assert.equal(modal.childNodes[0],draft,'validation errors return to the editable form');assert.equal(draft.value,'Unsaved student correction');
    assert.equal(typeof draft.onsubmit,'function');assert.equal(errors.at(-1),'Choose a valid course');
    assert.equal(onSavedCalls,0,'A failed save must not run the callback or clear a draft');
    assert.equal(savedDrafts.has('schedule-draft'),true);
    await shared.saveConnected('/api/admin/users/1','PATCH',payload,false,onSaved);
    const retry=shared.actions['connected-confirm-save']();
    await shared.actions['connected-confirm-save']();
    assert.equal(saves,2,'Repeated confirmation clicks cannot duplicate the retry');
    assert.equal(onSavedCalls,0,'The draft remains until the server confirms a successful save');
    const success={message:'Saved',id:123};resolveSave(success);await retry;
    assert.equal(saves,2);assert.equal(refreshes,1);assert.equal(modal.open,false);
    assert.equal(vm.runInContext('pendingConnectedSave',shared),null);assert.equal(confirmButton.disabled,false);
    assert.equal(onSavedCalls,1,'The forwarded callback runs exactly once after a successful save');
    assert.equal(savedResult,success,'The callback receives the actual saved server result');
    assert.equal(savedDrafts.has('schedule-draft'),false,'The success callback can clear the saved draft');
    await shared.actions['connected-confirm-save']();
    assert.equal(onSavedCalls,1,'A completed confirmation cannot invoke the callback again');

    vm.runInContext(between('    function routeDetails()','    function go('),shared);
    shared.location.hash='#admin-upcoming-recollection';assert.equal(shared.routeDetails().route,'admin-recollection-schedule');
    assert.equal(shared.routeDetails().params.get('phase'),'Upcoming');
    shared.location.hash='#admin-print-student-list?batch=10';assert.equal(shared.routeDetails().route,'admin-student-attendance');
    assert.equal(shared.routeDetails().params.get('batch'),'10');

    let mountedSchedules,mountedCreate,destroyedSchedules=0,createNavigations=[],builderSave;
    Object.assign(shared,{$:el,link:()=>'',unique:values=>[...new Set(values)],connectedGrid:()=>{},
        cfoState:{current_year:2026,server_date:'2026-10-01',academic_options:[],schedules:[schedule,{...schedule,id:11,date:'2026-09-01'},{...schedule,id:12,active:false},{...schedule,id:13,finalized_at:'2026-10-01'}]}});
    shared.window.CFO_BACKEND.user={id:99,name:'Administrator'};
    shared.go=route=>createNavigations.push(route);
    shared.window.CfoMySchedules={mount:options=>{mountedSchedules=options;return {isModalOpen:()=>false,destroy:()=>{destroyedSchedules++;}};}};
    shared.window.CfoScheduleCreate={mount:options=>{mountedCreate=options;}};
    vm.runInContext(between('    function connectedSchedules(options = {})','    function connectedReceipts('),shared);
    shared.location.hash='#admin-upcoming-recollection';shared.connectedSchedules();
    assert.equal(mountedSchedules.root,el('#page'),'The schedule page mounts the reusable real-data workspace.');
    assert.equal(mountedSchedules.state,shared.cfoState);
    assert.equal(mountedSchedules.request,shared.backend);
    assert.equal(typeof mountedSchedules.refresh,'function');
    mountedSchedules.onCreate();
    assert.deepEqual(createNavigations,['admin-add-new-recollection-schedule'],'Create opens the previous standalone builder route.');
    shared.connectedScheduleForm(new URLSearchParams());
    assert.equal(destroyedSchedules,1,'Leaving My schedules cleans up its controller before mounting the builder.');
    assert.equal(mountedCreate.root,el('#page'));
    assert.equal(mountedCreate.state,shared.cfoState,'The restored builder receives the live academic options and schedules.');
    assert.equal(mountedCreate.userId,99,'Drafts remain scoped to the signed-in administrator.');
    assert.equal(mountedCreate.request,shared.backend);
    assert.equal(mountedCreate.toast,shared.toast);
    assert.equal(mountedSchedules.create,undefined,'The create route cannot open the replacement My schedules modal.');
    const scheduleValues={course_ids:[1,2,3],year_levels:['1','4'],sections:['A','Z'],date:'2026-10-15'};
    const previousBackend=shared.backend,createdSchedule={id:14,...scheduleValues};let receivedSchedule;
    shared.backend=async(url,method,values)=>{builderSave={url,method,values};return createdSchedule;};
    await mountedCreate.save(scheduleValues,result=>{receivedSchedule=result;});
    assert.deepEqual(builderSave,{url:'/api/admin/schedules',method:'POST',values:scheduleValues},
        'The course-card builder saves through the existing authenticated backend.');
    assert.equal(receivedSchedule,createdSchedule,'The success callback receives the persisted schedule.');
    assert.equal(shared.cfoState.schedules.at(-1),createdSchedule,'The newly saved schedule immediately participates in live conflict checks.');
    const scheduleCount=shared.cfoState.schedules.length;
    shared.backend=async()=>{throw Error('This venue already has an overlapping schedule.');};
    await assert.rejects(mountedCreate.save(scheduleValues,()=>assert.fail('Rejected saves cannot reset the form')),
        /overlapping schedule/,'Server rejection is forwarded to the form for inline errors.');
    assert.equal(shared.cfoState.schedules.length,scheduleCount);
    shared.backend=previousBackend;
    let redirectedEdit;
    shared.location.replace=url=>{redirectedEdit=url;};
    shared.connectedScheduleForm(new URLSearchParams('id=10'));
    assert.equal(redirectedEdit,'/admin/schedules/10/edit','Legacy Edit links redirect to the protected prefilled page.');
    shared.window.CFO_BACKEND.edit_schedule={...schedule,assigned_academic_groups:[{course_id:1,year_level:'3',section:'A',count:1}]};
    shared.connectedScheduleForm(new URLSearchParams('id=10'));
    assert.equal(mountedCreate.editSchedule.id,10,'The same schedule builder opens in edit mode.');
    assert.equal(mountedCreate.editSchedule.assigned_academic_groups.length,1,'Edit receives the assigned-group locks.');
    assert.equal(mountedSchedules.openId,undefined,'Edit does not open the removed My Schedules modal.');
    assert.notEqual(mountedSchedules.create,true,'Editing uses the prefilled schedule page without a My Schedules form dialog.');

    const metrics=[];let dashboardPanel;
    shared.metric=(label,value,caption,icon,route)=>{metrics.push({label,value,route});return '';};
    Object.assign(shared,{panel:(title,body,controls)=>{dashboardPanel={title,body,controls};return body;},details:()=>'',connectedSchedule:r=>r.schedule.title,
        escapeHTML:value=>String(value??'').replace(/[&<>"']/g,character=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character])),
        cfoState:{...shared.cfoState,users:[{role:'Student',status:'Pending'}],registrations:[{id:1,has_receipt:true,receipt_status:'Pending'},{id:2,recollection_schedule_id:10,has_receipt:true,receipt_status:'Approved'}]}});
    shared.window.CFO_BACKEND.user={name:'Administrator'};
    vm.runInContext(fs.readFileSync(path.join(__dirname,'../../public/my-schedules.js'),'utf8'),shared);
    vm.runInContext(fs.readFileSync(path.join(__dirname,'../../public/admin-schedule-create.js'),'utf8'),shared);
    const dashboardSchedule={...schedule,id:50,title:'Annual <Recollection>',date:'2026-10-12',start_time:'08:00',end_time:'16:00',venue:'Campus Chapel',slot_limit:150,status:'Open',
        courses:[{id:11,name:'BSCE'},{id:12,name:'BSIT'},{id:13,name:'BSN'}],year_levels:['1','3'],sections:['A','B','C','D','Z'],
        course_sections_by_year:{11:{1:['A','B']},12:{1:['A','B'],3:['Z']},13:{3:['B','C','D']}}};
    shared.cfoState.schedules=[
        {...dashboardSchedule,id:51,date:'2026-10-20',title:'Later schedule'},
        {...dashboardSchedule,id:52,date:'2026-10-11',title:'Completed schedule',finalized_at:'2026-10-11'},
        {...dashboardSchedule,id:53,date:'2026-10-10',title:'Unpublished schedule',active:false},
        {...dashboardSchedule,id:54,date:'2026-09-30',title:'Past schedule'},
        {...dashboardSchedule,id:55,date:'2026-10-05',title:'Other recollection year',year:2025},
        dashboardSchedule,
    ];
    vm.runInContext(between('    function connectedDashboard()','    function connectedDirectory()'),shared);
    shared.connectedDashboard();assert.equal(metrics.find(m=>m.label==='New registrations').value,1);
    assert.equal(metrics.find(m=>m.label==='Awaiting approval').route,'admin-all-student?status=Pending');
    assert.equal(metrics.find(m=>m.label==='Pending receipts').route,'/admin/recollection-registrations?status=Pending');
    assert.equal(dashboardPanel.title,'Next annual recollection');
    assert.match(dashboardPanel.body,/class="dashboard-schedule-summary"/);
    assert.deepEqual(Array.from(dashboardPanel.body.matchAll(/<dt>([^<]+)<\/dt>/g),match=>match[1]),
        ['Title','Course(s)','Date','Time','Venue','Capacity','Status'],'The dashboard presents one ordered row per summary field.');
    assert.match(dashboardPanel.body,/Annual &lt;Recollection&gt;/,'The earliest upcoming published schedule is selected and its title is escaped.');
    assert.doesNotMatch(dashboardPanel.body,/Later schedule|Completed schedule|Unpublished schedule|Past schedule|Other recollection year|<Recollection>/);
    const dashboardGroups=Array.from(dashboardPanel.body.matchAll(/<li class="ms-course-group">([\s\S]*?)<\/li>/g),match=>({
        label:match[1].match(/<strong class="ms-course-group-name">([^<]+)<\/strong>/)[1],
        chips:Array.from(match[1].matchAll(/<span class="ms-section-chip">([^<]+)<\/span>/g),chip=>chip[1]),
    }));
    assert.deepEqual(dashboardGroups,[
        {label:'BSCE \u00b7 1st Year',chips:['1A','1B']},{label:'BSIT \u00b7 1st Year',chips:['1A','1B']},
        {label:'BSIT \u00b7 3rd Year',chips:['3Z']},{label:'BSN \u00b7 3rd Year',chips:['3B','3C','3D']},
    ],'Dashboard section chips stay with their saved course/year group, without inventing combinations.');
    assert.match(dashboardPanel.body,/October 12, 2026/);
    assert.match(dashboardPanel.body,/8:00 AM\s*\u2013\s*4:00 PM/,'The dashboard shows the complete schedule time range.');
    assert.match(dashboardPanel.body,/Campus Chapel/);assert.match(dashboardPanel.body,/150 students/);
    assert.match(dashboardPanel.body,/Open for Registration/);
    dashboardSchedule.slot_limit=null;shared.connectedDashboard();assert.match(dashboardPanel.body,/No limit/);
    dashboardSchedule.courses=[{id:11,name:'<img src=x>'}];dashboardSchedule.course_sections_by_year={11:{1:['<script>']}};
    shared.connectedDashboard();assert.match(dashboardPanel.body,/&lt;img src=x&gt;/);assert.match(dashboardPanel.body,/1&lt;script&gt;/);
    assert.doesNotMatch(dashboardPanel.body,/<img|<script/,'Stored course and section values remain escaped in dashboard chips.');
    shared.cfoState.schedules=[];shared.connectedDashboard();assert.match(dashboardPanel.body,/No upcoming annual schedule is published/);
    assert.doesNotMatch(dashboardPanel.body,/dashboard-schedule-summary|ms-course-groups/,'An empty dashboard retains its existing no-schedule message.');

    let print,exported;
    const included={user:{student_id:'ID-1',name:'Included student'},schedule,attendance:'Present'};
    vm.runInContext(fs.readFileSync(path.join(__dirname,'../../public/cfo-search.js'),'utf8'),shared);
    vm.runInContext(source,shared);
    Object.assign(shared,{activeGrid:{filtered:()=>[included],config:{key:'attendance',columns:[{label:'Attendance',get:r=>r.attendance}]}},
        printPreview:(title,markup)=>{print={title,markup};},csvDownload:(name,columns,rows)=>{exported={name,columns,rows};}});
    for(const action of ['connected-print','export-grid']){
        const definition=admin.match(new RegExp("^\\s*'"+action+"':(.+),\\r?$",'m'));assert.ok(definition);
        vm.runInContext('actions['+JSON.stringify(action)+']='+definition[1],shared);
        shared.actions[action]();
    }
    assert.match(print.markup,/Included student/);assert.match(print.markup,/Present/);
    assert.equal(exported.rows[0][0],'Present');
}
(async()=>{
    await checkSharedAdminControls();
    context.connectedAttendance();assert.match(el('#page').innerHTML,/Choose a recollection schedule/);
    batch='10';context.connectedAttendance();assert.equal(grid.rows().length,2);
    assert.match(grid.toolbar,/data-action="connected-print"/);assert.match(grid.toolbar,/data-action="attendance-export"/);
    assert.equal(grid.columns.find(column=>column.label==='Attendance').get(rows[0]),'Not Yet Recorded');
    assert.match(grid.columns.find(column=>column.label==='Attendance').html(rows[0]),/Present/);assert.match(grid.columns.find(column=>column.label==='Attendance').html(rows[0]),/Absent/);assert.match(grid.rowActions(rows[0]),/View/);
    assert.doesNotMatch(grid.rowActions(rows[0]),/batch-present|batch-absent/,'only View remains in the Action column');
    assert.equal(grid.compactFilters,true);assert.match(grid.filterToolbar,/batch-present-all/);
    context.reviewBatchArchive(10);assert.equal(rendered.title,'Attendance incomplete');assert.equal(requests.length,0);
    rows[0].attendance='Present';rows[1].attendance='Absent';
    assert.equal(grid.columns.find(column=>column.label==='Attendance').get(rows[1]),'Absent','CSV must retain the attendance value');
    let attendanceCsv;
    context.activeGrid={config:grid,filtered:()=>[rows[1]]};
    context.csvDownload=(name,columns,data)=>{attendanceCsv={name,columns,data};};
    context.actions['attendance-export']();
    assert.equal(attendanceCsv.name,'batch-attendance-10.csv');
    assert.deepEqual(Array.from(attendanceCsv.columns),['Student ID','Student Name','Course','Year','Section','Religion','Attendance','Marked at']);
    assert.equal(attendanceCsv.data.length,1,'Attendance export uses only the current matching records');
    assert.equal(attendanceCsv.data[0][1],'Student 2');assert.equal(attendanceCsv.data[0][6],'Absent');
    context.reviewBatchArchive(10);assert.match(rendered.body,/Sigurado ka bang ilipat ang students sa History\?/);
    assert.equal(requests.length,0,'opening confirmation must not archive');
    const pending=context.actions['batch-archive-confirm']('10');
    await context.actions['batch-archive-confirm']('10');assert.equal(requests.length,1,'duplicate click suppressed');
    assert.deepEqual(Array.from(requests[0].payload.registration_ids),[1,2]);
    release({message:'Moved successfully'});await pending;assert.equal(refreshes,1);assert.match(rendered.body,/Moved successfully/);
    context.connectedRegistration(1);assert.match(rendered.body,/View receipt/);assert.match(rendered.body,/name="schedule_id"/);assert.match(rendered.body,/confirm_attendance_reset/);
    assert.equal(context.scheduleUnavailable({...schedule,finalized_at:'2026-10-01'},rows[0]),'Batch already in History');
    assert.equal(context.scheduleUnavailable({...schedule,courses:[{name:'BSA'}],year_level:'1',section:'Z'},rows[0]),'');
    assert.equal(context.scheduleUnavailable({...schedule,id:20,registrations_count:10},rows[0]),'Full');
    context.markBatchAttendance(1,'Absent');assert.equal(rendered.title,'Confirm attendance correction');assert.equal(requests.length,1);
    rows[0].attendance='Not Yet Recorded';
    const previousDialog=rendered,previousRequests=requests.length,previousRefreshes=refreshes;
    context.actions['batch-present']('1');
    assert.equal(rendered,previousDialog,'First-time attendance saves without opening a dialog');
    assert.equal(requests.length,previousRequests+1);
    assert.equal(requests.at(-1).url,'/api/admin/registrations/1/attendance');
    assert.equal(requests.at(-1).payload.attendance,'Present');assert.equal(requests.at(-1).payload.confirm_edit,undefined);
    context.actions['batch-present']('1');assert.equal(requests.length,previousRequests+1,'Repeated quick marking is suppressed while saving');
    release({message:'Attendance saved'});await new Promise(resolve=>setImmediate(resolve));
    assert.equal(refreshes,previousRefreshes+1,'Quick marking refreshes the persisted attendance');
    rows.forEach(row=>row.is_archived=true);schedule.finalized_at='2026-10-01';context.connectedAttendance();assert.match(el('#page').innerHTML,/No active attendance batches/);
    console.log('PASS: grouped attendance, matching-record CSV, quick first-time marking, all-student completion gate, explicit archive confirmation, duplicate guard, schedule review, corrections and success modal.');
})().catch(error=>{console.error(error);process.exitCode=1;});
