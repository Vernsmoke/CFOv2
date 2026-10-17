const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const sandbox = {module:{exports:{}}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/admin-filter-dropdowns.js'), 'utf8'), sandbox);
const dropdowns = sandbox.module.exports;
const events = [], select = {
  selectedIndex:0, disabled:false,
  options:[{label:'All',value:''},{label:'A',value:'A'},{label:'Unavailable',value:'B',disabled:true},{label:'Disabled group',value:'C',parentElement:{tagName:'OPTGROUP',disabled:true}},{label:'Z',value:'Z'}],
  ownerDocument:{defaultView:{Event:class {constructor(type, options) {this.type = type; Object.assign(this, options);}}}},
  dispatchEvent(event) { events.push({type:event.type,bubbles:event.bubbles,value:this.options[this.selectedIndex].value}); }
};
assert.equal(dropdowns.commit(select, 1), true);
assert.equal(select.selectedIndex, 1);
assert.deepEqual(events, [{type:'input',bubbles:true,value:'A'},{type:'change',bubbles:true,value:'A'}], 'Existing listeners receive the chosen native select value, exactly once.');
for (const index of [1,2,3,-1,99]) assert.equal(dropdowns.commit(select, index), false, 'Unchanged and unavailable options do not fire filters.');
select.disabled = true; assert.equal(dropdowns.commit(select, 4), false); select.disabled = false;
select.options.splice(4, 1, {label:'New live section',value:'N'});
assert.equal(dropdowns.choices(select)[4].label, 'New live section', 'Options are read from the live select, without keeping a stale copy.');
assert.equal(dropdowns.commit(select, 4), true); assert.equal(events.at(-1).value, 'N');
assert.equal(events.length, 4);
for (const [rect,width,height] of [
  [{left:20,bottom:80,width:220},1440,900],
  [{left:190,bottom:310,width:220},360,640],
  [{left:20,bottom:600,width:280},320,640]
]) {
  const box = dropdowns.placement(rect,width,height);
  assert.ok(box.top > rect.bottom, 'Menus always begin below the control, including near the bottom of the screen.');
  assert.ok(box.left >= 0 && box.left + box.width <= width, 'The open menu stays inside narrow screens.');
  assert.ok(box.maxHeight <= 360 && box.maxHeight > 0, 'Long option lists get a scrollable height.');
}
// Exercise the actual click/keyboard handlers for ordinary student form selects.
function control(bodyClasses, isFilter) {
  class Element {
    constructor() { this.children=[]; this.dataset={}; this.style={}; this.attributes=new Map(); this.listeners={}; this.scrollTop=0; this.clientHeight=100; this.offsetHeight=34; }
    setAttribute(name,value) { this.attributes.set(name,String(value)); }
    getAttribute(name) { return this.attributes.get(name)??null; }
    hasAttribute(name) { return this.attributes.has(name); }
    removeAttribute(name) { this.attributes.delete(name); }
    appendChild(node) { node.parentElement=this; node.offsetTop=this.children.length*34; this.children.push(node); return node; }
    remove() { this.parentElement.children=this.parentElement.children.filter(node=>node!==this); }
    addEventListener(type,handler) { this.listeners[type]=handler; }
    querySelector(selector) { return this.children.find(node=>String(node.dataset.index)===selector.match(/data-index="(\d+)"/)?.[1]); }
    closest() { return this.dataset.index===undefined?null:this; }
  }
  const body=new Element(), doc={body,listeners:{},createElement:()=>new Element(),addEventListener(type,handler){this.listeners[type]=handler;}};
  body.matches=selector=>selector==='.dashboard-mode:not(.admin-mode)'?bodyClasses.includes('dashboard-mode')&&!bodyClasses.includes('admin-mode'):bodyClasses.some(name=>selector.split(',').includes('.'+name));
  doc.defaultView={innerWidth:360,innerHeight:640,Event:class{constructor(type,options){this.type=type;Object.assign(this,options);}},MutationObserver:class{observe(){}disconnect(){}},getComputedStyle:()=>({font:'14px Arial'}),addEventListener(){}};
  const native=new Element(), emitted=[];
  let rect={left:30,top:570,bottom:610,width:280},scrolled=false;
  Object.assign(native,{tagName:'SELECT',disabled:false,multiple:false,size:0,isConnected:true,selectedIndex:0,ownerDocument:doc,labels:[{textContent:'Course / Program'}],options:[{label:'Select course',value:'',disabled:true},{label:'BSIT',value:'BSIT'},{label:'BSN',value:'BSN'}],matches:()=>isFilter,focus(){},getClientRects:()=>[rect],getBoundingClientRect:()=>rect,scrollIntoView(){scrolled=true;rect={...rect,top:280,bottom:320};},closest:()=>null,dispatchEvent(event){emitted.push({type:event.type,value:this.options[this.selectedIndex].value});}});
  dropdowns.mount(doc);
  const event={target:native,button:0,preventDefault(){this.prevented=true;},stopPropagation(){}};
  doc.listeners.pointerdown(event);
  return {doc,native,event,emitted,body,get rect(){return rect;},get scrolled(){return scrolled;}};
}
const studentControl=control(['dashboard-mode'],false);
assert.equal(studentControl.event.prevented,true,'Ordinary student selects suppress the browser menu that can open upward.');
assert.equal(studentControl.scrolled,true,'A control near the screen bottom is brought into view to make room below it.');
const studentMenu=studentControl.body.children[0];
assert.ok(parseFloat(studentMenu.style.top)>studentControl.rect.bottom);
assert.equal(studentMenu.getAttribute('role'),'listbox');
assert.equal(studentControl.native.getAttribute('aria-expanded'),'true');
studentMenu.listeners.click({target:studentMenu.children[2],stopPropagation(){}});
assert.equal(studentControl.native.selectedIndex,2);
assert.deepEqual(studentControl.emitted,[{type:'input',value:'BSN'},{type:'change',value:'BSN'}]);
assert.equal(studentControl.body.children.length,0);
assert.equal(studentControl.native.getAttribute('aria-expanded'),null);
const keyboard={target:studentControl.native,key:'ArrowDown',preventDefault(){this.prevented=true;},stopPropagation(){}};
studentControl.doc.listeners.keydown(keyboard);
assert.equal(keyboard.prevented,true);
assert.ok(parseFloat(studentControl.body.children[0].style.top)>studentControl.rect.bottom);
studentControl.doc.listeners.keydown({...keyboard,key:'Escape'});
assert.equal(studentControl.body.children.length,0);
assert.equal(control(['admin-background'],false).event.prevented,undefined,'Unrelated admin form controls retain their existing behavior.');
assert.equal(control(['admin-background'],true).event.prevented,true,'Existing admin filters still open downward.');
assert.equal(control(['dashboard-mode','admin-mode'],false).event.prevented,undefined);
for (const file of ['resources/views/admin.blade.php','resources/views/student/index.blade.php','resources/views/layouts/registration-admin.blade.php']) {
  const content = fs.readFileSync(path.join(__dirname,'../..',file),'utf8');
  assert.equal((content.match(/asset\('admin-filter-dropdowns.js'\)/g) || []).length, 1);
  assert.equal((content.match(/asset\('admin-filter-dropdowns.css'\)/g) || []).length, 1);
}
console.log('PASS: downward positioning, mobile bounds, live options, disabled choices, native input/change events, and all portal entry points.');
