<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Inicio') · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-gray-50 font-sans text-gray-800 antialiased">
    <header class="bg-emerald-700 text-white">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('inicio') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>
            <ul class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                <li><a href="{{ route('inicio') }}" class="hover:underline">Inicio</a></li>
                <li><a href="#" class="hover:underline">Disponibilidad</a></li>
                <li><a href="#" class="hover:underline">Reservas</a></li>
                <li><a href="#" class="hover:underline">Clientes</a></li>
                <li><a href="#" class="hover:underline">Canchas</a></li>
                <li><a href="#" class="hover:underline">Usuarios</a></li>
            </ul>
        </nav>
    </header>
 
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        @yield('contenido')
    </main>
 
    <footer class="border-t border-gray-200 bg-white">
        <p class="mx-auto max-w-6xl px-4 py-4 text-xs text-gray-500">
            DSI406 · Tecnología Backend · IES Túpac Amaru
        </p>
    </footer>
</body>
</html>
