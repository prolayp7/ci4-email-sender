window.showToast = function (message, type) {
    type = type || 'success';
    const bg = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning' }[type] || 'text-bg-success';

    const el = document.createElement('div');
    el.className = 'toast align-items-center ' + bg + ' border-0';
    el.setAttribute('role', 'alert');

    const flex = document.createElement('div');
    flex.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message; // never innerHTML: message can come from a server response

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');

    flex.appendChild(body);
    flex.appendChild(closeBtn);
    el.appendChild(flex);

    document.getElementById('toastContainer').appendChild(el);
    new bootstrap.Toast(el, { delay: 4000 }).show();
};

window.confirmAction = function (message, onConfirm) {
    const modalEl = document.getElementById('confirmDialog');
    document.getElementById('confirmDialogMessage').textContent = message;
    const modal = new bootstrap.Modal(modalEl);
    const btn = document.getElementById('confirmDialogConfirmBtn');
    const handler = function () {
        modal.hide();
        btn.removeEventListener('click', handler);
        onConfirm();
    };
    btn.addEventListener('click', handler);
    modal.show();
};
