<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Inicio'])] class extends Component
{
    //
}; ?>

<div>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-7">
        <h1 class="text-xl font-bold mb-1.5">Bienvenido, {{ auth()->user()->name }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Rol(es): {{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}
        </p>
        <p class="text-sm text-slate-400 dark:text-slate-500 mt-4">
            Los dashboards por rol con indicadores se implementan en un módulo posterior.
            Por ahora usa el menú de navegación para gestionar los catálogos maestros disponibles.
        </p>
    </div>
</div>
