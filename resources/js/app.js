import './bootstrap';
import Notify from './notify';
import TomSelect from 'tom-select';

/**
 * Convención: cualquier <select> de la app usa el componente <x-select>
 * (resources/views/components/select.blade.php) en vez de un <select> plano,
 * para que todas las listas desplegables tengan búsqueda tipo "select2".
 * Este helper envuelve el <select> nativo con Tom Select sin tocar el
 * binding wire:model (Tom Select dispara 'change' sobre el <select> oculto).
 */
window.ServiopsSelect = {
    mount(select) {
        if (!select || select.tomselect) {
            return;
        }

        const ts = new TomSelect(select, {
            create: false,
            allowEmptyOption: true,
            maxOptions: null,
            plugins: select.multiple ? ['remove_button'] : [],
        });

        // Cuando este <select> aparece con un valor ya asignado (p. ej. al
        // editar una tarea existente), Livewire a veces termina de fijar el
        // valor real del <select> oculto justo después de que Alpine monta
        // Tom Select, y este se queda mostrando "Seleccionar..." aunque el
        // valor ya esté correcto por debajo. Resincroniza una vez más tras
        // el ciclo actual para no perder ese valor inicial.
        setTimeout(() => {
            if (select.value && ts.getValue() !== select.value) {
                ts.setValue(select.value, true);
            }
        }, 0);
    },
};

document.addEventListener('livewire:init', () => {
    /**
     * Convención: cualquier componente Livewire notifica al usuario con
     * $this->dispatch('notify', type: 'success'|'error', message: '...')
     * (ver App\Livewire\Concerns\Notifies) y aquí se muestra como toast.
     */
    Livewire.on('notify', ({ type = 'success', message }) => {
        Notify.toast(type, message);
    });
});

document.addEventListener('alpine:init', () => {
    window.Alpine.store('ui', {
        dark: localStorage.getItem('serviops.dark') === '1',
        navMode: localStorage.getItem('serviops.navMode') || 'sidebar',
        mobileNavOpen: false,

        toggleDark() {
            this.dark = !this.dark;
            localStorage.setItem('serviops.dark', this.dark ? '1' : '0');
            document.documentElement.classList.toggle('dark', this.dark);
        },

        setNavMode(mode) {
            this.navMode = mode;
            localStorage.setItem('serviops.navMode', mode);
            this.mobileNavOpen = false;
        },

        toggleMobileNav() {
            this.mobileNavOpen = !this.mobileNavOpen;
        },
    });
});
