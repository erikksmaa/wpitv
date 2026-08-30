/**
 * WPiTV Custom Admin JavaScript
 * Mazer Core behaviors (sidebar, dark theme, perfect-scrollbar) are loaded directly from public/mazer/
 */

import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

(function () {
    'use strict';

    const seenMessages = new Map();

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        showCloseButton: true,
        timer: 4200,
        timerProgressBar: true,
        didOpen: (popup) => {
            popup.addEventListener('mouseenter', Swal.stopTimer);
            popup.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });

    function normalizeType(type) {
        return type === 'danger' ? 'error' : (type || 'info');
    }

    function simplifyToastMessage(message, type) {
        if (type !== 'success') return message;

        const welcomeMatch = message.match(/^Selamat datang kembali,\s*(.+?)\.\s*Anda berhasil masuk/i);
        if (welcomeMatch) return `Selamat datang kembali, ${welcomeMatch[1]}.`;

        if (/status.+berhasil diperbarui/i.test(message)) return 'Status berhasil diubah.';
        if (/berhasil (ditambahkan|dibuat)/i.test(message)) return 'Data berhasil ditambahkan.';
        if (/berhasil (diperbarui|diubah)/i.test(message)) return 'Data berhasil diubah.';
        if (/berhasil dihapus/i.test(message)) return 'Data berhasil dihapus.';

        return message;
    }

    function toast(message, type = 'success', options = {}) {
        const cleanMessage = String(message || '').trim();
        if (!cleanMessage) return;

        const normalizedType = normalizeType(type);
        const displayMessage = simplifyToastMessage(cleanMessage, normalizedType);

        const now = Date.now();
        const key = `${normalizedType}:${displayMessage}`;
        if (seenMessages.has(key) && now - seenMessages.get(key) < 1200) return;
        seenMessages.set(key, now);

        const duration = options.duration ?? (normalizedType === 'error' ? 6000 : 4200);
        return Toast.fire({
            icon: normalizedType,
            title: displayMessage,
            timer: duration,
        });
    }

    function confirmAction(options = {}) {
        const type = normalizeType(options.type || 'warning');
        return Swal.fire({
            title: options.title || 'Apakah Anda yakin?',
            text: options.message || undefined,
            icon: type,
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Ya, lanjutkan',
            cancelButtonText: options.cancelText || 'Batal',
            confirmButtonColor: type === 'warning' || type === 'error' ? '#dc3545' : '#2788d8',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false,
        }).then((result) => result.isConfirmed);
    }

    function consumeAlerts(root = document) {
        root.querySelectorAll?.('.page-content .alert:not([data-wpi-consumed])').forEach((alert) => {
            if (alert.closest('.swal2-container')) return;
            const type = alert.classList.contains('alert-success') ? 'success'
                : alert.classList.contains('alert-danger') ? 'error'
                    : alert.classList.contains('alert-warning') ? 'warning' : 'info';
            const clone = alert.cloneNode(true);
            clone.querySelectorAll('button, i').forEach((node) => node.remove());
            alert.dataset.wpiConsumed = 'true';
            toast(clone.textContent.replace(/\s+/g, ' ').trim(), type);
            alert.remove();
        });
    }

    function syncModalOpenState() {
        const hasOpenAdminModal = document.querySelector('.modal.show.d-block[role="dialog"]');
        document.body.classList.toggle('modal-open', Boolean(hasOpenAdminModal));
    }

    function syncSidebarAccessibility() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.admin-menu-toggle');
        if (!sidebar || !toggle) return;

        const expanded = sidebar.classList.contains('active') && window.innerWidth < 1200;
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'Tutup menu admin' : 'Buka menu admin');
    }

    function initializeAdminSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || sidebar.dataset.wpiSidebarReady === 'true') {
            syncSidebarAccessibility();
            return;
        }

        sidebar.dataset.wpiSidebarReady = 'true';
        new MutationObserver(syncSidebarAccessibility).observe(sidebar, {
            attributes: true,
            attributeFilter: ['class'],
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && window.innerWidth < 1200 && sidebar.classList.contains('active')) {
                window.sidebar?.hide?.();
                document.querySelector('.admin-menu-toggle')?.focus();
            }
        });

        sidebar.addEventListener('click', (event) => {
            const link = event.target.closest('a.sidebar-link:not([href="#"]), a.submenu-link');
            if (link && window.innerWidth < 1200) window.sidebar?.hide?.();
        });

        window.addEventListener('resize', syncSidebarAccessibility, { passive: true });
        syncSidebarAccessibility();
    }

    function submitConfirmation(form) {
        if (form.id === 'logout-form' || form.dataset.adminConfirm === 'logout') {
            return {
                title: 'Apakah Anda yakin ingin keluar?',
                message: 'Sesi Anda akan diakhiri.',
                confirmText: 'Ya, keluar',
                type: 'warning',
            };
        }

        const submitDirective = form.getAttributeNames().find((name) => name.startsWith('wire:submit')) || '';
        const action = form.getAttribute(submitDirective) || '';
        if (action.includes('updateStatus')) {
            return {
                title: 'Apakah Anda yakin ingin mengubah status ini?',
                confirmText: 'Ya, ubah',
                type: 'info',
            };
        }

        const editing = /\/edit(?:\/|$)/.test(window.location.pathname);
        return editing ? {
            title: 'Apakah Anda yakin ingin mengubah data ini?',
            confirmText: 'Ya, ubah',
            type: 'info',
        } : {
            title: 'Apakah Anda yakin ingin menambahkan data ini?',
            confirmText: 'Ya, tambah',
            type: 'info',
        };
    }

    document.addEventListener('click', async (event) => {
        const target = event.target.closest('[data-admin-confirm]:not(form)');
        if (!target) return;
        if (target.dataset.adminConfirmed === 'true') {
            delete target.dataset.adminConfirmed;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const isUpdate = target.dataset.adminConfirmKind === 'update';
        const approved = await confirmAction({
            title: isUpdate ? 'Apakah Anda yakin ingin mengubah status ini?' : 'Apakah Anda yakin ingin menghapus data ini?',
            message: isUpdate ? 'Status akan disimpan sebelum draft WhatsApp disiapkan.' : 'Data yang dihapus tidak dapat dikembalikan.',
            confirmText: isUpdate ? 'Ya, simpan' : 'Ya, hapus',
            type: isUpdate ? 'info' : 'warning',
        });
        if (approved && target.isConnected) {
            target.dataset.adminConfirmed = 'true';
            target.click();
        }
    }, true);

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const isLivewireSubmit = form.getAttributeNames().some((name) => name.startsWith('wire:submit'));
        const needsConfirmation = isLivewireSubmit || form.dataset.adminConfirm === 'logout';
        if (!needsConfirmation || form.dataset.adminConfirmed === 'true') {
            delete form.dataset.adminConfirmed;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter;
        const approved = await confirmAction(submitConfirmation(form));
        if (approved && form.isConnected) {
            form.dataset.adminConfirmed = 'true';
            form.requestSubmit(submitter instanceof HTMLElement ? submitter : undefined);
        }
    }, true);

    window.Swal = Swal;
    window.WPiAlert = { Toast, toast, confirm: confirmAction };

    document.addEventListener('DOMContentLoaded', () => {
        consumeAlerts();
        syncModalOpenState();
        initializeAdminSidebar();
    });
    document.addEventListener('livewire:navigated', () => {
        consumeAlerts();
        syncModalOpenState();
        initializeAdminSidebar();
    });

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) consumeAlerts(node.parentElement || document);
        }));
        syncModalOpenState();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
