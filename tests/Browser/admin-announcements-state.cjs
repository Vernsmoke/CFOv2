// Executes the production announcements module and the shared save/live guards.
// Isolated DOM/API fixtures verify state transitions; this is not visual browser QA.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const read=name=>fs.readFileSync(path.join(__dirname,'../..',name),'utf8');
const source=read('public/admin-dashboard.js');
function between(start,end){const a=source.indexOf(start),b=source.indexOf(end,a);assert.ok(a>=0&&b>a,start);return source.slice(a,b);}
const escapeHTML=value=>String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
const flush=async()=>{for(let i=0;i<10;i++)await Promise.resolve();};
const button=(label,action,id='',variant='')=>`<button type="button" class="${variant}" data-action="${action}" data-id="${escapeHTML(id)}">${label}</button>`;
class Control{
  constructor(value=''){this.value=value;this.dataset={};this.attrs={};this.listeners={};this.style={};this.disabled=false;this.hidden=false;this.isConnected=true;this.children=new Map();this.nodes=[];this.textContent='';this.innerHTML='';this.classes=new Set();this.classList={contains:name=>this.classes.has(name),add:name=>this.classes.add(name),remove:name=>this.classes.delete(name),toggle:(name,on)=>on===undefined?(this.classes.has(name)?this.classes.delete(name):this.classes.add(name)):on?this.classes.add(name):this.classes.delete(name)};}
  addEventListener(name,callback){this.listeners[name]=callback;}
  removeEventListener(name){delete this.listeners[name];}
  setAttribute(name,value){this.attrs[name]=String(value);}
  removeAttribute(name){delete this.attrs[name];}
  hasAttribute(name){return Object.hasOwn(this.attrs,name);}
  querySelector(selector){return this.children.get(selector)||null;}
  querySelectorAll(selector){if(selector==='button')return this.buttons||[];return this.all?.get(selector)||[];}
  get childNodes(){return this.nodes;}
  replaceChildren(...nodes){this.nodes=nodes;}
  focus(){this.focused=true;}
  closest(selector){return selector==='form'?this.form:null;}
  contains(node){return node===this||[...this.children.values()].some(child=>child.contains(node));}
  checkValidity(){return !this.invalid;}
  reportValidity(){this.reported=true;return this.checkValidity();}
  setCustomValidity(message){this.validationMessage=message;this.invalid=Boolean(message);}
  scrollIntoView(){this.scrolled=true;}
}

function moduleHarness(initialRows=[]){
  const page=new Control(),form=new Control(),dialog=new Control(),error=new Control();
  form.elements=Object.fromEntries(['content','draft_id','publication_key','draft_revision'].map(name=>[name,new Control()]));
  for(const control of Object.values(form.elements))control.form=form;
  form.children.set('#ann-content-error',error);page.children.set('#connected-announcement-form',form);
  const publish=new Control(),saveDraft=new Control();form.all=new Map([['[data-action="ann-publish"],[data-action="ann-save-draft"]',[publish,saveDraft]]]);
  dialog.open=false;
  const records=structuredClone(initialRows),calls=[],dialogs=[],notices=[];
  const context=vm.createContext({window:{},location:{hash:'#admin-announcements'},crypto:require('node:crypto').webcrypto,Uint8Array});
  vm.runInContext(read('public/admin-announcements.js'),context);
  const module=context.window.CfoAnnouncements;
  const $=selector=>selector==='#connected-announcement-form'?form:selector==='#dialog'?dialog:null;
  const h={context,module,page,form,dialog,error,publish,saveDraft,records,calls,dialogs,notices,renders:0};
  const render=()=>{h.renders++;page.innerHTML=module.pageHtml({rows:records,panel:(title,body)=>`<section><h2>${escapeHTML(title)}</h2>${body}</section>`,button});module.bind(page);};
  let sequence=30;
  const backend=async(url,method,values)=>{
    const id=Number(url.match(/announcements\/(\d+)/)?.[1]),isDelete=method==='DELETE',existing=records.find(row=>row.id===id);
    if(isDelete)return {message:'Announcement deleted'};
    const published=url.endsWith('/publish')||url==='/api/admin/announcements';
    const row={...existing,id:existing?.id||sequence++,title:values.title,preview:values.preview,body:values.body,
      publication_key:values.publication_key||existing?.publication_key||'saved-shared-key',draft_revision:'revision-'+sequence,
      updated_at:'2026-10-10T04:00:00Z',published_at:published?'2026-10-10T04:00:00Z':null};
    return {message:'Announcement saved',announcement:row};
  };
  h.saveConnected=async(url,method,values,confirmed,onSaved)=>{calls.push({url,method,values,confirmed});const result=await h.backend(url,method,values);dialog.open=false;onSaved?.(result);render();};
  h.backend=backend;
  h.handlers=module.actions({$,rows:()=>records,saveConnected:(...args)=>h.saveConnected(...args),
    showDialog:(title,body,controls)=>{dialog.open=true;h.modal={title,body,controls};dialogs.push(h.modal);},toast:message=>notices.push(message),button,
    closeDialog:()=>{dialog.open=false;},render});
  h.edit=content=>{form.elements.content.value=content;form.elements.content.listeners.input();};
  h.cancel=()=>{dialog.open=false;dialog.listeners.close?.();};
  h.confirm=()=>h.handlers['ann-confirm-save']();
  h.render=render;render();return h;
}

