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

import './jquery-global';

import 'datatables.net-bs5';

// select2's own module.exports is the registration function itself
// (`module.exports = function (root, jQuery) { ...; factory(jQuery); }`,
// see node_modules/select2/dist/js/select2.js) — it registers $.fn.select2
// only once *called*, not merely imported/required. A side-effect-only
// `import 'select2'` never calls it, so $.fn.select2 stays undefined and
// every select2-enhanced dropdown silently falls back to a plain <select>.
import registerSelect2 from 'select2';
registerSelect2(window, window.jQuery);

import './performance-snapshot';
import './raw-data-paste-grid';
import './raw-data-countdown';
import './ticket-elapsed';
import './attachment-preview';
import './lead-duplicate-check';

import { initRichTextEditors } from './rich-text-editor';
import { initCkEditors } from './ck-editor';

document.addEventListener('DOMContentLoaded', () => {
    // Bootstrap tooltip/popover activation
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Rich text editors outside modals init now; ones inside a modal defer
    // to shown.bs.modal below (same reasoning as the select2 deferral).
    initRichTextEditors();
    initCkEditors();
    document.addEventListener('shown.bs.modal', (event) => {
        initRichTextEditors(event.target);
        initCkEditors(event.target);
    });

    // Select2 on any [data-select2-field] element. Wrapped defensively: a failure
    // here (e.g. a jQuery/select2 version mismatch) would otherwise throw
    // synchronously and silently abort every handler registered later in
    // this same callback — including the sidebar toggles below.
    if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
        const initSelect2 = ($el) => {
            if ($el.data('select2')) {
                return; // already initialized — a modal can be shown more than once
            }

            // A select2 dropdown inside a Bootstrap modal renders detached
            // from the modal (often mispositioned/behind it) unless its
            // dropdown is explicitly parented to that modal instead of the
            // document body select2 defaults to.
            const $modal = $el.closest('.modal');

            $el.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $modal.length ? $modal : window.jQuery(document.body),
            });
        };

        window.jQuery('[data-select2-field]').each(function () {
            const $el = window.jQuery(this);

            // A select2 field measures its own width at init time — a
            // Bootstrap modal is display:none until shown, so a field
            // inside one collapses to zero width if initialized here at
            // page load. Defer those to the modal's shown.bs.modal event
            // below instead, once it actually has real layout.
            if ($el.closest('.modal').length) {
                return;
            }

            initSelect2($el);
        });

        window.jQuery(document).on('shown.bs.modal', '.modal', function () {
            window.jQuery(this).find('[data-select2-field]').each(function () {
                initSelect2(window.jQuery(this));
            });
        });
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
