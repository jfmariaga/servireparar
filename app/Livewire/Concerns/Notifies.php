<?php

namespace App\Livewire\Concerns;

/**
 * Convención de notificaciones del sistema: cualquier componente Livewire/Volt
 * que cree, edite, active o inactive un registro debe avisar al usuario con
 * notifySuccess()/notifyError() en lugar de depender solo del cambio de estado
 * en pantalla. El evento "notify" lo captura el JS global (resources/js/app.js)
 * y lo muestra como toast de SweetAlert2.
 */
trait Notifies
{
    protected function notifySuccess(string $message): void
    {
        $this->dispatch('notify', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->dispatch('notify', type: 'error', message: $message);
    }
}
