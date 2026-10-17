const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const sandbox={module:{exports:{}}};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/cfo-account-ids.js'),'utf8'),sandbox);const ids=sandbox.module.exports;
assert.equal(ids.format('2410723'),'24-10723');assert.equal(ids.format('0500123'),'05-00123');assert.equal(ids.format('24-10723'),'24-10723');assert.equal(ids.format('24'),'24');assert.equal(ids.format('24-1'),'24-1');assert.equal(ids.format('letters'), '');
for(const value of ['24-10723','05-00123','00-00000','99-99999'])assert.equal(ids.valid(value),true,value);
for(const value of ['2410723','2-10723','24-1072','24-107234','AA-10723','24–10723','24-10723\n',2410723])assert.equal(ids.valid(value),false,String(value));
class Input {
  constructor(value=''){this.value=value;this.selectionStart=value.length;this.attributes=new Map();this.events=new Map();}
  addEventListener(event,handler){this.events.set(event,handler);}
  removeEventListener(event,handler){if(this.events.get(event)===handler)this.events.delete(event);}
  setAttribute(name,value){this.attributes.set(name,value);}
  removeAttribute(name){this.attributes.delete(name);}
  setCustomValidity(message){this.message=message;}
  setSelectionRange(start){this.selectionStart=start;}
  enter(value,caret=value.length){this.value=value;this.selectionStart=caret;this.events.get('input')();}
}
const input=new Input(),cleanup=ids.bind(input);
assert.equal(input.placeholder,'24-10723');assert.equal(input.attributes.get('inputmode'),'numeric');assert.equal(input.attributes.get('maxlength'),'8');assert.equal(input.attributes.get('pattern'),ids.pattern);
input.enter('2410723');assert.equal(input.value,'24-10723');assert.equal(input.selectionStart,8);assert.equal(input.message,'');
input.enter('24-1');assert.equal(input.message,ids.message);input.enter('0500123');assert.equal(input.value,'05-00123');assert.equal(input.message,'');
input.enter('2410723',2);assert.equal(input.value,'24-10723');assert.equal(input.selectionStart,2,'Editing before the separator preserves the caret');
input.enter('');assert.equal(input.message,'');cleanup();assert.equal(input.events.size,0);
const legacy=new Input('LEGACY-001');ids.bind(legacy,{allowOriginal:true,originalValue:'LEGACY-001'});assert.equal(legacy.value,'LEGACY-001');assert.equal(legacy.message,'');assert.equal(legacy.attributes.has('pattern'),false);
legacy.enter('2410723');assert.equal(legacy.value,'24-10723');assert.equal(legacy.message,'');assert.equal(legacy.attributes.get('maxlength'),'8');legacy.enter('LEGACY-001');assert.equal(legacy.value,'LEGACY-001');assert.equal(legacy.message,'');
console.log('PASS: ID mask, exact validation, leading zeroes, caret, numeric keyboard, input cleanup and unchanged legacy IDs.');
