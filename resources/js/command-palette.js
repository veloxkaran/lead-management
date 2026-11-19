// Ctrl/⌘+K quick search (markup: resources/views/partials/command-palette.blade.php).
//
// Three kinds of results, all plain links:
//   • "Search …"  — hands the typed text to an index page's existing search filter
//   • quick actions — "Add lead", "New task"… (rendered server-side with @can)
//   • "Go to"     — every page in the sidebar, read from the DOM so the list
//                   always matches what this member is allowed to see
// No new endpoints: everything lands on pages and filters that already exist.

// Index pages whose list filter takes free text, keyed by the sidebar link's path.
const SEARCHABLE = [
    { path: '/leads', param: 'search', label: 'leads', icon: 'bi-diagram-3' },
    { path: '/contacts', param: 'search', label: 'contacts', icon: 'bi-person-lines-fill' },
    { path: '/raw-data', param: 'search', label: 'raw data', icon: 'bi-inbox' },
    { path: '/requirements', param: 'search', label: 'requirements', icon: 'bi-list-check' },
    { path: '/support-tickets', param: 'search', label: 'support tickets', icon: 'bi-life-preserver' },
    { path: '/knowledge-base', param: 'q', label: 'knowledge base', icon: 'bi-journal-richtext' },
    { path: '/users', param: 'search', label: 'users', icon: 'bi-people' },
];

const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

function normalize(text) {
    return (text || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function sidebarPages() {
    const seen = new Set();

    return [...document.querySelectorAll('.app-sidebar a.nav-link[href]')]
        .map((link) => {
            const icon = [...(link.querySelector('i')?.classList || [])].find((c) => c.startsWith('bi-')) || 'bi-arrow-right';
            const label = (link.querySelector('.nav-label')?.textContent || link.textContent).trim();
            return { kind: 'page', label, href: link.href, icon, keywords: normalize(findSectionTitle(link)) };
        })
        .filter((page) => page.label && !seen.has(page.href) && seen.add(page.href));
}

// The section heading ("Business Development"…) is the nearest preceding
// .nav-section-title sibling — handy as extra search keywords.
function findSectionTitle(link) {
    let node = link.previousElementSibling;
    while (node) {
        if (node.classList?.contains('nav-section-title')) {
            return node.textContent;
        }
        node = node.previousElementSibling;
    }

    return '';
}

const pathOf = (href) => new URL(href, location.href).pathname.replace(/\/$/, '');

// Only actions whose section (data-section, e.g. "/leads") is in this
// member's sidebar — no "Add lead" for someone who can't see the leads list.
function quickActions(pages) {
    const template = document.getElementById('commandPaletteActions');
    if (!template) {
        return [];
    }

    const visible = pages.map((page) => pathOf(page.href));

    return [...template.content.querySelectorAll('a[href]')]
        .filter((a) => !a.dataset.section || visible.some((path) => path.endsWith(a.dataset.section)))
        .map((a) => ({
        kind: 'action',
        label: a.textContent.trim(),
        href: a.href,
        icon: a.dataset.icon || 'bi-plus-lg',
        keywords: normalize(a.dataset.keywords),
    }));
}

function searchTargets(pages) {
    return SEARCHABLE.map((target) => {
        const page = pages.find((p) => pathOf(p.href).endsWith(target.path));

        return page ? { ...target, base: new URL(page.href).origin + new URL(page.href).pathname } : null;
    }).filter(Boolean);
}

function score(item, tokens) {
    const label = normalize(item.label);
    const haystack = `${label} ${item.keywords || ''}`;

    if (!tokens.every((token) => haystack.includes(token))) {
        return -1;
    }

    const query = tokens.join(' ');

    if (label.startsWith(query)) {
        return 3;
    }

    return label.includes(query) ? 2 : 1;
}

function init() {
    const modalEl = document.getElementById('commandPalette');
    const input = document.getElementById('commandPaletteInput');
    const list = document.getElementById('commandPaletteList');

    if (!modalEl || !input || !list || !window.bootstrap) {
        return;
    }

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    const pages = sidebarPages();
    const actions = quickActions(pages);
    const searches = searchTargets(pages);
    let items = [];
    let active = 0;

    document.querySelectorAll('[data-shortcut-label]').forEach((el) => {
        el.textContent = isMac ? '⌘K' : 'Ctrl K';
    });

    const render = () => {
        const query = normalize(input.value);
        const tokens = query ? query.split(' ') : [];
        const rank = (pool) => pool
            .map((item) => ({ item, score: tokens.length ? score(item, tokens) : 0 }))
            .filter((r) => r.score >= 0)
            .sort((a, b) => b.score - a.score)
            .map((r) => r.item);

        const create = { title: 'Create', items: rank(actions) };
        const goTo = { title: 'Go to', items: rank(pages) };

        // No query: shortcuts first. With a query: a matching page/action is
        // usually what people want, then the "Search … for" rows (which come
        // first anyway when nothing else matches, e.g. a company name).
        const groups = query
            ? [goTo, create, {
                title: 'Search',
                items: searches.map((s) => ({
                    kind: 'search',
                    label: `Search ${s.label} for “${input.value.trim()}”`,
                    href: `${s.base}?${new URLSearchParams({ [s.param]: input.value.trim() })}`,
                    icon: 'bi-search',
                })),
            }]
            : [create, goTo];

        list.replaceChildren();
        items = [];

        groups.filter((g) => g.items.length).forEach((group) => {
            const heading = document.createElement('div');
            heading.className = 'command-palette-group';
            heading.textContent = group.title;
            list.appendChild(heading);

            group.items.forEach((item) => {
                const index = items.length;
                const link = document.createElement('a');
                link.className = 'command-palette-item';
                link.href = item.href;
                link.id = `command-palette-item-${index}`;
                link.setAttribute('role', 'option');
                link.tabIndex = -1;

                const icon = document.createElement('i');
                icon.className = `bi ${item.icon}`;
                icon.setAttribute('aria-hidden', 'true');

                const label = document.createElement('span');
                label.className = 'command-palette-label';
                label.textContent = item.label;

                const enter = document.createElement('i');
                enter.className = 'bi bi-arrow-return-left command-palette-enter';
                enter.setAttribute('aria-hidden', 'true');

                link.append(icon, label, enter);
                link.addEventListener('mousemove', () => setActive(index, false));
                list.appendChild(link);
                items.push(link);
            });
        });

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'command-palette-empty';
            empty.textContent = 'No matches. Try a different word.';
            list.appendChild(empty);
        }

        setActive(0);
    };

    const setActive = (index, scroll = true) => {
        if (!items.length) {
            input.removeAttribute('aria-activedescendant');

            return;
        }

        active = (index + items.length) % items.length;
        items.forEach((el, i) => el.classList.toggle('active', i === active));
        items.forEach((el, i) => el.setAttribute('aria-selected', i === active ? 'true' : 'false'));
        input.setAttribute('aria-activedescendant', items[active].id);

        if (scroll) {
            items[active].scrollIntoView({ block: 'nearest' });
        }
    };

    input.addEventListener('input', render);

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && items[active]) {
            event.preventDefault();

            if (event.metaKey || event.ctrlKey) {
                window.open(items[active].href, '_blank');
            } else {
                window.location.href = items[active].href;
            }
        }
    });

    modalEl.addEventListener('show.bs.modal', () => {
        input.value = '';
        render();
    });
    modalEl.addEventListener('shown.bs.modal', () => input.focus());

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && !event.altKey && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            modalEl.classList.contains('show') ? modal.hide() : modal.show();
        }
    });
}

document.addEventListener('DOMContentLoaded', init);
