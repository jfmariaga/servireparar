@props(['label' => null, 'for' => null, 'required' => false, 'hint' => null, 'error' => null])

{{--
    Envoltorio de campo de formulario: etiqueta + control (slot) + ayuda/errores,
    con espaciado consistente. Uso:
    <x-field label="Cliente" required>
        <x-select wire:model="clienteId"> ... </x-select>
        @error('clienteId') <x-slot:error>{{ $message }}</x-slot:error> @enderror
    </x-field>
--}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="text-[13px] font-semibold text-slate-700 dark:text-slate-200">
            {{ $label }}@if ($required)<span class="text-brand-red"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-slate-400">{{ $hint }}</p>
    @endif
    @if ($error)
        <p class="text-xs font-medium text-brand-red">{{ $error }}</p>
    @endif
</div>
