// The real TipTap/ProseMirror payload -- dynamically import()ed by editor.js, never loaded on
// a page with no editor mount point. Built from free/MIT TipTap primitives only: core engine +
// StarterKit + a short, individually license-checked list of official extensions (Underline,
// Link, TaskList/TaskItem) + the free @tiptap/suggestion utility for the slash menu -- not the
// paid tiptap.dev "Notion-like" template (Tiptap Start plan, React-only, paid UI Components +
// Cloud collaboration/AI -- see the editor integration's own decision entry for why that
// specific template couldn't be used).
import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Placeholder from '@tiptap/extension-placeholder';
import Suggestion from '@tiptap/suggestion';

const EMPTY_DOC = { type: 'doc', content: [{ type: 'paragraph' }] };

/**
 * Every EditorNode value's Tiptap-side mark toolbar button, in display order -- only the ones
 * that toggle inline marks; block-level types are handled by the slash menu instead.
 */
const TOOLBAR_MARKS = [
    { node: 'bold', label: 'B', title: 'Bold', run: (chain) => chain.toggleBold() },
    { node: 'italic', label: 'I', title: 'Italic', run: (chain) => chain.toggleItalic() },
    { node: 'underline', label: 'U', title: 'Underline', run: (chain) => chain.toggleUnderline() },
    { node: 'strike', label: 'S', title: 'Strikethrough', run: (chain) => chain.toggleStrike() },
    { node: 'code', label: '</>', title: 'Inline code', run: (chain) => chain.toggleCode() },
];

/**
 * Every EditorNode value's slash-menu block command, in menu order -- inserted/converted via
 * setNode()/toggleList() the same way any TipTap keyboard shortcut would.
 */
function slashItems(enabledNodes) {
    const has = (name) => enabledNodes.includes(name);
    const items = [];

    if (has('heading')) {
        items.push(
            { title: 'Heading 1', run: (chain) => chain.setNode('heading', { level: 1 }) },
            { title: 'Heading 2', run: (chain) => chain.setNode('heading', { level: 2 }) },
            { title: 'Heading 3', run: (chain) => chain.setNode('heading', { level: 3 }) },
        );
    }

    if (has('bulletList')) {
        items.push({ title: 'Bullet list', run: (chain) => chain.toggleBulletList() });
    }

    if (has('orderedList')) {
        items.push({ title: 'Numbered list', run: (chain) => chain.toggleOrderedList() });
    }

    if (has('taskList')) {
        items.push({ title: 'Task list', run: (chain) => chain.toggleTaskList() });
    }

    if (has('blockquote')) {
        items.push({ title: 'Quote', run: (chain) => chain.toggleBlockquote() });
    }

    if (has('codeBlock')) {
        items.push({ title: 'Code block', run: (chain) => chain.toggleCodeBlock() });
    }

    if (has('horizontalRule')) {
        items.push({ title: 'Divider', run: (chain) => chain.setHorizontalRule() });
    }

    return items;
}

/**
 * A minimal slash-command menu -- no floating-ui/positioning dependency, just an absolutely
 * positioned dropdown placed at the suggestion range's own screen coordinates. Deliberately
 * plain vanilla DOM, matching the rest of this bundle's "no framework" posture.
 */
function slashCommandExtension(enabledNodes) {
    const items = slashItems(enabledNodes);

    if (items.length === 0) {
        return null;
    }

    return Extension.create({
        name: 'kopSlashCommand',

        addProseMirrorPlugins() {
            return [
                Suggestion({
                    editor: this.editor,
                    char: '/',
                    startOfLine: false,
                    items: ({ query }) => items
                        .filter((item) => item.title.toLowerCase().includes(query.toLowerCase()))
                        .slice(0, 8),
                    command: ({ editor, range, props }) => {
                        props.run(editor.chain().focus().deleteRange(range)).run();
                    },
                    render: slashMenuRenderer,
                }),
            ];
        },
    });
}

function slashMenuRenderer() {
    let menu;
    let selected = 0;
    let currentItems = [];
    let currentProps;

    function draw() {
        menu.innerHTML = '';

        currentItems.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'kop-editor__slash-item' + (index === selected ? ' is-selected' : '');
            button.textContent = item.title;
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                currentProps.command(item);
            });
            menu.appendChild(button);
        });
    }

    function position(props) {
        const rect = props.clientRect?.();

        if (!rect) {
            return;
        }

        menu.style.left = `${rect.left + window.scrollX}px`;
        menu.style.top = `${rect.bottom + window.scrollY + 4}px`;
    }

    return {
        onStart(props) {
            currentItems = props.items;
            currentProps = props;
            selected = 0;

            menu = document.createElement('div');
            menu.className = 'kop-editor__slash-menu';
            document.body.appendChild(menu);

            draw();
            position(props);
        },
        onUpdate(props) {
            currentItems = props.items;
            currentProps = props;
            selected = 0;

            draw();
            position(props);
        },
        onKeyDown(props) {
            if (currentItems.length === 0) {
                return false;
            }

            if (props.event.key === 'ArrowDown') {
                selected = (selected + 1) % currentItems.length;
                draw();

                return true;
            }

            if (props.event.key === 'ArrowUp') {
                selected = (selected - 1 + currentItems.length) % currentItems.length;
                draw();

                return true;
            }

            if (props.event.key === 'Enter') {
                currentProps.command(currentItems[selected]);

                return true;
            }

            if (props.event.key === 'Escape') {
                menu.remove();

                return true;
            }

            return false;
        },
        onExit() {
            menu?.remove();
        },
    };
}

