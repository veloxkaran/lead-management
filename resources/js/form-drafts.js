// Form drafts: whatever someone types into a form is kept in this browser
// (localStorage) as they type, so a refresh, a crash or a dropped connection
// mid-save doesn't lose it. Reopening the page puts it back, with a notice
// and a "Discard" button.
//
// Which forms: POST forms inside the page content that have something to
// type into (text, textarea, rich text). Forms made only of dropdowns/
// checkboxes (e.g. "change status") are quick to redo and restoring an
// unsaved pick there would only mislead, so they are skipped.
//
// Never stored: passwords, file uploads, hidden fields (CSRF tokens etc.)
// and anything named like a secret/token/PIN. Opt out with data-no-autosave
// on a form or a field.
//
// When a draft is deleted:
//   • the save went through — the server answered with a redirect and the
//     page shows no validation errors (see settlePendingSubmit). A failed
//     connection never gets that far, so the draft survives it.
//   • "Discard" is clicked, the form is reset, or the draft is older than
//     MAX_AGE_MS.
//   • the member logs out (so a shared computer doesn't keep their drafts).
//
// Runs before the select2 / Quill / CKEditor setup in app.js (its
// DOMContentLoaded listener is registered first), so restored values are
// what those widgets start from.

const PREFIX = 'draft:v1:';
const PENDING_KEY = 'draft:pending';
const MAX_AGE_MS = 24 * 60 * 60 * 1000;
const SENSITIVE = /password|secret|token|api_?key|(^|_)(pin|otp|cvv)($|_)/i;
const TYPEABLE = 'textarea, input:not([type]), input[type="text"], input[type="email"], input[type="tel"], input[type="url"], input[type="number"], input[type="search"], [data-rich-text-input]';

const userId = document.querySelector('meta[name="user-id"]')?.content;

const storage = {
    get(key) {
        try {
            return JSON.parse(localStorage.getItem(key) || 'null');
        } catch (e) {
            return null;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // Full (e.g. a pasted image in rich text) or blocked — drop
            // this draft rather than keep a stale one.
            this.remove(key);
        }
    },
    remove(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) { /* ignore */ }
    },
    keys() {
        try {
            return Object.keys(localStorage).filter((key) => key.startsWith(PREFIX));
        } catch (e) {
            return [];
        }
    },
};

function isPost(form) {
    return (form.getAttribute('method') || 'get').toLowerCase() === 'post';
}

function isLogout(form) {
    return /\/logout\/?$/.test(new URL(form.action, location.href).pathname);
}

function trackedFields(form) {
    return [...form.elements].filter((el) => {
        if (!el.name || el.disabled || el.closest('[data-no-autosave]') || SENSITIVE.test(el.name)) {
            return false;
        }

        if (el.type === 'hidden') {
            return el.hasAttribute('data-rich-text-input'); // Quill's content field
        }

        return !['password', 'file', 'submit', 'button', 'reset', 'image'].includes(el.type)
            && el.tagName !== 'BUTTON' && el.tagName !== 'FIELDSET' && el.tagName !== 'OUTPUT';
    });
}

function isDraftable(form) {
    return isPost(form)
        && !isLogout(form)
        && !form.target
        && !form.hasAttribute('data-no-autosave')
        && !!form.closest('.app-content, .modal')
        && trackedFields(form).some((el) => el.matches(TYPEABLE));
}

// Stable id for a form: its action path, plus a counter when several forms
// on one page post to the same place (e.g. a comment box per item).
function formKeys(forms) {
    const seen = {};

    return forms.map((form) => {
        const action = new URL(form.action, location.href).pathname;
        seen[action] = (seen[action] || 0) + 1;

        return `${PREFIX}${userId}:${location.pathname}:${action}:${seen[action]}`;
    });
}

// Field identity inside a form: checkboxes by name+value, everything else by
// name + how many earlier fields share that name (for name="items[]" rows).
function fieldId(el, counts) {
    if (el.type === 'checkbox' || el.type === 'radio') {
        return `${el.name}=${el.value}`;
    }

    counts[el.name] = (counts[el.name] || 0) + 1;

    return `${el.name}#${counts[el.name]}`;
}

function readValue(el) {
    if (el.type === 'checkbox' || el.type === 'radio') {
        return el.checked;
    }

    if (el.tagName === 'SELECT' && el.multiple) {
        return [...el.selectedOptions].map((o) => o.value);
    }

    return el.value;
}

function snapshot(form) {
    const counts = {};
    const fields = {};

    trackedFields(form).forEach((el) => {
        fields[fieldId(el, counts)] = readValue(el);
    });

    return fields;
}

function sameValue(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
}

function writeValue(el, value) {
    if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = !!value;
    } else if (el.tagName === 'SELECT' && el.multiple) {
        [...el.options].forEach((o) => { o.selected = value.includes(o.value); });
    } else if (el.tagName === 'SELECT' && ![...el.options].some((o) => o.value === value)) {
        return false; // that choice isn't offered any more
    } else {
        el.value = value;
    }

    // Native events so Alpine (x-model) and dependent widgets follow along.
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
}

function restore(form, draft) {
    const counts = {};
    let changed = 0;

    trackedFields(form).forEach((el) => {
        const id = fieldId(el, counts);

        if (id in draft.fields && !sameValue(readValue(el), draft.fields[id]) && writeValue(el, draft.fields[id])) {
            changed++;
        }
    });

    return changed;
}

