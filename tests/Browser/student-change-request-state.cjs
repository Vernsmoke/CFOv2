// Executes the production page, submit helper, filters and native-dialog handlers against an isolated DOM model.
// This checks behavior and escaping; it is not a visual browser test.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/student/index.blade.php','utf8');
function between(start,end){const a=source.indexOf(start),b=source.indexOf(end,a);assert.ok(a>=0&&b>a,start);return source.slice(a,b);}
const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const decode=value=>String(value??'').replace(/&(amp|lt|gt|quot|#39);/g,(_,entity)=>({amp:'&',lt:'<',gt:'>',quot:'"','#39':"'"}[entity]));
const current={id:10,title:'Current <img src=x>',date:'2026-10-20',year:2026,active:true,registrations_count:1,capacity:5};
const alternative={...current,id:11,title:'Alternative day',date:'2026-10-22'};
const registration={id:20,year:2026,is_archived:false,status:'Approved',attendance:'Not Yet Recorded',recollection_schedule_id:10,schedule:current};
const request={id:30,registration_id:20,status:'Pending',student_status:'Pending',reason:'Exam conflict',current_schedule:current,assigned_schedule:null,student_message:'The CFO will assign your new schedule.',created_at:'2026-10-10T03:00:00Z'};
const baseline={current_year:2026,server_date:'2026-10-10',registrations:[registration],schedules:[current,alternative],requests:[]};
const template=(id,args=[])=>{
  const found=source.match(new RegExp('<template id="'+id+'"><!--([\\s\\S]*?)--></template>'));
  assert.ok(found,id);return found[1].replace(/@\{\{(\d+)\}\}/g,(_,index)=>args[Number(index)]??'');
};
function setup(state=baseline,{demo=false}={}){
  let context,html='',cleanup=0,forcedError=null,holdPost=null;const posts=[],notices=[],errors=[],closeTasks=[];
  class Element{
    constructor(tag='div'){this.tagName=tag.toUpperCase();this.children=[];this.attrs={};this.dataset={};this.listeners={};this.style={};this.value='';this.disabled=false;this.hidden=false;this.open=false;this.validationMessage='';this._text='';this.isConnected=true;}
    set id(value){this.attrs.id=String(value);}get id(){return this.attrs.id||'';}
    set className(value){this.attrs.class=String(value);}get className(){return this.attrs.class||'';}
    setAttribute(key,value){value=String(value);this.attrs[key]=value;if(key==='value')this.value=decode(value);if(key.startsWith('data-'))this.dataset[key.slice(5).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]=decode(value);if(key==='maxlength')this.maxLength=Number(value);if(key==='minlength')this.minLength=Number(value);if(['disabled','hidden','readonly'].includes(key))this[key==='readonly'?'readOnly':key]=true;}
    getAttribute(key){return this.attrs[key]??null;}removeAttribute(key){delete this.attrs[key];if(key==='disabled')this.disabled=false;}
    toggleAttribute(key,force){if(force??!Object.hasOwn(this.attrs,key))this.setAttribute(key,'');else this.removeAttribute(key);}
    get textContent(){return this._text+this.children.map(child=>child.textContent).join('');}set textContent(value){this._text=String(value??'');this.children=[];}
    get innerHTML(){return this._html||'';}
    set innerHTML(value){for(const child of this.children)child.setConnected(false);this._html=String(value);this.children=[];this._text='';const stack=[this];
      for(const match of String(value).matchAll(/<!--[\s\S]*?-->|<\/?[a-z][^>]*>|[^<]+/gi)){
        const token=match[0];if(token.startsWith('<!--'))continue;
        if(token.startsWith('</')){if(stack.length>1)stack.pop();continue;}
        if(token.startsWith('<')){
          const tag=token.match(/^<([a-z\d-]+)/i)[1],node=new Element(tag);const attrs=token.slice(tag.length+1,-1);
          for(const attr of attrs.matchAll(/([\w-]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g))node.setAttribute(attr[1],decode(attr[2]??attr[3]??attr[4]??''));
          stack.at(-1).append(node);if(!['input','br','hr','img','meta','link','path'].includes(tag.toLowerCase())&&!token.endsWith('/>'))stack.push(node);
        }else stack.at(-1)._text+=decode(token);
      }
      for(const select of this.querySelectorAll('select'))select.value=select.querySelector('option[selected]')?.value||select.querySelector('option')?.value||select.querySelector('option')?.textContent||'';
      for(const textarea of this.querySelectorAll('textarea'))textarea.value=textarea.textContent;
    }
    setConnected(value){this.isConnected=value;for(const child of this.children)child.setConnected(value);}
    append(...nodes){for(const node of nodes){node.parentElement=this;node.setConnected(this.isConnected);this.children.push(node);}}
    appendChild(node){this.append(node);return node;}replaceChildren(...nodes){for(const child of this.children)child.setConnected(false);this.children=[];this.append(...nodes);}
    remove(){this.setConnected(false);if(this.parentElement)this.parentElement.children=this.parentElement.children.filter(child=>child!==this);}
    contains(node){return node===this||this.children.some(child=>child.contains(node));}
    matches(selector){return selector.split(',').some(piece=>{
      piece=piece.trim();if(!piece)return false;
      const attrs=[...piece.matchAll(/\[([\w-]+)(?:=["']?([^\]"']*)["']?)?\]/g)];const base=piece.replace(/\[[^\]]*\]/g,'').replace(/:not\([^)]*\)/g,'');
      const id=base.match(/#([\w-]+)/)?.[1],classes=[...base.matchAll(/\.([\w-]+)/g)].map(m=>m[1]),tag=base.match(/^[a-z][\w-]*/i)?.[0];
      return(!id||this.id===id)&&classes.every(name=>this.className.split(/\s+/).includes(name))&&(!tag||this.tagName===tag.toUpperCase())&&attrs.every(([,key,val])=>{const actual=key.startsWith('data-')?this.dataset[key.slice(5).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]:this.attrs[key];return actual!==undefined&&(val===undefined||actual===val);});
    });}
    closest(selector){return this.matches(selector)?this:this.parentElement?.closest(selector)||null;}
    querySelectorAll(selector){const nodes=this.children.flatMap(child=>[child,...child.descendants()]);return nodes.filter(node=>selector.split(',').some(part=>{const bits=part.trim().split(/\s+(?![^\[]*\])/);if(!node.matches(bits.pop()))return false;let ancestor=node.parentElement;while(bits.length){const wanted=bits.pop();while(ancestor&&!ancestor.matches(wanted))ancestor=ancestor.parentElement;if(!ancestor)return false;ancestor=ancestor.parentElement;}return true;}));}
    descendants(){return this.children.flatMap(child=>[child,...child.descendants()]);}querySelector(selector){return this.querySelectorAll(selector)[0]||null;}
    addEventListener(type,fn){(this.listeners[type]||=[]).push(fn);}removeEventListener(type,fn){this.listeners[type]=(this.listeners[type]||[]).filter(value=>value!==fn);}
    emit(type,event={}){let prevented=false,stopped=false;const data={target:this,currentTarget:this,preventDefault(){prevented=true;},stopPropagation(){stopped=true;},...event};for(const fn of [...(this.listeners[type]||[]),...(this['on'+type]?[this['on'+type]]:[])])fn(data);if(!stopped&&this.parentElement)this.parentElement.emit(type,{...data,currentTarget:this.parentElement});return prevented;}
    focus(){document.activeElement=this;this.focused=true;}setCustomValidity(value){this.validationMessage=value;}reportValidity(){return !this.validationMessage;}
    checkValidity(){return !this.validationMessage;}showModal(){this.open=true;this.attrs.open='';}close(){this.open=false;delete this.attrs.open;closeTasks.push(()=>this.emit('close'));}
    getBoundingClientRect(){return {left:100,right:600,top:100,bottom:500};}
  }
  const body=new Element('body'),host=new Element('main');body.style.overflow='auto';body.append(host);
  const document={body,hidden:false,activeElement:null,listeners:{},querySelector:selector=>body.querySelector(selector),querySelectorAll:selector=>body.querySelectorAll(selector),createElement:tag=>new Element(tag),addEventListener(type,fn){(this.listeners[type]||=[]).push(fn);},removeEventListener(type,fn){this.listeners[type]=(this.listeners[type]||[]).filter(x=>x!==fn);},emit(type,event){for(const fn of this.listeners[type]||[])fn(event);}};
  const asyncRequest=async(url,method='GET',values)=>{
    posts.push({url,method,values});if(method!=='GET'&&forcedError)throw forcedError;
    if(method!=='GET'&&holdPost)await holdPost;
    if(url==='/api/student')return structuredClone(state);
    if(method==='POST'){state={...state,requests:[...state.requests,{...request,reason:values.reason}]};return {...request,reason:values.reason};}
    if(method==='PATCH'){state={...state,requests:state.requests.map(row=>String(row.id)===url.split('/')[3]?{...row,status:'Cancelled',student_status:'Cancelled',student_message:'You cancelled this request.'}:row)};return {message:'Request cancelled.',request:state.requests.find(row=>String(row.id)===url.split('/')[3])};}
    throw new Error('Unexpected request '+url);
  };
  context=vm.createContext({
    cfoStudent:structuredClone(state),document,window:{...(demo?{}:{CFO_BACKEND:{user:{id:2,role:'Student'}}}),CfoUsability:{formError:(form,error)=>errors.push({form,error})}},
    studentTemplate:template,escapeHTML:escape,icon:name=>`<svg class="icon" data-icon="${name}"></svg>`,icons:{},
    dashboard:config=>{context.dashboardCleanup();html=config.content;host.innerHTML=html;},
    cfoRequest:asyncRequest,dashboardCleanup:()=>cleanup++,showNotice:(...args)=>notices.push(args),
    setTimeout:fn=>fn(),requestAnimationFrame:fn=>fn(),location:{hash:'#schedule-change-request'},
  });
  vm.runInContext(between('async function cfoSubmit(', '// Consistent button contrast'),context);
  vm.runInContext(between('/* 7.3.8 — Schedule change requests */','/* 7.3.9'),context);
  if(demo)context.scheduleChangeRequest();else context.cfoScheduleChange();
  return {context,document,posts,notices,errors,Element,get html(){return html;},get cleanup(){return cleanup;},get:selector=>document.querySelector(selector),
    input(selector,value){const node=document.querySelector(selector);assert.ok(node,selector);node.value=value;node.emit('input');return node;},
    click(selector){const node=document.querySelector(selector);assert.ok(node,selector);node.emit('click');return node;},
    submit(selector='#cfo-change-form'){const form=document.querySelector(selector);assert.ok(form,selector);form.emit('submit');},
    fail(error){forcedError=error;},hold(promise){holdPost=promise;},clearFailure(){forcedError=null;},drainClose(){for(const task of closeTasks.splice(0))task();},
  };
}
const flush=()=>new Promise(resolve=>setImmediate(resolve));
const confirm=async env=>{env.click('#action-confirm-accept');await flush();await flush();};
(async()=>{
  let env=setup();assert.match(env.html,/RECOLLECTION/);assert.match(env.html,/Your Requests/);assert.doesNotMatch(env.html,/change-target|change-registration|Requested annual schedule/);
  assert.equal(env.get('#change-current-schedule').readOnly,true);assert.match(env.get('#change-current-schedule').value,/Current <img src=x>/);assert.doesNotMatch(env.html,/<img/);
  assert.equal(env.get('#change-reason').minLength,10);assert.equal(env.get('#change-reason').maxLength,2000,'The native UTF-16 limit accommodates 1,000 supplementary Unicode characters.');assert.equal(env.get('#change-reason-count').textContent,'0 / 1,000');
  assert.equal(env.get('#cfo-change-form [type=submit]').disabled,false);assert.equal(env.get('#student-change-empty').hidden,false);assert.match(env.get('#student-change-showing').textContent,/Showing 0 of 0 requests/);
  for(const [value,message] of [['   ','reason'],['ninechars','10'],['x'.repeat(1001),'1,000']]){
    env.input('#change-reason',value);env.submit();assert.equal(env.get('#action-confirm'),null);assert.match(env.get('#change-reason').validationMessage,new RegExp(message,'i'));assert.equal(env.get('#change-reason').focused,true);assert.equal(env.posts.length,0);
  }
  for(const [count,valid] of [[9,false],[10,true],[1000,true],[1001,false]]){
    const unicode=setup(),value='😀'.repeat(count);unicode.input('#change-reason',value);assert.equal(unicode.get('#change-reason-count').textContent,`${count} / 1,000`);unicode.submit();
    if(valid){assert.equal(unicode.get('#action-confirm').open,true);await confirm(unicode);assert.equal(unicode.posts[0].values.reason,value,'The submitted Unicode reason is intact.');}
    else {assert.equal(unicode.get('#action-confirm'),null);assert.match(unicode.get('#change-reason').validationMessage,count<10?/10/:/1,000/);assert.equal(unicode.posts.length,0);}
  }
  env.input('#change-reason','  Exam conflict  ');assert.match(env.get('#change-reason-count').textContent,/17 \/ 1,000/);env.submit();assert.equal(env.posts.length,0,'Submission waits for confirmation.');
  assert.equal(env.get('#action-confirm').open,true);assert.match(env.get('#action-confirm-details').textContent,/Exam conflict/);
  env.input('#change-reason','Changed after confirmation');await confirm(env);
  assert.equal(env.posts[0].url,'/api/change-requests');assert.equal(env.posts[0].method,'POST');assert.deepEqual({...env.posts[0].values},{registration_id:20,reason:'Exam conflict'});
  assert.equal(env.posts[1].url,'/api/student');assert.equal('requested_schedule_id' in env.posts[0].values,false);assert.equal(env.notices.at(-1)[0],'Request submitted');
  assert.equal(env.get('#cfo-change-form [type=submit]').disabled,true);assert.equal(env.get('#student-change-fields').disabled,true);assert.match(env.get('#change-availability').textContent,/pending/i);
  for(const schedules of [[],[current],[{...alternative,active:false}],[{...alternative,capacity:1,registrations_count:1}],[{...alternative,year:2027}]]){
    env=setup({...baseline,schedules});assert.equal(env.get('#cfo-change-form [type=submit]').disabled,false);env.input('#change-reason','Review this reason');env.submit();assert.equal(env.get('#action-confirm').open,true);
  }
  env=setup({...baseline,requests:[request]});env.input('#change-reason','Review this reason');env.submit();assert.equal(env.posts.length,0);assert.equal(env.get('#action-confirm'),null);
  for(const registrationOverride of [{is_archived:true},{year:2025},{attendance:'Present'},{attendance:'Absent'},{schedule:null}]){
    env=setup({...baseline,registrations:[{...registration,...registrationOverride}]});assert.equal(env.get('#cfo-change-form [type=submit]').disabled,true);assert.equal(env.get('#student-change-fields').disabled,true);assert.match(env.get('#change-availability').textContent,/assigned|eligible|current/i);
  }
  env=setup();env.input('#change-reason','Keep this reason');env.submit();let release;env.hold(new Promise(resolve=>release=resolve));env.click('#action-confirm-accept');
  assert.equal(env.get('#cfo-change-form [type=submit]').disabled,true);env.click('#action-confirm-accept');assert.equal(env.posts.filter(row=>row.method==='POST').length,1);release();await flush();await flush();
  env=setup();env.fail(new Error('The assignment changed. Please reload.'));env.input('#change-reason','Keep this failed reason');env.submit();await confirm(env);
  assert.equal(env.get('#change-reason').value,'Keep this failed reason');assert.equal(env.get('#cfo-change-form [type=submit]').disabled,false);assert.match(env.notices.at(-1)[1],/assignment changed/);assert.equal(env.errors.length,1);
  // A completed POST is authoritative even if the subsequent state refresh temporarily fails.
  env=setup();env.context.pendingStudentResources=new Set();const submitRequest=env.context.cfoRequest;
  env.context.cfoRequest=(url,...args)=>url==='/api/student'?Promise.reject(new Error('Refresh temporarily unavailable.')):submitRequest(url,...args);
  env.input('#change-reason','Confirmed reason survives refresh failure');env.submit();await confirm(env);
  assert.equal(env.notices.at(-1)[0],'Request submitted');assert.equal(env.get('#cfo-change-form [type=submit]').disabled,true);assert.match(env.get('#student-change-body').innerHTML,/Pending/);assert.equal(env.context.pendingStudentResources.has('schedule_changes'),true);env.submit();assert.equal(env.posts.filter(row=>row.method==='POST').length,1,'Refresh failure must not permit a duplicate request.');
  // Completing a request after navigation updates state without replacing the active page.
  env=setup();env.input('#change-reason','Delayed request reason');env.submit();let releaseNavigation;env.hold(new Promise(resolve=>releaseNavigation=resolve));env.click('#action-confirm-accept');
  const oldPage=env.get('#student-change-page'),oldHtml=env.html;env.context.location.hash='#student-dashboard';oldPage.remove();releaseNavigation();await flush();await flush();
  assert.equal(env.html,oldHtml);assert.equal(env.get('#student-change-page'),null);assert.equal(env.notices.length,0);assert.equal(env.context.cfoStudent.requests.length,1);
  const approved={...request,id:31,status:'Approved',student_status:'Approved',reason:'Later reason <script>bad</script>',created_at:'2026-10-12T03:00:00Z',assigned_schedule_id:11,assigned_schedule:{...alternative,title:'New <b>day</b>'},student_message:'Approved note <img src=x>'};
  const rejected={...request,id:32,status:'Rejected',student_status:'Not Approved',created_at:'2026-10-11T03:00:00Z',student_message:'Visit CFO for guidance.'};
  const closed={...request,id:33,status:'Closed',student_status:'Not Approved',created_at:'2026-10-09T03:00:00Z',student_message:'Request closed.'};
  const cancelled={...request,id:34,status:'Cancelled',student_status:'Cancelled',created_at:'2026-10-08T03:00:00Z',student_message:'You cancelled this request.'};
  env=setup({...baseline,requests:[closed,request,cancelled,rejected,approved]});
  assert.deepEqual(env.get('#student-change-page').querySelectorAll('.student-change-stat-count').map(node=>Number(node.textContent)),[1,1,2,1]);
  const table=()=>env.get('#student-change-body').innerHTML;assert.doesNotMatch(table(),/Rejected|Closed|<script|<img|<b>/);assert.match(table(),/Later reason &lt;script&gt;/);assert.match(table(),/New &lt;b&gt;day/);
  assert.deepEqual(env.get('#student-change-body').querySelectorAll('[data-student-change-action="view"]').map(button=>Number(button.dataset.id)),[31,32,30,33,34]);
  assert.equal(env.get('#student-change-body').querySelectorAll('[data-student-change-action="cancel"]').length,1,'Only Pending requests offer cancellation.');
  for(const query of ['Later reason','New <b>day</b>','Approved note']){env.input('#student-change-search',query);assert.equal(env.get('#student-change-body').querySelectorAll('tr').length,1,'Search matches '+query);}
  env.input('#student-change-search','Current');assert.equal(env.get('#student-change-body').querySelectorAll('tr').length,5);env.input('#student-change-search','Not Approved');assert.equal(env.get('#student-change-body').querySelectorAll('tr').length,2);
  env.click('#student-change-clear');const status=env.get('#student-change-status');status.value='Not Approved';status.emit('change');assert.equal(env.get('#student-change-body').querySelectorAll('tr').length,2);assert.match(env.get('#student-change-showing').textContent,/Showing 2 of 5 requests/);
  env.input('#student-change-search','No such reason');assert.equal(env.get('#student-change-empty').hidden,false);assert.match(env.get('#student-change-empty').textContent,/match/i);env.click('#student-change-clear');assert.equal(env.get('#student-change-search').value,'');assert.equal(status.value,'');assert.equal(env.get('#student-change-empty').hidden,true);
  const view=env.click('[data-student-change-action="view"][data-id="31"]');view.focus();env.click('[data-student-change-action="view"][data-id="31"]');
  let dialog=env.get('#student-change-dialog');assert.equal(dialog.tagName,'DIALOG');assert.equal(dialog.open,true);assert.equal(env.document.body.style.overflow,'hidden');assert.equal(dialog.contains(env.document.activeElement),true);assert.match(dialog.textContent,/Later reason <script>bad<\/script>/);assert.match(dialog.textContent,/New <b>day<\/b>/);assert.doesNotMatch(dialog.innerHTML,/<script|<b>|<img/);
  dialog.emit('cancel');assert.equal(dialog.open,false);assert.equal(env.document.activeElement,view);assert.equal(env.document.body.style.overflow,'auto');
  env.click('[data-student-change-action="view"][data-id="30"]');dialog=env.get('#student-change-dialog');dialog.emit('click',{target:dialog,clientX:0,clientY:0});assert.equal(dialog.open,false);assert.equal(env.document.body.style.overflow,'auto');
  env.click('[data-student-change-action="view"][data-id="30"]');dialog=env.get('#student-change-dialog');dialog.emit('click',{target:dialog.querySelector('[data-student-change-close]'),clientX:200,clientY:200});assert.equal(dialog.open,false);
  // Native dialog close events are queued. Closing the old View dialog must not undo the new confirmation's scroll lock or steal focus.
  const pendingView=env.click('[data-student-change-action="view"][data-id="30"]');env.click('[data-student-change-dialog-cancel]');dialog=env.get('#student-change-dialog');
  assert.equal(dialog.open,true);assert.ok(env.get('#student-change-cancel-form'));assert.equal(env.document.body.style.overflow,'hidden');env.drainClose();assert.equal(env.document.body.style.overflow,'hidden');assert.equal(dialog.contains(env.document.activeElement),true);
  env.click('#student-change-dialog [data-student-change-close]');assert.equal(dialog.open,false);assert.equal(env.document.body.style.overflow,'auto');assert.equal(env.document.activeElement,pendingView);env.drainClose();assert.equal(env.document.body.style.overflow,'auto');
  env=setup({...baseline,requests:[request]});env.click('[data-student-change-action="cancel"][data-id="30"]');assert.equal(env.posts.length,0);dialog=env.get('#student-change-dialog');assert.equal(dialog.open,true);assert.match(dialog.textContent,/cancel/i);assert.ok(env.get('#student-change-cancel-form'));
  env.submit('#student-change-cancel-form');await flush();await flush();assert.equal(env.posts[0].url,'/api/change-requests/30/cancel');assert.equal(env.posts[0].method,'PATCH');assert.equal(env.posts[1].url,'/api/student');assert.match(table(),/Cancelled/);assert.equal(env.get('[data-student-change-action="cancel"]'),null);assert.equal(env.document.body.style.overflow,'auto');assert.equal(env.get('#cfo-change-form [type=submit]').disabled,false);
  assert.equal(env.document.activeElement,env.get('[data-student-change-action="view"][data-id="30"]'),'Successful cancellation returns focus to the refreshed request row.');
  env=setup({...baseline,requests:[request]});env.click('[data-student-change-action="cancel"]');let releaseCancel;env.hold(new Promise(resolve=>releaseCancel=resolve));env.submit('#student-change-cancel-form');dialog=env.get('#student-change-dialog');
  assert.equal(dialog.getAttribute('aria-busy'),'true');assert.equal(env.get('#student-change-cancel-form [type=submit]').disabled,true);dialog.emit('cancel');env.click('#student-change-dialog [data-student-change-close]');assert.equal(dialog.open,true,'Keep/Escape cannot interrupt a submitted cancellation.');env.submit('#student-change-cancel-form');assert.equal(env.posts.filter(row=>row.method==='PATCH').length,1);releaseCancel();await flush();await flush();assert.match(table(),/Cancelled/);
  env=setup({...baseline,requests:[request]});env.context.pendingStudentResources=new Set();const cancelRequest=env.context.cfoRequest;
  env.context.cfoRequest=(url,...args)=>url==='/api/student'?Promise.reject(new Error('Refresh temporarily unavailable.')):cancelRequest(url,...args);
  env.click('[data-student-change-action="cancel"]');env.submit('#student-change-cancel-form');await flush();await flush();assert.equal(env.notices.at(-1)[0],'Request cancelled');assert.match(table(),/Cancelled/);assert.equal(env.get('[data-student-change-action="cancel"]'),null);assert.equal(env.get('#cfo-change-form [type=submit]').disabled,false);assert.equal(env.context.pendingStudentResources.has('schedule_changes'),true);
  env=setup({...baseline,requests:[request]});env.click('[data-student-change-action="cancel"]');let releaseAway;env.hold(new Promise(resolve=>releaseAway=resolve));env.submit('#student-change-cancel-form');env.context.location.hash='#student-dashboard';env.context.dashboardCleanup();env.get('#student-change-page').remove();releaseAway();await flush();await flush();
  assert.equal(env.get('#student-change-page'),null);assert.equal(env.get('#student-change-dialog'),null);assert.equal(env.document.body.style.overflow,'auto');assert.equal(env.notices.length,0);assert.equal(env.context.cfoStudent.requests[0].status,'Cancelled','A delayed cancellation updates state without replacing another route.');
  env=setup({...baseline,requests:[approved]});const forged=new env.Element('button');forged.setAttribute('data-student-change-action','cancel');forged.setAttribute('data-id','31');env.get('#student-change-page').append(forged);forged.emit('click');assert.equal(env.posts.length,0);assert.equal(env.get('#student-change-dialog'),null,'A forged Cancel action cannot open confirmation for a reviewed request.');
  env=setup({...baseline,requests:[request]});env.fail(new Error('Only pending requests can be cancelled.'));env.click('[data-student-change-action="cancel"]');env.submit('#student-change-cancel-form');await flush();assert.match(env.notices.at(-1)[1],/pending/);assert.equal(env.get('#student-change-dialog').open,true);assert.equal(env.get('#student-change-cancel-form [type=submit]').disabled,false);
  env=setup();const reason=env.input('#change-reason','Unsaved reason'),form=env.get('#cfo-change-form');let renders=0,refreshes=[];
  env.context.studentRouteLoading=false;env.context.routeVersion=0;env.context.useLiveStudentState=false;env.context.renderedRoute=null;env.context.route=async()=>{renders++;reason.value='rerendered';};
  env.context.cfoRequest=async url=>{refreshes.push(url);return {...baseline,requests:[request]};};
  vm.runInContext(between('let studentLiveVersions=null',"document.addEventListener('focusout'"),env.context);env.document.emit('input',{target:reason});assert.equal(form.dataset.dirty,'true');
  vm.runInContext('pendingStudentResources.add("schedule_changes")',env.context);await env.context.refreshStudentWhenSafe();assert.equal(renders,0);assert.equal(reason.value,'Unsaved reason');assert.equal(refreshes.length,0);
  form.dataset.dirty='false';await env.context.refreshStudentWhenSafe();assert.equal(renders,1);assert.ok(refreshes.includes('/api/student?resources=schedule_changes'));
  env=setup(baseline,{demo:true});assert.match(env.html,/Your Requests/);assert.ok(env.get('#change-reason'));assert.equal(env.posts.length,0,'The demo page uses the layout without making backend requests.');env.input('#change-reason','Preview only reason');env.submit();await flush();assert.equal(env.posts.length,0);
  env.click('[data-student-change-action="cancel"]');env.submit('#student-change-cancel-form');await flush();await flush();assert.equal(env.notices.at(-1)[0],'Cancellation preview');assert.equal(env.get('#cfo-change-form [type=submit]').disabled,false);assert.equal(env.posts.length,0);
  env.input('#change-reason','Preview only request after cancelling');env.submit();await confirm(env);assert.equal(env.notices.at(-1)[0],'Request preview');assert.match(table(),/Preview only request after cancelling/);assert.equal(env.posts.length,0,'Completed preview cancellation and submission never call the backend.');
  console.log('PASS: real student request page, read-only assignment, Unicode 10–1000 validation/counter, captured reason-only confirmation, busy/error/draft/navigation guards, successful mutations despite refresh failure, real-status stats, newest/search/filter/empty table, escaped details, queued native dialog focus/Esc/backdrop/scroll, Pending-only cancellation and demo path.');
})().catch(error=>{console.error(error);process.exitCode=1;});
