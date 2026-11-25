import assert from 'node:assert/strict';
import test from 'node:test';
import {
    assignFilesToInput,
    clearFileInput,
    initializeFileDropzones,
    validateFiles,
} from '../../resources/js/file-dropzone.js';

class FakeDataTransfer {
    constructor() {
        this.files = [];
        this.items = {
            add: (file) => this.files.push(file),
        };
    }
}

function fakeInput() {
    let files = [];
    let value = 'selected';

    return {
        get files() {
            return files;
        },
        set files(nextFiles) {
            files = nextFiles;
        },
        get value() {
            return value;
        },
        set value(nextValue) {
            value = nextValue;

            if (nextValue === '') {
                files = [];
            }
        },
        events: [],
        dispatchEvent(event) {
            this.events.push(event.type);

            return true;
        },
    };
}

function fakeFile(name, type, size) {
    return { name, type, size };
}

class FakeElement {
    constructor(ownerDocument = null) {
        this.ownerDocument = ownerDocument;
        this.dataset = {};
        this.children = [];
        this.attributes = new Map();
        this.className = '';
        this.hidden = false;
        this.textContent = '';
    }

    append(...children) {
        this.children.push(...children);
    }

    closest(selector) {
        if (selector === '[data-file-dropzone]') {
            return this.dropzone ?? (this.isDropzone ? this : null);
        }

        if (selector === '[data-file-dropzone-remove]') {
            return this.isRemoveButton ? this : null;
        }

        return null;
    }

    contains(target) {
        return this.children.includes(target);
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    querySelectorAll(selector) {
        return this.children.flatMap((child) => {
            const matches = selector === '[data-file-upload-ready-status]'
                && child.dataset.fileUploadReadyStatus !== undefined
                ? [child]
                : [];

            return [...matches, ...child.querySelectorAll(selector)];
        });
    }
}

class FakeInput extends FakeElement {
    constructor(ownerDocument, root) {
        super(ownerDocument);
        this.root = root;
        this.accept = '.pdf,application/pdf';
        this.multiple = false;
        this.files = [];
        this.events = [];
        this._value = '';
    }

    get value() {
        return this._value;
    }

    set value(value) {
        this._value = value;

        if (value === '') {
            this.files = [];
        }
    }

    closest(selector) {
        if (selector === '[data-file-dropzone]') {
            return this.dropzone ?? null;
        }

        return null;
    }

    matches(selector) {
        return selector === '[data-file-dropzone-input]';
    }

    dispatchEvent(event) {
        this.events.push(event.type);
        this.root.listeners.change({ target: this, type: event.type });

        return true;
    }

    focus() {}
}

class FakeList extends FakeElement {
    replaceChildren(...children) {
        this.children = children;
    }
}

class FakeButton extends FakeElement {}

class FakeForm extends FakeElement {
    constructor(ownerDocument) {
        super(ownerDocument);
        this.fileInputs = [];
        this.submitControls = [];
    }

    querySelectorAll(selector) {
        if (selector === '[data-file-dropzone-input]') {
            return this.fileInputs;
        }

        if (selector === 'button[type="submit"], button:not([type]), input[type="submit"]') {
            return this.submitControls;
        }

        return [];
    }
}

class FakeStorage {
    constructor() {
        this.items = new Map();
    }

    getItem(key) {
        return this.items.get(key) ?? null;
    }

    setItem(key, value) {
        this.items.set(key, value);
    }

    removeItem(key) {
        this.items.delete(key);
    }
}

class FakeRoot {
    constructor(ownerDocument = null) {
        this.ownerDocument = ownerDocument;
        this.listeners = {};
    }

    addEventListener(type, callback) {
        this.listeners[type] = callback;
    }

