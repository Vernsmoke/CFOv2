// Run the production certificate list, shared grid, search and export handler with isolated DOM fixtures.
// This checks behavior and data boundaries; it is not a visual browser test.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const {setup}=require('./attendance-workflow-state.cjs');
const source=fs.readFileSync(path.join(__dirname,'../../public/admin-dashboard.js'),'utf8');
const start=source.indexOf('    function connectedCertificates()'),end=source.indexOf('    function connectedTemplate()',start);
assert.ok(start>=0&&end>start,'production certificate function is available');
const exportHandler=source.match(/^\s*'export-grid':(.+),\r?$/m);assert.ok(exportHandler);

function certificateHarness(){
  const h=setup(),{ctx}=h;
  vm.runInContext(source.slice(start,end),ctx);
  vm.runInContext('actions["export-grid"]='+exportHandler[1],ctx);
  const original=h.state.registrations[0];
  const row=(id,changes={})=>({...structuredClone(original),id,attendance:'Present',attendance_verified_at:'2026-10-12T10:00:00Z',
    user:{...original.user,name:'Student '+id,student_id:'ID-'+id,course:'OUTDATED',year_level:'3',section:'A'},
    details:{course:'BSIT',yearLevel:'1',section:'B',medical:'private-medical',religion:'private-religion',emergencyPhone:'private-emergency'},
    receipt_path:'private-receipt',certificate:{id:100+id,certificate_number:'CFO-2026-'+id},...changes});
  const current=(id,name,date,details)=>row(id,{user:{...original.user,name,student_id:'ID-'+id,course:'OUTDATED',year_level:'3',section:'A'},
    schedule:{...original.schedule,title:'Batch '+id,date},details:{medical:'private-medical',religion:'private-religion',...details}});
  const rows=[
    current(1,'Zeta Current','2026-10-11',{course:' BSECE ',yearLevel:' 1 ',section:' Z '}),
    current(2,'Alpha Current','2026-10-11',{course:'BSN',yearLevel:'4',section:'Y'}),
    row(3,{attendance_verified_at:null}),row(4,{attendance:'Absent'}),row(5,{certificate:null}),
    row(6,{year:2025,is_archived:true,details:{},student_snapshot:{name:'Saved Historical Student',student_id:'OLD-ID',course:'BSED',year_level:'2',section:'C'},
      schedule:{...original.schedule,title:'Historical Batch',date:'2025-10-11'}}),
    current(7,'Legacy Group','2026-09-10',{course:'BSN',yearSection:'4-Y'}),
    row(8,{details:{},student_snapshot:{course:'BSED',year_level:'2',section:'C'}}),
    current(9,'<img src=x onerror=unsafe()>','2026-10-09',{course:'<script>unsafe()</script>',yearLevel:'1',section:'Z'})
  ];
  for(let id=10;id<=15;id++)rows.push(row(id,{schedule:{...original.schedule,date:'2026-08-01'}}));
  ctx.cfoState={...structuredClone(h.state),registrations:rows};
  ctx.connectedCertificates();
  return h;
}

const h=certificateHarness(),{ctx,$}=h;
let grid=h.grid(),root=$('#data-grid');
const ids=()=>Array.from(h.grid().filtered(),r=>r.id);
const search=value=>{const field=root.querySelector('[data-search]');field.value=value;field.listeners.input({target:field});};
const filter=(key,value)=>{const field=root.querySelector('[data-filter="'+key+'"]');field.value=value;field.listeners.change();};
const clear=()=>{search('');for(const item of h.grid().config.filters)filter(item.key,'');};
assert.match($('#page-actions').innerHTML,/Certificate template/);
assert.match($('#page').innerHTML,/role="note"/);
assert.deepEqual(Array.from(grid.config.rows(),r=>r.id),[2,1,9,8,7,10,11,12,13,14,15,6],
  'Only ready certificates with verified Present attendance appear, newest schedule first then student name.');
