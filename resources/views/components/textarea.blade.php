{{--
    Área de texto con el mismo estilo que <x-input>.
--}}
<textarea {{ $attributes->merge([
    'class' => 'w-full min-h-[92px] rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 outline-none transition focus:bg-white dark:focus:bg-slate-800 focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10',
]) }}>{{ $slot }}</textarea>
