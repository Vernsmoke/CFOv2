// Run the real grid and live-refresh functions with a small DOM/transport harness.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '../../', file), 'utf8');
const admin = read('public/admin-dashboard.js');
const student = read('resources/views/student/index.blade.php');
const searchContext = {URLSearchParams};
vm.runInNewContext(read('public/cfo-search.js'), searchContext);
function between(source, start, end) {
    const a = source.indexOf(start), b = source.indexOf(end, a);
    assert.ok(a >= 0 && b > a, start);
    return source.slice(a, b);
}
const elements = new Map();
function element(selector) {
    if (selector === '[data-filter="scheduleDate"]') selector='filter-scheduleDate';
    if (!elements.has(selector)) elements.set(selector, {
        innerHTML: '', textContent: '', value: '', disabled: false, hidden: false, isConnected: false,
        dataset: {}, attributes: {},
        addEventListener(name, callback) { this[name] = callback; },
        replaceChildren(...children) {
            for (const child of this.children || []) child.isConnected=false;
            this.children=children;this.innerHTML='';
            for (const child of children) child.isConnected=true;
            const clear=children[0]?.innerHTML.match(/<button[^>]*data-clear-search[^>]*>/);
            if (clear) element('[data-clear-search]').hidden=/\bhidden\b/.test(clear[0]);
        },
        setAttribute(name,value) { this.attributes[name]=value; },
        focus() { focusedElement=this; },
    });
    return elements.get(selector);
}
let fakeTime=0, timerSequence=0, rootSequence=0, focusedElement=null;
const timers=new Map();
function advanceTime(milliseconds) {
    const end=fakeTime+milliseconds;
    while (true) {
        const next=[...timers].filter(([,timer])=>timer.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];
        if (!next) break;
        fakeTime=next[1].at;timers.delete(next[0]);next[1].callback();
    }
    fakeTime=end;
}
const nameSort = Object.assign(element('name-sort'), {dataset:{sort:'0'},parentElement:element('name-heading'),querySelector:()=>element('name-direction')});
function sortControls(root) {
    return [...root.innerHTML.matchAll(/data-sort="([0-9]+)"/g)].map(([,index])=>index==='0'?nameSort:Object.assign(element('sort-'+index),{
        dataset:{sort:index},parentElement:element('heading-'+index),querySelector:()=>element('direction-'+index),
    }));
}
let detailRequest, detailDialog, exported;
const chosenDate = '2026-09-01';
const rows = Array.from({length:15}, (_, index) => ({
    id:index+1, year:index===12?2025:2026, is_archived:true,
    student_snapshot:{name:'Historical Student '+index,student_id:'ID-'+index,course:index%2?'BSN':'BSIT',year_level:'3',section:index%2?'B':'A'},
    user:{name:'Renamed Student',student_id:'UPDATED-ID',course:'Changed course',year_level:'9',section:'Z',email:'hidden-email@example.invalid'}, status:index%2?'Pending':'Approved',
    receipt_status:'Approved', attendance:index%2?'Absent':'Present', certificate:index%2?null:{id:index+1,certificate_number:'Hidden certificate number'},
    schedule:index===14?null:{title:'Annual recollection',date:index===12?'2025-09-01':index===13?'2026-09-02':chosenDate,venue:'Hidden schedule venue'},
    details:{medical:'Private medical answer'},receipt_path:'Hidden receipt path',
}));
const originalHistory = JSON.stringify(rows);
const context = vm.createContext({
    Map, Set, String, Number, Math, CSS:{escape:String},
    document:{createElement:()=>{
        const root=element('root-'+(++rootSequence));elements.set('root',root);return root;
    }}, window:{CfoSearch:searchContext.CfoSearch},
    setTimeout:(callback,delay)=>{const id=++timerSequence;timers.set(id,{callback,at:fakeTime+Number(delay)});return id;},
    clearTimeout:id=>timers.delete(id),
    $:element, $$:(selector,root)=>selector==='[data-filter]'
        ? [...root.innerHTML.matchAll(/data-filter="([^"]+)"/g)].map(([,key])=>Object.assign(element('filter-'+key),{dataset:{filter:key}}))
        : selector==='[data-sort]'?sortControls(root):[],
    escapeHTML:value=>String(value??''), icon:()=>'', options:()=>'',
    button:(label,action,id)=>`<button data-action="${action}" data-id="${id}">${label}</button>`,
    link:(label,route)=>`<a href="#${route}">${label}</a>`,
    metric:(label,value)=>`${label}: ${value};`,
    unique:values=>[...new Set(values)].sort(),
    objectFilter:(key,label,items)=>({key,label,values:[...new Set(items.map(row=>row[key]))],get:row=>row[key]}),
    connectedStudent:row=>row.user.name,
    connectedSchedule:row=>row.schedule?row.schedule.title+' '+row.schedule.date:'Not scheduled',
    cfoState:{current_year:2027,archive_timezone:'Asia/Manila',history:rows,registrations:[rows[0]]},
    backend:async url=>{detailRequest=url;return {registration:{...rows[0],has_receipt:true,certificate_record:{certificate_number:'CFO-HISTORY'},schedule:{...rows[0].schedule,time:'08:00',venue:'Chapel'}}};},
    showDialog:(...args)=>{detailDialog=args;},
    csvDownload:(...args)=>{exported=args;},
});
vm.runInContext(read('public/admin-history-documents.js'),context);
vm.runInContext(between(admin,'    const gridState =','    /* 11. Routes'),context);
vm.runInContext(between(admin,'    function connectedGrid(','    async function renderConnectedAdmin('),context);
context.syncAdminSearch=()=>{}; // Discovery is covered by context-search-state; this harness exercises the real table.
vm.runInContext(between(admin,'    function connectedRegistrations(','    function connectedSchedules('),context);
vm.runInContext(between(admin,'    async function connectedHistoryView(','    actions[\'connected-history-view\']'),context);
vm.runInContext("const exportGrid = ({"+between(admin,"        'export-grid':","        'logout':")+"})['export-grid'];",context);
vm.runInContext(read('resources/views/partials/history-details.blade.php').match(/<script>([\s\S]*?)<\/script>/)[1],context);

