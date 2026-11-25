function acceptedExtensions(accept) {
    return accept
        .split(',')
        .map((type) => type.trim().toLowerCase())
        .filter((type) => type.startsWith('.'));
}

function fileMatchesAccept(file, accept) {
    const acceptedTypes = accept
        .split(',')
        .map((type) => type.trim().toLowerCase())
        .filter(Boolean);

    if (acceptedTypes.length === 0) {
        return true;
    }

    const fileName = file.name.toLowerCase();
    const extensionStart = fileName.lastIndexOf('.');
    const extension = extensionStart >= 0 ? fileName.slice(extensionStart) : '';
    const mimeType = (file.type ?? '').toLowerCase();

    return acceptedTypes.some((type) => {
        if (type.startsWith('.')) {
            return extension === type;
        }

        if (type.endsWith('/*')) {
            return mimeType.startsWith(type.slice(0, -1));
        }

        return mimeType === type;
    });
}

function formatMegabytes(bytes) {
    const megabytes = bytes / 1024 / 1024;

    return Number.isInteger(megabytes)
        ? String(megabytes)
        : megabytes.toLocaleString('fr-FR', { maximumFractionDigits: 1 });
}

export function validateFiles(files, { accept = '', maxBytes = Number.POSITIVE_INFINITY, multiple = false } = {}) {
    const selectedFiles = Array.from(files);

    if (selectedFiles.length === 0) {
        return { valid: false, message: 'Sélectionnez un fichier.' };
    }

    if (!multiple && selectedFiles.length > 1) {
        return { valid: false, message: 'Cette zone accepte un seul fichier à la fois.' };
    }

    const extensions = acceptedExtensions(accept);

    for (const file of selectedFiles) {
        if (!fileMatchesAccept(file, accept)) {
            const formats = extensions.length > 0 ? extensions.join(', ') : accept;

            return {
                valid: false,
                message: `Type de fichier non autorisé. Formats acceptés : ${formats}.`,
            };
        }

        if (file.size > maxBytes) {
            return {
                valid: false,
                message: `La taille de chaque fichier ne peut pas dépasser ${formatMegabytes(maxBytes)} Mo.`,
            };
        }
    }

    return { valid: true, message: '' };
}