async function moduleChecks(){
  const h=moduleHarness(),{module:m}=h;
  assert.equal(m.parse(' \r\n '),null);
  assert.deepEqual({...m.parse('  Important notice\n\nPlease visit the CFO office.\nBring your receipt.  ')},
    {title:'Important notice',preview:'Please visit the CFO office.',body:'Please visit the CFO office.\nBring your receipt.'});
  assert.deepEqual({...m.parse('One-line notice')},{title:'One-line notice',preview:'One-line notice',body:'One-line notice'});
  assert.equal(m.compose({title:'One-line notice',body:'One-line notice'}),'One-line notice','same-title body is not duplicated in the single composer');
  assert.equal(m.compose({title:'Important notice',body:'Full body'}),'Important notice\n\nFull body');
  const longTitle='😀'.repeat(190),longBody='😀'.repeat(10000),tooLongTitle=longTitle+'😀';
  let parsed=m.parse(longTitle+'\n\n'+longBody);assert.equal(parsed.title,longTitle);assert.equal(parsed.body,longBody);assert.equal(m.validate(parsed),'');
  parsed=m.parse(tooLongTitle+'\n\nWhole body');assert.equal(parsed.title,tooLongTitle,'overlong first lines are retained for correction, never silently sliced');assert.match(m.validate(parsed),/190/);
  parsed=m.parse('Good title\n\n'+longBody+'😀');assert.equal(parsed.body,longBody+'😀');assert.match(m.validate(parsed),/10,000/);
  assert.equal(Array.from(m.preview('😀'.repeat(145))).length,140,'preview limit counts Unicode characters and includes the ellipsis');
  assert.ok(m.preview('😀'.repeat(145)).endsWith('…'));assert.equal(m.preview('  First sentence.   Remaining detail  '),'First sentence.');
  assert.equal(h.publish.disabled,true);assert.equal(h.saveDraft.disabled,true,'blank composer cannot save or publish');
  assert.match(h.page.innerHTML,/<textarea[^>]*name="content"/);assert.doesNotMatch(h.page.innerHTML,/name="title"|name="preview"|name="body"/,'the new composer has exactly one content entry field');
  let prevented=false;h.form.onsubmit({preventDefault(){prevented=true;}});assert.equal(prevented,true);assert.equal(h.form.reported,true);assert.equal(h.calls.length,0,'Enter cannot submit a native GET request');
  h.handlers['ann-save-draft']();h.handlers['ann-publish']();assert.equal(h.dialogs.length,0);
  h.edit(tooLongTitle+'\n\nWhole body');assert.equal(h.publish.disabled,true);assert.match(h.error.textContent,/190/);h.handlers['ann-save-draft']();assert.equal(h.dialogs.length,0);
  h.edit('Office reminder\n\nBring your verified receipt. Extra details remain in the full body.');
  assert.equal(h.publish.disabled,false);assert.equal(h.form.dataset.dirty,'true');
  const key=h.form.elements.publication_key.value;
  h.handlers['ann-save-draft']();assert.equal(h.calls.length,0);assert.match(h.modal.body,/admin-only draft/);assert.match(h.modal.controls,/ann-confirm-save/);
  h.cancel();assert.equal(h.form.elements.content.value,'Office reminder\n\nBring your verified receipt. Extra details remain in the full body.');assert.equal(h.form.elements.publication_key.value,key);
  h.handlers['ann-save-draft']();await h.confirm();assert.equal(h.calls.length,1);assert.equal(h.calls[0].url,'/api/admin/announcements/drafts');assert.equal(h.calls[0].method,'POST');assert.equal(h.calls[0].confirmed,true);
  assert.equal(h.calls[0].values.publication_key,key);assert.equal(h.calls[0].values.preview,'Bring your verified receipt.');
  assert.equal(h.records.length,1);assert.equal(h.records[0].published_at,null);assert.equal(h.form.elements.draft_id.value,String(h.records[0].id));assert.equal(h.form.dataset.dirty,'false');
  assert.match(h.page.innerHTML,/Draft announcements/);assert.match(h.page.innerHTML,/ann-edit-draft/);
  h.edit('Office reminder\n\nBring your corrected receipt.');h.handlers['ann-save-draft']();await h.confirm();
  assert.equal(h.calls[1].method,'PUT');assert.equal(h.calls[1].url,'/api/admin/announcements/'+h.records[0].id);assert.ok(h.calls[1].values.draft_revision,'editing includes the current shared-draft version');
  h.handlers['ann-publish']();assert.match(h.modal.body,/notify approved students/);await h.confirm();
  assert.match(h.calls[2].url,/\/publish$/);assert.ok(h.calls[2].values.draft_revision);assert.ok(h.records[0].published_at);assert.equal(h.form.elements.content.value,'');assert.equal(h.form.elements.draft_id.value,'');assert.equal(h.form.dataset.dirty,'false');

  const stored={id:1,title:'T'.repeat(190),body:'Existing saved full body',preview:'P'.repeat(280),published_at:null,updated_at:'2026-10-10',publication_key:'shared-key',draft_revision:'shared-revision'};
  const legacy=moduleHarness([stored]);legacy.handlers['ann-edit-draft'](1);assert.equal(legacy.form.elements.content.value,legacy.module.compose(stored));assert.equal(legacy.form.dataset.dirty,'false');
  legacy.handlers['ann-save-draft']();await legacy.confirm();assert.equal(legacy.calls[0].values.title,stored.title);assert.equal(legacy.calls[0].values.body,stored.body);assert.equal(legacy.calls[0].values.preview,stored.preview,'editing an unchanged legacy draft retains its saved 280-character preview');
  assert.equal(legacy.calls[0].values.draft_revision,'shared-revision');

  const published={...stored,id:2,title:'<img src=x onerror=alert(1)>',body:'<script>unsafe()</script>\nFull public content',preview:'<b>Preview</b>',author:{name:'<em>Admin</em>'},published_at:'2026-10-10'};
  const safe=moduleHarness([stored,published]);assert.doesNotMatch(safe.page.innerHTML,/<img\b|<script\b|<b>Preview|<em>Admin/);assert.match(safe.page.innerHTML,/&lt;img/);
  safe.handlers['ann-view'](2);assert.match(safe.modal.body,/&lt;script/);assert.doesNotMatch(safe.modal.body,/<script\b|<b>/);
  safe.handlers['ann-edit-draft'](2);assert.equal(safe.form.elements.draft_id.value,'','published rows have no edit behavior');
  safe.handlers['ann-publish-draft'](2);assert.equal(safe.calls.length,0,'a published row cannot be published again from stale controls');
  safe.handlers['ann-delete'](2);assert.match(safe.modal.body,/student notifications/);assert.match(safe.modal.body,/&lt;img/);safe.cancel();assert.equal(safe.records.length,2);assert.equal(safe.calls.length,0);
  safe.handlers['ann-delete'](2);await safe.confirm();assert.equal(safe.calls[0].method,'DELETE');assert.equal(safe.records.length,1);

  const dirty=moduleHarness([stored,{...stored,id:3,title:'Second shared draft',publication_key:'other-key',draft_revision:'other-revision'}]);
  dirty.edit('Unsaved composer\n\nKeep these details.');const unsaved=dirty.form.elements.content.value,unsavedKey=dirty.form.elements.publication_key.value;
  dirty.handlers['ann-clear']();assert.match(dirty.modal.title,/unsaved/);dirty.cancel();assert.equal(dirty.form.elements.content.value,unsaved);
  dirty.handlers['ann-edit-draft'](1);assert.match(dirty.modal.title,/unsaved/);dirty.cancel();assert.equal(dirty.form.elements.content.value,unsaved);
  dirty.handlers['ann-publish-draft'](1);assert.match(dirty.modal.body,/Unsaved composer text will be kept/);await dirty.confirm();assert.equal(dirty.calls[0].values.title,stored.title);assert.equal(dirty.calls[0].values.preview,stored.preview);
  assert.equal(dirty.form.elements.content.value,unsaved);assert.equal(dirty.form.elements.publication_key.value,unsavedKey);assert.equal(dirty.form.dataset.dirty,'true','publishing another saved draft preserves dirty text after redraw');
  dirty.handlers['ann-delete'](3);await dirty.confirm();assert.equal(dirty.form.elements.content.value,unsaved);assert.equal(dirty.form.elements.publication_key.value,unsavedKey);assert.equal(dirty.form.dataset.dirty,'true');
  dirty.handlers['ann-clear']();dirty.handlers['ann-confirm-discard']();assert.equal(dirty.form.elements.content.value,'');assert.notEqual(dirty.form.elements.publication_key.value,unsavedKey);assert.equal(dirty.form.dataset.dirty,'false');

  const linked=moduleHarness([stored]);linked.handlers['ann-edit-draft'](1);linked.edit(stored.title+'\n\nUnsaved linked changes');const linkedText=linked.form.elements.content.value;
  linked.handlers['ann-delete'](1);await linked.confirm();assert.equal(linked.form.elements.content.value,linkedText);assert.equal(linked.form.elements.draft_id.value,'');assert.equal(linked.form.elements.draft_revision.value,'');assert.equal(linked.form.dataset.dirty,'true','deleting a loaded draft keeps its unsaved text as a fresh composer');
  linked.handlers['ann-save-draft']();await linked.confirm();assert.equal(linked.calls[1].url,'/api/admin/announcements/drafts','deleted draft content is not sent back to a missing draft ID');

  const duplicate=moduleHarness();duplicate.edit('A notice\n\nFull detail');duplicate.handlers['ann-save-draft']();let release;const original=duplicate.backend;
  duplicate.backend=async(...args)=>{await new Promise(resolve=>{release=resolve;});return original(...args);};
  const saving=duplicate.confirm();await flush();await duplicate.confirm();duplicate.handlers['ann-clear']();assert.equal(duplicate.calls.length,1,'module confirmation also guards repeated actions while saving');
  assert.equal(duplicate.form.elements.content.value,'A notice\n\nFull detail');release();await saving;

  const failed=moduleHarness();failed.edit('Draft notice\n\nKeep it after an error');const failedKey=failed.form.elements.publication_key.value;
  failed.saveConnected=async()=>{failed.dialog.open=false;};failed.handlers['ann-save-draft']();await failed.confirm();
  assert.equal(failed.form.elements.content.value,'Draft notice\n\nKeep it after an error');assert.equal(failed.form.elements.publication_key.value,failedKey);assert.equal(failed.form.elements.draft_id.value,'');assert.equal(failed.form.dataset.dirty,'true');
  failed.saveConnected=async(url,method,values,confirmed,onSaved)=>{failed.calls.push({url,method,values,confirmed});onSaved({announcement:{id:99,...values,published_at:null,draft_revision:'successful-version'}});throw Error('GET state refresh failed after the draft save');};
  failed.handlers['ann-save-draft']();await assert.rejects(failed.confirm(),/GET state refresh failed/);
  // A successful server mutation is retained in module state even if its subsequent helper refresh fails.
  failed.render();assert.equal(failed.form.elements.draft_id.value,'99');assert.equal(failed.records[0].id,99);assert.equal(failed.form.dataset.dirty,'false');

  const navigated=moduleHarness();navigated.edit('Delayed notice\n\nSaved away from this route');navigated.handlers['ann-save-draft']();let finish;
  navigated.backend=async()=>{await new Promise(resolve=>{finish=resolve;});return {announcement:{id:40,title:'Delayed notice',body:'Saved away from this route',preview:'Saved away from this route',published_at:null,draft_revision:'new-version'}};};
  const deferred=navigated.confirm();await flush();navigated.context.location.hash='#admin-dashboard';const before=navigated.renders;
  // The shared helper owns its ordinary reload; this fixture suppresses it to isolate the module route guard.
  navigated.render=()=>{};finish();await deferred;assert.equal(navigated.renders,before+1,'module does not add a second announcements render after the route changes');
}

