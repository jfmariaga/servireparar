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

        new TomSelect(select, {
            create: false,
            allowEmptyOption: true,
            maxOptions: null,
            plugins: select.multiple ? ['remove_button'] : [],
        });
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
