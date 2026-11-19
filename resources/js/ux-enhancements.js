// App-wide UX touches that work on the existing markup — no per-page changes.
//
//  1. Instant filters: list filter forms (GET forms with a "Filter" funnel
//     button) apply as soon as a dropdown/date/checkbox changes, and the
//     search box you were typing in keeps focus after the page reloads.
//  2. Busy buttons: a POST form's submit button shows a spinner and locks
//     after the first click, so a slow save can't create duplicates.
//  3. Scrollable tab strips (lead page) fade at the edge that has more tabs,
//     scroll with the mouse wheel, and keep the active tab in view.
//  4. Sidebar keeps the current page's link in view; "/" jumps to the page's
//     search box (or opens quick search when the page has none).

const FOCUS_KEY = 'ux:filter-focus';

function isFilterForm(form) {
    return form instanceof HTMLFormElement
        && (form.getAttribute('method') || 'get').toLowerCase() === 'get'
        && form.dataset.autoFilter !== 'off'
        && !!form.querySelector('button .bi-funnel');
}

function isVisible(el) {
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
}

function isTypingTarget(el) {
    return !!el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
}

// ---- 1. Instant filters ---------------------------------------------------

function initInstantFilters() {
    const onChange = (field) => {
        const form = field.form;

        if (!isFilterForm(form) || field.type === 'text' || field.type === 'search') {
            return;
        }

        // "Custom range" only reveals the date fields — wait for the dates.
        if (field.value === 'custom') {
            return;
        }

        // Date ranges apply once every visible date field is filled in.
        if (field.type === 'date') {
            const dates = [...form.querySelectorAll('input[type="date"]')].filter(isVisible);
            if (dates.some((d) => !d.value)) {
                return;
            }
        }

        form.requestSubmit();
    };

    // jQuery's delegated handler also sees select2's jQuery-triggered
    // "change", which a native listener would miss.
    if (window.jQuery) {
        window.jQuery(document).on('change', 'form select, form input[type="date"], form input[type="checkbox"], form input[type="radio"]', function () {
            onChange(this);
        });
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!isFilterForm(form)) {
            return;
        }

        const field = document.activeElement;

        if (field?.form === form && field.name && /^(text|search)$/.test(field.type)) {
            try {
                sessionStorage.setItem(FOCUS_KEY, JSON.stringify({ path: location.pathname, name: field.name }));
            } catch (e) { /* storage blocked — focus just isn't restored */ }
        }

        document.body.classList.add('is-filtering');
    });

    let saved = null;
    try {
        saved = JSON.parse(sessionStorage.getItem(FOCUS_KEY) || 'null');
        sessionStorage.removeItem(FOCUS_KEY);
    } catch (e) { /* ignore */ }

    if (saved && saved.path === location.pathname) {
        const field = [...document.querySelectorAll('form input')].find((el) => el.name === saved.name && isFilterForm(el.form));

        if (field) {
            field.focus({ preventScroll: true });
            const end = field.value.length;
            field.setSelectionRange?.(end, end);
        }
    }
}

// ---- 2. Busy buttons ------------------------------------------------------

function setBusy(button, busy) {
    button.classList.toggle('is-busy', busy);
    button.disabled = busy;
    button.toggleAttribute('aria-busy', busy);
}

function initBusyButtons() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)
            || (form.getAttribute('method') || 'get').toLowerCase() !== 'post'
            || form.target
            || form.hasAttribute('data-no-busy')) {
            return;
        }

        const button = event.submitter instanceof HTMLButtonElement
            ? event.submitter
            : form.querySelector('button[type="submit"], button:not([type])');

        if (!button) {
            return;
        }

        // Deferred one tick: (a) every other submit handler — confirm
        // dialogs, AJAX forms — has run, so defaultPrevented is final; and
        // (b) the browser has already captured the clicked button's
        // name/value (e.g. "Save & add another") before it is disabled.
        setTimeout(() => {
            if (event.defaultPrevented) {
                return;
            }

            setBusy(button, true);

            // Safety net for responses that don't leave the page (file
            // downloads, validation handled in place).
            setTimeout(() => setBusy(button, false), 20000);
        }, 0);
    });

    // Back/forward cache restores the page as it was left — spinner included.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            document.querySelectorAll('.is-busy').forEach((button) => setBusy(button, false));
            document.body.classList.remove('is-filtering');
        }
    });
}

// ---- 3. Scrollable tab strips --------------------------------------------

function initScrollableTabs() {
    document.querySelectorAll('.lead-tabs').forEach((strip) => {
        const update = () => {
            const max = strip.scrollWidth - strip.clientWidth;
            strip.classList.toggle('has-more-start', strip.scrollLeft > 4);
            strip.classList.toggle('has-more-end', strip.scrollLeft < max - 4);
        };

        strip.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        // A plain mouse wheel only scrolls vertically — let it move the strip.
        strip.addEventListener('wheel', (event) => {
            if (strip.scrollWidth > strip.clientWidth && Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
                event.preventDefault();
                strip.scrollLeft += event.deltaY;
            }
        }, { passive: false });

        const keepActiveInView = () => {
            strip.querySelector('.nav-link.active')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            update();
        };

        strip.addEventListener('shown.bs.tab', keepActiveInView);
        strip.addEventListener('click', () => requestAnimationFrame(keepActiveInView));
        keepActiveInView();
    });
}

// ---- 4. Sidebar + "/" shortcut -------------------------------------------

function initNavigationHelpers() {
    document.querySelector('.app-sidebar .nav-link.active')?.scrollIntoView({ block: 'nearest' });

    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey
            || isTypingTarget(document.activeElement) || document.querySelector('.modal.show')) {
            return;
        }

        const search = [...document.querySelectorAll('.app-content form input[type="text"], .app-content form input[type="search"]')]
            .find((el) => isVisible(el) && isFilterForm(el.form) && /search|^q$/i.test(el.name));

        event.preventDefault();

        if (search) {
            search.focus();
            search.select();
        } else {
            const palette = document.getElementById('commandPalette');
            palette && window.bootstrap?.Modal.getOrCreateInstance(palette).show();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initInstantFilters();
    initBusyButtons();
    initScrollableTabs();
    initNavigationHelpers();
});