function saveHarness(options={}){
  const form=options.form||new Control(),textarea=options.form?.elements.content||new Control('Important notice\nKeep this unsaved draft.'),dialog=options.dialog||new Control(),page=new Control();
  if(!options.form){form.elements={content:textarea};form.dataset.dirty='true';}textarea.form=form;form.children.set('textarea',textarea);page.children.set('form',form);
  dialog.open=false;dialog.showModal=()=>{dialog.open=true;};dialog.close=()=>{dialog.open=false;dialog.listeners.close?.();};dialog.buttons=[new Control(),new Control()];dialog.children.set('form',null);
  const calls=[],notices=[],errors=[],draws=[];
  const context=vm.createContext({window:{CFO_BACKEND:{user:{}},CfoUsability:{formError:(...args)=>errors.push(args)}},location:{hash:'#admin-announcements'},document:{querySelector:selector=>selector==='#page form'?form:null},
    dialog,dialogOpener:null,batchSaveBusy:false,FormData:class FormData{},closeDialog:()=>dialog.close(),button:(label,action)=>`<button type="button" data-action="${action}">${label}</button>`,escapeHTML,
    showDialog:(title,body,controls)=>{dialog.open=true;context.modal={title,body,controls};dialog.nodes=[{title,body,controls}];dialog.children.set('form',null);},
    toast:message=>notices.push(message),renderConnectedAdmin:async()=>{draws.push(context.location.hash);},actions:{},
    fetch:async()=>{throw Error('the isolated backend override must be used');},
  });
  vm.runInContext('let pendingConnectedSave=null;let connectedSaveBusy=false;let cfoRouteVersion=1;',context);
  vm.runInContext(between('    function cancelConnectedSave()','    const connectedStudent ='),context);
  context.backend=async(url,method,values)=>{calls.push({url,method,values});return {message:'Announcement saved',announcement:{id:22,...values,published_at:values.publish?'2026-10-10T04:00:00Z':null}};};
  return {context,form,textarea,dialog,calls,notices,errors,draws,pending:()=>vm.runInContext('pendingConnectedSave',context),busy:()=>vm.runInContext('connectedSaveBusy',context)};
}

