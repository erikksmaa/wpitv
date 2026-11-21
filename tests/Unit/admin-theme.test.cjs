const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');

const source = readFileSync(join(__dirname, '../../public/js/admin-theme-init.js'), 'utf8');
const key = 'wpitv.admin.theme';

function browser({ values = new Map(), systemDark = false, blocked = false, bodyReady = true } = {}) {
    const listeners = {};
    const attrs = {};
    const button = {
        attrs: {}, icon: {}, label: {},
        setAttribute(name, value) { this.attrs[name] = value; },
        querySelector(name) { return name.includes('-icon') ? this.icon : this.label; },
    };
    const body = { classes: new Set(), classList: { toggle(name, active) { active ? body.classes.add(name) : body.classes.delete(name); } } };
    const media = { matches: systemDark, addEventListener(type, callback) { listeners.media = callback; } };
    const document = {
        body: bodyReady ? body : null,
        documentElement: { style: {}, setAttribute(name, value) { attrs[name] = value; } },
        querySelectorAll() { return this.body ? [button] : []; },
        addEventListener(type, callback) { (listeners[type] ||= []).push(callback); },
    };
    const window = { matchMedia: () => media, addEventListener(type, callback) { listeners[type] = [callback]; } };
    const localStorage = {
        getItem(name) { if (blocked) throw new Error('blocked'); return values.get(name) || null; },
        setItem(name, value) { if (blocked) throw new Error('blocked'); values.set(name, value); },
    };
    const context = vm.createContext({ document, window, localStorage });
    vm.runInContext(source, context);
    return { attrs, button, body, document, window, values, listeners, context, emit(type, event = {}) { for (const callback of listeners[type] || []) callback(event); } };
}

test('theme is applied in head before body exists, without using public/Mazer preferences', () => {
    const ui = browser({ values: new Map([['theme', 'dark'], ['wpitv-v2-theme', 'dark']]), bodyReady: false });
    assert.equal(ui.attrs['data-bs-theme'], 'light');
    ui.document.body = ui.body;
    ui.emit('DOMContentLoaded');
    assert.equal(ui.button.icon.className, 'bi bi-sun');
    assert.equal(ui.button.attrs['aria-pressed'], 'false');
});

test('toggle persists only the admin key and sets icon/accessible action', () => {
    const ui = browser({ values: new Map([['wpitv-v2-theme', 'light']]) });
    ui.emit('click', { target: { closest: () => ui.button } });
    assert.equal(ui.attrs['data-bs-theme'], 'dark');
    assert.equal(ui.values.get(key), 'dark');
    assert.equal(ui.values.get('wpitv-v2-theme'), 'light');
    assert.equal(ui.button.icon.className, 'bi bi-moon-stars');
    assert.equal(ui.button.attrs['aria-label'], 'Aktifkan mode terang');
    assert.equal(ui.body.classes.has('theme-dark'), true);
    ui.window.WPiAdminTheme.toggle();
    assert.equal(ui.body.classes.has('dark'), false);
    assert.equal(ui.attrs['data-bs-theme'], 'light');
});

test('refresh and Livewire navigation keep an explicit preference', () => {
    const values = new Map([[key, 'dark']]);
    const ui = browser({ values });
    ui.attrs['data-bs-theme'] = 'light';
    ui.emit('livewire:navigated');
    assert.equal(ui.attrs['data-bs-theme'], 'dark');
    assert.equal(browser({ values }).attrs['data-bs-theme'], 'dark');
    vm.runInContext(source, ui.context);
    assert.equal(ui.listeners.click.length, 1, 'Re-executing the head script must not duplicate listeners.');
});

test('blocked localStorage does not break toggling or navigation', () => {
    const ui = browser({ blocked: true });
    ui.window.WPiAdminTheme.toggle();
    ui.emit('livewire:navigated');
    assert.equal(ui.attrs['data-bs-theme'], 'dark');
    ui.listeners.media({ matches: false });
    assert.equal(ui.attrs['data-bs-theme'], 'dark', 'Explicit in-memory choice wins over the system.');
});

test('system and cross-tab changes respect the admin preference boundary', () => {
    const ui = browser({ systemDark: true });
    assert.equal(ui.attrs['data-bs-theme'], 'dark');
    ui.listeners.media({ matches: false });
    assert.equal(ui.attrs['data-bs-theme'], 'light');
    ui.emit('storage', { key: 'wpitv-v2-theme', newValue: 'dark' });
    assert.equal(ui.attrs['data-bs-theme'], 'light');
    ui.emit('storage', { key, newValue: 'dark' });
    assert.equal(ui.attrs['data-bs-theme'], 'dark');
    ui.window.WPiAdminTheme.set('invalid');
    assert.equal(ui.attrs['data-bs-theme'], 'dark');
});

test('admin layout loads the blocking theme script before styles and removes competing theme initializers', () => {
    const layout = readFileSync(join(__dirname, '../../resources/views/layouts/admin.blade.php'), 'utf8');
    assert.ok(layout.indexOf('js/admin-theme-init.js') < layout.indexOf('mazer/compiled/css/app.css'));
    assert.equal(layout.includes('static/js/components/dark.js'), false);
    assert.equal(layout.includes('static/js/initTheme.js'), false);
    assert.ok(layout.includes('data-admin-theme-toggle'));
});

function contrast(a, b) {
    const luminance = (hex) => {
        const rgb = hex.match(/[a-f0-9]{2}/gi).map(c => parseInt(c, 16) / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
        return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
    };
    const x = luminance(a), y = luminance(b);
    return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
}

test('core text, muted text, status badges and confirmation buttons meet AA contrast', () => {
    for (const [fg, bg] of [['#0f172a', '#ffffff'], ['#64748b', '#ffffff'], ['#e5edf7', '#111c32'], ['#94a3b8', '#16243d'], ['#a4d0ff', '#163758'], ['#8fe6c0', '#153e35'], ['#ffb1bc', '#482733'], ['#ffffff', '#155dac'], ['#ffffff', '#b42332'], ['#ffffff', '#2878c8']]) {
        assert.ok(contrast(fg, bg) >= 4.5, `${fg} on ${bg} must meet 4.5:1`);
    }
});
