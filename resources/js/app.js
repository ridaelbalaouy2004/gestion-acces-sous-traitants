//

import { initializeFileDropzones } from './file-dropzone.js';

initializeFileDropzones();

const passwordToggle = document.querySelector('[data-toggle-password]');

passwordToggle?.addEventListener('click', () => {
    const passwordInput = document.querySelector('#password');

    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    const shouldShowPassword = passwordInput.type === 'password';

    passwordInput.type = shouldShowPassword ? 'text' : 'password';
    passwordToggle.setAttribute('aria-pressed', String(shouldShowPassword));
    passwordToggle.setAttribute(
        'aria-label',
        shouldShowPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
    );
    passwordToggle.textContent = shouldShowPassword ? 'Masquer' : 'Afficher';
});

const sidebar = document.querySelector('[data-sidebar]');
const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarClose = document.querySelector('[data-sidebar-close]');

function setSidebarOpen(isOpen) {
    if (!(sidebar instanceof HTMLElement)) {
        return;
    }

    sidebar.classList.toggle('is-open', isOpen);
    sidebarOverlay?.classList.toggle('is-visible', isOpen);
    sidebarToggle?.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('overflow-hidden', isOpen);
}

sidebarToggle?.addEventListener('click', () => {
    setSidebarOpen(!sidebar?.classList.contains('is-open'));
});

sidebarClose?.addEventListener('click', () => setSidebarOpen(false));
sidebarOverlay?.addEventListener('click', () => setSidebarOpen(false));

sidebar?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setSidebarOpen(false));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebarOpen(false);
    }
});

