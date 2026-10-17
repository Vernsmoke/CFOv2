const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const html = fs.readFileSync(0, 'utf8');
const template = html.match(/<template\s+id="embedded-student-assets">([\s\S]*?)<\/template>/);
assert.ok(template, 'The rendered student page includes its image resources.');
const resources = [...template[1].matchAll(/<img\b[^>]*>/g)].map(([tag]) => {
    const src = tag.match(/\bsrc="([^"]+)"/)[1];
    const resource = tag.match(/\bdata-resource="([^"]+)"/)[1];
    assert.ok(new URL(src).pathname.startsWith('/assets/student/images/'), 'Extracted images use public asset URLs.');
    const bytes = fs.readFileSync(path.join(__dirname, '../../public', new URL(src).pathname));
    assert.equal(bytes.toString('ascii', 0, 4), 'RIFF');
    assert.equal(bytes.toString('ascii', 8, 12), 'WEBP');
    return {dataset: {resource}, getAttribute: name => name === 'src' ? src : null, src};
});
assert.equal(resources.length, 1);
assert.equal(resources[0].dataset.resource, 'dashboard-reference');

const css = new Map();
const targets = resources.map(image => ({
    src: '', dataset: {studentImage: image.dataset.resource},
    removeAttribute(name) { if (name === 'data-student-image') delete this.dataset.studentImage; },
}));
let observeImages;
const document = {
    body: {},
    documentElement: {style: {setProperty: (name, value) => css.set(name, value)}},
    querySelector(selector) {
        assert.equal(selector, '#embedded-student-assets');
        return {content: {querySelectorAll: () => resources}};
    },
    querySelectorAll(selector) {
        assert.equal(selector, 'img[data-student-image]');
        return targets.filter(image => image.dataset.studentImage);
    },
};
const context = vm.createContext({document, atob, Blob, URL, Uint8Array, MutationObserver: class {
    constructor(callback) { observeImages = callback; }
    observe(target, options) {
        assert.equal(target, document.body);
        assert.equal(options.childList, true);
        assert.equal(options.subtree, true);
    }
}});
const start = html.indexOf('const embeddedImages = new Map();');
const end = html.indexOf('// Opening the file directly', start);
assert.ok(start >= 0 && end > start, 'The image startup code is present.');
vm.runInContext(html.slice(start, end), context);
vm.runInContext('hydrateStudentImages();', context);
for (let i = 0; i < resources.length; i++) {
    assert.equal(css.get('--' + resources[i].dataset.resource), 'url("' + resources[i].src + '")');
    assert.equal(targets[i].src, resources[i].src);
    assert.equal(targets[i].dataset.studentImage, undefined);
}

// Images inserted during later dashboard renders receive the same extracted URL.
const laterImage = {src: '', dataset: {studentImage: 'dashboard-reference'},
    removeAttribute() { delete this.dataset.studentImage; }};
targets.push(laterImage);
observeImages();
assert.equal(laterImage.src, resources.find(image => image.dataset.resource === 'dashboard-reference').src);
console.log('Student image startup and later dashboard images use the extracted WebP URLs.');
