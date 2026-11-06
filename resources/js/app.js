//

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
