<form
    id="bulk-document-approval-form"
    data-bulk-document-form
    method="POST"
    action="{{ $action }}"
    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
>
    @csrf
    <p class="text-sm text-slate-600">{{ $description }}</p>
    <button
        type="submit"
        data-bulk-document-submit
        disabled
        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600"
    >
        Valider la sélection (0)
    </button>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-bulk-document-approval]').forEach((container) => {
            const submitButton = container.querySelector('[data-bulk-document-submit]');
            const checkboxes = Array.from(container.querySelectorAll('[data-bulk-document-checkbox]'));

            if (!submitButton) {
                return;
            }

            const updateSelection = () => {
                const count = checkboxes.filter((checkbox) => checkbox.checked).length;
                submitButton.disabled = count === 0;
                submitButton.textContent = `Valider la sélection (${count})`;
            };

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
            updateSelection();
        });
    });
</script>
