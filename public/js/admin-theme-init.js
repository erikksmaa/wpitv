(function () {
    'use strict';
    if (window.WPiAdminTheme) {
        window.WPiAdminTheme.sync();
        return;
    }
    const key = 'wpitv.admin.theme';
    const valid = (theme) => theme === 'light' || theme === 'dark';
    let preference = null;
    try { preference = localStorage.getItem(key); } catch { /* Storage can be unavailable. */ }
    if (!valid(preference)) preference = null;
    const media = window.matchMedia?.('(prefers-color-scheme: dark)');
    let current = preference || (media?.matches ? 'dark' : 'light');

    function sync() {
        document.documentElement.setAttribute('data-bs-theme', current);
        document.documentElement.style.colorScheme = current;
        if (document.body) {
            document.body.classList.toggle('theme-dark', current === 'dark');
            document.body.classList.toggle('dark', current === 'dark');
            document.body.classList.toggle('light', current === 'light');
        }
        document.querySelectorAll('[data-admin-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', String(current === 'dark'));
            button.setAttribute('aria-label', current === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
            button.title = current === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
            const icon = button.querySelector('[data-admin-theme-icon]');
            if (icon) icon.className = current === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun';
            const label = button.querySelector('[data-admin-theme-label]');
            if (label) label.textContent = current === 'dark' ? 'Mode gelap' : 'Mode terang';
        });
    }

    function set(theme) {
        if (!valid(theme)) return;
        preference = current = theme;
        try { localStorage.setItem(key, theme); } catch { /* Keep an in-memory preference. */ }
        sync();
    }

    window.WPiAdminTheme = Object.freeze({ sync, set, toggle: () => set(current === 'dark' ? 'light' : 'dark') });
    // Synchronous in <head>, before stylesheets: no Vite/module startup delay.
    sync();
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-admin-theme-toggle]')) window.WPiAdminTheme.toggle();
    });
    document.addEventListener('DOMContentLoaded', sync);
    document.addEventListener('livewire:navigated', sync);
    window.addEventListener('storage', (event) => {
        if (event.key !== key) return;
        preference = valid(event.newValue) ? event.newValue : null;
        current = preference || (media?.matches ? 'dark' : 'light');
        sync();
    });
    media?.addEventListener?.('change', (event) => {
        if (preference !== null) return;
        current = event.matches ? 'dark' : 'light';
        sync();
    });
})();
