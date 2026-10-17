<style>
  .template-editor { --ink: #132b43; }
  .template-editor .ce-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
  .template-editor .ce-toolbar p { margin:0; max-width:650px; }
  .template-editor .ce-layout { display:grid; grid-template-columns:155px minmax(300px,1fr) 255px; gap:16px; align-items:start; }
  .template-editor .ce-panel { padding:18px; border:1px solid var(--border); border-radius:12px; background:#fff; min-width:0; }
  .template-editor h3 { font-size:13px; margin:0 0 14px; color:var(--ink); }
  .template-editor .ce-elements { display:grid; gap:7px; }
  .template-editor .ce-elements button { text-align:left; white-space:normal; justify-content:flex-start; width:100%; }
  .template-editor button[aria-pressed=true] { background:#e8f4ef; border-color:#075a43; color:#075a43; }
  .template-editor .ce-stage { position:relative; aspect-ratio:1120/790; background:#f6f3ea; border:1px solid #d9c7a1; border-radius:4px; overflow:hidden; }
  .template-editor .ce-stage img { display:block; width:100%; height:100%; }
  .template-editor .ce-stage img[hidden] { display:none; }
  .template-editor .ce-overlay { position:absolute; inset:0; }
  .template-editor .ce-box { position:absolute; padding:0; background:transparent; border:1px solid transparent; cursor:move; touch-action:none; border-radius:2px; }
  .template-editor .ce-box:hover, .template-editor .ce-box:focus-visible { border:1px dashed #075a43; background:#075a430a; }
  .template-editor .ce-box[aria-pressed=true] { border:2px dashed #075a43; background:#075a4308; }
  .template-editor .ce-box.ce-logo-empty { border:1px dashed #9aa99f; color:#586961; font-size:10px; background:#ffffff88; }
  .template-editor .ce-fields { display:grid; gap:13px; }
  .template-editor .ce-pair { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .template-editor .ce-settings { margin-top:16px; }
  .template-editor .ce-general { display:grid; grid-template-columns:1fr 1fr; gap:13px; }
  .template-editor .ce-general .ce-full { grid-column:1/-1; }
  .template-editor .field { min-width:0; }
  .template-editor input, .template-editor select, .template-editor textarea { width:100%; min-width:0; box-sizing:border-box; }
  .template-editor input[type=color] { height:38px; padding:3px; }
  .template-editor input[type=checkbox] { width:auto; }
  .template-editor textarea { min-height:88px; resize:vertical; }
  .template-editor .ce-note { font-size:12px; color:#596971; line-height:1.5; margin:12px 0 0; }
  .template-editor .ce-status { min-height:36px; font-size:12px; margin:12px 0 0; }
  @media(max-width:1250px) { .template-editor .ce-layout { grid-template-columns:140px minmax(280px,1fr); } .template-editor .ce-inspector { grid-column:1/-1; } .template-editor .ce-fields { grid-template-columns:repeat(3,minmax(0,1fr)); } }
  @media(max-width:700px) { .template-editor .ce-layout { grid-template-columns:minmax(0,1fr); } .template-editor .ce-elements { grid-template-columns:1fr 1fr; } .template-editor .ce-general, .template-editor .ce-fields { grid-template-columns:1fr; } .template-editor .ce-panel { padding:13px; } }
</style>
<script>
window.mountCertificateEditor = function ({host, template, request, save}) {
    const defaults = @json(\App\Services\CertificateTemplate::editorDefaults());
    const labels = @json(\App\Services\CertificateTemplate::elementLabels());
    const fonts = @json(array_keys(\App\Services\CertificateTemplate::FONTS));
    const clone = value => JSON.parse(JSON.stringify(value));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    let draft = {...clone(defaults), ...clone(template), layout_version:2};
    if (template.layout_version != 2) {
        draft.elements = clone(defaults.elements);
        for (const block of Object.values(draft.elements)) {
            block.font_family = template.font_family || defaults.font_family;
            block.font_size = Math.min(80, Math.max(10, Math.round(block.font_size * (template.font_size || 19) / 19)));
        }
        for (const key of ['title','description','signatory']) draft.elements[key].text = draft[key];
    }
    let selected='name', timer, revision=0, saving=false;
    const assets={image:null, logo_image:null}, removed={image:false, logo_image:false};
    const control=(label,key,value,type='text',attrs='')=>`<label class="field">${esc(label)}<input data-setting="${key}" type="${type}" value="${esc(value)}" ${attrs}></label>`;
    const select=(label,key,values,value)=>`<label class="field">${esc(label)}<select data-setting="${key}">${values.map(v=>`<option value="${esc(v)}" ${v===value?'selected':''}>${esc(v)}</option>`).join('')}</select></label>`;
    host.innerHTML=`<form id="certificate-template-form" class="template-editor">
      <div class="ce-toolbar"><p class="muted small">Select a text block or logo to customize it. Saved changes automatically update all eligible student certificates.</p><div class="form-actions"><button type="button" class="btn" data-action="reset">Reset to default</button><button type="submit" class="btn primary">Save template</button></div></div>
      <div class="ce-layout">
        <section class="ce-panel"><h3>Template elements</h3><div class="ce-elements">${Object.entries({...labels,logo:'Logo / seal'}).map(([id,label])=>`<button type="button" class="btn compact" data-select="${id}" aria-pressed="false">${esc(label)}</button>`).join('')}</div><p class="ce-note">Drag blocks on the preview, or enter precise positions. Arrow keys move a selected block; Shift moves 10 px.</p></section>
        <div><section class="ce-panel"><h3>Template preview <span class="muted small">&middot; sample data</span></h3><div class="ce-stage"><img alt="Certificate preview with sample student Juan Dela Cruz" hidden><div class="ce-overlay"></div></div><p class="ce-status" role="status" aria-live="polite">Loading preview...</p><button type="button" class="btn compact" data-action="preview">Refresh preview</button><p class="ce-note">Landscape canvas: 1120 &times; 790. Boxes are editor guides and do not print. Long text fits within its box.</p></section>
        <section class="ce-panel ce-settings"><h3>Background and frame</h3><div class="ce-general">
          ${select('Background','background_mode',['solid','gradient'],draft.background_mode)}${control('Base color','background_color',draft.background_color,'color')}
          ${control('Gradient end color','gradient_color',draft.gradient_color,'color')}${select('Gradient direction','gradient_direction',['horizontal','vertical','diagonal'],draft.gradient_direction)}
          ${select('Border style','border_style',['none','simple','double'],draft.border_style)}${control('Border color','border_color',draft.border_color,'color')}
          <label class="field ce-full">Background image<input type="file" data-upload="image" accept="image/png,image/jpeg"><small data-asset="image"></small></label>
          <label class="check-label ce-full"><input type="checkbox" data-remove="image"> Remove background image</label>
          <label class="field ce-full">School logo / seal<input type="file" data-upload="logo_image" accept="image/png,image/jpeg"><small data-asset="logo_image"></small></label>
          <label class="check-label ce-full"><input type="checkbox" data-remove="logo_image"> Remove logo / seal</label>
        </div><p class="ce-note">JPG or PNG, up to 5 MB and 6000 &times; 6000 pixels each. Images retain their proportions. An opaque background image covers the color or gradient. A transparent PNG works well for a logo.</p></section></div>
        <section class="ce-panel ce-inspector"><h3 id="ce-selected-title">Element settings</h3><div class="ce-fields"></div><p class="ce-note">System fonts work offline. The exact appearance depends on fonts installed on the printing device. Review print preview before issuing.</p></section>
      </div>
    </form>`;
    const form=host.querySelector('form'), status=form.querySelector('.ce-status'), preview=form.querySelector('.ce-stage img'), overlay=form.querySelector('.ce-overlay'), inspector=form.querySelector('.ce-fields');
    const active=()=>selected==='logo'?draft.logo:draft.elements[selected];
    const boxStyle=block=>`left:${block.x/1120*100}%;top:${block.y/790*100}%;width:${block.width/1120*100}%;height:${block.height/790*100}%;`;
    function updateAssets() {
        for (const [field,path] of [['image','image_path'],['logo_image','logo_path']]) {
            form.querySelector(`[data-asset="${field}"]`).textContent=assets[field]?assets[field].name:removed[field]?'Image will be removed on save.':draft[path]?'Saved image is active.':'No image selected.';
        }
    }
    function paintBoxes() {
        overlay.innerHTML=Object.entries({...draft.elements,logo:draft.logo}).map(([id,block])=>{
            const empty=id==='logo'&&!assets.logo_image&&(!draft.logo_path||removed.logo_image);
            return `<button type="button" class="ce-box ${empty?'ce-logo-empty':''}" data-box="${id}" aria-label="Position ${esc(labels[id]||'logo')}" aria-pressed="${id===selected}" style="${boxStyle(block)}">${empty?'Logo':''}</button>`;
        }).join('');
    }
    function showInspector() {
        const block=active();
        form.querySelector('#ce-selected-title').textContent=labels[selected]||'Logo / seal';
        for (const button of form.querySelectorAll('[data-select]')) button.setAttribute('aria-pressed',String(button.dataset.select===selected));
        for (const button of overlay.querySelectorAll('[data-box]')) button.setAttribute('aria-pressed',String(button.dataset.box===selected));
        const position=`<div class="ce-pair">${control('X position','x',block.x,'number','required min=0 max=1080 step=1')}${control('Y position','y',block.y,'number','required min=0 max=770 step=1')}</div><div class="ce-pair">${control('Box width','width',block.width,'number','required min=40 max=1120 step=1')}${control('Box height','height',block.height,'number','required min=20 max=790 step=1')}</div>`;
        inspector.innerHTML=selected==='logo'?`<p class="ce-note">Upload the official logo below the preview, then drag or position it here.</p>${position}`:
            `<label class="field">Content<textarea data-setting="text" required maxlength="${['title','signatory'].includes(selected)?190:500}">${esc(block.text)}</textarea></label>
             <p class="ce-note">Live fields: ${esc(['student_name','recollection_date','event','venue','year','certificate_number'].map(key=>'{'+ '{'+key+'}'+'}').join(', '))}. These fill automatically on issuance.</p>
             ${select('Font family','font_family',fonts,block.font_family)}
             <div class="ce-pair">${control('Font size (px)','font_size',block.font_size,'number','required min=10 max=80 step=0.5')}${control('Text color','color',block.color,'color')}</div>
             ${select('Alignment','align',['left','center','right'],block.align)}${select('Weight','weight',['normal','bold'],block.weight)}${position}`;
    }
    function payload() {
        const data=new FormData();
        const append=(key,value)=>{
            if (value!==null && typeof value==='object') Object.entries(value).forEach(([child,v])=>append(`${key}[${child}]`,v));
            else if(value!==null) data.append(key,String(value));
        };
        for (const key of ['title','description','signatory']) draft[key]=draft.elements[key].text;
        for (const key of ['title','description','signatory','font_family','font_size','background_color','layout_version','elements','background_mode','gradient_color','gradient_direction','border_style','border_color','logo']) append(key,draft[key]);
        for (const field of ['image','logo_image']) if (assets[field]) data.append(field,assets[field]);
        data.set('remove_image',removed.image?'1':'0');data.set('remove_logo',removed.logo_image?'1':'0');
        return data;
    }
    async function updatePreview() {
        clearTimeout(timer);
        const current=++revision;
        if(!form.checkValidity()){status.textContent='Check the selected element settings before previewing or saving.';return false;}
        status.textContent='Updating preview...';
        try {
            const result=await request('/api/admin/certificate-template/preview','POST',payload());
            if(current!==revision||!form.isConnected)return false;
            const url=URL.createObjectURL(new Blob([result.svg],{type:'image/svg+xml'}));
            preview.onload=()=>URL.revokeObjectURL(url);preview.onerror=()=>URL.revokeObjectURL(url);
            preview.src=url;preview.hidden=false;
            status.textContent='Preview ready. Save template to apply this design to future certificates.';
            return true;
        } catch(error){if(current===revision&&form.isConnected)status.textContent=error.message;return false;}
    }
    function changed() {
        form.dataset.dirty='true';++revision;clearTimeout(timer);
        status.textContent='Unsaved changes. Updating preview...';
        timer=setTimeout(updatePreview,350);
    }
    form.addEventListener('input',event=>{
        const input=event.target, key=input.dataset.setting;
        if (!key)return;
        const target=inspector.contains(input)?active():draft;
        target[key]=input.type==='number'?Number(input.value):input.value;
        paintBoxes();changed();
    });
    form.addEventListener('change',event=>{
        const input=event.target;
        if(input.dataset.upload){
            const field=input.dataset.upload, file=input.files[0]||null;
            if(file && (file.size>5*1024*1024||!['image/png','image/jpeg'].includes(file.type))){input.value='';status.textContent='Choose a JPG or PNG no larger than 5 MB.';return;}
            assets[field]=file;
            if(file){removed[field]=false;form.querySelector(`[data-remove="${field}"]`).checked=false;}
            updateAssets();paintBoxes();changed();
        }
        if(input.dataset.remove){
            const field=input.dataset.remove;removed[field]=input.checked;
            if(input.checked){assets[field]=null;form.querySelector(`[data-upload="${field}"]`).value='';}
            updateAssets();paintBoxes();changed();
        }
    });
    form.addEventListener('click',event=>{
        const button=event.target.closest('button');if(!button)return;
        if(button.dataset.select||button.dataset.box){selected=button.dataset.select||button.dataset.box;showInspector();}
        if(button.dataset.action==='preview')updatePreview();
        if(button.dataset.action==='reset'){
            draft=clone(defaults);assets.image=null;assets.logo_image=null;removed.image=true;removed.logo_image=true;
            for(const input of form.querySelectorAll('.ce-general [data-setting]'))input.value=draft[input.dataset.setting];
            for(const input of form.querySelectorAll('[data-upload]'))input.value='';
            for(const input of form.querySelectorAll('[data-remove]'))input.checked=true;
            showInspector();paintBoxes();updateAssets();changed();
            status.textContent='Default restored in this draft. Save template to make it active.';
        }
    });
    function move(block,x,y) {
        block.x=Math.round(Math.max(0,Math.min(1120-block.width,x)));
        block.y=Math.round(Math.max(0,Math.min(790-block.height,y)));
        const button=overlay.querySelector(`[data-box="${selected}"]`);
        button.style.cssText=boxStyle(block);
        inspector.querySelector('[data-setting=x]').value=block.x;
        inspector.querySelector('[data-setting=y]').value=block.y;
    }
    overlay.addEventListener('keydown',event=>{
        const id=event.target.dataset.box;if(!id||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
        event.preventDefault();selected=id;showInspector();const block=active(), step=event.shiftKey?10:1;
        move(block,block.x+({'ArrowLeft':-step,'ArrowRight':step}[event.key]||0),block.y+({'ArrowUp':-step,'ArrowDown':step}[event.key]||0));changed();
    });
    overlay.addEventListener('pointerdown',event=>{
        const button=event.target.closest('[data-box]');if(!button||event.button!==0)return;
        selected=button.dataset.box;showInspector();button.setPointerCapture(event.pointerId);
        const block=active(), start={x:event.clientX,y:event.clientY,bx:block.x,by:block.y}, bounds=overlay.getBoundingClientRect();
        const drag=e=>{move(block,start.bx+(e.clientX-start.x)*1120/bounds.width,start.by+(e.clientY-start.y)*790/bounds.height);changed();};
        const end=()=>{button.removeEventListener('pointermove',drag);button.removeEventListener('pointerup',end);button.removeEventListener('pointercancel',end);};
        button.addEventListener('pointermove',drag);button.addEventListener('pointerup',end);button.addEventListener('pointercancel',end);
    });
    form.onsubmit=async event=>{
        event.preventDefault();if(saving)return;saving=true;
        try{if(await updatePreview())save('/api/admin/settings/certificate','POST',payload());}finally{saving=false;}
    };
    paintBoxes();showInspector();updateAssets();updatePreview();
};
</script>
