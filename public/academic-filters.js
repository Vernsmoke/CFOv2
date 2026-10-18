(function (global) {
    'use strict';
    const keys = ['department', 'course', 'major'];
    const labels = {department: 'Department', course: 'Course', major: 'Major'};
    const all = {department: 'All Department', course: 'All Courses', major: 'All Major'};
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const list = value => Array.isArray(value) ? [...new Set(value.filter(Boolean))] : value && value !== 'All' ? [value] : [];
    const catalog = () => global.CFO_ACADEMIC_CATALOG || global.CFO_BACKEND?.academic_catalog || {departments: []};
    function paths(extra = []) {
        const rows = (catalog().departments || []).flatMap(d => d.courses.map(c => ({department:d.name, course:c.name, majors:c.majors || []})));
        for (const course of extra) if (course && !rows.some(r => r.course === course)) rows.push({department:'Other / Legacy Courses', course, majors:[]});
        return rows;
    }
    function profile(row, displayedCourse) {
        const record = row.registration || row, details = record.details || {}, snapshot = record.student_snapshot || {}, user = record.user || row.user || row;
        const course = displayedCourse ?? (details.course || snapshot.course || user.course || '');
        return {course, department:paths().find(p => p.course === course)?.department || details.department || snapshot.department || user.department || 'Other / Legacy Courses', major:details.major || snapshot.major || user.major || ''};
    }
    function matches(selection, row) {
        return keys.every(key => !list(selection[key]).length || list(selection[key]).includes(row[key]));
    }
    function populationPaths(profiles) {
        const rows = [];
        for (const raw of profiles) {
            const item = profile(raw);
            if (!item.course) continue;
            let row = rows.find(r => r.department === item.department && r.course === item.course);
            if (!row) { row = {...item, majors:[]}; rows.push(row); }
            if (item.major && !row.majors.includes(item.major)) row.majors.push(item.major);
        }
        return rows;
    }
    function options(key, selection, extra, profiles) {
        const rows = Array.isArray(profiles) ? populationPaths(profiles) : paths(extra), departments = list(selection.department), courses = list(selection.course);
        if (key === 'department') return [...new Set(rows.map(r => r.department))].map(value => ({value, group:''}));
        if (!departments.length) return [];
        const eligible = rows.filter(r => departments.includes(r.department));
        if (key === 'course') return eligible.map(r => ({value:r.course, group:r.department}));
        if (!courses.length) return [];
        return eligible.filter(r => courses.includes(r.course)).flatMap(r => r.majors.map(major => ({value:JSON.stringify([r.course, major]), label:major, group:r.department + ' / ' + r.course})));
    }
    function matchesProfile(selection, row) {
        return matches({...selection, major:[]}, row) && (!list(selection.major).length || list(selection.major).some(value => value === row.major || value === JSON.stringify([row.course, row.major])));
    }
    function mount(root, config = {}) {
        let selection = Object.fromEntries(keys.map(key => [key, list(config.value?.[key])]));
        let multiple = !!config.value?.multiple;
        const extra = config.courses || [];
        const available = key => options(key, selection, extra, typeof config.profiles === 'function' ? config.profiles() : config.profiles);
        // Preserve incoming course links by deriving their parent department.
        if (!selection.department.length && selection.course.length) selection.department = [...new Set(paths(extra).filter(p => selection.course.includes(p.course)).map(p => p.department))];
        let dialog;
        function normalize() {
            if (!selection.department.length) { selection.course=[]; selection.major=[]; }
            selection.course = selection.course.filter(v => available('course').some(o => o.value === v));
            if (!selection.course.length) selection.major=[];
            selection.major = selection.major.filter(v => available('major').some(o => o.value === v));
        }
        function emit(reason = 'selection') { config.onChange?.({...selection, multiple}, {reason}); }
        function render() {
            normalize();
            root.innerHTML = `<label class="af-mode"><input type="checkbox" data-af-multiple ${multiple?'checked':''}> Select multiple departments, courses, and majors</label><div class="af-fields">${keys.map(key => {
                const choices = available(key), disabled = !choices.length;
                const display = selection[key].length ? selection[key].map(v => choices.find(o => o.value === v)?.label || v).join(', ') : all[key];
                return `<label class="af-field"><span>${labels[key]}</span>${multiple ? `<button type="button" data-af-open="${key}" aria-haspopup="dialog" ${disabled?'disabled':''}>${esc(display)} <span aria-hidden="true">▾</span></button>` : `<select data-af-select="${key}" ${disabled?'disabled':''}><option value="">${all[key]}</option>${choices.map(o => `<option value="${esc(o.value)}" ${selection[key].includes(o.value)?'selected':''}>${esc(o.label || o.value)}</option>`).join('')}</select>`}</label>`;
            }).join('')}</div>`;
            root.querySelector('[data-af-multiple]').onchange = event => {
                multiple=event.target.checked;
                if (!multiple) for (const key of keys) selection[key]=selection[key].slice(0,1);
                render(); emit('mode');
            };
            root.querySelectorAll('[data-af-select]').forEach(select => select.onchange = () => commit(select.dataset.afSelect, list(select.value)));
            root.querySelectorAll('[data-af-open]').forEach(button => button.onclick = () => open(button.dataset.afOpen, button));
        }
        function commit(key, values) {
            selection[key]=values;
            for (const child of keys.slice(keys.indexOf(key)+1)) selection[child]=[];
            render(); emit();
        }
        function open(key, opener) {
            const choices=available(key), draft=new Set(selection[key]);
            dialog=document.createElement('dialog'); dialog.className='af-dialog';
            const groups=[...new Set(choices.map(o=>o.group))];
            dialog.innerHTML=`<h2>Select ${labels[key].toLowerCase()}s</h2><p>Choose one or more. No checked items means ${all[key].toLowerCase()}.</p><label class="af-all"><input type="checkbox" data-af-all ${draft.size?'':'checked'}> ${all[key]}</label><div class="af-choices">${groups.map(group=>`<fieldset><legend>${esc(group || labels[key])}</legend>${choices.filter(o=>o.group===group).map(o=>`<label><input type="checkbox" data-af-value="${esc(o.value)}" ${draft.has(o.value)?'checked':''}> ${esc(o.label || o.value)}</label>`).join('')}</fieldset>`).join('')}</div><div class="af-actions"><button type="button" data-af-clear>Clear</button><button type="button" data-af-cancel>Cancel</button><button type="button" data-af-apply>Apply</button></div>`;
            const title=dialog.querySelector('h2'); title.id='academic-filter-dialog-title'; dialog.setAttribute('aria-labelledby',title.id);
            function close() { dialog.close(); dialog.remove(); dialog=null; opener.focus(); }
            function clear() { draft.clear(); dialog.querySelectorAll('[data-af-value]').forEach(i=>i.checked=false); dialog.querySelector('[data-af-all]').checked=true; }
            dialog.querySelector('[data-af-all]').onchange=clear;
            dialog.querySelectorAll('[data-af-value]').forEach(input=>input.onchange=()=>{input.checked?draft.add(input.dataset.afValue):draft.delete(input.dataset.afValue);dialog.querySelector('[data-af-all]').checked=!draft.size;});
            dialog.querySelector('[data-af-clear]').onclick=clear;
            dialog.querySelector('[data-af-cancel]').onclick=close;
            dialog.querySelector('[data-af-apply]').onclick=()=>{const chosen=[...draft];close();commit(key,chosen);root.querySelector(`[data-af-open="${key}"]`)?.focus();};
            dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
            document.body.append(dialog); dialog.showModal();
        }
        render();
        return {value:()=>({...selection,multiple}), reset(){selection={department:[],course:[],major:[]};render();emit();}, destroy(){dialog?.remove();}};
    }
    global.CfoAcademicFilters={mount,paths,profile,matches:matchesProfile,options,populationPaths};
})(window);
