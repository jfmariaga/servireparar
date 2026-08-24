<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SERVIOPS' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-800">
    @auth
        <nav class="bg-slate-900 text-white">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
                <a href="{{ url('/') }}" class="font-bold text-lg">SERVIOPS</a>
                <div class="flex items-center gap-4 text-sm">
                    @can('manage-clientes')
                        <a href="{{ route('clientes.index') }}" class="hover:underline">Clientes</a>
                    @endcan
                    @can('manage-proveedores')
                        <a href="{{ route('proveedores.index') }}" class="hover:underline">Proveedores</a>
                    @endcan
                    @can('manage-contratistas')
                        <a href="{{ route('contratistas.index') }}" class="hover:underline">Contratistas</a>
                    @endcan
                    @can('manage-usuarios')
                        <a href="{{ route('usuarios.index') }}" class="hover:underline">Usuarios</a>
                    @endcan
                    <span class="text-slate-400">|</span>
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="hover:underline">Salir</button>
                    </form>
                </div>
            </div>
        </nav>
    @endauth

    <main class="max-w-6xl mx-auto px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