async function realHelperModuleChecks(){
  const h=moduleHarness(),shared=saveHarness({form:h.form,dialog:h.dialog});
  h.saveConnected=(...args)=>shared.context.saveConnected(...args);
  shared.context.backend=async(...args)=>{shared.calls.push({url:args[0],method:args[1],values:args[2]});return h.backend(...args);};
  shared.context.renderConnectedAdmin=async()=>{throw Error('The state refresh failed after the mutation.');};
  h.edit('Saved draft\n\nPreserve the server result if GET state fails.');h.handlers['ann-save-draft']();await h.confirm();
  assert.equal(shared.calls.length,1);assert.equal(h.records.length,1);assert.equal(h.form.elements.draft_id.value,String(h.records[0].id));assert.equal(h.form.dataset.dirty,'false');
  assert.match(shared.notices.at(-1),/state refresh failed/);assert.equal(shared.busy(),false);
  h.handlers['ann-save-draft']();await h.confirm();assert.equal(shared.calls[1].method,'PUT','a failed GET after a successful POST retains the saved draft ID and cannot create a duplicate draft');

  const error=moduleHarness(),errorShared=saveHarness({form:error.form,dialog:error.dialog});error.saveConnected=(...args)=>errorShared.context.saveConnected(...args);
  errorShared.context.backend=async()=>{throw Error('Another admin updated this draft.');};
  error.edit('Keep my draft\n\nCorrect it after the request fails.');const key=error.form.elements.publication_key.value;
  error.handlers['ann-save-draft']();await error.confirm();assert.equal(error.form.elements.content.value,'Keep my draft\n\nCorrect it after the request fails.');assert.equal(error.form.elements.publication_key.value,key);assert.equal(error.form.dataset.dirty,'true');assert.equal(errorShared.busy(),false);
  assert.match(errorShared.notices.at(-1),/Another admin/);

  const cancelled=moduleHarness([{id:1,title:'Published',body:'Visible text',preview:'Visible text',published_at:'2026-10-10'}]);
  cancelled.edit('Unsubmitted draft\n\nStill being edited.');cancelled.handlers['ann-save-draft']();cancelled.cancel();
  cancelled.handlers['ann-view'](1);await cancelled.confirm();assert.equal(cancelled.calls.length,0,'an old cancelled save intent cannot run when a different View dialog opens');
}

