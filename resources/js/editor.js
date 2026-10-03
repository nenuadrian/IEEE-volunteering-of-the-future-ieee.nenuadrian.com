import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import EditorjsList from '@editorjs/list';
import ImageTool from '@editorjs/image';
import Quote from '@editorjs/quote';
import Table from '@editorjs/table';
import Delimiter from '@editorjs/delimiter';
import Marker from '@editorjs/marker';
import InlineCode from '@editorjs/inline-code';
import CodeTool from '@editorjs/code';
import RawTool from '@editorjs/raw';
import Embed from '@editorjs/embed';

/**
 * Boot an EditorJS instance on a `[data-editorjs]` holder.
 *
 * Initial block data is read from the form's hidden `editor_data` input (the
 * single source of truth, seeded server-side). On submit we await editor.save(),
 * write the JSON back into that input, then let the native submit proceed.
 */
function bootEditor(holder) {
    const form = holder.closest('form');
    const hidden = form?.querySelector('input[name="editor_data"]');
    if (!form || !hidden) return;

    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content ||
        form.querySelector('input[name="_token"]')?.value ||
        '';

    let initial = {};
    try {
        const raw = (hidden.value || '').trim();
        if (raw) initial = JSON.parse(raw);
    } catch (e) {
        console.error('EditorJS: could not parse initial data', e);
    }

    let ready = false;
    let submitting = false;

    const editor = new EditorJS({
        holder,
        data: initial,
        minHeight: 200,
        placeholder: holder.dataset.placeholder || 'Write something…',
        tools: {
            header: { class: Header, inlineToolbar: true, config: { levels: [2, 3, 4], defaultLevel: 2 } },
            list: { class: EditorjsList, inlineToolbar: true, config: { defaultStyle: 'unordered' } },
            quote: { class: Quote, inlineToolbar: true },
            table: { class: Table, inlineToolbar: true, config: { withHeadings: true } },
            code: CodeTool,
            delimiter: Delimiter,
            raw: RawTool,
            marker: { class: Marker, shortcut: 'CMD+SHIFT+M' },
            inlineCode: { class: InlineCode },
            // Restricted to the two services our sanitiser whitelists as iframes.
            embed: { class: Embed, config: { services: { youtube: true, vimeo: true } } },
            image: {
                class: ImageTool,
                config: {
                    endpoints: { byFile: holder.dataset.uploadUrl },
                    field: 'image',
                    types: 'image/png,image/jpeg,image/gif,image/webp',
                    additionalRequestHeaders: csrf ? { 'X-CSRF-TOKEN': csrf } : {},
                },
            },
        },
    });

    editor.isReady
        .then(() => { ready = true; })
        .catch((e) => console.error('EditorJS failed to initialise', e));

    form.addEventListener('submit', (e) => {
        // Second pass (after we re-trigger), or editor never booted → let it submit natively.
        if (submitting || !ready) return;
        e.preventDefault();
        const submitter = e.submitter;

        editor.save()
            .then((data) => { hidden.value = JSON.stringify(data); })
            .catch((err) => console.error('EditorJS save failed', err))
            .finally(() => {
                submitting = true;
                if (submitter) submitter.click();
                else form.requestSubmit();
            });
    });
}

export function initEditors(holders) {
    holders.forEach(bootEditor);
}