assert.equal(grid.state.filters.year,'2026','the current recollection year is initially selected when it has ready records');
assert.equal(grid.filtered().length,11);
assert.deepEqual(ids().slice(0,3),[2,1,9]);
assert.equal((root.querySelector('tbody').innerHTML.match(/<tr /g)||[]).length,10,'the shared grid paginates the current year');
assert.deepEqual(Array.from(grid.config.filters,f=>f.key),['schedule','course','year']);
assert.deepEqual(Array.from(grid.config.columns,c=>c.label),['Student','Course / Year / Section','Recollection schedule','Recollection date']);
assert.equal(grid.config.columns[1].get(ctx.cfoState.registrations[0]),'BSECE · 1 · Z','submitted choices override the current account profile');
assert.equal(grid.config.columns[1].get(ctx.cfoState.registrations.find(r=>r.id===7)),'BSN · 4 · Y','legacy combined year/section is supported');
assert.equal(grid.config.columns[1].get(ctx.cfoState.registrations.find(r=>r.id===8)),'BSED · 2 · C','snapshot academic fallback is supported');
assert.ok(!grid.config.filters.find(f=>f.key==='course').values.includes('OUTDATED'),'course filter matches the displayed academic data');
assert.doesNotMatch(root.querySelector('tbody').innerHTML,/<img |<script>/,'student and course content is HTML escaped');
assert.match(root.querySelector('tbody').innerHTML,/&lt;img/);
for(const [query,expected] of [['BSECE 1Z',[1]],['BSN 4Y',[2,7]],['OUTDATED',[]],['CFO-2026-7',[7]],['private-medical',[]],['private-religion',[]],['private-emergency',[]],['private-receipt',[]]]){
  search(query);assert.deepEqual(ids(),expected,'certificate search uses allowed fields: '+query);
}
search('no such student');assert.match($('#data-grid .table-empty').textContent,/No certificates match your search and filters/);clear();
filter('course','BSECE');filter('schedule','Batch 1 · 2026-10-11');assert.deepEqual(ids(),[1],'schedule and course filters combine');
ctx.actions['export-grid']();
assert.equal(ctx.download.filename,'certificates.csv');
assert.deepEqual(Array.from(ctx.download.columns),['Student ID','Student','Course','Year level','Section','Recollection schedule','Recollection date','Certificate number']);
assert.deepEqual(Array.from(ctx.download.data[0]),['ID-1','Zeta Current','BSECE','1','Z','Batch 1','2026-10-11','CFO-2026-1']);
assert.doesNotMatch(JSON.stringify(ctx.download),/private-|OUTDATED/,'certificate exports omit private registration fields and stale academics');
clear();ctx.actions['export-grid']();assert.equal(ctx.download.data.length,12,'CSV exports matching records beyond the visible page');
filter('year','2025');assert.deepEqual(ids(),[6]);
assert.match(root.querySelector('tbody').innerHTML,/Saved Historical Student/);assert.match(root.querySelector('tbody').innerHTML,/OLD-ID/);
ctx.actions['export-grid']();assert.deepEqual(Array.from(ctx.download.data[0].slice(0,5)),['OLD-ID','Saved Historical Student','BSED','2','C']);
clear();grid.state.sortIndex=0;grid.state.sortDirection='asc';grid.draw();
assert.equal(ids().at(-1),1,'Student sorting uses the displayed name');
grid.state.sortIndex=3;grid.state.sortDirection='asc';grid.draw();assert.equal(ids()[0],6,'Date sorting uses the raw schedule date');
grid.state.sortDirection='desc';grid.draw();assert.deepEqual(ids().slice(0,2),[2,1]);

// Re-entering after a live update recalculates ready records and preserves this grid's filter/search state.
clear();filter('course','BSECE');search('Zeta');
ctx.cfoState=structuredClone(ctx.cfoState);ctx.cfoState.registrations.find(r=>r.id===3).attendance_verified_at='2026-10-12T11:00:00Z';
ctx.cfoState.registrations.find(r=>r.id===1).attendance='Absent';ctx.connectedCertificates();
grid=h.grid();root=$('#data-grid');assert.equal(grid.state.filters.course,'BSECE');assert.equal(grid.state.q,'Zeta');assert.deepEqual(ids(),[]);
assert.ok(grid.config.rows().some(r=>r.id===3),'newly verified Present students arrive without a separate certificate generation action');
assert.ok(!grid.config.rows().some(r=>r.id===1),'corrected non-Present attendance leaves the certificate list');
assert.equal(h.calls.length,0,'list controls and export never mutate attendance');

// Print waits for the existing certificate endpoint to load and reports blocked or failed browser printing.
let opened;ctx.window.open=(url,target)=>{opened={url,target,opener:'parent',addEventListener(event,callback,options){this.event=event;this.callback=callback;this.options=options;},focus(){this.focused=true;},print(){this.printed=true;}};return opened;};
ctx.actions['certificate-print'](101);assert.equal(opened.url,'/certificates/101');assert.equal(opened.target,'_blank');assert.equal(opened.opener,null);
assert.equal(opened.event,'load');assert.equal(opened.options.once,true);assert.equal(opened.printed,undefined);opened.callback();assert.equal(opened.focused,true);assert.equal(opened.printed,true);
ctx.actions['certificate-print']('unsafe/id');assert.equal(opened.url,'/certificates/unsafe%2Fid');
opened.print=()=>{throw Error('browser print refused');};opened.callback();assert.match(ctx.feedback,/Use the browser Print command/);
ctx.window.open=()=>null;ctx.actions['certificate-print'](101);assert.match(ctx.feedback,/Allow pop-ups/);
let anchor,appended;ctx.document.createElement=tag=>{assert.equal(tag,'a');anchor={click(){this.clicked=true;},remove(){this.removed=true;}};return anchor;};
ctx.document.body={appendChild(node){appended=node;}};ctx.actions['certificate-download']('unsafe/id');
assert.equal(anchor.href,'/certificates/unsafe%2Fid?download=1');assert.equal(anchor.rel,'noopener');assert.equal(appended,anchor);assert.equal(anchor.clicked,true);assert.equal(anchor.removed,true);assert.match(ctx.feedback,/SVG download requested/);

const empty=certificateHarness();empty.ctx.cfoState.registrations=[];empty.ctx.connectedCertificates();
assert.equal(empty.grid().filtered().length,0);assert.match(empty.$('#data-grid .table-empty').textContent,/No certificates are ready yet/);
console.log('PASS: ready eligibility, current-year default, selected academic fields, history identity, filters, search privacy, sorting, pagination, CSV boundaries, escaping, live redraw, print and SVG download.');
