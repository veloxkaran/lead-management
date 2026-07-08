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

document.addEventListener('DOMContentLoaded', () => {
    // Bootstrap tooltip/popover activation
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Select2 on any [data-select2] element
    if (window.jQuery) {
        window.jQuery('[data-select2]').select2({ theme: 'bootstrap-5', width: '100%' });
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
                confirmButtonText: 'Yes, delete it',
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
});
