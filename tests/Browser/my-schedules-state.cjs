// Run production helpers against isolated fixtures without writing any schedules.
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const moduleShim = {exports:{}};
vm.runInThisContext('(function(module){' + fs.readFileSync(path.join(__dirname,'../../public/my-schedules.js'),'utf8') + '\n})')(moduleShim);
const ms = moduleShim.exports;
const ids = rows => rows.map(row => row.id);
const base = {id:1,title:'Formation Day',date:'2026-10-15',start_time:'08:00',end_time:'17:00',venue:'Chapel',
    courses:[{id:11,name:'BSIT'}],year_level:'3',section:'A',active:true,status:'Open',registrations_count:12,slot_limit:25,theme:'Hidden legacy theme',facilitator:'Hidden legacy facilitator',description:'Hidden legacy description'};
const rows = [base,
    {...base,id:2,title:'Formation draft',date:'2026-10-16',status:'Draft',active:false,courses:[{id:12,name:'BSA'}],year_level:'1'},
    {...base,id:3,title:'Chapel Formation',date:'2026-10-14',status:'Closed',active:false,section:'AB',registrations_count:2},
    {...base,id:4,title:'Past Formation',date:'2026-09-27',status:'Open',finalized_at:'2026-09-27 18:00:00',registrations_count:40},
    {...base,id:5,title:'Cancelled retreat',date:'2026-10-18',status:'Cancelled',active:false,year_level:'13'},
    {...base,id:6,title:'Faith retreat',date:'2026-10-15',courses:[{id:11,name:'BSIT'},{id:12,name:'BSA'}],
        year_levels:['1','3'],sections:['A','Z'],slot_limit:null,registrations_count:100},
];
assert.deepEqual(rows.map(ms.statusOf), ['Open','Draft','Closed','Completed','Cancelled','Open']);
assert.equal(ms.statusOf({...base,status:undefined,active:false}), 'Closed');
assert.equal(ms.yearSection(base), '3A');
assert.equal(ms.yearSection(rows[5]), '1A, 1Z, 3A, 3Z', 'Preserve every legacy multi-group combination.');
assert.equal(ms.yearSection({...base,year_level:null,section:null}), '—');
assert.deepEqual(ms.selections({...base,year_levels:[]}, 'year_levels', 'year_level'), [], 'Empty arrays cannot inherit scalar eligibility.');
assert.equal(ms.timeRange({...base,start_time:'08:00:00',end_time:'17:00:00'}), '08:00 – 17:00');
assert.equal(ms.calendarTime(base), '8:00 AM–5:00 PM');
assert.equal(ms.calendarTime({...base,start_time:'00:00',end_time:'12:00'}), '12:00 AM–12:00 PM');

assert.deepEqual(ids(ms.filterSchedules(rows,{q:'formation',status:'Open',course:'11',year_level:'3',section:'A'})), [1]);
assert.deepEqual(ids(ms.filterSchedules(rows,{q:'faith',status:'Open',course:'BSA',year_level:'3',section:'Z'})), [6]);
assert.deepEqual(ids(ms.filterSchedules(rows,{year_level:'3'})), [1,3,4,6], 'Year 3 must not match year 13.');
assert.deepEqual(ids(ms.filterSchedules(rows,{section:'A'})), [1,2,4,5,6], 'Section A must not match section AB.');
assert.deepEqual(ids(ms.filterSchedules(rows,{status:'Completed'})), [4]);
assert.deepEqual(ids(ms.filterSchedules(rows,{q:'2026-10-16'})), [2]);
assert.equal(ms.filterSchedules(rows,{q:'08:00'}).length, rows.length);
assert.equal(ms.filterSchedules(rows,{q:'5:00 PM'}).length, rows.length);
assert.deepEqual(ids(ms.filterSchedules(rows,{q:'Oct 15, 2026'})),[1,6],'Search includes the readable date shown in the table.');
assert.deepEqual(ids(ms.filterSchedules(rows,{q:'Thursday'})),[1,6],'Search includes the displayed weekday.');
assert.equal(ms.filterSchedules(rows,{q:'08:00 AM'}).length,rows.length,'Search includes the padded displayed 12-hour time.');
assert.equal(ms.filterSchedules(rows,{q:'Chapel'}).length, rows.length);
assert.deepEqual(ids(ms.filterSchedules(rows,{q:'3Z'})), [6]);
assert.deepEqual(ids(ms.filterSchedules(rows,{q:'Cancelled'})), [5]);
assert.deepEqual(ids(ms.filterSchedules(rows,{course:'BS'})), [], 'Course filter uses a whole name or id.');
assert.deepEqual(ids(ms.filterSchedules(rows,{status:'All',course:'All',year_level:'All',section:'All'})), ids(rows));
assert.deepEqual(ids(ms.sortSchedules(rows)), [4,3,1,6,2,5], 'An explicit date sort remains chronological across every status.');
assert.deepEqual(ids(ms.sortSchedules(rows,'date','desc')), [5,2,1,6,3,4]);
assert.deepEqual(ids(ms.sortSchedules(rows,'registration','asc')), [3,1,2,5,4,6]);
assert.deepEqual(ids(ms.sortSchedules(rows,'registration','desc')), [6,4,1,2,5,3]);
assert.deepEqual(ids(ms.sortSchedules(rows,'title','asc')), [5,3,6,1,2,4]);
assert.deepEqual(ids(ms.sortSchedules(rows,'title','desc')), [4,2,1,6,3,5]);
for (const [key, earlier, later] of [
    ['courses',{courses:[{name:'BSA'}]},{courses:[{name:'BSIT'}]}],
    ['year_section',{year_level:'1',section:'A'},{year_level:'4',section:'Z'}],
    ['time',{start_time:'07:00',end_time:'08:00'},{start_time:'18:00',end_time:'19:00'}],
    ['venue',{venue:'Chapel'},{venue:'Gymnasium'}], ['status',{status:'Closed'},{status:'Open'}],
]) {
    const pair=[{...base,id:92,...later},{...base,id:91,...earlier}];
    assert.deepEqual(ids(ms.sortSchedules(pair,key,'asc')),[91,92],`${key} ascending`);
    assert.deepEqual(ids(ms.sortSchedules(pair,key,'desc')),[92,91],`${key} descending`);
}
for (const key of ['title','courses','year_section','time','venue','status']) {
    const actual = ms.sortSchedules(rows,key);
    assert.equal(actual.length, rows.length);
    assert.deepEqual([...ids(actual)].sort((a,b)=>a-b), [1,2,3,4,5,6]);
}
assert.deepEqual(ids(rows), [1,2,3,4,5,6], 'Sorting must not mutate the live state.');