// Request history retains the original assignment, the optional legacy target and the actual new assignment.
const changeHistory={...rows[0],changes:[
    {id:101,status:'Approved',created_at:'2026-08-01',current_schedule:{title:'Original day',date:'2026-08-10'},requested_schedule:null,
        assigned_schedule:{title:'Assigned <script>day</script>',date:'2026-09-01'},reason:'Exam <img src=x>',review_reason:'Verified conflict',student_message:'The CFO assigned your new day.'},
    {id:102,status:'Rejected',student_status:'Not Approved',current_schedule:{title:'Earlier day',date:'2026-08-11'},requested_schedule:{title:'Legacy target',date:'2026-08-12'},
        assigned_schedule:null,reason:'Family event',review_reason:'Visit the office.',student_message:'Please visit the CFO office.'},
]};
let changeHtml=context.window.cfoHistoryDetails({registration:changeHistory});
assert.match(changeHtml,/Original day · 2026-08-10/);assert.match(changeHtml,/Assigned schedule/);
assert.match(changeHtml,/Assigned &lt;script&gt;day&lt;\/script&gt; · 2026-09-01/);assert.doesNotMatch(changeHtml,/<script>|<img/);
assert.match(changeHtml,/Requested schedule \(legacy\)/);assert.match(changeHtml,/Legacy target · 2026-08-12/);assert.match(changeHtml,/Not assigned/);
context.window.CFO_BACKEND={user:{role:'Student'}};
changeHtml=context.window.cfoHistoryDetails({registration:changeHistory});
assert.match(changeHtml,/Not Approved/);assert.match(changeHtml,/The CFO assigned your new day/);assert.match(changeHtml,/Please visit the CFO office/);
delete context.window.CFO_BACKEND;