export function assignFilesToInput(input, files, DataTransferConstructor = globalThis.DataTransfer) {
    if (typeof DataTransferConstructor !== 'function') {
        throw new Error('Le navigateur ne permet pas le dépôt de fichier. Cliquez pour parcourir.');
    }

    const transfer = new DataTransferConstructor();

    for (const file of files) {
        transfer.items.add(file);
    }

    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

export function clearFileInput(input) {
    input.value = '';
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function zoneForTarget(target) {
    return target instanceof Element ? target.closest('[data-file-dropzone]') : null;
}

function inputForZone(zone) {
    return zone?.querySelector('[data-file-dropzone-input]') ?? null;
}

function setZoneError(zone, message) {
    const error = zone.querySelector('[data-file-dropzone-error]');

    if (!(error instanceof HTMLElement)) {
        return;
    }

    error.textContent = message;
    error.hidden = message === '';

    if (message === '') {
        delete zone.dataset.invalid;
    } else {
        zone.dataset.invalid = 'true';
    }
}

function renderFileSelection(zone, files) {
    const list = zone.querySelector('[data-file-dropzone-files]');
    const selection = zone.querySelector('[data-file-dropzone-selection]');

    if (!(list instanceof HTMLUListElement) || !(selection instanceof HTMLElement)) {
        return;
    }

    list.replaceChildren();

    for (const file of files) {
        const row = zone.ownerDocument.createElement('li');
        const icon = zone.ownerDocument.createElement('span');
        const details = zone.ownerDocument.createElement('div');
        const fileName = zone.ownerDocument.createElement('span');
        const fileSize = zone.ownerDocument.createElement('span');
        const fileStatus = zone.ownerDocument.createElement('span');

        const iconPresentation = fileIconPresentation(file);

        row.className = 'flex w-full min-w-0 items-start gap-3';
        icon.className = `inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[0.65rem] font-bold ${iconPresentation.className}`;
        icon.textContent = iconPresentation.label;
        icon.setAttribute('aria-hidden', 'true');
        details.className = 'flex min-w-0 flex-1 flex-col gap-0.5';
        fileName.className = 'block min-w-0 flex-1 truncate overflow-hidden whitespace-nowrap text-sm font-semibold text-slate-800';
        fileName.setAttribute('title', file.name);
        fileName.textContent = file.name;
        fileSize.className = 'text-xs text-slate-600';
        fileSize.textContent = formatFileSize(file.size);
        fileStatus.className = 'text-xs font-semibold text-emerald-700';
        fileStatus.dataset.fileUploadReadyStatus = '';
        fileStatus.textContent = 'Prêt à envoyer';
        details.append(fileName, fileSize, fileStatus);
        row.append(icon, details);
        list.append(row);
    }

    selection.hidden = files.length === 0;
}

function fileIconPresentation(file) {
    const extensionStart = file.name.lastIndexOf('.');
    const extension = extensionStart >= 0 ? file.name.slice(extensionStart).toLowerCase() : '';
    const mimeType = (file.type ?? '').toLowerCase();

    if (mimeType === 'application/pdf' || extension === '.pdf') {
        return { label: 'PDF', className: 'bg-rose-50 text-rose-700' };
    }

    if (mimeType.startsWith('image/') || ['.jpg', '.jpeg', '.png', '.webp'].includes(extension)) {
        return { label: 'IMG', className: 'bg-violet-50 text-violet-700' };
    }

    if (
        ['.csv', '.ods', '.xls', '.xlsx'].includes(extension)
        || mimeType.includes('spreadsheet')
        || mimeType.includes('excel')
    ) {
        return { label: 'XLS', className: 'bg-emerald-50 text-emerald-700' };
    }

    if (
        ['.doc', '.docx', '.odt', '.rtf'].includes(extension)
        || mimeType.includes('word')
        || mimeType.includes('document')
    ) {
        return { label: 'DOC', className: 'bg-blue-50 text-blue-700' };
    }

    return {
        label: extension ? extension.slice(1, 5).toUpperCase() : 'FILE',
        className: 'bg-slate-100 text-slate-600',
    };
}

function formatFileSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} octets`;
    }

    const megabytes = bytes / 1024 / 1024;

    if (megabytes >= 1) {
        return `${megabytes.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`;
    }

    return `${(bytes / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} Ko`;
}

function clearZoneDragState(zone) {
    delete zone.dataset.dragActive;
}

function containsFiles(event) {
    const dataTransfer = event.dataTransfer;

    return Boolean(
        dataTransfer
        && (
            dataTransfer.files?.length > 0
            || Array.from(dataTransfer.types ?? []).includes('Files')
        ),
    );
}

const pendingUploadStorageKey = 'pending-file-upload-feedback';

function getSessionStorage() {
    try {
        return globalThis.sessionStorage ?? null;
    } catch {
        return null;
    }
}

function rememberPendingUploadNames(fileInputs) {
    const storage = getSessionStorage();

    if (storage === null) {
        return;
    }

    const names = fileInputs.flatMap((input) => Array.from(input.files ?? []).map((file) => file.name));

    try {
        storage.setItem(pendingUploadStorageKey, JSON.stringify(names));
    } catch {
        console.warn('Le nom du fichier ne pourra pas être conservé après la redirection.');
    }
}

function showServerConfirmedUpload(root) {
    const storage = getSessionStorage();

    if (storage === null) {
        return;
    }

    let serializedNames;

    try {
        serializedNames = storage.getItem(pendingUploadStorageKey);
        storage.removeItem(pendingUploadStorageKey);
    } catch {
        return;
    }

    if (serializedNames === null) {
        return;
    }

    let names;

    try {
        names = JSON.parse(serializedNames);
    } catch {
        return;
    }

    const status = root.querySelector('[data-server-flash-status]');
    const statusMessage = status?.querySelector('[data-server-flash-message]');
    const errors = root.querySelector('[data-server-validation-errors]');

    if (
        !Array.isArray(names)
        || names.length === 0
        || !(status instanceof HTMLElement)
        || !(statusMessage instanceof HTMLElement)
        || errors !== null
    ) {
        return;
    }

    const document = root.ownerDocument ?? root;
    const sentBadge = document.createElement('span');
    const confirmation = document.createElement('span');
    const fileNames = document.createElement('span');

    sentBadge.className = 'inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800';
    sentBadge.textContent = 'Envoyé';
    confirmation.className = 'font-semibold';
    confirmation.textContent = 'Fichier envoyé avec succès';
    fileNames.className = 'min-w-0 flex-1 truncate overflow-hidden whitespace-nowrap';
    fileNames.textContent = names.filter((name) => typeof name === 'string').join(', ');
    fileNames.setAttribute('title', fileNames.textContent);
    status.append(sentBadge, confirmation, fileNames);
}

function setZoneUploadInProgress(zone, fileCount) {
    const progressState = zone.querySelector('[data-file-upload-progress-state]');
    const progressMessage = zone.querySelector('[data-file-upload-progress-message]');

    if (progressState instanceof HTMLElement) {
        progressState.hidden = false;
    }

    if (progressMessage instanceof HTMLElement) {
        progressMessage.textContent = fileCount > 1
            ? 'Envoi des fichiers en cours...'
            : 'Envoi du fichier en cours...';
    }

    zone.querySelectorAll('[data-file-upload-ready-status]').forEach((status) => {
        if (status instanceof HTMLElement) {
            status.className = 'text-xs font-semibold text-blue-700';
            status.textContent = fileCount > 1
                ? 'Envoi en cours...'
                : 'Envoi du fichier en cours...';
        }
    });
}

export function initializeFileDropzones(root = document) {
    showServerConfirmedUpload(root);

    root.addEventListener('dragenter', (event) => {
        const zone = zoneForTarget(event.target);

        if (containsFiles(event)) {
            event.preventDefault();
        }

        if (zone === null) {
            return;
        }

        event.preventDefault();
        zone.dataset.dragActive = 'true';
    });

    root.addEventListener('dragover', (event) => {
        const zone = zoneForTarget(event.target);

        if (containsFiles(event)) {
            event.preventDefault();
        }

        if (zone === null) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'copy';
        }

        zone.dataset.dragActive = 'true';
    });

    root.addEventListener('dragleave', (event) => {
        const zone = zoneForTarget(event.target);

        if (zone === null || (event.relatedTarget instanceof Node && zone.contains(event.relatedTarget))) {
            return;
        }

        clearZoneDragState(zone);
    });

    root.addEventListener('drop', (event) => {
        const zone = zoneForTarget(event.target);

        if (containsFiles(event)) {
            event.preventDefault();
        }

        if (zone === null) {
            return;
        }

        event.preventDefault();
        clearZoneDragState(zone);

        const input = inputForZone(zone);
        const files = event.dataTransfer?.files;

        if (!(input instanceof HTMLInputElement) || !files || files.length === 0) {
            return;
        }

        const validation = validateFiles(files, {
            accept: input.accept,
            maxBytes: Number(zone.dataset.maxBytes),
            multiple: input.multiple,
        });

        if (!validation.valid) {
            setZoneError(zone, validation.message);

            return;
        }

        try {
            assignFilesToInput(input, files);
            setZoneError(zone, '');
        } catch (error) {
            console.error('Le dépôt du fichier dans le champ a échoué.', error);
            setZoneError(zone, error instanceof Error ? error.message : 'Le fichier n’a pas pu être ajouté au champ.');
        }
    });

    root.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-file-dropzone-input]')) {
            return;
        }

        const zone = input.closest('[data-file-dropzone]');

        if (!(zone instanceof HTMLElement)) {
            return;
        }

        const files = input.files;

        if (!files || files.length === 0) {
            renderFileSelection(zone, []);
            setZoneError(zone, '');

            return;
        }

        const validation = validateFiles(files, {
            accept: input.accept,
            maxBytes: Number(zone.dataset.maxBytes),
            multiple: input.multiple,
        });

        if (!validation.valid) {
            input.value = '';
            renderFileSelection(zone, []);
            setZoneError(zone, validation.message);

            return;
        }

        renderFileSelection(zone, files);
        setZoneError(zone, '');
    });

    root.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const removeButton = event.target.closest('[data-file-dropzone-remove]');
        const zone = removeButton?.closest('[data-file-dropzone]');
        const input = inputForZone(zone);

        if (!(removeButton instanceof HTMLButtonElement) || !(input instanceof HTMLInputElement)) {
            return;
        }

        clearFileInput(input);
        input.focus();
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.fileUploadSubmitting === 'true') {
            event.preventDefault();

            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        const fileInputs = Array.from(form.querySelectorAll('[data-file-dropzone-input]'))
            .filter((input) => input instanceof HTMLInputElement && input.files?.length > 0);

        if (fileInputs.length === 0) {
            return;
        }

        form.dataset.fileUploadSubmitting = 'true';
        form.setAttribute('aria-busy', 'true');
        rememberPendingUploadNames(fileInputs);

        fileInputs.forEach((input) => {
            const zone = input.closest('[data-file-dropzone]');

            if (zone instanceof HTMLElement) {
                setZoneUploadInProgress(zone, input.files.length);
            }
        });

        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((control) => {
            if (control instanceof HTMLElement) {
                control.setAttribute('aria-disabled', 'true');
                control.className = `${control.className} cursor-wait opacity-60`.trim();
            }
        });
    });

    root.addEventListener('reset', (event) => {
        if (!(event.target instanceof HTMLFormElement)) {
            return;
        }

        queueMicrotask(() => {
            event.target.querySelectorAll('[data-file-dropzone-input]').forEach((input) => {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const zone = input.closest('[data-file-dropzone]');

                if (zone instanceof HTMLElement) {
                    renderFileSelection(zone, []);
                    setZoneError(zone, '');
                }
            });
        });
    });
}