// The presentation order differs from CSV, which intentionally keeps its original columns.
assert.deepEqual(ms.TABLE_COLUMNS, [
    ['Date & Time','date_time'], ['Course(s)','courses'],
    ['Venue','venue'], ['Status','status'], ['Registration','registration'],
]);
function renderedCourseGroups(html) {
    return [...html.matchAll(/<li\b[^>]*class="ms-course-group"[^>]*>([\s\S]*?)<\/li>/g)].map(([, group]) => ({
        label: group.match(/<strong\b[^>]*class="ms-course-group-name"[^>]*>([\s\S]*?)<\/strong>/)?.[1],
        chips: [...group.matchAll(/<span\b[^>]*class="ms-section-chip"[^>]*>([\s\S]*?)<\/span>/g)].map(([, label]) => label),
    }));
}
// The organized table formats calendar dates locally, without changing the export/calendar formats.
assert.deepEqual(ms.tableDate({...base,date:'2026-10-24'}),{date:'Oct 24, 2026',weekday:'Saturday',datetime:'2026-10-24'});
assert.deepEqual(ms.tableDate({...base,date:'2026-10-24T00:00:00+08:00'}),{date:'Oct 24, 2026',weekday:'Saturday',datetime:'2026-10-24'},
    'The table keeps the saved calendar day rather than converting its timezone.');
assert.deepEqual(ms.tableDate({...base,date:'2028-02-29'}),{date:'Feb 29, 2028',weekday:'Tuesday',datetime:'2028-02-29'});
for(const date of [null,'','2026-02-30','not-a-date'])
    assert.deepEqual(ms.tableDate({...base,date}),{date:'—',weekday:'',datetime:''},'Bad old dates have a readable fallback.');
assert.equal(ms.tableTimeRange({...base,start_time:'08:00:00',end_time:'16:00:00'}),'08:00 AM – 04:00 PM');
assert.equal(ms.tableTimeRange({...base,start_time:'00:05',end_time:'12:30'}),'12:05 AM – 12:30 PM');
assert.equal(ms.tableTimeRange({...base,start_time:null,end_time:'17:00'}),'— – 05:00 PM');
assert.equal(ms.tableTimeRange({...base,start_time:'25:00',end_time:'08:75'}),'— – —');
const chronological=[{...base,id:91,date:'2026-10-25',start_time:'00:01'},
    {...base,id:92,date:'2026-10-24',start_time:'17:00'}, {...base,id:93,date:'2026-10-24',start_time:'08:00'},
    {...base,id:94,date:'2026-10-24',start_time:'12:00'}, {...base,id:95,date:'2026-10-24',start_time:'00:00'}];
assert.deepEqual(ids(ms.sortSchedules(chronological,'date_time','asc')),[95,93,94,92,91],
    'Combined Date & Time sorts by saved dates and 24-hour time, rather than its formatted label.');
assert.deepEqual(ids(ms.sortSchedules(chronological,'date_time','desc')),[91,92,94,93,95]);
for(const [status,description] of [['Open','Registration ongoing'],['Draft','Not yet open'],['Closed','Registration ended'],
    ['Completed','Registration closed'],['Cancelled','Schedule cancelled']])
    assert.deepEqual(ms.tableStatus({...base,status,finalized_at:status==='Completed'?'2026-10-24 17:00:00':null}),{status,description},
        'Status text describes real system states without inventing a mockup-only Upcoming status.');
assert.equal(ms.registrationProgress({...base,finalized_at:'2026-10-24 17:00:00',registrations_count:0,registrations_total_count:19}).count,19,
    'Completed schedules keep their existing archived total rather than showing zero active registrations.');
