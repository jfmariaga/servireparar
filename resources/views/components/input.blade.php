@props(['type' => 'text'])

{{--
    Campo de texto estándar de la app: mismo alto, radio y foco que <x-select>
    (Tom Select). Acepta cualquier atributo nativo y wire:model.
--}}
<input {{ $attributes->merge([
    'type' => $type,
    'class' => 'w-full h-11 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3.5 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 outline-none transition focus:bg-white dark:focus:bg-slate-800 focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10 disabled:opacity-60 disabled:cursor-not-allowed',
]) }}>
