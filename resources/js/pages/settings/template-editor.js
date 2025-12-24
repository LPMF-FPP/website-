const editors = new Map();

function createEditorInstance({ initialHtml = '', initialCss = '', projectData = null } = {}) {
    let html = initialHtml;
    let css = initialCss;
    let project = projectData;

    return {
        __destroyed: false,
        setComponents(value = '') {
            html = value;
        },
        setStyle(value = '') {
            css = value;
        },
        refresh() {},
        loadProjectData(data) {
            project = data;
        },
        getHtml() {
            return html;
        },
        getCss() {
            return css;
        },
        getProjectData() {
            return project ?? { components: html, styles: css };
        },
        destroy() {
            this.__destroyed = true;
        },
    };
}

export async function createTemplateEditor({ key = 'default', container, options = {} } = {}) {
    if (!container) {
        throw new Error('Template editor container tidak tersedia.');
    }

    const editor = createEditorInstance({
        initialHtml: options.initialHtml || '',
        initialCss: options.initialCss || '',
        projectData: options.projectData || null,
    });

    editors.set(key, editor);
    return editor;
}

export function destroyTemplateEditor(key = 'default') {
    const editor = editors.get(key);
    if (editor) {
        editor.destroy();
        editors.delete(key);
    }
}

export function refreshTemplateEditor(key = 'default') {
    const editor = editors.get(key);
    if (editor && !editor.__destroyed) {
        editor.refresh();
    }
}

export function waitForEditorLoad() {
    return Promise.resolve(true);
}

export function isAlive(editor) {
    return !!editor && !editor.__destroyed;
}
