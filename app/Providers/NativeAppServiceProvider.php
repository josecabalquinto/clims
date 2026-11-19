<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->title('CLIMS')
            ->width(1280)
            ->height(820)
            ->minWidth(900)
            ->minHeight(640)
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     *
     * @return array<string, bool|int|string>
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
