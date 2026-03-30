function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar) {
        return;
    }

    if (window.innerWidth > 991) {
        sidebar.classList.remove('active');

        if (overlay) {
            overlay.classList.remove('active');
        }

        document.body.classList.remove('sidebar-open');
        return;
    }

    const shouldOpen = typeof forceState === 'boolean'
        ? forceState
        : !sidebar.classList.contains('active');

    sidebar.classList.toggle('active', shouldOpen);

    if (overlay) {
        overlay.classList.toggle('active', shouldOpen);
    }

    document.body.classList.toggle('sidebar-open', shouldOpen && window.innerWidth <= 991);
}

document.addEventListener('click', function (event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');

    if (!sidebar || !toggleBtn) {
        return;
    }

    if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            toggleSidebar(false);
        }
    }
});

window.addEventListener('resize', function () {
    if (window.innerWidth > 991) {
        toggleSidebar(false);
    }
});

function getUserEditorFieldState(field) {
    const key = field.name || field.id || field.type;

    if (field.type === 'checkbox' || field.type === 'radio') {
        return `${key}:${field.checked}`;
    }

    return `${key}:${field.value}`;
}

function bindUserEditorForm(form) {
    const modalContent = form.closest('.user-editor-modal');

    if (!modalContent) {
        return;
    }

    const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter((field) => {
        if (field.disabled) {
            return false;
        }

        return !['_token', '_method'].includes(field.name);
    });

    const buildSnapshot = () => fields.map(getUserEditorFieldState).join('|');
    const initialSnapshot = buildSnapshot();
    const forceDirty = form.dataset.initialDirty === 'true';

    const syncDirtyState = () => {
        const isDirty = forceDirty || buildSnapshot() !== initialSnapshot;
        modalContent.classList.toggle('is-dirty', isDirty);
    };

    syncDirtyState();

    fields.forEach((field) => {
        field.addEventListener('input', syncDirtyState);
        field.addEventListener('change', syncDirtyState);
    });

    form.addEventListener('reset', function () {
        requestAnimationFrame(syncDirtyState);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-user-editor-form]').forEach(bindUserEditorForm);
});
