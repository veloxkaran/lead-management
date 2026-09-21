import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const TOOLBAR_OPTIONS = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const EMPTY_BODY = '<p><br></p>';

function initOne(root) {
    if (root.dataset.richTextReady === 'true') {
        return; // a modal can be shown more than once
    }

    const input = root.querySelector('[data-rich-text-input]');
    const body = root.querySelector('[data-rich-text-body]');
    const toolbar = root.querySelector('[data-rich-text-toolbar]');

    if (!input || !body || !toolbar) {
        return;
    }

    root.dataset.richTextReady = 'true';

    const quill = new Quill(body, {
        theme: 'snow',
        modules: { toolbar },
        placeholder: root.dataset.richTextPlaceholder || '',
    });

    if (input.value) {
        quill.clipboard.dangerouslyPasteHTML(input.value);
    }

    const sync = () => {
        const html = quill.root.innerHTML;
        input.value = html === EMPTY_BODY ? '' : html;
    };

    quill.on('text-change', sync);

    const form = root.closest('form');
    if (form) {
        // Belt-and-suspenders: guarantees the hidden field holds the latest
        // content even if a submit fires between keystrokes and the next
        // text-change sync.
        form.addEventListener('submit', sync);
    }
}

/**
 * Initializes every [data-rich-text-editor] under `root`, except ones still
 * inside a not-yet-shown Bootstrap modal — Quill measures its toolbar at
 * init time and collapses to zero width if built while display:none,
 * mirroring how select2 init is deferred to shown.bs.modal in app.js.
 */
export function initRichTextEditors(root = document) {
    root.querySelectorAll('[data-rich-text-editor]').forEach((el) => {
        const modal = el.closest('.modal');

        if (modal && !modal.classList.contains('show')) {
            return;
        }

        initOne(el);
    });
}
