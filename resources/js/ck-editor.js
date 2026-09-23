import { ClassicEditor, Essentials, Paragraph, Bold, Italic, Underline, List, Link } from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';

/**
 * Attaches directly to the source textarea (CKEditor's own pattern) rather
 * than a separate hidden input like rich-text-editor.js's Quill setup —
 * updateSourceElement() on submit writes the editor's HTML straight back
 * into that same textarea, so the form's own `name` attribute just works.
 */
function initOne(textarea) {
    if (textarea.dataset.ckeditorReady === 'true') {
        return; // a modal can be shown more than once
    }

    textarea.dataset.ckeditorReady = 'true';

    const field = textarea.closest('[data-ckeditor-field]');

    ClassicEditor
        .create(textarea, {
            licenseKey: 'GPL',
            plugins: [Essentials, Paragraph, Bold, Italic, Underline, List, Link],
            toolbar: ['bold', 'italic', 'underline', '|', 'bulletedList', 'numberedList', '|', 'link'],
            placeholder: field?.dataset.ckeditorPlaceholder || '',
        })
        .then((editor) => {
            const form = textarea.closest('form');

            if (form) {
                form.addEventListener('submit', () => editor.updateSourceElement());
            }
        });
}

/**
 * Initializes every [data-ckeditor] under `root`, except ones still inside
 * a not-yet-shown Bootstrap modal — mirrors initRichTextEditors' reasoning:
 * CKEditor measures its toolbar at init time and misbehaves if built while
 * display:none.
 */
export function initCkEditors(root = document) {
    root.querySelectorAll('[data-ckeditor]').forEach((el) => {
        const modal = el.closest('.modal');

        if (modal && !modal.classList.contains('show')) {
            return;
        }

        initOne(el);
    });
}
