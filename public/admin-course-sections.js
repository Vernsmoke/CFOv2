(function (global) {
    'use strict';
    const text = value => String(value ?? '').trim();
    const escape = value => text(value).replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    const collator = new Intl.Collator('en', {numeric: true, sensitivity: 'base'});
    const unique = values => [...new Set(values)];
    let selected = {course: '', year: ''};

    function courses(state) {
        return (state.academic_options || []).filter(option => option.type === 'course').slice().sort((a, b) => collator.compare(a.name, b.name));
    }
    function years(state) {
        return unique(['1', '2', '3', '4', ...(state.academic_options || []).filter(option => option.type === 'year_level').map(option => text(option.name))]).filter(Boolean).sort(collator.compare);
    }
    function configured(state, course, year) {
        return (state.course_year_sections || []).filter(row => text(row.course_id) === text(course) && text(row.year_level) === text(year)).slice().sort((a, b) => collator.compare(a.section, b.section));
    }
    function validate(state, values) {
        const errors = {};
        if (!courses(state).some(course => text(course.id) === text(values.course_id))) errors.course_id = 'Select a course.';
        if (!years(state).includes(text(values.year_level))) errors.year_level = 'Select a year level.';
        const section = text(values.section);
        if (!section) errors.section = 'Enter a section name.';
        else if (section.length > 190) errors.section = 'Use a section name of 190 characters or fewer.';
        else if (configured(state, values.course_id, values.year_level).some(row => text(row.section).toLocaleLowerCase() === section.toLocaleLowerCase())) errors.section = 'This section is already configured for this course and year level.';
        return errors;
    }

    function mount({root, state, request, onChanged, toast}) {
        const options = courses(state), yearOptions = years(state);
        if (!options.some(course => text(course.id) === selected.course)) selected.course = options.length ? text(options[0].id) : '';
        if (!yearOptions.includes(selected.year)) selected.year = yearOptions[0] || '';
        root.innerHTML = `<p class="muted small">Configure the sections available to each course and year level. Unconfigured pairs keep the existing global section choices. Existing schedules keep their saved sections.</p>
            <form data-course-sections-form><div class="form-grid"><label class="field">Course<select name="course_id" required ${options.length ? '' : 'disabled'}>${options.length ? options.map(course => `<option value="${course.id}" ${text(course.id) === selected.course ? 'selected' : ''}>${escape(course.name)}</option>`).join('') : '<option value="">Add a course first</option>'}</select></label><label class="field">Year level<select name="year_level" required>${yearOptions.map(year => `<option value="${escape(year)}" ${year === selected.year ? 'selected' : ''}>${escape(year)}</option>`).join('')}</select></label><label class="field">Section name<input name="section" required maxlength="190" autocomplete="off" aria-describedby="course-section-help"></label></div>
            <p id="course-section-help" class="muted small">Add each section separately, for example A then B. Section names must be unique within this course and year level.</p><div class="form-actions"><button type="submit" class="btn primary" ${options.length ? '' : 'disabled'}>Add section</button></div><div data-course-sections-error role="alert" hidden></div><div data-course-sections-list></div><div data-course-sections-confirm hidden></div></form>`;
        const form = root.querySelector('form'), list = root.querySelector('[data-course-sections-list]'), error = root.querySelector('[data-course-sections-error]'), confirm = root.querySelector('[data-course-sections-confirm]');
        let busy = false, pendingRemoval = null;
        const displayError = failure => { error.innerHTML = failure ? `<p class="cfo-form-error">${escape(failure)}</p>` : ''; error.hidden = !failure; };
        function draw() {
            const rows = configured(state, selected.course, selected.year);
            list.innerHTML = `<p class="muted small">${rows.length ? `${rows.length} configured section${rows.length === 1 ? '' : 's'}` : 'No sections configured. Schedules will use the global section list.'}</p><div class="form-actions">${rows.map(row => `<span>${escape(row.section)} <button type="button" class="btn compact danger" data-course-section-remove="${row.id}" aria-label="Remove section ${escape(row.section)}">Remove</button></span>`).join('')}</div>`;
            pendingRemoval = null; confirm.hidden = true;
        }
        async function save(url, method, body) {
            if (busy) return;
            busy = true; form.dataset.dirty = 'true'; displayError('');
            const controls = [...form.querySelectorAll('input,select,button')];
            controls.forEach(control => { control.dataset.previousDisabled = String(control.disabled); control.disabled = true; });
            try {
                const result = await request(url, method, body);
                form.dataset.dirty = 'false';
                toast?.(result.message || 'Course sections saved.');
                await onChanged?.();
            } catch (failure) {
                displayError(failure.message || 'Unable to save course sections. Try again.');
            } finally {
                busy = false;
                controls.forEach(control => { control.disabled = control.dataset.previousDisabled === 'true'; delete control.dataset.previousDisabled; });
            }
        }
        form.addEventListener('change', event => {
            if (event.target.name === 'course_id' || event.target.name === 'year_level') {
                selected = {course: text(form.elements.course_id.value), year: text(form.elements.year_level.value)};
                displayError(''); draw();
                form.dataset.dirty = Boolean(text(form.elements.section.value)).toString();
            }
        });
        form.addEventListener('input', event => {
            if (event.target.name === 'section') { displayError(''); form.dataset.dirty = Boolean(text(event.target.value)).toString(); }
        });
        form.addEventListener('submit', event => {
            event.preventDefault();
            const values = {course_id: Number(selected.course), year_level: selected.year, section: text(form.elements.section.value)};
            const errors = validate(state, values);
            if (Object.keys(errors).length) { displayError(Object.values(errors).join(' ')); return; }
            save('/api/admin/course-year-sections', 'POST', values);
        });
        root.addEventListener('click', event => {
            if (busy) return;
            const remove = event.target.closest('[data-course-section-remove]');
            if (remove) {
                pendingRemoval = configured(state, selected.course, selected.year).find(row => text(row.id) === remove.dataset.courseSectionRemove);
                if (!pendingRemoval) return;
                confirm.innerHTML = `<p>Remove section <strong>${escape(pendingRemoval.section)}</strong> for <strong>${escape(options.find(course => text(course.id) === selected.course)?.name)} ${escape(selected.year)}</strong>? Sections used by students or schedules cannot be removed.</p><div class="form-actions"><button type="button" class="btn" data-course-section-keep>Keep section</button><button type="button" class="btn danger" data-course-section-confirm>Remove section</button></div>`;
                confirm.hidden = false; form.dataset.dirty = 'true'; confirm.querySelector('button').focus();
            } else if (event.target.closest('[data-course-section-keep]')) {
                pendingRemoval = null; confirm.hidden = true; form.dataset.dirty = Boolean(text(form.elements.section.value)).toString();
            } else if (event.target.closest('[data-course-section-confirm]') && pendingRemoval) save('/api/admin/course-year-sections/' + pendingRemoval.id, 'DELETE', {});
        });
        draw();
        return {selection: () => ({...selected})};
    }
    const api = {mount, courses, years, configured, validate};
    if (typeof module === 'object' && module.exports) module.exports = api;
    global.CfoCourseYearSections = api;
})(typeof window === 'object' ? window : globalThis);