async function sharedSaveChecks(){
  const h=saveHarness(),values={title:'Important notice',preview:'Keep this unsaved draft.',body:'Important notice\nKeep this unsaved draft.',publish:false,publication_key:'stable-key'};
  await h.context.saveConnected('/api/admin/announcements','POST',values);
  assert.equal(h.calls.length,0,'saving waits for the existing confirmation dialog');
  assert.equal(h.pending().form,h.form);assert.equal(h.pending().values,values);
  h.context.cancelConnectedSave();assert.equal(h.calls.length,0);assert.equal(h.textarea.value,'Important notice\nKeep this unsaved draft.','cancelling confirmation retains the page composer');
  await h.context.saveConnected('/api/admin/announcements','POST',values);
  const realBackend=h.context.backend;let release;
  h.context.backend=async(...args)=>{await new Promise(resolve=>{release=resolve;});return realBackend(...args);};
  const saving=h.context.saveConnected('/api/admin/announcements','POST',values,true);
  await flush();assert.equal(h.busy(),true);assert.ok(h.dialog.buttons.every(button=>button.disabled));
  await h.context.saveConnected('/api/admin/announcements','POST',values,true);assert.equal(h.calls.length,0,'a repeated confirmation cannot start another mutation');
  h.context.cancelConnectedSave();assert.ok(h.pending(),'busy confirmation cannot be cancelled into an editable duplicate save');
  release();await saving;assert.equal(h.calls.length,1);assert.equal(h.busy(),false);assert.ok(h.dialog.buttons.every(button=>!button.disabled));

  const failure=saveHarness();await failure.context.saveConnected('/api/admin/announcements','POST',values);
  failure.context.backend=async()=>{const error=Error('This draft changed. Reload it before saving.');error.fields={title:['This draft changed.']};throw error;};
  await failure.context.saveConnected('/api/admin/announcements','POST',values,true);
  assert.equal(failure.textarea.value,'Important notice\nKeep this unsaved draft.');assert.equal(failure.busy(),false);assert.equal(failure.draws.length,0);
  assert.equal(failure.errors[0][0],failure.form);assert.match(failure.notices.at(-1),/draft changed/);
}

