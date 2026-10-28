@props(['wireModel', 'placeholder' => 'Precio'])

{{--
    Convención: todo campo de PRECIO EDITABLE (no solo de visualización) usa
    <x-input-money wire-model="..."> en vez de un <input type="number"> plano,
    para que el usuario vea el separador de miles mientras escribe (45000 ->
    45.000), igual que App\Support\Moneda::cop() lo muestra en el resto de la
    app (sin centavos: el COP no los usa en el día a día del taller).
    El valor real que llega a Livewire son solo dígitos (@entangle sincroniza
    la propiedad indicada por wire-model); Alpine solo formatea la vista.
--}}
<div x-data="{
        raw: @entangle($wireModel),
        get display() {
            if (this.raw === null || this.raw === undefined || this.raw === '') return '';
            return new Intl.NumberFormat('es-CO').format(this.raw);
        },
        onInput(e) {
            const digits = e.target.value.replace(/\D/g, '');
            this.raw = digits === '' ? '' : digits;
            this.$nextTick(() => { e.target.value = this.display; });
        },
     }">
    <input type="text" inputmode="numeric" placeholder="{{ $placeholder }}"
           x-bind:value="display" x-on:input="onInput($event)"
           {{ $attributes->merge(['class' => 'w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue']) }}>
</div>
