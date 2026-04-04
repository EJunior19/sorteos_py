@extends('layouts.app')

@section('title', 'Crear Sorteo')

@section('content')

    <div class="bg-[#141414] p-6 rounded-2xl border border-yellow-500/30 shadow-lg">

        <h1 class="text-2xl text-yellow-400 mb-5 text-center font-bold">
            🎁 Crear Sorteo
        </h1>

        <!-- 🔴 ERRORES -->
        @if ($errors->any())
            <div class="bg-red-500 text-white p-3 rounded mb-4">
                @foreach ($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <!-- NOMBRE -->
            <input name="name" required placeholder="Nombre del sorteo"
                class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400">

            <!-- PRECIO -->
            <input id="price" name="price" required type="text" placeholder="10.000"
                class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400">

            <!-- CANTIDAD -->
            <input name="total_numbers" required type="number" placeholder="50"
                class="w-full p-4 rounded-xl bg-black text-white border border-yellow-400">

            <!-- IMAGEN -->
            <input type="file" name="image" accept="image/*"
                class="w-full p-3 rounded-xl bg-black text-white border border-yellow-400">

            <!-- BOTON -->
            <button type="submit" class="w-full bg-yellow-400 text-black py-4 rounded-xl font-bold">
                Crear Sorteo
            </button>

        </form>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const priceInput = document.getElementById('price');

            if (priceInput) {
                priceInput.addEventListener('input', function (e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = new Intl.NumberFormat('es-PY').format(value);
                    e.target.value = value;
                });
            }
        });
    </script>

@endsection