async function liveGuardChecks(){
  let focused=false,dirty=true;const renders=[],textarea={value:'Unsaved updated announcement'},dialog={open:false};
  const live=vm.createContext({Set,Number,Object,window:{},dialog,batchSaveBusy:false,mySchedulesController:null,
    document:{hidden:false,activeElement:{matches:()=>focused},addEventListener(){},querySelector:selector=>selector==='#page form[data-dirty="true"]'&&dirty?{}:null},
    $:()=>null,setTimeout(){},clearInterval(){},setInterval(){},backend:async()=>({}),renderConnectedAdmin:async resources=>{renders.push(Array.from(resources));textarea.value='refreshed';}});
  vm.runInContext(between('    function setLiveStatus(','    function connectAdminEvents()'),live);
  await live.applyLiveVersions({year:2026,announcements:1});await live.applyLiveVersions({year:2026,announcements:2});
  assert.equal(renders.length,0);assert.equal(textarea.value,'Unsaved updated announcement');
  assert.equal(vm.runInContext('pendingLiveResources.has("announcements")',live),true,'dirty composer defers announcement updates after blur');
  dirty=false;focused=true;await live.refreshAdminWhenSafe();assert.equal(renders.length,0,'typing retains the composer');
  focused=false;dialog.open=true;await live.refreshAdminWhenSafe();assert.equal(renders.length,0,'open confirmation defers live updates');
  dialog.open=false;await live.refreshAdminWhenSafe();assert.equal(renders.length,1);assert.ok(renders[0].includes('announcements'));
}

(async()=>{
  await moduleChecks();await realHelperModuleChecks();await sharedSaveChecks();await liveGuardChecks();
  console.log('PASS: single composer, Unicode limits, lossless legacy drafts, create/edit/publish/delete, escaped content, unsaved-draft protection, shared confirmation, duplicate-save/error/cancel retention, route guards and deferred live refresh.');
})().catch(error=>{console.error(error);process.exitCode=1;});
