import './bootstrap';
import Notify from './notify';
import TomSelect from 'tom-select';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Legend, Tooltip } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Legend, Tooltip);

/**
 * PWA básica (pulido de producto): registra el service worker que cachea los
 * assets estáticos de Vite (/build/...) para que la app cargue más rápido y
 * sea instalable. No cachea HTML ni datos — sin edición offline de OT.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

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
    /**
     * Convención: la gráfica "Tendencia OT en curso" del dashboard de piso
     * (spec 007 extendida) vive en un x-data="serviopsPisoChart(...)" con un
     * <canvas x-ref="canvas"> dentro de un wire:ignore, para que Chart.js
     * mantenga su propio DOM entre polls de Livewire. Los datos nuevos llegan
     * por el evento 'piso-tendencia-actualizada' ($this->dispatch en el
     * componente) y solo actualizan los datasets, sin recrear el canvas.
     */
    window.Alpine.data('serviopsPisoChart', (data) => ({
        chart: null,

        init() {
            this.chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: this.buildData(data),
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { position: 'bottom' } },
                },
            });

            Livewire.on('piso-tendencia-actualizada', ({ data }) => this.actualizar(data));
        },

        buildData(data) {
            return {
                labels: data.map((b) => b.hora),
                datasets: [
                    { label: 'Taller', data: data.map((b) => b.taller), borderColor: '#0ea5e9', backgroundColor: '#0ea5e9', tension: 0.3 },
                    { label: 'A domicilio', data: data.map((b) => b.domicilio), borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: 0.3 },
                ],
            };
        },

        actualizar(data) {
            const nuevos = this.buildData(data);
            this.chart.data.labels = nuevos.labels;
            this.chart.data.datasets.forEach((ds, i) => { ds.data = nuevos.datasets[i].data; });
            this.chart.update();
        },
    }));

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