function buildExtensions(enabledNodes, { placeholder } = {}) {
    const has = (name) => enabledNodes.includes(name);

    const extensions = [
        Placeholder.configure({ placeholder: placeholder || '' }),
        StarterKit.configure({
            heading: has('heading') ? {} : false,
            bold: has('bold') ? {} : false,
            italic: has('italic') ? {} : false,
            strike: has('strike') ? {} : false,
            code: has('code') ? {} : false,
            codeBlock: has('codeBlock') ? {} : false,
            blockquote: has('blockquote') ? {} : false,
            bulletList: has('bulletList') ? {} : false,
            orderedList: has('orderedList') ? {} : false,
            hardBreak: has('hardBreak') ? {} : false,
            horizontalRule: has('horizontalRule') ? {} : false,
        }),
    ];

    if (has('underline')) {
        extensions.push(Underline);
    }

    if (has('link')) {
        extensions.push(Link.configure({ openOnClick: false, autolink: true }));
    }

    if (has('taskList')) {
        extensions.push(TaskList, TaskItem.configure({ nested: false }));
    }

    const slash = slashCommandExtension(enabledNodes);

    if (slash) {
        extensions.push(slash);
    }

    return extensions;
}

function renderToolbar(toolbarEl, editor, enabledNodes) {
    const buttons = TOOLBAR_MARKS.filter((mark) => enabledNodes.includes(mark.node));

    if (buttons.length === 0) {
        toolbarEl.hidden = true;

        return;
    }

    buttons.forEach((mark) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.title = mark.title;
        button.className = 'kop-editor__toolbar-btn';
        button.textContent = mark.label;
        button.addEventListener('mousedown', (event) => {
            // mousedown (not click) so the editor's own selection never loses focus first.
            event.preventDefault();
            mark.run(editor.chain().focus()).run();
        });

        editor.on('selectionUpdate', () => syncActive());
        editor.on('transaction', () => syncActive());

        function syncActive() {
            button.classList.toggle('is-active', editor.isActive(mark.node));
        }

        toolbarEl.appendChild(button);
    });
}

function parseInitialDoc(raw) {
    if (!raw) {
        return EMPTY_DOC;
    }

    try {
        const parsed = JSON.parse(raw);

        return parsed && typeof parsed === 'object' ? parsed : EMPTY_DOC;
    } catch {
        return EMPTY_DOC;
    }
}

/**
 * Mounts one editor onto `node` (a `[data-tiptap-editor]` element, see
 * `k-core/views/editor/notion.blade.php`) and returns its imperative API. htmx's own normal
 * FormData submission already picks up the hidden `[data-editor-input]`'s value, so no
 * `hx-vals`/custom serialization is needed on the surrounding `<form>`.
 */
export function mount(node) {
    const enabledNodes = JSON.parse(node.dataset.editorNodes || '[]');
    const input = node.querySelector('[data-editor-input]');
    const contentEl = node.querySelector('[data-editor-content]');
    const toolbarEl = node.querySelector('[data-editor-toolbar]');

    const editor = new Editor({
        element: contentEl,
        extensions: buildExtensions(enabledNodes, { placeholder: node.dataset.editorPlaceholder }),
        content: parseInitialDoc(input?.value),
        onUpdate: () => sync(),
    });

    function sync() {
        if (input) {
            input.value = JSON.stringify(editor.getJSON());
        }
    }

    sync();

    if (toolbarEl) {
        renderToolbar(toolbarEl, editor, enabledNodes);
    }

    const api = {
        insertText(text) {
            editor.chain().focus().insertContent(text).run();
            sync();
        },
        insertQuote({ author, text }) {
            if (enabledNodes.includes('blockquote')) {
                editor.chain().focus()
                    .insertContent({
                        type: 'blockquote',
                        content: [{
                            type: 'paragraph',
                            content: text ? [{ type: 'text', text: author ? `${author}: ${text}` : text }] : [],
                        }],
                    })
                    .insertContent({ type: 'paragraph' })
                    .run();
            } else {
                editor.chain().focus().insertContent(`${author ? author + ': ' : ''}${text}\n`).run();
            }

            sync();
        },
        clear() {
            editor.commands.clearContent(true);
            sync();
        },
        getEditor() {
            return editor;
        },
        destroy() {
            editor.destroy();
        },
    };

    node.kopEditor = api;

    return api;
}
