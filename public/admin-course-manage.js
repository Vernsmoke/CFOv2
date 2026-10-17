(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) root.CfoCourseManage = api;
})(typeof window === 'undefined' ? null : window, function () {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    const normalize = name => String(name ?? '').trim().toLocaleLowerCase();
    const courses = state => (state.academic_options || []).filter(option => option.type === 'course');
    function validate(state, id, name) {
        if (!String(name).trim()) return 'Enter a course name.';
        if (Array.from(String(name).trim()).length > 190) return 'The course name must not exceed 190 characters.';
        if (courses(state).some(course => String(course.id) !== String(id) && normalize(course.name) === normalize(name))) return 'This course already exists.';
        return '';
    }
    function reconcileGroups(groups, state) {
        const ids = new Set(courses(state).map(course => String(course.id)));
        return groups.map(group => group.course_id && !ids.has(String(group.course_id)) ? {...group, course_id: '', year_level: '', sections: []} : group);
    }
    function build(state, model = {}) {
        const disabled = model.busy ? ' disabled' : '';
        const rows = courses(state).map(course => {
            const id = escape(course.id), name = escape(course.name);
            if (String(model.editing) === String(course.id)) return `<tr><td colspan="2"><label class="field">Course Name<input data-cm-name maxlength="190" value="${escape(model.draft)}" autocomplete="off" aria-label="Edit course ${name}"${disabled}></label><div class="form-actions"><button type="button" class="btn primary" data-cm-save="${id}"${disabled}>Save</button><button type="button" class="btn" data-cm-cancel="${id}"${disabled}>Cancel</button></div></td></tr>`;
            if (String(model.deleting) === String(course.id)) return `<tr><td colspan="2"><p>Delete course ${name}?</p><div class="form-actions"><button type="button" class="btn danger" data-cm-confirm="${id}"${disabled}>Delete</button><button type="button" class="btn" data-cm-cancel="${id}"${disabled}>Cancel</button></div></td></tr>`;
            return `<tr><td>${name}</td><td><div class="form-actions"><button type="button" class="btn" data-cm-edit="${id}" aria-label="Edit ${name}"${disabled}>Edit</button><button type="button" class="btn danger" data-cm-delete="${id}" aria-label="Delete ${name}"${disabled}>Delete</button></div></td></tr>`;
        }).join('');
        return `<h3>Existing Courses</h3><p class="sb-form-error" data-cm-error role="alert"${model.error ? '' : ' hidden'}>${escape(model.error || '')}</p><p data-cm-notice role="status"${model.notice ? '' : ' hidden'}>${escape(model.notice || '')}</p><div class="table-wrap"><table><thead><tr><th scope="col">Course</th><th scope="col">Actions</th></tr></thead><tbody>${rows || '<tr><td colspan="2">No courses added yet.</td></tr>'}</tbody></table></div>`;
    }
    const failureMessage = failure => failure?.errors ? [...new Set(Object.values(failure.errors).flat())].join(' ') : failure?.message || 'The course could not be updated. Please try again.';
    function mount(element, context) {
        const {state, request, onChanged, onBusy, isBlocked, toast} = context;
        let model = {}, destroyed = false;
        const render = () => { if (!destroyed) element.innerHTML = build(state, model); };
        const focus = selector => element.querySelector(selector)?.focus();
        function changed() { onChanged?.(); render(); }
        async function mutate(id, method, name) {
            model.busy = true; model.error = ''; model.notice = ''; render(); onBusy?.(true);
            try {
                const result = await request('/api/admin/academic-options/' + encodeURIComponent(id), method, method === 'PATCH' ? {name} : undefined);
                // Apply a successful write immediately, even if the following refresh loses its connection.
                if (method === 'DELETE') state.academic_options = state.academic_options.filter(option => String(option.id) !== String(id));
                else {
                    const saved = result?.academic_option?.name || name;
                    state.academic_options = state.academic_options.map(option => String(option.id) === String(id) ? {...option, name: saved} : option);
                    for (const schedule of state.schedules || []) for (const course of schedule.courses || []) if (String(course.id) === String(id)) course.name = saved;
                }
                model.editing = null; model.deleting = null; model.draft = '';
                changed();
                try {
                    const refreshed = await request('/api/admin/state?resources=academic_options');
                    if (!Array.isArray(refreshed.academic_options)) throw Error('Reference options unavailable.');
                    state.academic_options = refreshed.academic_options;
                    if (Array.isArray(refreshed.course_year_sections)) state.course_year_sections = refreshed.course_year_sections;
                    changed();
                } catch (_) {
                    model.notice = 'The course was ' + (method === 'DELETE' ? 'deleted' : 'updated') + ', but the latest course list could not be loaded.';
                }
                toast?.(method === 'DELETE' ? 'Course deleted.' : 'Course updated.');
            } catch (failure) { model.error = failureMessage(failure); }
            finally {
                model.busy = false; onBusy?.(false); render();
                if (model.editing != null) focus('[data-cm-name]');
                else focus('[data-cm-edit]');
            }
        }
        async function click(event) {
            const button = event.target.closest('[data-cm-edit],[data-cm-delete],[data-cm-cancel],[data-cm-save],[data-cm-confirm]');
            if (!button || button.disabled || model.busy || isBlocked?.()) return;
            event.preventDefault();
            const data = button.dataset, id = data.cmEdit ?? data.cmDelete ?? data.cmCancel ?? data.cmSave ?? data.cmConfirm;
            const course = courses(state).find(course => String(course.id) === String(id));
            if (!course) { model = {error: 'This course is no longer available.'}; render(); return; }
            if (data.cmEdit !== undefined) { model = {editing: id, draft: course.name}; render(); focus('[data-cm-name]'); }
            else if (data.cmDelete !== undefined) { model = {deleting: id}; render(); focus('[data-cm-cancel]'); }
            else if (data.cmCancel !== undefined) { model = {}; render(); focus('[data-cm-edit="' + id + '"]'); }
            else if (data.cmSave !== undefined && String(model.editing) === String(id)) {
                const name = element.querySelector('[data-cm-name]').value.trim();
                model.draft = name; model.error = validate(state, id, name);
                if (model.error) { render(); focus('[data-cm-name]'); return; }
                await mutate(id, 'PATCH', name);
            } else if (data.cmConfirm !== undefined && String(model.deleting) === String(id)) await mutate(id, 'DELETE');
        }
        const input = event => { if (event.target.matches('[data-cm-name]')) model.draft = event.target.value; };
        const keydown = event => {
            if (event.key === 'Enter' && event.target.matches('[data-cm-name]')) {
                event.preventDefault(); element.querySelector('[data-cm-save]')?.click();
            }
        };
        element.addEventListener('click', click); element.addEventListener('input', input); element.addEventListener('keydown', keydown);
        render();
        return {
            render, busy: () => !!model.busy,
            reset() { if (!model.busy) { model = {}; render(); } },
            destroy() {
                destroyed = true;
                element.removeEventListener('click', click); element.removeEventListener('input', input); element.removeEventListener('keydown', keydown);
            },
        };
    }
    return {build, courses, validate, reconcileGroups, mount};
});
