import toastr from 'toastr';
import 'toastr/build/toastr.min.css';
import '../css/notifications.css';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

toastr.options = {
    positionClass: 'toast-bottom-center',
    closeButton: true,
    closeHtml: '<button type="button" aria-label="Dismiss notification">&times;</button>',
    progressBar: true,
    preventDuplicates: true,
    escapeHtml: true,
    newestOnTop: true,
    timeOut: 6000,
    extendedTimeOut: 2000,
    showDuration: reducedMotion.matches ? 0 : 160,
    hideDuration: reducedMotion.matches ? 0 : 120,
    tapToDismiss: false,
};

function notify({ type = 'info', message = '' } = {}) {
    if (typeof message !== 'string' || !message.trim()) return;
    const variant = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    toastr[variant](message);
}

function showPageNotifications() {
    document.querySelectorAll('template[data-clims-notifications]').forEach((template) => {
        const messages = JSON.parse(template.content.textContent);
        template.remove();
        messages.forEach(notify);
    });
}

// One window listener survives Livewire navigation, without accumulating handlers.
window.addEventListener('notify', (event) => notify(event.detail));
document.addEventListener('livewire:navigating', () => toastr.remove());
document.addEventListener('livewire:navigated', showPageNotifications);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showPageNotifications, { once: true });
} else {
    showPageNotifications();
}