for(const [count,limit,percentage,width] of [[0,100,0,0],[9,10,90,90],[10,10,100,100],[11,10,110,100],[90,92,98,98]]) {
    assert.deepEqual(ms.registrationProgress({...base,registrations_count:count,slot_limit:limit}),
        {count,limit,percentage,width,hasLimit:true});
    const html=ms.registrationHtml({...base,registrations_count:count,slot_limit:limit});
    assert.ok(html.includes(`${count} / ${limit}`)); assert.ok(html.includes(`${percentage}%`));
    assert.ok(html.includes('role="progressbar"'));
    assert.ok(html.includes('aria-valuemin="0"')); assert.ok(html.includes(`aria-valuemax="${limit}"`));
    assert.ok(html.includes(`aria-valuenow="${Math.min(count,limit)}"`),'Progressbar values stay within their accessible range.');
    assert.ok(html.includes(`width:${width}%`),'Progress remains inside its track even when old data exceeds capacity.');
    assert.ok(!html.includes('NaN')&&!html.includes('Infinity'));
}
for(const limit of [null,0,-1,'not-a-number']) {
    const progress=ms.registrationProgress({...base,registrations_count:12,slot_limit:limit});
    assert.equal(progress.count,12); assert.equal(progress.limit,null); assert.equal(progress.hasLimit,false);
    assert.equal(progress.percentage,null);
    const html=ms.registrationHtml({...base,registrations_count:12,slot_limit:limit});
    assert.ok(html.includes('12 registered')); assert.ok(html.includes('No limit'));
    assert.ok(!html.includes('role="progressbar"')&&!html.includes('NaN')&&!html.includes('Infinity'));
}
assert.equal(ms.ATTENTION_WINDOW_DAYS,7);
assert.equal(ms.ALMOST_FULL_RATIO,.9);
assert.equal(ms.MAX_ATTENTION_BADGES,2);
const today='2026-10-09';
const attentionSchedule={...base,date:today,registrations_count:0,slot_limit:null,
    current_change_requests_count:0,requested_change_requests_count:0};
const indicators=changes=>ms.attentionIndicators({...attentionSchedule,...changes},today);
const attentionLabels=changes=>indicators(changes).map(badge=>badge.label);
const attentionKinds=changes=>indicators(changes).map(badge=>badge.kind);
assert.deepEqual(attentionLabels({status:'Draft'}),['Not published']);
assert.deepEqual(attentionKinds({status:'Draft'}),['draft']);
assert.deepEqual(attentionLabels({slot_limit:10,registrations_count:10}),['Full']);
assert.deepEqual(attentionLabels({slot_limit:10,registrations_count:11}),['Full'],'Full includes counts above a legacy capacity.');
assert.deepEqual(attentionLabels({slot_limit:10,registrations_count:9}),['Almost full']);
assert.deepEqual(attentionLabels({slot_limit:10,registrations_count:8}),[]);
assert.deepEqual(attentionLabels({slot_limit:25,registrations_count:22}),[],'Do not round 88% up to the threshold.');
assert.deepEqual(attentionLabels({slot_limit:25,registrations_count:23}),['Almost full']);
assert.deepEqual(attentionLabels({slot_limit:null,registrations_count:100}),[],'Unlimited schedules cannot be almost full or full.');
assert.deepEqual(attentionLabels({slot_limit:0,registrations_count:12}),[],'Invalid old zero capacities cannot trigger capacity warnings.');
assert.deepEqual(attentionLabels({slot_limit:10,registrations_count:8,registrations_total_count:25}),[],
    'Occupancy badges use active registrations rather than the edit-protection total.');
for(const date of ['2026-10-09','2026-10-16','2026-10-16T23:59:59+08:00'])
    assert.deepEqual(attentionLabels({date}),['No registrations yet'],`Zero registrations warning includes calendar day ${date}.`);
for(const date of ['2026-10-08','2026-10-17','',null,'2026-02-30'])
    assert.deepEqual(attentionLabels({date}),[],`No near-date warning for ${date}.`);
assert.deepEqual(attentionLabels({date:'2026-10-16',registrations_count:1}),[]);
assert.deepEqual(attentionLabels({date:'2026-10-16',status:'Closed'}),[],'Only Open schedules receive occupancy warnings.');
assert.deepEqual(attentionLabels({current_pending_change_requests_count:2,requested_pending_change_requests_count:3}),
    ['5 change requests pending','No registrations yet']);
assert.deepEqual(attentionLabels({current_pending_change_requests_count:1,requested_pending_change_requests_count:0,date:'2026-10-30'}),
    ['1 change request pending']);
assert.deepEqual(attentionLabels({current_pending_change_requests_count:0,requested_pending_change_requests_count:0,
    current_change_requests_count:7,requested_change_requests_count:4,date:'2026-10-30'}),[],
    'Historical resolved requests do not produce pending badges when dedicated pending counts exist.');
assert.deepEqual(attentionLabels({current_change_requests_count:1,requested_change_requests_count:2,date:'2026-10-30'}),
    ['3 change requests pending'],'Existing count fields remain supported for older payloads.');
assert.deepEqual(attentionKinds({slot_limit:10,registrations_count:10,current_pending_change_requests_count:2}),
    ['full','pending'],'The most urgent warnings come first.');
assert.deepEqual(attentionKinds({slot_limit:10,registrations_count:9,current_pending_change_requests_count:2}),
    ['pending','almost-full']);
assert.deepEqual(attentionKinds({status:'Draft',current_pending_change_requests_count:2}),['pending','draft']);
for(const changes of [{status:'Cancelled'},{finalized_at:'2026-10-09 17:00:00'}])
    assert.deepEqual(indicators({...changes,slot_limit:10,registrations_count:10,current_pending_change_requests_count:3}),[],
        'Completed and Cancelled schedules do not receive attention indicators.');
for(const badge of indicators({status:'Draft',current_pending_change_requests_count:1}))
    assert.ok(badge.icon&&badge.label,'Every badge has an icon and explanatory text.');
let attention=ms.attentionPresentation({...attentionSchedule,slot_limit:10,registrations_count:9,
    current_pending_change_requests_count:2},today);
assert.deepEqual(attention.visible.map(badge=>badge.kind),['pending','almost-full']);
assert.ok(attention.visible.length<=2,'At most two indicators are displayed on a row.');
assert.equal(attention.hidden.length,0); assert.equal(attention.hiddenTitle,'');

