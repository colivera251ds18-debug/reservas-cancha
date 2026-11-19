@extends('layouts.app')
@section('titulo', 'Inicio')
@section('contenido')
    <h1 class="text-2xl font-bold text-emerald-800 sm:text-3xl">
Sistema de reservas de la cancha deportiva
    </h1>
    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <p>Proyecto base funcionando con Laravel {{ app()->version() }}.</p>
        <p class="mt-2">Base de datos conectada:
            <strong>{{ DB::scalar('select current_database()') }}</strong>
            en <strong>{{ config('database.connections.pgsql.host') }}</strong>
        </p>
    </div>
@endsection
