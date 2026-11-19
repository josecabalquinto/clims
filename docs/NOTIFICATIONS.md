# Application notifications

Toastr and its required jQuery dependency are installed through npm and bundled locally with Vite. No CDN is required.

`resources/js/notifications.js` owns the notification handler and imports the vendor and CLIMS notification styles. Notifications appear at the bottom center with mobile safe-area spacing. Success uses the application's deep teal; error, warning and information messages have separate accessible colors. Toasts support dismissal, duplicate suppression, progress indicators, escaped text and reduced motion.

All application and authentication layouts include `components/notifications.blade.php`. It carries session feedback in an inert, safely encoded template; the JavaScript handler consumes it on initial load and after Livewire navigation. It supports `equipment-status`, `borrowing-status`, `status`, `success`, `error`, `warning` and `info`. Authentication redirect errors also receive an error toast. The template is consumed once; navigation clears old toasts.

Livewire actions without redirects use:

```php
$this->dispatch('notify', type: 'success', message: __('Changes saved.'));
```

Supported types are `success`, `error`, `warning` and `info`. Redirecting actions can keep their existing session flashes. Do not also dispatch a toast for the same redirect result.

Inline save/status banners and Flux toast hosts have been removed. Field validation errors remain beside their controls so users can correct inputs. They are distinct from action confirmation alerts.