const defaultRows=[
    {...base,id:21,date:'2026-10-10',status:'Open'},
    {...base,id:22,date:'2026-10-11',status:'Draft'},
    {...base,id:23,date:'2026-10-09',status:'Closed'},
    {...base,id:24,date:'2026-10-08',status:'Open'},
    {...base,id:25,date:'2026-10-01',status:'Draft'},
    {...base,id:26,date:'2026-09-10',finalized_at:'2026-09-10 17:00:00'},
    {...base,id:27,date:'2026-11-01',status:'Cancelled'},
];
assert.deepEqual(ids(ms.defaultOrderSchedules(defaultRows,today)),[23,21,22,25,24,26,27],
    'Upcoming active schedules lead, then past active schedules, then terminal schedules.');
assert.deepEqual(ids(defaultRows),[21,22,23,24,25,26,27],'Default ordering does not mutate live state.');
assert.deepEqual(ids(ms.sortSchedules(defaultRows,'date','asc')),[26,25,24,23,21,22,27],
    'A selected column sort replaces default status buckets.');
assert.deepEqual(ids(ms.defaultOrderSchedules(ms.filterSchedules(defaultRows,{status:'Draft'}),today)),[22,25],
    'Default ordering still works after combined filtering.');

let restrictions=ms.editRestrictions({...base,registrations_count:3,registrations_total_count:19});
assert.equal(restrictions.assignedCount,19,'Use the same all-registrations count as the backend capacity guard.');
assert.equal(restrictions.minCapacity,19);
assert.equal(restrictions.attendanceRecorded,false);
assert.equal(restrictions.readOnlyReason,'');
restrictions=ms.editRestrictions({...base,registrations_count:7});
assert.equal(restrictions.assignedCount,7,'Old payloads fall back to the available active count.');
assert.equal(restrictions.minCapacity,7);
assert.equal(ms.editRestrictions({...base,registrations_count:0,registrations_total_count:0}).minCapacity,1);
assert.equal(ms.editRestrictions({...base,registrations_recorded_attendance_count:1}).attendanceRecorded,true,
    'Recorded attendance locks date, start/end time and venue.');
assert.equal(ms.editRestrictions({...base,registrations_recorded_attendance_count:0,registrations_count:20}).attendanceRecorded,false,
    'Assignments alone do not lock date, time and venue.');
assert.equal(ms.editRestrictions({...base,registrations_recorded_attendance_count:'3'}).attendanceRecorded,true);
assert.equal(ms.editRestrictions({...base,status:'Cancelled'}).readOnlyReason,'Cancelled schedules are view only.');
assert.equal(ms.editRestrictions({...base,finalized_at:'2026-10-09 17:00:00'}).readOnlyReason,'Completed schedules are view only.');

const many = Array.from({length:63}, (_,index)=>({...base,id:index+1,title:`Schedule ${index+1}`}));
let page = ms.paginateSchedules(many,2,25);
assert.deepEqual({page:page.page,pages:page.totalPages,total:page.total,from:page.from,to:page.to},
    {page:2,pages:3,total:63,from:26,to:50});
assert.deepEqual(ids(page.rows), Array.from({length:25},(_,index)=>index+26));
page = ms.paginateSchedules(many,999,10);
assert.equal(page.page,7); assert.equal(page.from,61); assert.equal(page.to,63);
page = ms.paginateSchedules(many,-1,50);
assert.equal(page.page,1); assert.equal(page.rows.length,50); assert.equal(page.totalPages,2);
assert.deepEqual(ms.paginateSchedules([],3,25), {rows:[],page:1,totalPages:1,total:0,from:0,to:0});

// Independently parse exported CSV, including multiline fields and doubled quotes.
function readCsv(csv) {
    const result=[], record=[]; let value='', quoted=false;
    for(let i=csv.charCodeAt(0)===0xFEFF?1:0;i<csv.length;i++) {
        const char=csv[i];
        if(char==='"') { if(quoted&&csv[i+1]==='"'){value+='"';i++;} else quoted=!quoted; }
        else if(char===','&&!quoted){record.push(value);value='';}
        else if(char==='\n'&&!quoted){record.push(value.replace(/\r$/,''));result.push([...record]);record.length=0;value='';}
        else value+=char;
    }
    assert.equal(quoted,false,'CSV quotes must balance.'); return result;
}
const csv=ms.buildCsv(ms.filterSchedules(many,{status:'Open',course:'BSIT',year_level:'3',section:'A'}));
assert.equal(csv.charCodeAt(0),0xFEFF,'Include the Excel UTF-8 BOM.');
assert.ok(csv.includes('\r\n')); assert.ok(!csv.includes('\\n'),'Use real line breaks.');
let exported=readCsv(csv);
assert.equal(exported.length,64,'Export every matching record beyond the current page.');
assert.deepEqual(exported[0],['Recollection Day','Course(s)','Year/Section','Date','Time','Venue','Registration','Capacity','Status']);
assert.deepEqual(exported[1],['Schedule 1','BSIT','3A','2026-10-15','08:00 – 17:00','Chapel','12','25','Open']);
exported=readCsv(ms.buildCsv([{...rows[5],title:'Faith, "Hope"\nRetreat',venue:'CFO, Chapel'},{...base,title:'=2+2'}]));
assert.equal(exported[1][0],'Faith, "Hope"\nRetreat'); assert.equal(exported[1][1],'BSIT / BSA');
assert.equal(exported[1][2],'1A, 1Z, 3A, 3Z'); assert.equal(exported[1][5],'CFO, Chapel');
assert.equal(exported[1][6],'100'); assert.equal(exported[1][7],'No limit');
assert.equal(exported[2][0],"'=2+2",'User titles must not execute Excel formulas.');

