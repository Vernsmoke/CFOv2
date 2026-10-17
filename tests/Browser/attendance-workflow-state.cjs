// Execute the existing production grid and workflow functions with isolated API/DOM fixtures.
// This verifies behavior, not visual layout in a browser.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const read=name=>fs.readFileSync(path.join(__dirname,'../..',name),'utf8');
const admin=read('public/admin-dashboard.js');
const between=(start,end)=>{const a=admin.indexOf(start),b=admin.indexOf(end,a);assert.ok(a>=0&&b>a);return admin.slice(a,b);};
class Control {
    constructor(){this.innerHTML='';this.textContent='';this.value='';this.disabled=false;this.listeners={};this.children=new Map();this.attrs={};this.dataset={};this.style={};this.classList={add(){}};}
    get firstElementChild(){return this.child||(this.child=new Control());}
    setAttribute(key,value){this.attrs[key]=value;}
    removeAttribute(key){delete this.attrs[key];}
    hasAttribute(key){return Object.hasOwn(this.attrs,key);}
    addEventListener(name,callback){this.listeners[name]=callback;}
    focus(){this.focused=true;}
    contains(node){return node===this||[...this.children.values()].some(child=>child.contains(node));}
    querySelector(key){if(!this.children.has(key)){const node=new Control(),match=key.match(/^\[(data-[\w-]+)(?:="([^"]*)")?\]$/);if(match){node.setAttribute(match[1],match[2]||'');node.dataset[match[1].slice(5).replace(/-([a-z])/g,(_,letter)=>letter.toUpperCase())]=match[2]||'';}this.children.set(key,node);}return this.children.get(key);}
    querySelectorAll(key){return key==='[data-filter]'?[...this.innerHTML.matchAll(/data-filter="([^"]+)"/g)].map(match=>{const name=match[1],node=this.querySelector('[data-filter="'+name+'"]');node.dataset={filter:name};return node;}):[];}
    replaceChildren(node){this.child=node;}
    insertAdjacentHTML(_,html){this.innerHTML+=html;}
}
function setup(){
    const elements=new Map(),calls=[];
    const $=(key,root)=>{
        if(root)return root.querySelector(key);
        const grid=elements.get('#grid-host')?.child;
        if(key==='#data-grid')return grid;
        const attr=key.match(/^\[([^\]]+)\]$/),cssClass=key.match(/^\.([\w-]+)$/);
        if(grid&&((attr&&grid.innerHTML.includes(attr[1]))||(cssClass&&grid.innerHTML.includes(cssClass[1]))))return grid.querySelector(key);
        if(!elements.has(key))elements.set(key,new Control());
        return elements.get(key);
    };
    const schedule={id:10,title:'Formation Day',date:'2026-10-05',year:2026,start_time:'08:00',end_time:'16:00',venue:'University Chapel',active:true,capacity:50,registrations_count:15};
    const students=Array.from({length:15},(_,i)=>({id:i+1,user:{name:'Student '+(i+1),last_name:'Surname '+(i+1),first_name:'Given',student_id:'ID-'+(i+1),course:i%2?'BSA':'BSIT',year_level:i%2?'2':'3',section:i%2?'B':'A'},details:{religion:'Roman Catholic'},status:'Approved',receipt_status:'Approved',attendance:'Not Yet Recorded',year:2026,is_archived:false,recollection_schedule_id:10,schedule}));
    const other={...students[0],id:99,recollection_schedule_id:11,schedule:{...schedule,id:11,title:'Other Batch'},user:{...students[0].user,name:'Other Student'}};
    const state={current_year:2026,server_date:'2026-10-05',registrations:[...students,other],schedules:[schedule,other.schedule]};
    const ctx=vm.createContext({Set,Map,URLSearchParams,document:{createElement:()=>new Control(),querySelectorAll:()=>[],addEventListener(){}},
        $,$$:(key,root)=>root?root.querySelectorAll(key):[],actions:{},window:{},dialog:Object.assign(new Control(),{open:false}),
        cfoState:structuredClone(state),routeDetails:()=>({route:'admin-student-attendance',params:new URLSearchParams('batch=10')}),
        button:(label,action,id='')=>`<button data-action="${action}" data-id="${id}">${label}</button>`,link:label=>label,icon:()=>'',
        escapeHTML:value=>String(value??'').replace(/</g,'&lt;'),empty:(title,description)=>title+description,go:route=>{ctx.navigation=route;},syncAdminSearch(){},hydrateIcons(){},toast:message=>{ctx.feedback=message;},
        showDialog:(title,body,controls)=>{ctx.dialog.open=true;ctx.modal={title,body,controls};ctx.dialogs.push(ctx.modal);},closeDialog:()=>{ctx.dialog.open=false;},dialogs:[],
        csvDownload:(filename,columns,data)=>{ctx.download={filename,columns,data};},printPreview:(title,body)=>{ctx.print={title,body};},
        backend:async(url,method='GET',payload)=>{calls.push({url,method,payload});if(method==='GET')return structuredClone(state);const id=Number(url.match(/registrations\/(\d+)/)?.[1]);const row=state.registrations.find(r=>r.id===id);assert.ok(row);if(row.attendance!=='Not Yet Recorded'&&row.attendance!==payload.attendance)assert.equal(payload.confirm_edit,true);row.attendance=payload.attendance;return {message:'Attendance saved'};},
        renderConnectedAdmin:async()=>{ctx.cfoState=structuredClone(state);ctx.connectedAttendance();},
        findRegistration:id=>ctx.cfoState.registrations.find(r=>String(r.id)===String(id))});
    vm.runInContext(read('public/cfo-search.js'),ctx);
    vm.runInContext(between('    const escapeHTML =','    const iconPaths ='),ctx);
    vm.runInContext(between('    function details(pairs)','    function metric('),ctx);
    vm.runInContext(between('    const options =','    const multiSelectField ='),ctx);
    vm.runInContext(between('    const gridState =','    /* 11. Routes'),ctx);
    vm.runInContext(between('    function connectedGrid(','    function syncAdminSearch('),ctx);
    vm.runInContext(read('resources/views/partials/admin-batch-workflow.blade.php'),ctx);
    ctx.connectedAttendance();
    return {ctx,state,calls,$,grid:()=>vm.runInContext('activeGrid',ctx)};
}
module.exports={setup};
async function waitForQuickSave(h){
    for(let attempt=0;attempt<20&&vm.runInContext('batchSaveBusy',h.ctx);attempt++)await Promise.resolve();
    assert.equal(vm.runInContext('batchSaveBusy',h.ctx),false,'quick save finishes and unlocks controls');
}
function selectRows(h,ids){
    const root=h.$('#data-grid');
    for(const id of ids){const checkbox=root.querySelector('[data-select="'+id+'"]');checkbox.checked=true;root.listeners.change({target:checkbox});}
}
if(require.main===module)(async()=>{
    const h=setup(),grid=h.grid();
    assert.match(h.$('#page').innerHTML,/Recollection Attendance/);
    assert.match(h.$('#data-grid').innerHTML,/Show Filters/);
    assert.match(h.$('#data-grid').innerHTML,/id="attendance-health-filters"[^>]*hidden/);
    assert.match(h.$('#data-grid').innerHTML,/data-action="batch-present-all"/);
    assert.doesNotMatch(h.$('#data-grid').innerHTML,/attendance-filter-menu/);
    assert.match(grid.config.columns.find(c=>c.label==='Attendance').html(h.state.registrations[0]),/batch-present/);
    assert.doesNotMatch(grid.config.rowActions(h.state.registrations[0]),/batch-present|batch-absent/);
    assert.equal(h.$('[data-attendance-count="Not Yet Recorded"]').textContent,15);
    const toggle=h.$('#data-grid').querySelector('[data-toggle-filters]'),area=h.$('#data-grid').querySelector('#attendance-health-filters'),badge=h.$('#data-grid').querySelector('[data-active-filters]');
    toggle.onclick();assert.equal(area.hidden,false);assert.equal(toggle.attrs['aria-expanded'],'true');assert.equal(h.$('#data-grid').querySelector('[data-toggle-filter-label]').textContent,'Hide Filters');
    toggle.onclick();assert.equal(area.hidden,true);assert.equal(toggle.attrs['aria-expanded'],'false');assert.equal(h.$('#data-grid').querySelector('[data-toggle-filter-label]').textContent,'Show Filters');
    assert.equal(grid.filtered().length,15,'selected schedule never mixes another batch');
    assert.match(h.$('#data-grid').querySelector('tbody').innerHTML,/<td>10<\/td>/);
    h.$('#data-grid').querySelector('[data-next]').onclick();assert.match(h.$('#data-grid').querySelector('tbody').innerHTML,/<td>11<\/td>/,'row numbers account for pagination');
    assert.match(h.$('#data-grid').querySelector('[data-range]').textContent,/11.*15 of 15/);
    for(const [key,value,count] of [['course','BSIT',8],['year_level','3',8],['section','A',8],['attendance','Not Yet Recorded',8]]){
        const control=h.$('#data-grid').querySelector('[data-filter="'+key+'"]');control.value=value;control.listeners.change();assert.equal(grid.filtered().length,count);
    }
    const search=h.$('#data-grid').querySelector('[data-search]');search.value='ID-3';search.listeners.input({target:search});assert.equal(grid.filtered().length,1);
    assert.equal(badge.textContent,5,'four selected filters plus search share the Health & Religion badge behavior');
    toggle.onclick();assert.equal(grid.filtered().length,1);toggle.onclick();assert.equal(grid.filtered().length,1,'hiding filters keeps them applied');
    assert.equal(h.$('[data-attendance-count="Not Yet Recorded"]').textContent,1,'inline totals follow table filters');
    h.$('#data-grid').querySelector('[data-reset]').onclick();assert.equal(grid.filtered().length,15);assert.equal(h.$('[data-attendance-count="Not Yet Recorded"]').textContent,15,'Clear Filters restores the batch totals');
    assert.equal(badge.hidden,true);
    assert.equal(h.$('[data-attendance-progress-text]').textContent,'0 of 15 marked (0%)');
    assert.equal(h.$('[data-attendance-progress-bar]').attrs['aria-valuenow'],'0');
    assert.equal(h.$('[data-attendance-progress-bar]').firstElementChild.style.width,'0%');
    search.value='ID-3';search.listeners.input({target:search});
    h.ctx.reviewBatchCompletion(10);assert.equal(h.ctx.modal.title,'Attendance incomplete');assert.equal(h.calls.length,0);assert.match(h.ctx.modal.controls,/batch-absent-remaining/);
    h.ctx.reviewBatchArchive(10);assert.equal(h.ctx.modal.title,'Attendance incomplete');
    h.$('#attendance-schedule').onchange({target:{value:'11'}});assert.equal(h.ctx.navigation,'admin-student-attendance?batch=11');
    h.ctx.reviewBatchPresent(10);assert.match(h.ctx.modal.body,/15 students/,'bulk confirmation includes students hidden by filters and pagination');assert.equal(h.calls.length,0);
    await h.ctx.saveBatchPresent('10');
    assert.equal(h.calls.filter(c=>c.method==='POST').length,15);assert.ok(h.state.registrations.filter(r=>r.recollection_schedule_id===10).every(r=>r.attendance==='Present'));
    assert.equal(h.state.registrations.find(r=>r.id===99).attendance,'Not Yet Recorded');assert.equal(h.ctx.modal.title,'Batch attendance saved');
    assert.equal(h.$('[data-attendance-progress-text]').textContent,'15 of 15 marked (100%)','progress uses the whole batch while search is filtered');
    assert.equal(h.$('[data-attendance-progress-note]').textContent,'All students marked');
    assert.equal(h.$('[data-attendance-progress-bar]').firstElementChild.style.width,'100%');
    h.ctx.reviewBatchCompletion(10);assert.equal(h.ctx.modal.title,'Complete Attendance');await h.ctx.confirmBatchCompletion('10');assert.equal(h.ctx.modal.title,'Attendance complete');
    assert.equal(h.calls.filter(c=>c.url.endsWith('/archive')).length,0,'completion does not silently archive');
    h.ctx.reviewBatchArchive(10);assert.match(h.ctx.modal.controls,/batch-archive-confirm/);

    const correction=setup();correction.state.registrations[0].attendance='Absent';correction.state.registrations[1].attendance='Present';correction.ctx.cfoState=structuredClone(correction.state);
    correction.ctx.reviewBatchPresent(10);assert.match(correction.ctx.modal.body,/recorded attendance result/);await correction.ctx.saveBatchPresent(10);
    const posts=correction.calls.filter(c=>c.method==='POST');assert.equal(posts.length,14);assert.equal(posts[0].payload.confirm_edit,true);assert.ok(!posts.some(c=>c.url.includes('/2/attendance')),'already Present is skipped');

    const partial=setup(),realSave=partial.ctx.backend;partial.ctx.backend=async(url,method,payload)=>{if(url.endsWith('/3/attendance')){partial.calls.push({url,method,payload});throw Error('Receipt or batch changed');}return realSave(url,method,payload);};
    partial.ctx.reviewBatchPresent(10);await partial.ctx.saveBatchPresent(10);assert.equal(partial.calls.filter(c=>c.method==='POST').length,3);
    assert.match(partial.ctx.modal.body,/2 of 15 saves confirmed/);assert.match(partial.ctx.modal.body,/12 records were not attempted/);assert.match(partial.ctx.modal.body,/Surname 3/);assert.equal(partial.ctx.modal.title,'Batch attendance needs attention');

    const duplicate=setup(),duplicateSave=duplicate.ctx.backend;let release;
    duplicate.ctx.backend=async(...args)=>{if(args[0].endsWith('/1/attendance'))await new Promise(resolve=>{release=resolve;});return duplicateSave(...args);};
    duplicate.ctx.reviewBatchPresent(10);const pending=duplicate.ctx.saveBatchPresent(10);await Promise.resolve();await Promise.resolve();
    assert.equal(vm.runInContext('batchSaveBusy',duplicate.ctx),true);await duplicate.ctx.saveBatchPresent(10);assert.equal(duplicate.calls.filter(c=>c.method==='GET').length,1,'duplicate confirmation is ignored');release();await pending;
    assert.equal(duplicate.calls.filter(c=>c.method==='POST').length,15);

    const changed=setup();changed.ctx.reviewBatchPresent(10);changed.state.registrations[0].attendance='Absent';await changed.ctx.saveBatchPresent(10);
    assert.equal(changed.calls.filter(c=>c.method==='POST').length,0,'concurrent corrections require a fresh review');assert.match(changed.ctx.modal.body,/attendance changed/);
    const completion=setup();completion.state.registrations.forEach(r=>r.attendance='Present');completion.ctx.cfoState=structuredClone(completion.state);
    completion.ctx.reviewBatchCompletion(10);completion.state.registrations[0].attendance='Not Yet Recorded';await completion.ctx.confirmBatchCompletion(10);assert.equal(completion.ctx.modal.title,'Attendance needs review');assert.match(completion.ctx.modal.body,/incomplete/);
    completion.ctx.cfoState=structuredClone(completion.state);completion.ctx.cfoState.registrations[0].attendance='Present';completion.ctx.reviewBatchCompletion(10);completion.state.registrations.push({...completion.state.registrations[0],id:100});await completion.ctx.confirmBatchCompletion(10);assert.match(completion.ctx.modal.body,/membership changed/);
    const navigation=setup();navigation.$('#data-grid').querySelector('[data-toggle-filters]').onclick();navigation.ctx.connectedAttendance();
    assert.equal(navigation.grid().state.filtersOpen,true,'live redraw retains the opened panel');assert.match(navigation.$('#data-grid').innerHTML,/Hide Filters/);
    navigation.ctx.actions['attendance-view']('health');assert.equal(Boolean(navigation.grid().state.filtersOpen),false,'Health uses its own filter navigation state');
    navigation.ctx.actions['attendance-view']('students');assert.equal(navigation.grid().state.filtersOpen,true,'Attendance retains its open state across tabs');

    const quick=setup();quick.ctx.actions['batch-present'](1);
    assert.equal(quick.ctx.dialogs.length,0,'first-time attendance saves without a confirmation or success dialog');
    await waitForQuickSave(quick);assert.equal(quick.state.registrations[0].attendance,'Present');
    assert.equal(quick.calls[0].payload.confirm_edit,undefined);assert.match(quick.ctx.feedback,/marked Present/);
    assert.equal(quick.$('#page').attrs['aria-busy'],undefined);assert.equal(quick.ctx.dialog.attrs['aria-busy'],undefined);
    assert.equal(quick.$('[data-attendance-progress-text]').textContent,'1 of 15 marked (7%)');
    quick.ctx.actions['batch-present'](1);assert.equal(quick.calls.length,1,'marking the same status performs no additional save');
    quick.ctx.actions['batch-absent'](1);assert.equal(quick.ctx.modal.title,'Confirm attendance correction');
    assert.equal(quick.calls.length,1,'a correction waits for explicit confirmation');
    await quick.ctx.actions['batch-confirm-absent'](1);assert.equal(quick.state.registrations[0].attendance,'Absent');assert.equal(quick.calls[1].payload.confirm_edit,true);
    const quickFailure=setup();quickFailure.ctx.backend=async()=>{throw Error('Attendance could not be saved');};
    quickFailure.ctx.actions['batch-present'](1);await waitForQuickSave(quickFailure);
    assert.equal(quickFailure.state.registrations[0].attendance,'Not Yet Recorded');assert.equal(quickFailure.ctx.feedback,'Attendance could not be saved');assert.equal(quickFailure.ctx.dialogs.length,0);

    const ordered=setup();ordered.state.registrations[0].attendance='Present';ordered.state.registrations[1].attendance='Absent';ordered.ctx.cfoState=structuredClone(ordered.state);ordered.ctx.connectedAttendance();
    ordered.grid().state.sortIndex=1;ordered.grid().state.sortDirection='desc';ordered.grid().state.page=2;ordered.grid().draw();
    ordered.ctx.actions['attendance-unmarked-first']();const unmarkedRows=Array.from(ordered.grid().filtered());
    assert.equal(ordered.grid().state.sortIndex,undefined,'unmarked-first overrides a prior column sort');assert.equal(ordered.grid().state.page,1);
    assert.ok(unmarkedRows.slice(0,13).every(r=>r.attendance==='Not Yet Recorded'));assert.ok(unmarkedRows.slice(13).every(r=>r.attendance!=='Not Yet Recorded'));
    assert.match(ordered.$('#data-grid').innerHTML,/Unmarked first: On/);ordered.ctx.actions['attendance-unmarked-first']();assert.equal(ordered.grid().filtered()[0].id,1,'turning off restores alphabetical order');
    const exportRoot=ordered.$('#data-grid'),exportSearch=exportRoot.querySelector('[data-search]');exportSearch.value='ID-3';exportSearch.listeners.input({target:exportSearch});
    ordered.ctx.actions['attendance-export']();assert.equal(ordered.ctx.download.filename,'batch-attendance-10.csv');
    assert.deepEqual(Array.from(ordered.ctx.download.columns),['Student ID','Student Name','Course','Year','Section','Religion','Attendance','Marked at']);assert.equal(ordered.ctx.download.data.length,1);assert.equal(ordered.ctx.download.data[0][0],'ID-3');

    const academic=setup();academic.state.registrations=academic.state.registrations.slice(0,4);
    Object.assign(academic.state.registrations[0],{details:{course:' BSECE ',yearLevel:' 1 ',section:' Z ',yearSection:'1Z'}});
    Object.assign(academic.state.registrations[1],{details:{course:'BSN',yearSection:'4-Y'}});
    Object.assign(academic.state.registrations[2],{details:{},student_snapshot:{course:'BSED',year_level:'2',section:'C'}});
    academic.ctx.cfoState=structuredClone(academic.state);academic.ctx.connectedAttendance();
    const academicRows=academic.ctx.cfoState.registrations,academicGrid=academic.grid(),academicRoot=academic.$('#data-grid');
    const academicColumn=academicGrid.config.columns.find(column=>column.label==='Course – Year & Section');
    const academicValues=academicRows.map(row=>Array.from(Object.values(academic.ctx.attendanceAcademicGroup(row))));
    assert.deepEqual(academicValues,[['BSECE','1','Z'],['BSN','4','Y'],['BSED','2','C'],['BSA','2','B']],
        'Attendance uses submitted academic choices, then legacy combined year/section, snapshots and profile fallback.');
    assert.match(academicColumn.html(academicRows[0]),/BSECE/);assert.match(academicColumn.html(academicRows[0]),/Year 1 · Section Z/);
    assert.doesNotMatch(academicColumn.html(academicRows[0]),/BSIT|Year 3|Section A/,'Old profile data cannot replace the submitted form.');
    assert.match(academic.$('#page').innerHTML,/BSECE · 1Z/);assert.match(academic.$('#page').innerHTML,/BSN · 4Y/);
    for(const [key,expected] of [['course',['BSA','BSECE','BSED','BSN']],['year_level',['1','2','4']],['section',['B','C','Y','Z']]]){
        assert.deepEqual(Array.from(academicGrid.config.filters.find(filter=>filter.key===key).values),expected,
            key+' options are derived from the same submitted details shown in the table.');
    }
    const academicSearch=academicRoot.querySelector('[data-search]');
    for(const [query,expected] of [['BSECE',[1]],['1Z',[1]],['BSN 4Y',[2]],['BSED',[3]],['BSIT',[]]]){
        academicSearch.value=query;academicSearch.listeners.input({target:academicSearch});
        assert.deepEqual(Array.from(academicGrid.filtered(),row=>row.id),expected,'Attendance search uses selected academic fields: '+query);
    }
    academicRoot.querySelector('[data-reset]').onclick();
    for(const [key,value] of [['course','BSECE'],['year_level','1'],['section','Z']]){
        const control=academicRoot.querySelector('[data-filter="'+key+'"]');control.value=value;control.listeners.change();
        assert.deepEqual(Array.from(academicGrid.filtered(),row=>row.id),[1],'Academic filter '+key+' matches the submitted choice.');
    }
    academic.ctx.actions['attendance-export']();
    assert.deepEqual(Array.from(academic.ctx.download.data[0].slice(2,5)),['BSECE','1','Z'],'Attendance CSV exports the same selected academic group.');
    const printDefinition=admin.match(/^\s*'connected-print':(.+),\r?$/m);assert.ok(printDefinition);
    academic.ctx.connectedSchedule=row=>row.schedule?.title||'—';
    vm.runInContext('actions["connected-print"]='+printDefinition[1],academic.ctx);academic.ctx.actions['connected-print']();
    assert.match(academic.ctx.print.body,/BSECE/);assert.match(academic.ctx.print.body,/<td>1<\/td>/);assert.match(academic.ctx.print.body,/<td>Z<\/td>/);
    assert.doesNotMatch(academic.ctx.print.body,/BSIT/,'Attendance print uses selected registration details.');
    const maliciousAcademic={...academicRows[0],details:{course:'<img src=x>',yearLevel:'1',section:'<script>'}};
    assert.doesNotMatch(academicColumn.html(maliciousAcademic),/<img|<script/,'Submitted course and section values are escaped in attendance.');

    const selected=setup();selected.state.registrations[0].attendance='Absent';selected.state.registrations[1].attendance='Present';selected.ctx.cfoState=structuredClone(selected.state);selected.ctx.connectedAttendance();
    selectRows(selected,[1,2,3]);assert.equal(selected.$('[data-attendance-bulk]').hidden,false);assert.equal(selected.$('[data-bulk-count]').textContent,'3 selected');assert.equal(selected.$('[data-bulk-matching]').textContent,'Select all 15 matching');
    selected.ctx.actions['batch-selected-present'](10);assert.match(selected.ctx.modal.body,/2 selected students/);assert.match(selected.ctx.modal.body,/1 recorded result/);assert.equal(selected.calls.length,0);
    await selected.ctx.actions['batch-selected-confirm'](10);const selectedPosts=selected.calls.filter(c=>c.method==='POST');
    assert.deepEqual(selectedPosts.map(c=>c.url),['/api/admin/registrations/1/attendance','/api/admin/registrations/3/attendance']);assert.equal(selectedPosts[0].payload.confirm_edit,true);assert.equal(selectedPosts[1].payload.confirm_edit,undefined);
    assert.equal(selected.state.registrations[3].attendance,'Not Yet Recorded');assert.equal(selected.state.registrations.find(r=>r.id===99).attendance,'Not Yet Recorded');
    assert.equal(selected.grid().state.selected.size,0);assert.equal(selected.$('[data-attendance-bulk]').hidden,true,'success redraw clears the visible selection toolbar');assert.equal(selected.$('[data-bulk-count]').textContent,'0 selected');assert.doesNotMatch(selected.$('#data-grid').querySelector('tbody').innerHTML,/data-select="[^"]+"[^>]*checked/);

    const matching=setup(),matchingRoot=matching.$('#data-grid'),course=matchingRoot.querySelector('[data-filter="course"]');course.value='BSIT';course.listeners.change();matching.grid().state.size=5;matching.grid().draw();
    matching.ctx.actions['batch-select-matching']();assert.equal(matching.grid().state.selected.size,8,'select all matching respects filters across pages');assert.equal(matching.$('[data-bulk-matching]').hidden,true);
    matching.ctx.actions['batch-selected-clear']();assert.equal(matching.grid().state.selected.size,0);assert.equal(matching.$('[data-attendance-bulk]').hidden,true);
    matchingRoot.listeners.change({target:Object.assign(matchingRoot.querySelector('[data-select-all]'),{checked:true})});assert.equal(matching.grid().state.selected.size,5,'header checkbox selects only the current page');matching.ctx.actions['batch-select-matching']();assert.equal(matching.grid().state.selected.size,8);
    matching.ctx.actions['batch-selected-absent'](10);assert.match(matching.ctx.modal.body,/notification/);await matching.ctx.actions['batch-selected-confirm'](10);
    assert.equal(matching.calls.filter(c=>c.method==='POST').length,8);assert.ok(matching.state.registrations.filter(r=>r.recollection_schedule_id===10&&r.user.course==='BSIT').every(r=>r.attendance==='Absent'));assert.ok(matching.state.registrations.filter(r=>r.recollection_schedule_id===10&&r.user.course==='BSA').every(r=>r.attendance==='Not Yet Recorded'));

    for(const mutate of [h=>{h.state.registrations[0].attendance='Absent';},h=>{h.state.registrations[0].recollection_schedule_id=11;},h=>{h.state.registrations[0].status='Pending';}]){
        const stale=setup();selectRows(stale,[1,2]);stale.ctx.reviewSelectedAttendance(10,'Present');mutate(stale);await stale.ctx.saveSelectedAttendance(10);
        assert.equal(stale.calls.filter(c=>c.method==='POST').length,0,'selected saves refuse changed attendance, membership or approval');assert.equal(stale.ctx.modal.title,'Attendance needs attention');assert.match(stale.ctx.modal.body,/batch changed/);assert.equal(vm.runInContext('batchSaveBusy',stale.ctx),false);
    }
    const selectedFailure=setup(),selectedBackend=selectedFailure.ctx.backend;selectRows(selectedFailure,[1,2,3,4]);
    selectedFailure.ctx.backend=async(url,method,payload)=>{if(url.endsWith('/2/attendance')){selectedFailure.calls.push({url,method,payload});throw Error('Selected save rejected');}return selectedBackend(url,method,payload);};
    selectedFailure.ctx.reviewSelectedAttendance(10,'Absent');await selectedFailure.ctx.saveSelectedAttendance(10);
    assert.equal(selectedFailure.calls.filter(c=>c.method==='POST').length,2);assert.equal(selectedFailure.state.registrations[0].attendance,'Absent');assert.equal(selectedFailure.state.registrations[1].attendance,'Not Yet Recorded');assert.match(selectedFailure.ctx.modal.body,/1 of 4 saves confirmed/);assert.match(selectedFailure.ctx.modal.body,/2 records were not attempted/);assert.match(selectedFailure.ctx.modal.body,/Surname 2/);assert.equal(selectedFailure.grid().state.selected.size,4,'failed selection remains available for review');
    const selectedDuplicate=setup(),duplicateSelectedBackend=selectedDuplicate.ctx.backend;let releaseSelected;
    selectRows(selectedDuplicate,[1,2]);selectedDuplicate.ctx.reviewSelectedAttendance(10,'Present');selectedDuplicate.ctx.backend=async(...args)=>{if(args[0].endsWith('/1/attendance'))await new Promise(resolve=>{releaseSelected=resolve;});return duplicateSelectedBackend(...args);};
    const selectedPending=selectedDuplicate.ctx.saveSelectedAttendance(10);await Promise.resolve();await Promise.resolve();assert.equal(vm.runInContext('batchSaveBusy',selectedDuplicate.ctx),true);
    await selectedDuplicate.ctx.saveSelectedAttendance(10);selectedDuplicate.ctx.actions['attendance-unmarked-first']();selectedDuplicate.ctx.actions['attendance-view']('health');
    assert.equal(selectedDuplicate.calls.filter(c=>c.method==='GET').length,1,'duplicate selected confirmation is ignored');assert.equal(vm.runInContext('attendanceUnmarkedFirst',selectedDuplicate.ctx),false,'busy saves block roster rearrangement');assert.equal(selectedDuplicate.grid().config.key,'batch-attendance-10','busy saves block tab changes');releaseSelected();await selectedPending;
    assert.equal(selectedDuplicate.calls.filter(c=>c.method==='POST').length,2);assert.equal(selectedDuplicate.grid().state.selected.size,0);

    const remaining=setup();remaining.state.registrations[0].attendance='Present';remaining.state.registrations[1].attendance='Absent';remaining.ctx.cfoState=structuredClone(remaining.state);remaining.ctx.connectedAttendance();
    remaining.grid().state.q='ID-3';remaining.grid().draw();remaining.ctx.actions['batch-absent-remaining'](10);assert.match(remaining.ctx.modal.body,/13 students/);await remaining.ctx.saveSelectedAttendance(10);
    assert.equal(remaining.calls.filter(c=>c.method==='POST').length,13);assert.equal(remaining.state.registrations[0].attendance,'Present');assert.equal(remaining.state.registrations[1].attendance,'Absent');assert.equal(remaining.state.registrations.find(r=>r.id===99).attendance,'Not Yet Recorded');assert.equal(remaining.$('[data-attendance-progress-text]').textContent,'15 of 15 marked (100%)');
    console.log('PASS: filters, search, pagination, schedule isolation, progress, quick marks, corrections, unmarked-first, attendance CSV, selected and whole-batch saves, partial failures, duplicate protection, and fresh completion validation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