    querySelector() {
        return null;
    }
}

function withFakeDom(callback) {
    const descriptors = new Map();
    const globals = {
        Element: FakeElement,
        HTMLElement: FakeElement,
        HTMLInputElement: FakeInput,
        HTMLUListElement: FakeList,
        HTMLButtonElement: FakeButton,
        HTMLFormElement: FakeForm,
        Node: FakeElement,
        DataTransfer: FakeDataTransfer,
        sessionStorage: new FakeStorage(),
    };

    for (const [name, value] of Object.entries(globals)) {
        descriptors.set(name, Object.getOwnPropertyDescriptor(globalThis, name));
        Object.defineProperty(globalThis, name, { configurable: true, writable: true, value });
    }

    try {
        callback();
    } finally {
        for (const [name, descriptor] of descriptors) {
            if (descriptor) {
                Object.defineProperty(globalThis, name, descriptor);
            } else {
                delete globalThis[name];
            }
        }
    }
}

test('validates accepted file types and the configured maximum size', () => {
    const acceptedFile = fakeFile('proof.pdf', 'application/pdf', 1024);
    const unsupportedFile = fakeFile('proof.exe', 'application/x-msdownload', 1024);
    const oversizedFile = fakeFile('large.pdf', 'application/pdf', 5 * 1024 * 1024 + 1);

    assert.deepEqual(
        validateFiles([acceptedFile], {
            accept: '.pdf,application/pdf',
            maxBytes: 5 * 1024 * 1024,
        }),
        { valid: true, message: '' },
    );
    assert.equal(
        validateFiles([unsupportedFile], { accept: '.pdf,application/pdf' }).message,
        'Type de fichier non autorisé. Formats acceptés : .pdf.',
    );
    assert.equal(
        validateFiles([oversizedFile], {
            accept: '.pdf,application/pdf',
            maxBytes: 5 * 1024 * 1024,
        }).message,
        'La taille de chaque fichier ne peut pas dépasser 5 Mo.',
    );
});

test('supports multiple files only when the input allows them', () => {
    const files = [
        fakeFile('first.pdf', 'application/pdf', 100),
        fakeFile('second.pdf', 'application/pdf', 200),
    ];

    assert.equal(validateFiles(files, {
        accept: '.pdf,application/pdf',
        maxBytes: 1024,
        multiple: true,
    }).valid, true);
    assert.equal(validateFiles(files, {
        accept: '.pdf,application/pdf',
        maxBytes: 1024,
    }).message, 'Cette zone accepte un seul fichier à la fois.');

    const input = fakeInput();
    assignFilesToInput(input, files, FakeDataTransfer);
    assert.deepEqual(input.files, files);
});

test('assigns dropped files to the upload input and replaces its previous selection', () => {
    const input = fakeInput();
    const firstFile = fakeFile('first.pdf', 'application/pdf', 100);
    const replacementFile = fakeFile('replacement.pdf', 'application/pdf', 200);

    assignFilesToInput(input, [firstFile], FakeDataTransfer);
    assert.deepEqual(input.files, [firstFile]);

    assignFilesToInput(input, [replacementFile], FakeDataTransfer);
    assert.deepEqual(input.files, [replacementFile]);
    assert.deepEqual(input.events, ['change', 'change']);
});

test('removes selected files from the upload input', () => {
    const input = fakeInput();
    assignFilesToInput(input, [fakeFile('selected.pdf', 'application/pdf', 100)], FakeDataTransfer);

    clearFileInput(input);

    assert.equal(input.value, '');
    assert.deepEqual(input.files, []);
    assert.deepEqual(input.events, ['change', 'change']);
});

test('drop events populate the real upload input and the remove action clears it', () => {
    withFakeDom(() => {
        const root = new FakeRoot();
        const ownerDocument = {
            createElement: () => new FakeElement(ownerDocument),
        };
        const zone = new FakeElement(ownerDocument);
        zone.isDropzone = true;
        zone.dataset.maxBytes = String(1024 * 1024);
        const list = new FakeList(ownerDocument);
        const selection = new FakeElement(ownerDocument);
        const error = new FakeElement(ownerDocument);
        error.hidden = true;
        zone.querySelector = (selector) => ({
            '[data-file-dropzone-files]': list,
            '[data-file-dropzone-selection]': selection,
            '[data-file-dropzone-error]': error,
            '[data-file-upload-progress-state]': progressState,
            '[data-file-upload-progress-message]': progressMessage,
            '[data-file-dropzone-input]': input,
        })[selector] ?? null;
        zone.querySelectorAll = (selector) => list.querySelectorAll(selector);
        const progressState = new FakeElement(ownerDocument);
        progressState.hidden = true;
        const progressMessage = new FakeElement(ownerDocument);
        const input = new FakeInput(ownerDocument, root);
        input.dropzone = zone;
        input.accept = '.pdf,.png,application/pdf,image/png';

        initializeFileDropzones(root);

        const firstFile = fakeFile('first.pdf', 'application/pdf', 100);
        const dropEvent = {
            target: input,
            dataTransfer: { files: [firstFile], dropEffect: 'none' },
            preventDefault() {
                this.defaultPrevented = true;
            },
        };
        root.listeners.dragenter({ target: input, preventDefault() {} });
        assert.equal(zone.dataset.dragActive, 'true');
        root.listeners.dragleave({ target: input, relatedTarget: null });
        assert.equal(zone.dataset.dragActive, undefined);
        root.listeners.dragenter({ target: input, preventDefault() {} });

        root.listeners.drop(dropEvent);
        assert.equal(dropEvent.defaultPrevented, true);
        assert.deepEqual(input.files, [firstFile]);
        assert.equal(zone.dataset.dragActive, undefined);
        assert.equal(selection.hidden, false);
        assert.equal(list.children[0].children[0].textContent, 'PDF');
        assert.equal(list.children[0].children[1].children[0].textContent, 'first.pdf');
        assert.equal(list.children[0].children[1].children[1].textContent, '100 octets');
        assert.equal(list.children[0].children[1].children[2].textContent, 'Prêt à envoyer');

        const replacementFile = fakeFile('replacement.png', 'image/png', 200);
        root.listeners.drop({
            target: input,
            dataTransfer: { files: [replacementFile], dropEffect: 'none' },
            preventDefault() {},
        });
        assert.deepEqual(input.files, [replacementFile]);
        assert.equal(list.children[0].children[0].textContent, 'IMG');
        assert.deepEqual(input.events, ['change', 'change']);

        root.listeners.drop({
            target: input,
            dataTransfer: { files: [fakeFile('bad.exe', 'application/x-msdownload', 100)], dropEffect: 'none' },
            preventDefault() {},
        });
        assert.deepEqual(input.files, [replacementFile]);
        assert.equal(error.hidden, false);
        assert.match(error.textContent, /Type de fichier non autorisé/);

        const outsideTarget = new FakeElement(ownerDocument);
        const outsideDragEvent = {
            target: outsideTarget,
            dataTransfer: {
                files: [fakeFile('outside.pdf', 'application/pdf', 100)],
                types: ['Files'],
                dropEffect: 'none',
            },
            preventDefault() {
                this.defaultPrevented = true;
            },
        };
        root.listeners.dragenter(outsideDragEvent);
        assert.equal(outsideDragEvent.defaultPrevented, true);

        const outsideDragOverEvent = {
            ...outsideDragEvent,
            defaultPrevented: false,
            preventDefault() {
                this.defaultPrevented = true;
            },
        };
        root.listeners.dragover(outsideDragOverEvent);
        assert.equal(outsideDragOverEvent.defaultPrevented, true);

        const outsideDropEvent = {
            ...outsideDragEvent,
            defaultPrevented: false,
            preventDefault() {
                this.defaultPrevented = true;
            },
        };
        root.listeners.drop(outsideDropEvent);
        assert.equal(outsideDropEvent.defaultPrevented, true);
        assert.deepEqual(input.files, [replacementFile]);
        assert.deepEqual(input.events, ['change', 'change']);

        const otherZone = new FakeElement(ownerDocument);
        otherZone.isDropzone = true;
        otherZone.dataset.maxBytes = String(1024 * 1024);
        const otherList = new FakeList(ownerDocument);
        const otherSelection = new FakeElement(ownerDocument);
        const otherError = new FakeElement(ownerDocument);
        otherZone.querySelector = (selector) => ({
            '[data-file-dropzone-files]': otherList,
            '[data-file-dropzone-selection]': otherSelection,
            '[data-file-dropzone-error]': otherError,
            '[data-file-dropzone-input]': otherInput,
        })[selector] ?? null;
        const otherInput = new FakeInput(ownerDocument, root);
        otherInput.dropzone = otherZone;
        const otherFile = fakeFile('other.pdf', 'application/pdf', 300);
        root.listeners.drop({
            target: otherInput,
            dataTransfer: { files: [otherFile], types: ['Files'], dropEffect: 'none' },
            preventDefault() {},
        });
        assert.deepEqual(otherInput.files, [otherFile]);
        assert.deepEqual(input.files, [replacementFile]);

        const removeButton = new FakeButton(ownerDocument);
        removeButton.isRemoveButton = true;
        removeButton.dropzone = zone;
        root.listeners.click({ target: removeButton });
        assert.deepEqual(input.files, []);
        assert.equal(selection.hidden, true);
        assert.deepEqual(input.events, ['change', 'change', 'change']);
    });
});

test('shows indeterminate progress and blocks duplicate submissions for file forms', () => {
    withFakeDom(() => {
        const ownerDocument = {
            createElement: () => new FakeElement(ownerDocument),
        };
        const root = new FakeRoot(ownerDocument);
        const zone = new FakeElement(ownerDocument);
        zone.isDropzone = true;
        zone.dataset.maxBytes = String(1024 * 1024);
        const list = new FakeList(ownerDocument);
        const progressState = new FakeElement(ownerDocument);
        progressState.hidden = true;
        const progressMessage = new FakeElement(ownerDocument);
        zone.querySelector = (selector) => ({
            '[data-file-dropzone-files]': list,
            '[data-file-dropzone-selection]': new FakeElement(ownerDocument),
            '[data-file-dropzone-error]': new FakeElement(ownerDocument),
            '[data-file-upload-progress-state]': progressState,
            '[data-file-upload-progress-message]': progressMessage,
            '[data-file-dropzone-input]': input,
        })[selector] ?? null;
        zone.querySelectorAll = (selector) => list.querySelectorAll(selector);

        const input = new FakeInput(ownerDocument, root);
        input.accept = '.pdf,application/pdf';
        input.dropzone = zone;

        const submitButton = new FakeButton(ownerDocument);
        const form = new FakeForm(ownerDocument);
        form.fileInputs = [input];
        form.submitControls = [submitButton];

        initializeFileDropzones(root);
        root.listeners.drop({
            target: input,
            dataTransfer: { files: [fakeFile('preuve.pdf', 'application/pdf', 100)], types: ['Files'] },
            preventDefault() {},
        });

        const firstSubmit = {
            target: form,
            defaultPrevented: false,
            preventDefault() {
                this.defaultPrevented = true;
            },
        };
        root.listeners.submit(firstSubmit);

        assert.equal(firstSubmit.defaultPrevented, false);
        assert.equal(form.dataset.fileUploadSubmitting, 'true');
        assert.equal(form.attributes.get('aria-busy'), 'true');
        assert.equal(progressState.hidden, false);
        assert.equal(progressMessage.textContent, 'Envoi du fichier en cours...');
        assert.equal(list.children[0].children[1].children[2].textContent, 'Envoi du fichier en cours...');
        assert.equal(submitButton.attributes.get('aria-disabled'), 'true');
        assert.match(submitButton.className, /cursor-wait opacity-60/);
        assert.equal(
            globalThis.sessionStorage.getItem('pending-file-upload-feedback'),
            JSON.stringify(['preuve.pdf']),
        );

        const duplicateSubmit = {
            target: form,
            defaultPrevented: false,
            preventDefault() {
                this.defaultPrevented = true;
            },
        };
        root.listeners.submit(duplicateSubmit);
        assert.equal(duplicateSubmit.defaultPrevented, true);
    });
});

test('shows upload success only when the server flash status is present', () => {
    withFakeDom(() => {
        const ownerDocument = {
            createElement: () => new FakeElement(ownerDocument),
        };
        const root = new FakeRoot(ownerDocument);
        const status = new FakeElement(ownerDocument);
        const statusMessage = new FakeElement(ownerDocument);
        statusMessage.textContent = 'Le document a été téléversé.';
        status.querySelector = (selector) => selector === '[data-server-flash-message]' ? statusMessage : null;
        status.children.push(statusMessage);
        root.querySelector = (selector) => selector === '[data-server-flash-status]' ? status : null;
        globalThis.sessionStorage.setItem(
            'pending-file-upload-feedback',
            JSON.stringify(['preuve.pdf']),
        );

        initializeFileDropzones(root);

        assert.equal(status.children[1].textContent, 'Envoyé');
        assert.equal(status.children[2].textContent, 'Fichier envoyé avec succès');
        assert.equal(status.children[3].textContent, 'preuve.pdf');
        assert.equal(globalThis.sessionStorage.getItem('pending-file-upload-feedback'), null);
    });

    withFakeDom(() => {
        const ownerDocument = {
            createElement: () => new FakeElement(ownerDocument),
        };
        const root = new FakeRoot(ownerDocument);
        const status = new FakeElement(ownerDocument);
        const statusMessage = new FakeElement(ownerDocument);
        status.querySelector = (selector) => selector === '[data-server-flash-message]' ? statusMessage : null;
        root.querySelector = (selector) => {
            if (selector === '[data-server-flash-status]') {
                return status;
            }

            return selector === '[data-server-validation-errors]' ? new FakeElement(ownerDocument) : null;
        };
        globalThis.sessionStorage.setItem(
            'pending-file-upload-feedback',
            JSON.stringify(['refusé.pdf']),
        );

        initializeFileDropzones(root);

        assert.deepEqual(status.children, []);
        assert.equal(globalThis.sessionStorage.getItem('pending-file-upload-feedback'), null);
    });

    withFakeDom(() => {
        const ownerDocument = {
            createElement: () => new FakeElement(ownerDocument),
        };
        const root = new FakeRoot(ownerDocument);
        globalThis.sessionStorage.setItem(
            'pending-file-upload-feedback',
            JSON.stringify(['sans-confirmation.pdf']),
        );

        initializeFileDropzones(root);

        assert.equal(globalThis.sessionStorage.getItem('pending-file-upload-feedback'), null);
    });
});