const sameDate=[...rows,{...base,id:7,title:'Early morning',start_time:'07:00',end_time:'08:00'},
    {...base,id:8,title:'Late day',start_time:'18:00',end_time:'19:00'}];
let calendar=ms.calendarMonth(sameDate,2026,9);
assert.equal(calendar.title,'October 2026');
assert.deepEqual(calendar.weekdays,['MON','TUE','WED','THU','FRI','SAT','SUN']);
assert.equal(calendar.cells.length%7,0); assert.equal(calendar.cells[0].date,'2026-09-28');
const day=calendar.cells.find(day=>day.date==='2026-10-15');
assert.equal(day.outside,false); assert.equal(day.day,15);
assert.deepEqual(ids(day.events),[7,1,6,8]); assert.deepEqual(ids(day.visibleEvents),[7,1]); assert.equal(day.more,2);
calendar=ms.calendarMonth(ms.filterSchedules(sameDate,{status:'Draft',course:'BSA'}),2026,9);
assert.deepEqual(calendar.cells.flatMap(day=>ids(day.events)),[2],'Calendar receives the table matching records.');
calendar=ms.calendarMonth(rows,2026,8);
assert.equal(calendar.title,'September 2026'); assert.equal(calendar.cells.find(day=>day.date==='2026-09-27').events[0].id,4);
assert.equal(ms.calendarMonth(rows,2026,12).title,'January 2027','Month navigation crosses the year boundary.');

// Per-course groups must never expand into the global section union for eligibility or filtering.
const grouped={...base,id:70,courses:[{id:11,name:'BSIT',pivot:{sections:['A','B']}},{id:14,name:'BSN',pivot:{sections:['A','B','C']}}],
    year_levels:['3'],sections:['A','B','C'],course_sections:{11:['A','B'],14:['A','B','C']}};
assert.deepEqual(ms.courseSections(grouped),{'11':['A','B'],'14':['A','B','C']});
assert.equal(ms.hasCourseSections(grouped),true);
assert.equal(ms.hasCourseSections({...base,course_sections:{},courses:[{id:11,name:'BSIT',pivot:{sections:null}}]}),false,
    'Old null pivot values do not turn a legacy schedule into a per-course schedule.');
assert.deepEqual(ms.courseSections({...grouped,course_sections:undefined}),{'11':['A','B'],'14':['A','B','C']},
    'The additive pivot payload remains compatible if the explicit map is omitted.');
assert.deepEqual(ms.courseSections({...grouped,course_sections:{11:['B']}}),{'11':['B'],'14':['A','B','C']},
    'An explicit map takes precedence over the same course pivot.');
assert.deepEqual(ms.sectionsForCourse(grouped,11),['A','B']);
assert.deepEqual(ms.sectionsForCourse(grouped,14),['A','B','C']);
assert.deepEqual(ms.sectionsForCourse(base,11),['A'],'Legacy schedules keep their old section eligibility.');
assert.deepEqual(ms.yearSections(grouped),['3A','3B','3C']);
assert.equal(ms.yearSection(grouped),'BSIT 3A, 3B · BSN 3A, 3B, 3C');
assert.deepEqual(ms.tableGroupLines(grouped),['BSIT 3A, 3B','BSN 3A, 3B, 3C'],
    'The table gives each real course its own readable line.');
assert.deepEqual(ms.tableGroupLines(base),['3A'],'Legacy unmapped schedules retain their familiar Year/Section value.');
assert.deepEqual(ms.tableCourseGroups(base),[{courseName:'BSIT',year:'3',sections:['A']}],
    'A legacy scalar schedule still presents its course, year and section together.');
assert.deepEqual(ms.tableCourseGroups(grouped),[
    {courseName:'BSIT',year:'3',sections:['A','B']},{courseName:'BSN',year:'3',sections:['A','B','C']},
], 'Flat per-course maps retain only their own selected sections.');
assert.deepEqual(ms.tableCourseGroups(rows[5]),[
    {courseName:'BSIT',year:'1',sections:['A','Z']},{courseName:'BSIT',year:'3',sections:['A','Z']},
    {courseName:'BSA',year:'1',sections:['A','Z']},{courseName:'BSA',year:'3',sections:['A','Z']},
], 'Unmapped legacy multi-selections keep all their saved course/year combinations.');
const groupedHtml=ms.courseGroupsHtml(grouped);
assert.match(groupedHtml,/<ul\b[^>]*class="ms-course-groups"/);
assert.match(groupedHtml,/<div\b[^>]*class="ms-section-chips"/);
assert.deepEqual(renderedCourseGroups(groupedHtml),[
    {label:'BSIT \u00b7 3rd Year',chips:['3A','3B']},{label:'BSN \u00b7 3rd Year',chips:['3A','3B','3C']},
], 'Course headings own their section chips instead of sharing an unrelated column.');
const unsafeCourseHtml=ms.courseGroupsHtml({...base,courses:[{id:11,name:'<img src=x>'}],section:'<b>'});
assert.ok(unsafeCourseHtml.includes('&lt;img src=x&gt;')&&unsafeCourseHtml.includes('3&lt;b&gt;'));
assert.ok(!unsafeCourseHtml.includes('<img')&&!unsafeCourseHtml.includes('<b>'),
    'Stored course names and section values cannot add elements to the grouped table.');
assert.equal(ms.yearSection({...rows[5],course_sections:{}}),'1A, 1Z, 3A, 3Z','An empty map preserves old presentation.');
assert.deepEqual(ids(ms.filterSchedules([grouped],{course:'BSIT',year_level:'3',section:'C'})),[],
    'BSN section C must not leak into the BSIT filter.');
