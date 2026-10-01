import Quill from 'quill';
import { addControls } from 'quill/modules/toolbar';
import 'quill/dist/quill.snow.css';

const TOOLBAR_OPTIONS = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const IMAGE_TOOLBAR_OPTIONS = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link', 'image'],
    ['clean'],
];

const EMPTY_BODY = '<p><br></p>';

const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

function formatBytes(bytes) {
    return bytes < 1024 ? `${bytes} bytes` : `${(bytes / 1024).toFixed(1)} KB`;
}

/**
 * Approximate saved size of the editor's HTML: base64 images count as
 * their decoded bytes (the server stores them as files), already-stored
 * images by their known size — the same total CampaignSignature::bytes()
 * checks on save.
 */
function contentBytes(html, storedSizes) {
    let bytes = 0;
    const seen = new Set();

    const withoutData = html.replace(/data:image\/[a-z+.-]+;base64,([A-Za-z0-9+/=]+)/gi, (match, data) => {
        bytes += Math.floor((data.length * 3) / 4) - (data.endsWith('==') ? 2 : data.endsWith('=') ? 1 : 0);
        // The server swaps the data URI for a URL about this long.
        return 'x'.repeat(90);
    });

    for (const [file, size] of Object.entries(storedSizes)) {
        if (withoutData.includes(file) && !seen.has(file)) {
            seen.add(file);
            bytes += size;
        }
    }

    return bytes + new TextEncoder().encode(withoutData).length;
}

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

    const withImages = root.hasAttribute('data-rich-text-images');

    // Image-enabled editors get real toolbar buttons, including the image
    // button (whose default handler inserts the picked file as base64).
    if (withImages && !toolbar.children.length) {
        addControls(toolbar, IMAGE_TOOLBAR_OPTIONS);
    }

    const quill = new Quill(body, {
        theme: 'snow',
        // table: keeps pasted tables (e.g. from ChatGPT/Docs/Excel) as real
        // rows/cells — without it Quill flattens them into one run-on line.
        modules: { toolbar, table: true, ...(withImages ? { uploader: { mimetypes: IMAGE_TYPES } } : {}) },
        placeholder: root.dataset.richTextPlaceholder || '',
    });

    if (input.value) {
        quill.clipboard.dangerouslyPasteHTML(input.value);
    }

    const sizeLabel = root.querySelector('[data-rich-text-size]');
    const maxBytes = parseInt(root.dataset.richTextMaxBytes || '0', 10);
    let storedSizes = {};
    try {
        storedSizes = JSON.parse(root.dataset.richTextImageSizes || '{}');
    } catch (e) {
        storedSizes = {};
    }

    const showSize = () => {
        if (!sizeLabel || !maxBytes) return;
        const bytes = contentBytes(input.value, storedSizes);
        const over = bytes > maxBytes;
        sizeLabel.textContent = `Size: ${formatBytes(bytes)} of ${formatBytes(maxBytes)}${over ? ' — too large, use a smaller image' : ''}`;
        sizeLabel.classList.toggle('text-danger', over);
        sizeLabel.classList.toggle('fw-semibold', over);
    };

    const sync = () => {
        const html = quill.root.innerHTML;
        input.value = html === EMPTY_BODY ? '' : html;
        showSize();
    };

    quill.on('text-change', sync);
    showSize();

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