(async()=>{
    const grid = () => vm.runInContext('activeGrid',context);
    const resultIds = () => Array.from(grid().filtered(),row=>row.id);
    function setDate(value) {
        const control=element('filter-scheduleDate');control.value=value;
        assert.equal(typeof control.input,'function','the native date input applies immediately');
        control.input({target:control});
    }
    function typeSearch(value) {
        const control=element('[data-search]');control.value=value;control.input({target:control});
    }
    function search(value) { typeSearch(value);advanceTime(300); }
    function clearSearch() { element('[data-clear-search]').onclick(); }
    function clearDate() { element('[data-clear-date]').onclick(); }
    function resetHistory() { clearDate();clearSearch(); }
    function summary(records,years,present,certificates) {
        const html=element('#history-summary').innerHTML;
        assert.match(html,new RegExp('Matching records: '+records+';'));
        assert.match(html,new RegExp('Recollection years: '+years+';'));
        assert.match(html,new RegExp('Present: '+present+';'));
        assert.match(html,new RegExp('Certificates issued: '+certificates+';'));
        assert.equal(element('[data-count]').textContent,'Showing '+records+' student'+(records===1?'':'s'));
    }
    context.connectedRegistrations(true);
    const historyLabels=['Name','ID number','Date of recollection','Course','Year/Section','Attendance','Action'];
    assert.deepEqual(Array.from(grid().config.columns,column=>column.label),historyLabels);
    assert.ok(!grid().config.rowActions,'History Action is its seventh column, without a duplicate Actions column');
    assert.equal(grid().config.placeholder,'Search name, ID number, course, or attendance');
    assert.deepEqual(sortControls(element('root')).map(control=>Number(control.dataset.sort)),[0,1,2,3,4,5],'every data column remains sortable but Action does not');
    assert.match(element('root').innerHTML,/<th scope="col"[^>]*>Action<\/th>/);
    assert.doesNotMatch(element('root').innerHTML,/data-sort="6"|>Actions<\/th>/);
    assert.deepEqual(resultIds(),[15,14,12,11,10,9,8,7,6,5,4,3,2,1,13],'History starts with recollection year descending, then ID descending');
    const dateColumn=grid().config.columns[2],yearSectionColumn=grid().config.columns[4];
    assert.equal(dateColumn.get(rows[0]),'Sep 1, 2026');
    assert.equal(dateColumn.get({...rows[0],schedule:{date:'2026-10-15'}}),'Oct 15, 2026');
    assert.match(dateColumn.get(rows[14]),/^(Not scheduled|—)$/,'missing schedule dates are readable');
    assert.equal(dateColumn.sort(rows[0]),chosenDate,'sorting uses the raw schedule date');
    assert.equal(yearSectionColumn.get(rows[0]),'3A','snapshot year and section are combined without a separator');
    assert.equal(yearSectionColumn.get({...rows[0],details:{yearSection:' 4C '}}),'4C','submitted year/section is preferred and trimmed');
    assert.equal(yearSectionColumn.get({...rows[0],details:{yearSection:' '}}),'3A','empty submitted data falls back to the snapshot');
    assert.equal(yearSectionColumn.get({...rows[0],student_snapshot:{year_level:'3'}}),'3','one recorded snapshot field remains visible');
    assert.equal(yearSectionColumn.get({...rows[0],student_snapshot:{section:'A'}}),'A','a section remains visible when year level is missing');
    assert.equal(yearSectionColumn.get({...rows[0],student_snapshot:{}}),'Not recorded','missing academic snapshot data is explicit');
    assert.equal(grid().config.columns[6].sort,false,'Action is explicitly excluded from heading sorting');
    assert.equal(grid().config.columns[6].get(rows[0]),'View');
    const chronological=[{id:103,schedule:{date:'2026-12-01'}},{id:101,schedule:{date:'2025-10-15'}},{id:102,schedule:{date:'2026-02-01'}}];
    assert.deepEqual(Array.from(context.window.CfoSearch.sortRows(chronological,grid().config.columns,2,'asc'),row=>row.id),[101,102,103],'dates sort chronologically across months and years');
    assert.deepEqual(Array.from(context.window.CfoSearch.sortRows(chronological,grid().config.columns,2,'desc'),row=>row.id),[103,102,101]);
    for(const control of sortControls(element('root'))){
        const index=Number(control.dataset.sort);
        control.onclick();
        assert.equal(grid().state.sortIndex,index);
        assert.equal(control.parentElement.attributes['aria-sort'],'ascending');
        assert.deepEqual(resultIds(),Array.from(context.window.CfoSearch.sortRows(grid().config.rows(),grid().config.columns,index,'asc'),row=>row.id));
        control.onclick();
        assert.equal(control.parentElement.attributes['aria-sort'],'descending');
        assert.deepEqual(resultIds(),Array.from(context.window.CfoSearch.sortRows(grid().config.rows(),grid().config.columns,index,'desc'),row=>row.id));
    }
    delete grid().state.sortIndex;delete grid().state.sortDirection;grid().draw();
    assert.ok(element('#page').innerHTML.includes('Completed batches appear here after you click Move to History. The previous year automatically appears here. Registration details, receipts, attendance records, and certificates are kept.'));
    assert.equal(grid().config.historyControls,true);
    assert.equal(grid().config.searchDebounce,300);
    assert.equal(grid().config.filters.length,1,'History has only the recollection date filter');
    const dateFilter=grid().config.filters[0];
    assert.equal(dateFilter.key,'scheduleDate');assert.equal(dateFilter.label,'Recollection date');
    assert.equal(dateFilter.type,'date');assert.equal(dateFilter.event,'input');
    assert.equal(dateFilter.get(rows[14]),'','unassigned archives have no schedule date');
    assert.equal((element('root').innerHTML.match(/data-search\b/g)||[]).length,1);
    assert.equal((element('root').innerHTML.match(/data-filter=/g)||[]).length,1);
    assert.match(element('root').innerHTML,/<input type="date" data-filter="scheduleDate"/);
    assert.doesNotMatch(element('root').innerHTML,/<select data-filter=/);
    assert.doesNotMatch(element('root').innerHTML,/data-reset\b/,'History has independent clears instead of the shared reset');
    assert.match(element('root').innerHTML,/aria-label="Search recollection history"/);
    assert.equal(element('filter-scheduleDate').attributes['aria-label'],'Filter by recollection schedule date');
    assert.equal(element('[data-clear-search]').hidden,true);
    summary(15,2,8,8);
    assert.ok(resultIds().includes(15),'unassigned archives appear with no date');
    assert.match(element('[data-page]').textContent,/1 \/ 2/);

    // A single 300 ms redraw uses the latest query, with state/pagination updated immediately.
    element('[data-next]').onclick();grid().state.selected.add('15');
    const oldBody=element('tbody').innerHTML;
    typeSearch('Historical Student 1');
    assert.equal(grid().state.q,'Historical Student 1');assert.equal(grid().state.page,1);
    assert.equal(grid().state.selected.size,0);assert.equal(element('[data-clear-search]').hidden,false);
    advanceTime(150);typeSearch('  HISTORICAL STUDENT 0  ');
    assert.equal(timers.size,1,'the previous search timer is cancelled');
    advanceTime(299);assert.equal(element('tbody').innerHTML,oldBody,'table waits for the full debounce');
    summary(15,2,8,8);
    advanceTime(1);summary(1,1,1,1);assert.deepEqual(resultIds(),[1]);
    assert.equal(timers.size,0);
    assert.match(element('[data-page]').textContent,/1 \/ 1/);
    clearSearch();summary(15,2,8,8);assert.equal(focusedElement,element('[data-search]'));

    setDate(chosenDate);summary(12,1,6,6);
    assert.deepEqual(resultIds(),Array.from({length:12},(_,index)=>12-index),'schedule dates include their exact year');
    assert.equal((element('tbody').innerHTML.match(/<tr data-row-id=/g)||[]).length,10);
    assert.doesNotMatch(element('tbody').innerHTML,/Renamed Student/);
    element('[data-next]').onclick();assert.match(element('[data-page]').textContent,/2 \/ 2/);
    search('Historical Student 0');summary(1,1,1,1);assert.deepEqual(resultIds(),[1]);
    assert.match(element('[data-page]').textContent,/1 \/ 1/,'search resets pagination');
    assert.match(element('tbody').innerHTML,/data-action="connected-history-view"/);
    await context.connectedHistoryView(1);
    assert.equal(detailRequest,'/api/history/1');
    for(const value of ['Historical Student 0','Submitted registration information','Private medical answer','Receipt status','Annual recollection','Chapel','Attendance','Present','CFO-HISTORY'])assert.ok(detailDialog[1].includes(value),'History View retains '+value);
    assert.match(detailDialog[1],/data-action="history-receipt-preview" data-id="1"/,'the existing receipt opens a preview modal');
    assert.match(detailDialog[1],/data-action="history-certificate-preview" data-id="1"/,'the existing certificate opens a preview modal');
    assert.match(detailDialog[1],/href="\/certificates\/1\?download=1"/,'the existing certificate remains downloadable');

    // The search corpus is precisely the displayed cell values, in column order.
    for (const row of rows) assert.equal(grid().config.search(row),grid().config.columns.map(column=>column.get(row)).join(' '));
    for (const column of grid().config.columns) {
        const query=String(column.get(rows[0])).toUpperCase();
        search(query);assert.ok(resultIds().includes(1),'displayed column is searchable: '+column.label);
        assert.ok(Array.from(grid().filtered()).every(row=>grid().config.columns.map(c=>c.get(row)).join(' ').toLowerCase().includes(query.trim().toLowerCase())));
    }
    for (const [query,expected] of [
        ['id-0',[1]],['sIt',[11,9,7,5,3,1]],['bsn',[12,10,8,6,4,2]],
        ['026',Array.from({length:12},(_,index)=>12-index)],['BSIT 3A',[11,9,7,5,3,1]],
    ]) {search(query);assert.deepEqual(resultIds(),expected,'case-insensitive partial visible text: '+query);}
    for (const query of ['Renamed Student','UPDATED-ID','Changed course','BSIT-3A','BSIT-9Z','Private medical answer','hidden-email@example.invalid','Hidden certificate number','Hidden schedule venue','Hidden receipt path','Annual recollection','Approved','Issued']) {
        search(query);assert.deepEqual(resultIds(),[],'hidden data and invented aliases are excluded: '+query);
        assert.equal(element('#data-grid .table-empty').textContent,'No students are scheduled on this date.');
    }
    search('Student Historical');assert.deepEqual(resultIds(),[],'History matches one literal substring rather than reordered search tokens');
    assert.equal(element('#data-grid .table-empty').textContent,'No students are scheduled on this date.');
    clearDate();assert.equal(element('#data-grid .table-empty').textContent,'No students found.');
    search('.*');assert.deepEqual(resultIds(),[],'punctuation is literal, never a regular expression');
    assert.equal(element('#data-grid .table-empty').textContent,'No students found.');

    setDate(chosenDate);search('BSIT');summary(6,1,6,6);
    clearSearch();summary(12,1,6,6);
    assert.equal(grid().state.filters.scheduleDate,chosenDate,'clear search retains date');
    assert.equal(element('filter-scheduleDate').value,chosenDate);assert.equal(element('[data-search]').value,'');
    assert.equal(element('[data-clear-search]').hidden,true);
    search('BSIT');clearDate();summary(8,2,8,8);
    assert.equal(element('[data-search]').value,'BSIT','clear date retains query');
    assert.equal(grid().state.q,'BSIT');assert.equal(element('filter-scheduleDate').value,'');
    assert.equal(focusedElement,element('filter-scheduleDate'));
    assert.deepEqual(resultIds(),[15,11,9,7,5,3,1,13]);
    setDate('2025-09-01');summary(1,1,1,1);assert.deepEqual(resultIds(),[13]);
    setDate('2026-09-02');summary(0,0,0,0);
    assert.equal(element('#data-grid .table-empty').textContent,'No students are scheduled on this date.','date-selected emptiness keeps the same message even when query caused it');
    search('BSN');summary(1,1,0,0);assert.deepEqual(resultIds(),[14]);
    setDate('2030-09-01');summary(0,0,0,0);assert.equal(element('#data-grid .table-empty').textContent,'No students are scheduled on this date.');
    resetHistory();summary(15,2,8,8);

    // Date changes and both clear controls flush/cancel pending search draws immediately.
    typeSearch('BSIT');assert.equal(timers.size,1);setDate(chosenDate);summary(6,1,6,6);assert.equal(timers.size,0);
    typeSearch('BSN');clearSearch();summary(12,1,6,6);assert.equal(timers.size,0);
    typeSearch('BSIT');clearDate();summary(8,2,8,8);assert.equal(timers.size,0);
    setDate(chosenDate);
    const retainedState=grid().state;
    context.cfoState={...context.cfoState,history:rows.map(row=>({...row}))};
    context.connectedRegistrations(true);
    assert.equal(grid().state,retainedState);assert.equal(grid().state.filters.scheduleDate,chosenDate);assert.equal(grid().state.q,'BSIT');
    assert.match(element('root').innerHTML,/<input type="date" data-filter="scheduleDate"[^>]*value="2026-09-01"/);
    assert.match(element('root').innerHTML,/<input[^>]*type="search"[^>]*data-search[^>]*value="BSIT"/);
    assert.equal(element('[data-clear-search]').hidden,false);summary(6,1,6,6);
    typeSearch('Historical Student 0');
    const newMatch={...rows[0],id:9001,student_snapshot:{...rows[0].student_snapshot,name:'Historical Student 0 new archive'}};
    context.cfoState={...context.cfoState,history:[...rows,newMatch]};
    context.connectedRegistrations(true);
    summary(2,1,2,2);const refreshedBody=element('tbody').innerHTML;
    advanceTime(300);assert.equal(element('tbody').innerHTML,refreshedBody,'a stale search cannot replace fresh live matches with old archive rows');
    summary(2,1,2,2);
    context.cfoState={...context.cfoState,history:rows};context.connectedRegistrations(true);

    resetHistory();element('[data-next]').onclick();nameSort.onclick();
    assert.match(element('[data-page]').textContent,/1 \/ 2/,'sorting resets pagination');
    assert.equal(nameSort.parentElement.attributes['aria-sort'],'ascending');assert.equal(grid().filtered()[0].student_snapshot.name,'Historical Student 0');
    nameSort.onclick();assert.equal(nameSort.parentElement.attributes['aria-sort'],'descending');
    assert.equal(grid().filtered()[0].student_snapshot.name,'Historical Student 14');
    setDate(chosenDate);assert.equal(grid().filtered()[0].student_snapshot.name,'Historical Student 11');
    vm.runInContext('exportGrid()',context);
    assert.equal(exported[0],'connected-history.csv');assert.equal(exported[2].length,12,'CSV uses every filtered page');
    assert.deepEqual(Array.from(exported[1]),historyLabels,'CSV columns exactly match all seven table columns');
    assert.ok(exported[2].every(row=>row.length===7&&row[2]==='Sep 1, 2026'&&row[6]==='View'),'CSV contains readable schedule dates and the Action value');
    assert.deepEqual(Array.from(exported[2],row=>row[0]),Array.from({length:12},(_,index)=>'Historical Student '+(11-index)));
    search('BSIT');vm.runInContext('exportGrid()',context);
    assert.equal(exported[2].length,6);assert.deepEqual(Array.from(exported[2],row=>row[0]),[10,8,6,4,2,0].map(index=>'Historical Student '+index));
    resetHistory();search(dateColumn.get(rows[14]));vm.runInContext('exportGrid()',context);
    assert.equal(exported[2].length,1);assert.match(exported[2][0][2],/^(Not scheduled|—)$/);
    assert.equal(exported[2][0][4],'3A');assert.equal(exported[2][0][6],'View');
    assert.equal(JSON.stringify(rows),originalHistory,'History filtering/viewing/exporting never mutates archives');

    typeSearch('Historical Student 0');context.connectedRegistrations(false);
    const currentBody=element('tbody').innerHTML,currentCount=element('[data-count]').textContent;
    advanceTime(300);assert.equal(element('tbody').innerHTML,currentBody,'stale History search cannot redraw another module');
    assert.equal(element('[data-count]').textContent,currentCount);assert.match(currentCount,/0 records/);
    assert.match(element('#page-actions').innerHTML,/#admin-history/);assert.equal(grid().config.filters.length,0);
    assert.deepEqual(Array.from(grid().config.columns,column=>column.label),['Name','ID number','Recollection year','Schedule','Registration','Receipt','Attendance','Certificate'],'current Recollection Registrations columns are unchanged');
    assert.equal(typeof grid().config.rowActions,'function');
    assert.match(element('root').innerHTML,/>Actions<\/th>/,'the current table retains its existing Actions label');
    assert.match(grid().config.rowActions(rows[0]),/data-action="connected-registration"/);
    assert.equal(grid().config.historyControls,undefined);assert.equal(grid().config.searchDebounce,undefined);
    assert.match(element('root').innerHTML,/data-reset\b/,'other modules retain Clear filters');
    assert.doesNotMatch(element('root').innerHTML,/data-clear-search|data-clear-date/);
    context.dataGrid({key:'default-grid',title:'Original grid',rows:()=>[{id:101,value:'Student ID-10 YEAR-2026'}],filters:[],search:row=>row.value,columns:[{label:'Text',get:row=>row.value}]});
    typeSearch('1');assert.deepEqual(resultIds(),[],'other grids keep whole-number token matching');assert.equal(timers.size,0,'other grids remain immediate');
    element('[data-reset]').onclick();assert.deepEqual(resultIds(),[101]);assert.equal(grid().state.q,'');

    // History uses the one search input already present in its table.
    const discoveryHost=element('#cfo-context-search');
    discoveryHost.innerHTML='<input type="search">';discoveryHost.hidden=false;discoveryHost._cfoSearch={key:'previous-route'};
    const discovery=vm.createContext({
        $:element,window:{CfoSearch:searchContext.CfoSearch},
        routeDetails:()=>({route:'admin-history',params:new URLSearchParams()}),
    });
    vm.runInContext(between(admin,'    function syncAdminSearch()','    async function renderConnectedAdmin('),discovery);
    discovery.syncAdminSearch();
    assert.equal(discoveryHost.hidden,true,'the duplicate contextual search is hidden on History');
    assert.equal(discoveryHost.innerHTML,'');
    assert.equal(discoveryHost._cfoSearch,null,'returning from another page discards its contextual search UI');

    // Year change before the very first poll, including a dirty form deferral.
    let editing=true, nextYear=2027, renders=0;
    const requests=[];
    const live=vm.createContext({
        Number, Object, Set, document:{hidden:false},window:{CFO_BACKEND:{user:{}}},location:{hash:'#dashboard'},
        cfoStudent:{current_year:2026,registrations:[{id:1,year:2026}]},
        studentLiveVersions:null,studentRefreshBusy:false,pendingStudentResources:new Set(),
        isStudentEditing:()=>editing,updateStudentNotificationBell(){},
        route:async()=>{renders++;},useLiveStudentState:false,renderedRoute:null,studentRouteLoading:false,routeVersion:0,
        cfoRequest:async url=>{
            requests.push(url);
            return url==='/api/live/versions'?{year:nextYear,registrations:1}
                :{current_year:nextYear,registrations:[{id:1,year:2026}]};
        },
    });
    vm.runInContext(between(student,'async function pollStudentVersions(){',"document.addEventListener('focusout'"),live);
    await live.pollStudentVersions();
    assert.ok(live.pendingStudentResources.has('year'));
    assert.equal(renders,0,'an open form must retain its draft');
    editing=false;await live.refreshStudentWhenSafe();
    assert.ok(requests.includes('/api/student?resources=year'));
    assert.equal(live.cfoStudent.current_year,2027);
    assert.equal(live.cfoStudent.registrations.filter(row=>row.year===live.cfoStudent.current_year).length,0);
    assert.equal(renders,1);
    nextYear=2028;await live.pollStudentVersions();
    assert.equal(live.cfoStudent.current_year,2028);
    assert.equal(renders,2);
    console.log('PASS: History visible-column substring search with 300ms debounce, cancellation/stale guards, exact date AND filtering, accessible independent clears, counts/empty states, snapshots/live state, pagination/sort/CSV/details, immutable archives, unaffected other grids and year rollover.');
})().catch(error=>{console.error(error);process.exitCode=1;});