assert.deepEqual(ids(ms.filterSchedules([grouped],{course:'11',section:'C'})),[]);
assert.deepEqual(ids(ms.filterSchedules([grouped],{course:'BSN',year_level:'3',section:'C',q:'Formation'})),[70]);
assert.deepEqual(ids(ms.filterSchedules([grouped],{course:'14',section:'C'})),[70]);
assert.deepEqual(ids(ms.filterSchedules([grouped],{section:'C'})),[70],'Section-only filters can match any real selected course.');
assert.deepEqual(ids(ms.filterSchedules([grouped],{q:'BSN 3C'})),[70],'Search includes each real course/year/section combination.');
assert.deepEqual(ids(ms.filterSchedules([grouped],{q:'BSIT 3C'})),[], 'Search does not invent a course section combination.');
assert.deepEqual(ids(ms.filterSchedules([grouped],{course:'BSN',year_level:'13',section:'C'})),[]);
assert.equal(readCsv(ms.buildCsv([grouped]))[1][2],'BSIT 3A, 3B · BSN 3A, 3B, 3C', 'CSV preserves the per-course grouping.');
assert.deepEqual(ms.calendarMonth(ms.filterSchedules([grouped],{course:'BSN',section:'C'}),2026,9).cells
    .flatMap(day=>ids(day.events)),[70], 'The calendar uses the same paired course/section filters.');


// Independent year blocks preserve exact course/year/section groups, without a cross product.
const blocks={...grouped,id:90,year_levels:['3','4'],sections:['A','B','C','Z'],
    course_sections:{11:['A','B','Z'],14:['C']},
    course_sections_by_year:{11:{3:['A','B'],4:['Z']},14:{4:['C']}},
    courses:[{id:11,name:'BSIT',pivot:{sections:{3:['A','B'],4:['Z']}}},{id:14,name:'BSN',pivot:{sections:{4:['C']}}}]};
assert.deepEqual(ms.courseSectionsByYear(blocks),{'11':{'3':['A','B'],'4':['Z']},'14':{'4':['C']}});
assert.deepEqual(ms.courseSections(blocks),{'11':['A','B','Z'],'14':['C']});
assert.deepEqual(ms.sectionsForCourse(blocks,11,'3'),['A','B']);
assert.deepEqual(ms.sectionsForCourse(blocks,11,'4'),['Z']);
assert.deepEqual(ms.sectionsForCourse(blocks,14,'3'),[],'A course absent from a year block cannot inherit another year’s sections.');
assert.deepEqual(ms.sectionsForCourse(grouped,14,'3'),['A','B','C'],'Old flat maps retain schedule-wide year selection.');
assert.deepEqual(ms.sectionsForCourse(base,11,'3'),['A'],'Unmapped legacy schedules retain global sections.');
assert.equal(ms.yearSection(blocks),'BSIT 3A, 3B, 4Z · BSN 4C');
assert.deepEqual(ms.tableGroupLines(blocks),['BSIT 3A, 3B, 4Z','BSN 4C'],
    'Multiline year sections do not invent the course/year cross product.');
assert.deepEqual(ms.tableCourseGroups(blocks),[
    {courseName:'BSIT',year:'3',sections:['A','B']},{courseName:'BSIT',year:'4',sections:['Z']},
    {courseName:'BSN',year:'4',sections:['C']},
], 'Nested schedules produce one organized block per saved course/year pair.');
assert.deepEqual(renderedCourseGroups(ms.courseGroupsHtml(blocks)),[
    {label:'BSIT \u00b7 3rd Year',chips:['3A','3B']},{label:'BSIT \u00b7 4th Year',chips:['4Z']},
    {label:'BSN \u00b7 4th Year',chips:['4C']},
], 'An absent course/year pair does not inherit chips from another group.');
assert.deepEqual(ms.yearSections(blocks),['3A','3B','4Z','4C']);
assert.deepEqual(ms.academicGroups(blocks).map(group=>group.label),['BSIT 3A','BSIT 3B','BSIT 4Z','BSN 4C']);
assert.deepEqual(ids(ms.filterSchedules([blocks],{q:'BSIT 3rd Year'})),[90],
    'Search includes the readable course/year heading displayed in Course(s).');
assert.deepEqual(ids(ms.filterSchedules([blocks],{q:'BSN 4th Year'})),[90]);
assert.deepEqual(ids(ms.filterSchedules([blocks],{q:'BSN 3rd Year'})),[],
    'Search cannot create a course/year heading absent from the saved nested groups.');
assert.deepEqual(ms.courseSectionsByYear({...blocks,course_sections_by_year:undefined,course_sections:undefined}),ms.courseSectionsByYear(blocks),
    'Nested pivot payloads still render when the additive attributes are omitted.');
for(const filters of [{course:'BSIT',year_level:'4',section:'B'}, {course:'BSN',year_level:'3',section:'C'},
    {year_level:'3',section:'Z'},{course:'BSN',year_level:'3'},{q:'BSIT 3Z'}])
    assert.deepEqual(ms.filterSchedules([blocks],filters),[],'Never invent a cross-year combination: '+JSON.stringify(filters));
for(const filters of [{course:'BSIT',year_level:'4',section:'Z'},{course:'BSN',year_level:'4',section:'C'},
    {course:'11',section:'Z'},{year_level:'3',section:'B'},{q:'BSIT 4Z'},{q:'BSN 4C'}])
    assert.deepEqual(ids(ms.filterSchedules([blocks],filters)),[90],JSON.stringify(filters));
