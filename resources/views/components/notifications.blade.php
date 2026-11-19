@php
    $notifications = [];
    foreach (['equipment-status', 'borrowing-status', 'status', 'success', 'error', 'warning', 'info'] as $key) {
        $message = session($key);
        if (is_string($message) && $message !== '') {
            $notifications[] = ['type' => in_array($key, ['error', 'warning', 'info'], true) ? $key : 'success', 'message' => __($message)];
        }
    }
    $notificationErrors = session('errors', $errors);
    if ($notificationErrors instanceof Illuminate\Support\ViewErrorBag && $notificationErrors->any()) {
        $notifications[] = ['type' => 'error', 'message' => $notificationErrors->first()];
    }
@endphp

<template data-clims-notifications>{{ Illuminate\Support\Js::encode($notifications) }}</template>