function timeAgo(timestamp) {
    const minutes = Math.round((Date.now() - timestamp) / 60000);

    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.round(minutes / 60);

    return `${hours} hour${hours === 1 ? '' : 's'} ago`;
}

function showNotice(form, draft, onDiscard) {
    const notice = document.createElement('div');
    notice.className = 'alert alert-info draft-notice d-flex align-items-center gap-2 py-2 px-3 small';
    notice.setAttribute('role', 'status');

    const icon = document.createElement('i');
    icon.className = 'bi bi-cloud-check';

    const text = document.createElement('span');
    text.className = 'flex-grow-1';
    text.textContent = `We restored what you typed here ${timeAgo(draft.savedAt)} — it hasn't been saved yet.`;

    const discard = document.createElement('button');
    discard.type = 'button';
    discard.className = 'btn btn-sm btn-link p-0 text-decoration-none';
    discard.textContent = 'Discard';
    discard.addEventListener('click', onDiscard);

    notice.append(icon, text, discard);
    form.prepend(notice);
}

// Did the last submit go through? Laravel answers a processed POST with a
// redirect — to another page on success, or back to the form with errors
// when validation fails. So: this page load came from a redirect and shows
// no validation errors → saved. A dropped connection, a server error page or
// an expired session never redirects here, so the draft survives those.
// Read on the very next page load only (even a signed-out one), so a later
// unrelated redirect — like logging back in — can't be mistaken for it.
function takePendingSubmit() {
    try {
        const pending = JSON.parse(sessionStorage.getItem(PENDING_KEY) || 'null');
        sessionStorage.removeItem(PENDING_KEY);

        return pending && Date.now() - pending.at < 5 * 60 * 1000 ? pending : null;
    } catch (e) {
        return null;
    }
}

function settlePendingSubmit(pending) {
    const navigation = performance.getEntriesByType?.('navigation')?.[0];
    const redirected = navigation?.type === 'navigate' && navigation.redirectCount > 0;
    const hasErrors = !!document.querySelector('.app-content > .alert-danger, .app-content .is-invalid');

    if (redirected && !hasErrors) {
        storage.remove(pending.key);
    }
}

function clearAllForUser() {
    storage.keys().filter((key) => key.startsWith(`${PREFIX}${userId}:`)).forEach((key) => storage.remove(key));
}

function pruneExpired() {
    storage.keys().forEach((key) => {
        const draft = storage.get(key);
        if (!draft || Date.now() - draft.savedAt > MAX_AGE_MS) {
            storage.remove(key);
        }
    });
}

function init() {
    const pending = takePendingSubmit();

    if (!userId) {
        return; // signed-out pages (login etc.)
    }

    pruneExpired();

    if (pending) {
        settlePendingSubmit(pending);
    }

    document.querySelectorAll('form').forEach((form) => {
        if (isPost(form) && isLogout(form)) {
            form.addEventListener('submit', clearAllForUser);
        }
    });

    const forms = [...document.querySelectorAll('form')].filter(isDraftable);
    const keys = formKeys(forms);

    forms.forEach((form, index) => {
        const key = keys[index];
        const draft = storage.get(key);
        // What the server rendered; a draft equal to it is pointless.
        let original = snapshot(form);
        let touched = false;
        let timer = null;

        if (draft?.fields && restore(form, draft) > 0) {
            touched = true;
            showNotice(form, draft, () => {
                storage.remove(key);
                location.reload();
            });
        } else if (draft?.fields) {
            // The page already shows the draft — typically Laravel refilling
            // the form after a validation error. That refill is one-time
            // (flashed), so keep the draft: a refresh now would otherwise
            // lose everything. No notice needed; nothing changed on screen.
            touched = true;
            original = null;
        }

        const save = () => {
            clearTimeout(timer);
            timer = null;

            if (!touched) {
                return;
            }

            const fields = snapshot(form);

            if (original && sameValue(fields, original)) {
                storage.remove(key);
            } else {
                storage.set(key, { savedAt: Date.now(), fields });
            }
        };

        const schedule = () => {
            clearTimeout(timer);
            timer = setTimeout(save, 400);
        };

        // Only real typing/clicking counts as "touched" — editors and
        // widgets fire synthetic events while they set themselves up, and
        // those alone mustn't create a draft.
        ['keydown', 'paste', 'drop', 'input', 'change'].forEach((type) => {
            form.addEventListener(type, (event) => {
                if (event.isTrusted) {
                    touched = true;
                }
                if (type === 'input' || type === 'change' || type === 'paste' || type === 'drop') {
                    schedule();
                }
            });
        });

        // select2 picks happen in a dropdown outside the form and arrive as a
        // jQuery-triggered "change" that native listeners never see.
        window.jQuery?.(form).on('change', 'select', (event) => {
            if (!event.originalEvent) {
                touched = true;
                schedule();
            }
        });

        form.addEventListener('reset', () => {
            storage.remove(key);
            touched = false;
        });

        form.addEventListener('submit', (event) => {
            save();

            // Deferred so confirm dialogs / AJAX handlers have had their say.
            setTimeout(() => {
                if (event.defaultPrevented) {
                    return;
                }

                try {
                    sessionStorage.setItem(PENDING_KEY, JSON.stringify({ key, path: location.pathname, at: Date.now() }));
                } catch (e) { /* ignore */ }
            }, 0);
        });

        // Last chance before the tab goes away (refresh, close, crash-prone mobile).
        window.addEventListener('pagehide', () => timer && save());
        document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && timer && save());
    });
}

document.addEventListener('DOMContentLoaded', init);
