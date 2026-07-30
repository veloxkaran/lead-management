import './bootstrap';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import Chart from 'chart.js/auto';
window.Chart = Chart;

import Swal from 'sweetalert2';
window.Swal = Swal;

import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

import 'datatables.net-bs5';
import 'select2';

import './activity-feed';
import './raw-data-paste-grid';
import './raw-data-countdown';
import './attachment-preview';

document.addEventListener('DOMContentLoaded', () => {
    // Bootstrap tooltip/popover activation
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Select2 on any [data-select2] element. Wrapped defensively: a failure
    // here (e.g. a jQuery/select2 version mismatch) would otherwise throw
    // synchronously and silently abort every handler registered later in
    // this same callback — including the sidebar toggles below.
    if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
        window.jQuery('[data-select2]').select2({ theme: 'bootstrap-5', width: '100%' });
    } else if (window.jQuery) {
        console.error('select2 plugin is not registered on jQuery — skipping select2 initialization.');
    }

    // Confirm-and-submit delete forms via SweetAlert2
    document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();

            Swal.fire({
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirmText || 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: form.dataset.confirmButtonText || 'Yes, delete it',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });

    // Sidebar toggle for small screens
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    }

    // Collapsible sidebar (icon-rail) for large screens, persisted across page loads.
    // The `sidebar-collapsed` class on <html> is set synchronously before paint by
    // an inline script in layouts/app.blade.php, so this only needs to sync the
    // toggle button's icon and handle clicks — no flash of the wrong state.
    const sidebarCollapseToggle = document.getElementById('sidebarCollapseToggle');
    if (sidebarCollapseToggle) {
        const icon = sidebarCollapseToggle.querySelector('i');

        const syncIcon = (collapsed) => {
            icon.classList.toggle('bi-layout-sidebar-inset', !collapsed);
            icon.classList.toggle('bi-layout-sidebar', collapsed);
        };

        syncIcon(document.documentElement.classList.contains('sidebar-collapsed'));

        sidebarCollapseToggle.addEventListener('click', () => {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
            syncIcon(collapsed);
        });
    }
});
