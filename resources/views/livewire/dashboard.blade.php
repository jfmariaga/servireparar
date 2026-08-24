<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout')] class extends Component
{
    //
}; ?>

<div>
    <h1 class="text-xl font-bold mb-4">Bienvenido, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-slate-600">
        Rol(es): {{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}
    </p>
    <p class="text-sm text-slate-400 mt-4">
        Los dashboards por rol con indicadores (spec 007) se implementan en un módulo posterior.
        Por ahora usa el menú superior para gestionar los catálogos maestros disponibles.
    </p>
</div>
