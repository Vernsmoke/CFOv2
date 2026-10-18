(() => {
    'use strict';
    const host = document.querySelector('#registration-academic-filters');
    if (!host || !window.CfoAcademicFilters) return;
    const form = host.closest('form'), initial = JSON.parse(host.dataset.initial), values = form.querySelector('#registration-academic-values');
    const legacy = form.querySelector('[name="course"]');
    const profiles = JSON.parse(host.dataset.population);
    legacy.disabled = true; legacy.closest('label').hidden = true;
    function serialize(value) {
        const yearSection = form.querySelector('[name="year_section"]');
        const previous = yearSection.value;
        const combinations = [...new Set(profiles.filter(row=>window.CfoAcademicFilters.matches(value, window.CfoAcademicFilters.profile(row))).map(row=>String(row.year_level || '') + String(row.section || '')).filter(Boolean))].sort();
        yearSection.replaceChildren(new Option('All Year & Sections', ''), ...combinations.map(item=>new Option(item,item)));
        yearSection.value = combinations.includes(previous) ? previous : '';
        values.replaceChildren();
        for (const key of ['department', 'course', 'major']) for (const item of value[key]) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = key + 's[]'; input.value = item; values.append(input);
        }
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'multiple'; input.value = value.multiple ? '1' : '0'; values.append(input);
    }
    const control = window.CfoAcademicFilters.mount(host, {
        courses: JSON.parse(host.dataset.courses),
        profiles,
        value: {department:initial.departments, course:initial.courses || initial.course, major:initial.majors, multiple:initial.multiple === '1'},
        onChange: (value, event) => {
            serialize(value);
            form.dispatchEvent(new Event('input', {bubbles:true}));
            if (event.reason !== 'mode') form.requestSubmit();
        }
    });
    serialize(control.value());
})();