window.matchMedia('(min-width: 768px)').addEventListener('change', (event) => {
    if (event.matches) {
        setSidebarOpen(false);
    }
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm ?? 'Confirmer cette action ?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('form[data-guide-email-form]').forEach((form) => {
    const dialog = document.getElementById(form.dataset.confirmationDialog ?? '');
    const confirmButton = dialog?.querySelector('[data-guide-email-confirm]');
    const cancelButton = dialog?.querySelector('[data-guide-email-cancel]');
    const confirmationInput = form.querySelector('[data-guide-email-confirmed]');
    const submitButton = form.querySelector('[data-guide-email-submit]');

    if (
        !(dialog instanceof HTMLDialogElement)
        || !(confirmButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
        || !(confirmationInput instanceof HTMLInputElement)
        || !(submitButton instanceof HTMLButtonElement)
    ) {
        return;
    }

    let confirmed = false;

    form.addEventListener('submit', (event) => {
        if (!confirmed) {
            event.preventDefault();
            dialog.showModal();
            return;
        }

        confirmed = false;
        submitButton.disabled = true;
        submitButton.textContent = 'Envoi en cours…';
    });

    confirmButton.addEventListener('click', () => {
        confirmed = true;
        confirmationInput.value = 'yes';
        confirmButton.disabled = true;
        dialog.close();
        form.requestSubmit();
    });

    cancelButton.addEventListener('click', () => {
        dialog.close();
    });

    dialog.addEventListener('close', () => {
        confirmButton.disabled = false;
    });
});

const excelImportModal = document.querySelector('[data-excel-import-modal]');

document.addEventListener('click', async (event) => {
    if (!(event.target instanceof Element) || !(excelImportModal instanceof HTMLDialogElement)) {
        return;
    }

    const closeButton = event.target.closest('[data-excel-import-close]');

    if (closeButton instanceof HTMLButtonElement) {
        excelImportModal.close();
        return;
    }

    const trigger = event.target.closest('[data-excel-import-trigger]');

    if (!(trigger instanceof HTMLButtonElement)) {
        if (event.target === excelImportModal) {
            excelImportModal.close();
        }

        return;
    }

    const content = excelImportModal.querySelector('[data-excel-import-modal-content]');
    const title = excelImportModal.querySelector('[data-excel-import-modal-title]');
    const dataUrl = trigger.dataset.url;

    if (!(content instanceof HTMLElement) || !(title instanceof HTMLElement) || !dataUrl) {
        return;
    }

    title.textContent = trigger.dataset.title ?? 'Données Excel importées';
    content.textContent = 'Chargement des données importées…';
    excelImportModal.showModal();

    try {
        const response = await fetch(dataUrl, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Impossible de charger les données Excel (${response.status}).`);
        }

        const markup = await response.text();

        if (excelImportModal.open) {
            content.innerHTML = markup;
        }
    } catch {
        if (excelImportModal.open) {
            content.textContent = 'Les données Excel n’ont pas pu être chargées. Fermez cette fenêtre et réessayez.';
        }
    }
});

document.addEventListener('error', (event) => {
    const image = event.target;

    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    const fallbackSelector = image.hasAttribute('data-team-employee-photo')
        ? '[data-profile-photo-error-fallback]'
        : image.hasAttribute('data-company-machinery-photo')
            ? '[data-machinery-photo-error-fallback]'
            : image.hasAttribute('data-company-tooling-photo')
                ? '[data-tooling-photo-error-fallback]'
            : null;

    if (fallbackSelector === null) {
        return;
    }

    const fallback = image.parentElement?.querySelector(fallbackSelector);

    if (!(fallback instanceof HTMLElement)) {
        return;
    }

    image.hidden = true;
    fallback.hidden = false;
}, true);

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const trainingEditButton = event.target.closest('[data-training-edit-open]');

    if (trainingEditButton instanceof HTMLButtonElement) {
        const trainingCard = trainingEditButton.closest('[data-training-card]');
        const editForm = trainingCard?.querySelector('[data-training-edit-form]');

        if (!(editForm instanceof HTMLFormElement)) {
            return;
        }

        editForm.hidden = false;
        trainingEditButton.setAttribute('aria-expanded', 'true');

        const firstField = editForm.querySelector('input:not([type="hidden"]), select, textarea');

        if (firstField instanceof HTMLElement) {
            firstField.focus();
        }

        return;
    }

    const trainingEditCancelButton = event.target.closest('[data-training-edit-cancel]');

    if (trainingEditCancelButton instanceof HTMLButtonElement) {
        const trainingCard = trainingEditCancelButton.closest('[data-training-card]');
        const editForm = trainingCard?.querySelector('[data-training-edit-form]');
        const trainingEditButton = trainingCard?.querySelector('[data-training-edit-open]');

        if (
            !(editForm instanceof HTMLFormElement)
            || !(trainingEditButton instanceof HTMLButtonElement)
        ) {
            return;
        }

        editForm.reset();
        editForm.hidden = true;
        trainingEditButton.setAttribute('aria-expanded', 'false');
        trainingEditButton.focus();
        return;
    }

    const navigationContainer = event.target.closest('[data-navigate-to]');

    if (
        !(navigationContainer instanceof HTMLElement)
        || event.target.closest('a, button, input, select, textarea, form, label, summary, [role="button"], [role="link"], [contenteditable="true"], [data-no-row-navigation]')
    ) {
        return;
    }

    const destination = navigationContainer.dataset.navigateTo;

    if (destination) {
        window.location.assign(destination);
    }
});

document.addEventListener('keydown', (event) => {
    if (!(event.target instanceof HTMLElement) || !event.target.hasAttribute('data-navigate-to')) {
        return;
    }

    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    event.preventDefault();

    const destination = event.target.dataset.navigateTo;

    if (destination) {
        window.location.assign(destination);
    }
});

document.querySelectorAll('[data-copy-password]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    button.addEventListener('click', async () => {
        const target = document.getElementById(button.dataset.copyPassword ?? '');
        const feedback = document.querySelector('[data-copy-feedback]');

        if (!(target instanceof HTMLInputElement) || !(feedback instanceof HTMLElement)) {
            return;
        }

        try {
            await navigator.clipboard.writeText(target.value);
            feedback.textContent = 'Le mot de passe temporaire a été copié.';
        } catch {
            target.focus();
            target.select();
            feedback.textContent = 'La copie automatique a échoué. Le mot de passe est sélectionné pour une copie manuelle.';
        }
    });
});

const customFields = document.querySelector('[data-custom-fields]');
const addCustomFieldButton = document.querySelector('[data-add-custom-field]');

if (customFields instanceof HTMLElement && addCustomFieldButton instanceof HTMLButtonElement) {
    let nextCustomFieldIndex = customFields.querySelectorAll('[data-custom-field-row]').length;

    const updateCustomFieldButton = () => {
        addCustomFieldButton.disabled = customFields.querySelectorAll('[data-custom-field-row]').length >= 10;
    };

    const addCustomField = () => {
        if (customFields.querySelectorAll('[data-custom-field-row]').length >= 10) {
            return;
        }

        const index = nextCustomFieldIndex;
        nextCustomFieldIndex += 1;

        const row = document.createElement('div');
        row.className = 'grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]';
        row.dataset.customFieldRow = '';

        [
            { label: 'Nom du champ', key: 'name', id: `custom-field-name-${index}` },
            { label: 'Valeur', key: 'value', id: `custom-field-value-${index}` },
        ].forEach((field) => {
            const wrapper = document.createElement('div');
            const label = document.createElement('label');
            const input = document.createElement('input');

            label.htmlFor = field.id;
            label.className = 'mb-1 block text-xs font-semibold text-slate-600';
            label.textContent = field.label;
            input.id = field.id;
            input.name = `custom_fields[${index}][${field.key}]`;
            input.maxLength = field.key === 'name' ? 100 : 500;
            input.className = 'form-control min-h-10 w-full rounded-lg border border-slate-300 px-3 text-sm';

            wrapper.append(label, input);
            row.append(wrapper);
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.dataset.removeCustomField = '';
        removeButton.className = 'min-h-10 self-end rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50';
        removeButton.textContent = 'Supprimer';
        row.append(removeButton);
        customFields.append(row);
        updateCustomFieldButton();
    };

    addCustomFieldButton.addEventListener('click', addCustomField);
    customFields.addEventListener('click', (event) => {
        const target = event.target;

        if (target instanceof HTMLButtonElement && target.matches('[data-remove-custom-field]')) {
            target.closest('[data-custom-field-row]')?.remove();
            updateCustomFieldButton();
        }
    });

    updateCustomFieldButton();
}
