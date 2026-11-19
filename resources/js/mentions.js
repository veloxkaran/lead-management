// @mention suggestions for comment boxes. Any <textarea>/<input> with
// data-mentions="<url>" gets a teammate picker when you type "@": the URL
// (MentionController) returns only active teammates allowed to see that
// record. Picking one inserts their @handle, which the server turns into a
// notification + Slack post when the comment is saved (MentionNotifier).
//
// Delegated from the document, so fields Alpine renders later (Meeting Room
// replies) work too.

const MAX_RESULTS = 7;
const TOKEN = /(?:^|[\s(])@([\w.]*)$/;

const cache = new Map(); // url => Promise<users[]>
let menu = null;
let state = null; // { field, items, active, start, end }

function loadUsers(url) {
    if (!cache.has(url)) {
        cache.set(url, fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : []))
            .catch(() => []));
    }

    return cache.get(url);
}

function initials(name) {
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');
}

function ensureMenu() {
    if (!menu) {
        menu = document.createElement('div');
        menu.className = 'mention-menu';
        menu.id = 'mention-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        // Keep focus in the field while clicking a suggestion.
        menu.addEventListener('mousedown', (event) => event.preventDefault());
        document.body.appendChild(menu);
    }

    return menu;
}

function close() {
    if (menu) {
        menu.hidden = true;
    }

    if (state?.field) {
        state.field.setAttribute('aria-expanded', 'false');
        state.field.removeAttribute('aria-activedescendant');
    }

    state = null;
}

function position(field) {
    const rect = field.getBoundingClientRect();
    const menuHeight = menu.offsetHeight || 240;
    const below = rect.bottom + 4;
    const fitsBelow = below + menuHeight < window.innerHeight;

    menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8)) + window.scrollX}px`;
    menu.style.top = `${(fitsBelow ? below : rect.top - menuHeight - 4) + window.scrollY}px`;
    menu.style.minWidth = `${Math.min(Math.max(rect.width * 0.6, 240), 360)}px`;
}

function setActive(index) {
    if (!state || !state.items.length) {
        return;
    }

    state.active = (index + state.items.length) % state.items.length;

    [...menu.querySelectorAll('.mention-option')].forEach((el, i) => {
        el.classList.toggle('active', i === state.active);
        el.setAttribute('aria-selected', i === state.active ? 'true' : 'false');
        if (i === state.active) {
            state.field.setAttribute('aria-activedescendant', el.id);
            el.scrollIntoView({ block: 'nearest' });
        }
    });
}

function render(field, users, query, start, end) {
    const q = query.toLowerCase();
    const matches = users
        .map((user) => {
            const name = user.name.toLowerCase();
            const rank = user.handle.startsWith(q) || name.startsWith(q) ? 0
                : name.split(/\s+/).some((word) => word.startsWith(q)) ? 1
                    : user.handle.includes(q) || name.includes(q) ? 2 : -1;

            return { user, rank };
        })
        .filter((m) => m.rank >= 0)
        .sort((a, b) => a.rank - b.rank)
        .slice(0, MAX_RESULTS)
        .map((m) => m.user);

    ensureMenu().replaceChildren();

    if (!matches.length) {
        if (!users.length) {
            close();

            return;
        }

        const empty = document.createElement('div');
        empty.className = 'mention-empty';
        empty.textContent = `No teammate matches “${query}”`;
        menu.appendChild(empty);
    }

    matches.forEach((user, index) => {
        const option = document.createElement('div');
        option.className = 'mention-option';
        option.id = `mention-option-${index}`;
        option.setAttribute('role', 'option');

        const avatar = document.createElement('span');
        avatar.className = 'mention-avatar';
        avatar.textContent = initials(user.name);

        const text = document.createElement('span');
        text.className = 'mention-text';
        const name = document.createElement('span');
        name.className = 'mention-name';
        name.textContent = user.name;
        const meta = document.createElement('span');
        meta.className = 'mention-meta';
        meta.textContent = `@${user.handle}${user.role ? ` · ${user.role}` : ''}`;
        text.append(name, meta);

        option.append(avatar, text);
        option.addEventListener('mousemove', () => setActive(index));
        option.addEventListener('click', () => choose(index));
        menu.appendChild(option);
    });

    state = { field, items: matches, active: 0, start, end };
    menu.hidden = false;
    field.setAttribute('aria-expanded', 'true');
    field.setAttribute('aria-controls', 'mention-menu');
    position(field);
    setActive(0);
}

function choose(index) {
    if (!state || !state.items[index]) {
        return;
    }

    const { field, start, end } = state;
    const insert = `@${state.items[index].handle} `;

    field.value = field.value.slice(0, start) + insert + field.value.slice(end);
    const caret = start + insert.length;
    field.setSelectionRange(caret, caret);

    // Alpine x-model and form drafts listen for "input".
    field.dispatchEvent(new Event('input', { bubbles: true }));
    close();
    field.focus();
}

async function update(field) {
    const caret = field.selectionStart;

    if (caret !== field.selectionEnd) {
        close();

        return;
    }

    const match = field.value.slice(0, caret).match(TOKEN);

    if (!match) {
        close();

        return;
    }

    const users = await loadUsers(field.dataset.mentions);

    // The user may have kept typing while the list loaded.
    if (document.activeElement !== field || field.selectionStart !== caret) {
        return;
    }

    render(field, users, match[1], caret - match[1].length - 1, caret);
}

function isMentionField(el) {
    return el instanceof HTMLElement && el.matches('textarea[data-mentions], input[data-mentions]');
}

document.addEventListener('input', (event) => {
    if (isMentionField(event.target) && event.isTrusted) {
        update(event.target);
    }
});

document.addEventListener('click', (event) => {
    if (isMentionField(event.target)) {
        update(event.target);
    }
});

document.addEventListener('keydown', (event) => {
    if (!state || event.target !== state.field || menu.hidden) {
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(state.active + (event.key === 'ArrowDown' ? 1 : -1));
    } else if ((event.key === 'Enter' || event.key === 'Tab') && state.items.length) {
        event.preventDefault(); // don't submit / leave the field
        choose(state.active);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation(); // don't also close a surrounding modal
        close();
    }
}, true);

document.addEventListener('focusout', (event) => {
    if (state && event.target === state.field) {
        setTimeout(() => document.activeElement !== state?.field && close(), 120);
    }
});

window.addEventListener('resize', () => state && position(state.field));
window.addEventListener('scroll', () => state && position(state.field), true);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-mentions], input[data-mentions]').forEach((field) => {
        field.setAttribute('aria-autocomplete', 'list');
        field.setAttribute('aria-expanded', 'false');
    });
});
