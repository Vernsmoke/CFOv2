// Exercise the rendered editor's event/state logic with DOM stubs, not visual browser acceptance.
const vm=require('node:vm'), assert=require('node:assert/strict'), fs=require('node:fs');
const source=fs.readFileSync(0,'utf8');
const events=()=>({handlers:{},dataset:{},style:{},addEventListener(type,fn){this.handlers[type]=fn;},setAttribute(key,value){this[key]=value;}});
const form=events(), overlay=events(), inspector=events(), preview={}, status={}, title={};
const fields=new Map(), boxes=new Map(), controls=new Map();
const get=(map,key)=>{if(!map.has(key))map.set(key,events());return map.get(key);};
inspector.contains=input=>input.inspector===true;
inspector.querySelector=selector=>get(fields,selector);
overlay.querySelector=selector=>get(boxes,selector);
overlay.querySelectorAll=()=>[...boxes.values()];
form.isConnected=true;let valid=true;
form.checkValidity=()=>valid;
form.querySelector=selector=>({'.ce-status':status,'.ce-stage img':preview,'.ce-overlay':overlay,'.ce-fields':inspector,'#ce-selected-title':title}[selector]||get(controls,selector));
form.querySelectorAll=()=>[];
const host={querySelector:()=>form};
const requests=[], saves=[], timers=new Map(), urls=[];let timerId=0;
class FormDataStub{constructor(){this.values=new Map();}append(key,value){this.values.set(key,value);}set(key,value){this.values.set(key,value);}get(key){return this.values.get(key);}}
const context=vm.createContext({window:{},FormData:FormDataStub,Blob,
    URL:{createObjectURL(blob){urls.push(blob);return 'blob:preview-'+urls.length;},revokeObjectURL(){}},
    setTimeout(fn){timers.set(++timerId,fn);return timerId;},clearTimeout(id){timers.delete(id);},
});
vm.runInContext(source,context);
const request=(url,method,data)=>new Promise((resolve,reject)=>requests.push({url,method,data,resolve,reject}));
const click=dataset=>form.handlers.click({target:{closest:()=>({dataset})}});
const input=(key,value,type='text',isElement=true)=>form.handlers.input({target:{dataset:{setting:key},value,type,inspector:isElement}});
const tick=()=>new Promise(setImmediate);
(async()=>{
    context.window.mountCertificateEditor({host,template:{title:'Saved title',description:'Saved body',signatory:'CFO',font_family:'Verdana',font_size:19,image_path:'saved.png',logo_path:'seal.png'},request,save:(...args)=>saves.push(args)});
    assert.equal(requests.length,1);
    assert.equal(requests[0].data.get('elements[title][text]'),'Saved title');
    assert.equal(requests[0].data.get('elements[name][font_family]'),'Verdana');
    assert.equal(requests[0].data.get('image_path'),undefined);
    input('font_size','52','number');
    assert.equal(form.dataset.dirty,'true');
    [...timers.values()].at(-1)();
    assert.equal(requests.length,2);
    assert.equal(requests[1].data.get('elements[name][font_size]'),'52');
    requests[1].resolve({svg:'newest preview'});await tick();
    requests[0].resolve({svg:'outdated preview'});await tick();
    assert.equal(urls.length,1);
    assert.equal(await urls[0].text(),'newest preview');

    // Precise keyboard movement clamps to the certificate, including Shift increments.
    overlay.handlers.keydown({target:{dataset:{box:'name'}},key:'ArrowRight',shiftKey:true,preventDefault(){}});
    click({action:'preview'});
    assert.equal(requests.at(-1).data.get('elements[name][x]'),'190');
    requests.at(-1).resolve({svg:'moved'});await tick();
    input('x','359','number');
    overlay.handlers.keydown({target:{dataset:{box:'name'}},key:'ArrowRight',shiftKey:true,preventDefault(){}});
    click({action:'preview'});
    assert.equal(requests.at(-1).data.get('elements[name][x]'),'360');
    requests.at(-1).resolve({svg:'edge'});await tick();

    click({select:'title'});input('text','Updated title');
    click({action:'preview'});
    assert.equal(requests.at(-1).data.get('title'),'Updated title');
    assert.equal(requests.at(-1).data.get('elements[title][text]'),'Updated title');
    requests.at(-1).resolve({svg:'updated'});await tick();
    assert.equal(saves.length,0);

    click({action:'reset'});click({action:'preview'});
    assert.equal(requests.at(-1).data.get('remove_image'),'1');
    assert.equal(requests.at(-1).data.get('remove_logo'),'1');
    assert.equal(requests.at(-1).data.get('elements[name][font_size]'),'46');
    assert.equal(requests.at(-1).data.get('title'),'Certificate of Participation');
    requests.at(-1).resolve({svg:'reset'});await tick();
    assert.equal(saves.length,0,'Reset must be a draft until Save is confirmed');

    const submitting=form.onsubmit({preventDefault(){}});
    requests.at(-1).resolve({svg:'valid'});await submitting;
    assert.equal(saves.length,1);
    assert.equal(saves[0][0],'/api/admin/settings/certificate');
    assert.equal(saves[0][2].get('layout_version'),'2');
    valid=false;await form.onsubmit({preventDefault(){}});
    assert.equal(saves.length,1);
    valid=true;const failing=form.onsubmit({preventDefault(){}});
    requests.at(-1).reject(Error('Block is outside the certificate'));await failing;
    assert.equal(saves.length,1);assert.match(status.textContent,/outside/);
    console.log('PASS: legacy draft conversion, per-element edits, stale-preview protection, keyboard positioning, reset, and save validation.');
})().catch(error=>{console.error(error);process.exitCode=1;});