assert.equal(readCsv(ms.buildCsv([blocks]))[1][2],'BSIT 3A, 3B, 4Z · BSN 4C');
assert.deepEqual(ms.calendarMonth(ms.filterSchedules([blocks],{course:'BSN',year_level:'4',section:'C'}),2026,9).cells.flatMap(day=>ids(day.events)),[90]);
assert.equal(ms.editPageUrl(90),'/admin/schedules/90/edit');

// Exercise the production navigation and retained modal lifecycle with minimal DOM fixtures.
function element() {
    const children=new Map();
    return {innerHTML:'',textContent:'',value:'',dataset:{},style:{},offsetWidth:190,offsetHeight:180,classList:{add(){},remove(){},toggle(){}},
        addEventListener(){},setAttribute(){},removeAttribute(){},focus(){},scrollIntoView(){},remove(){this.removed=true;},
        querySelector(selector){if(!children.has(selector))children.set(selector,element());return children.get(selector);},
        querySelectorAll(){return [];},append(child){this.appended=child;},contains(){return true;},
        getBoundingClientRect(){return {right:300,bottom:100,top:60};},getClientRects(){return [{}];}};
}
async function verifyNavigationAndModals() {
    const previousDocument=global.document, previousLocation=global.location, previousListener=global.addEventListener;
    const targets=[],requests=[];
    global.document={activeElement:element(),addEventListener(){},createElement:element};
    global.addEventListener=()=>{};
    global.location={assign:url=>targets.push(url)};
    try {
        const root=element();let edited=null,created=0;
        const controller=ms.mount({root,state:{schedules:rows,server_date:'2026-10-09'},onEdit:schedule=>{edited=schedule.id;},
            onCreate:()=>created++,request:async(url,method,body)=>{requests.push({url,method,body});return {message:'Saved'};}});
        const headHtml=()=>root.querySelector('thead').innerHTML;
        const bodyHtml=()=>root.querySelector('tbody').innerHTML;
        const renderedRows=()=>[...bodyHtml().matchAll(/<tr\b[^>]*data-ms-schedule-id="(\d+)"[^>]*>([\s\S]*?)<\/tr>/g)]
            .map(match=>({id:Number(match[1]),html:match[2],cells:[...match[2].matchAll(/<td\b[^>]*>([\s\S]*?)<\/td>/g)].map(cell=>cell[1])}));
        const initialHeaders=[...headHtml().matchAll(/<th\b[^>]*>([\s\S]*?)<\/th>/g)];
        assert.equal(initialHeaders.length,6,'There are exactly six organized table columns.');
        assert.deepEqual([...headHtml().matchAll(/data-ms-sort="([^"]+)"/g)].map(match=>match[1]),ms.TABLE_COLUMNS.map(column=>column[1]));
        assert.equal((headHtml().match(/aria-sort="none"/g)||[]).length,5,'Default presentation order does not claim a selected column sort.');
        assert.ok(!initialHeaders.at(-1)[0].includes('aria-sort')&&!initialHeaders.at(-1)[0].includes('<button'),
            'Actions is the sole nonsortable heading.');
        const first=renderedRows().find(row=>row.id===1);
        assert.equal(first.cells.length,6);
        assert.ok(first.cells[0].includes('Oct 15, 2026')&&first.cells[0].includes('08:00 AM – 05:00 PM')&&first.cells[0].includes('Thursday'),
            'The first cell groups readable date, padded time and weekday.');
        assert.ok(!first.html.includes('ms-schedule-title'),'Recollection Title is omitted from table rows.');
        assert.deepEqual(renderedCourseGroups(first.cells[1]),[{label:'BSIT \u00b7 3rd Year',chips:['3A']}]);
        assert.ok(first.cells[2].includes('Chapel')); assert.ok(first.cells[3].includes('Open'));
        assert.ok(first.cells[4].includes('12 / 25')&&first.cells[4].includes('48%'));
        assert.ok(first.cells[5].includes('data-ms-action="view"')&&first.cells[5].includes('data-ms-action="edit"')
            &&first.cells[5].includes('data-ms-action="more"'),'Row actions keep their existing connected handlers.');
        for(const [label,key] of ms.TABLE_COLUMNS) {
            controller.action('sort',{dataset:{msSort:key}});
            assert.equal(controller.ui.sort,key); assert.equal(controller.ui.direction,'asc');
            assert.deepEqual(renderedRows().map(row=>row.id),ids(ms.sortSchedules(rows,key,'asc')),`${label} heading sorts the real matching rows ascending.`);
            let heading=[...headHtml().matchAll(/<th\b[^>]*>[\s\S]*?<\/th>/g)].find(match=>match[0].includes(`data-ms-sort="${key}"`))[0];
            assert.ok(heading.includes('aria-sort="ascending"')&&heading.includes(`aria-label="Sort by ${label}, descending"`));
            assert.ok(heading.includes('↑'));
            controller.action('sort',{dataset:{msSort:key}});
            assert.equal(controller.ui.direction,'desc');
            assert.deepEqual(renderedRows().map(row=>row.id),ids(ms.sortSchedules(rows,key,'desc')),`${label} heading sorts descending on its second activation.`);
            heading=[...headHtml().matchAll(/<th\b[^>]*>[\s\S]*?<\/th>/g)].find(match=>match[0].includes(`data-ms-sort="${key}"`))[0];
            assert.ok(heading.includes('aria-sort="descending"')&&heading.includes('↓'));
        }
        controller.ui.sort=null;controller.update({schedules:[blocks],server_date:'2026-10-09'});
        const groupedCells=renderedRows()[0].cells;
        assert.equal(groupedCells.length,6);
        assert.deepEqual(renderedCourseGroups(groupedCells[1]),[
            {label:'BSIT \u00b7 3rd Year',chips:['3A','3B']},{label:'BSIT \u00b7 4th Year',chips:['4Z']},
            {label:'BSN \u00b7 4th Year',chips:['4C']},
        ], 'The combined Course(s) cell shows only saved per-course/per-year groups.');
        Object.assign(controller.ui,{q:'Formation',course:'BSN',year_level:'4',section:'C'});controller.draw();
        assert.deepEqual(renderedRows().map(row=>row.id),[90],'Search and paired filters still combine around the new table presentation.');
        assert.equal(root.querySelector('[data-ms-record-count]').textContent,'1 record');
        controller.ui.course='BSIT';controller.draw();assert.equal(renderedRows().length,0);
        assert.ok(bodyHtml().includes('colspan="6"'),'Empty results keep the six-column structure.');
        controller.action('clear-filters',{dataset:{}});controller.action('clear-search',{dataset:{}});
        controller.update({schedules:many,server_date:'2026-10-09'});
        assert.equal(renderedRows().length,10);assert.ok(root.querySelector('.ms-table-footer').innerHTML.includes('Showing 1–10 of 63'));
        controller.action('page-next',{dataset:{}});
        assert.deepEqual(renderedRows().map(row=>row.id),ids(controller.matching()).slice(10,20));
        assert.ok(root.querySelector('.ms-table-footer').innerHTML.includes('Showing 11–20 of 63'));
        const refreshed={...many[0],title:'Updated schedule',registrations_count:24};
        controller.ui.page=1;controller.update({schedules:[refreshed],server_date:'2026-10-09'});
        assert.ok(bodyHtml().includes('Updated schedule')&&bodyHtml().includes('24 / 25')&&bodyHtml().includes('96%'),
            'Live state updates replace the displayed title, count and progress together.');
        assert.ok(bodyHtml().includes('Almost full'),'Existing needs-attention indicators update with real occupancy.');
        controller.action('view-calendar',{dataset:{}});
        assert.equal(root.querySelector('.ms-calendar-panel').hidden,false);
        assert.equal(root.querySelector('.ms-table-footer').hidden,true,'Calendar view hides the detached table pagination banner.');
        assert.ok(root.querySelector('.ms-calendar-grid').innerHTML.includes('Updated schedule'));
        controller.action('view-table',{dataset:{}});assert.equal(root.querySelector('.ms-table-panel').hidden,false);
        assert.equal(root.querySelector('.ms-table-footer').hidden,false,'Returning to Table restores the pagination banner.');
        controller.update({schedules:rows,server_date:'2026-10-09'});
        controller.openEdit(1);
        assert.equal(edited,1);assert.equal(controller.isModalOpen(),false,'Edit navigates instead of rendering the removed form.');
        controller.action('create',{dataset:{}});assert.equal(created,1,'Create still uses its existing page callback.');
        controller.openView(1);assert.ok(controller.modal.innerHTML.includes('Recollection Schedule Details'));
        assert.doesNotMatch(controller.modal.innerHTML,/Hidden legacy theme|Hidden legacy facilitator|Hidden legacy description|>Theme<|>Facilitator<|>Description</,'Saved optional details are not displayed');
        assert.ok(controller.modal.innerHTML.includes('Edit Schedule'));
        controller.action('edit',{dataset:{msId:'1'}});assert.equal(edited,1);assert.equal(controller.isModalOpen(),false,'Details Edit also navigates.');
        controller.openEdit(4);assert.ok(controller.modal.innerHTML.includes('Completed schedules are view only.'));
        controller.openEdit(5);assert.ok(controller.modal.innerHTML.includes('Cancelled schedules are view only.'));
        controller.openDelete(2);assert.ok(controller.modal.innerHTML.includes('Delete Recollection Schedule?'));
        controller.openCancel(1);assert.ok(controller.modal.innerHTML.includes('Cancel Recollection Schedule?'));
        controller.showErrors({status:['Status rejected.']});assert.equal(controller.modal.querySelector('.ms-modal-feedback').textContent,'Status rejected.');
        controller.openMenu(1,element());assert.ok(controller.menu.innerHTML.includes('Close registration'));
        assert.ok(controller.menu.innerHTML.includes('Cancel schedule'));
        await controller.changeStatus(1,'Closed');
        assert.deepEqual(requests[0],{url:'/api/admin/schedules/1/status',method:'PATCH',body:{status:'Closed'}});
        controller.options.onEdit=null;controller.openEdit(1);assert.equal(targets.at(-1),'/admin/schedules/1/edit');
        controller.openEdit(999);assert.equal(root.querySelector('.ms-page-feedback').textContent,'The schedule was not found.');
        controller.destroy();
        const source=fs.readFileSync(path.join(__dirname,'../../public/my-schedules.js'),'utf8');
        assert.equal(source.includes('data-ms-schedule-form'),false,'Old Edit modal markup is removed.');
        assert.equal(source.includes('saveForm('),false,'Old Edit modal save path is removed.');
        assert.equal(source.includes('renderCourseSectionFields('),false,'Old Edit modal group controls are removed.');
    } finally {global.document=previousDocument;global.location=previousLocation;global.addEventListener=previousListener;}
}
verifyNavigationAndModals().then(()=>console.log('My schedules helpers passed: organized six-column rendering, combined course/year/section chips, readable date/time/weekday search, five accessible sorts, occupancy percentages, live updates, Edit page navigation, retained View/status/delete/cancel dialogs, exact per-year grouping/filter/search/CSV/calendar, legacy groups, indicators, ordering and pagination.'))
    .catch(error=>{console.error(error);process.exitCode=1;});
