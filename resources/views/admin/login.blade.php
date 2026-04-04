@extends('layouts.app')

@section('title', 'Login Admin')

@section('content')

    <div class="min-h-[70vh] flex items-center justify-center">

        <div
            class="w-full bg-[#141414] p-6 rounded-2xl border border-yellow-500/30 shadow-[0_0_25px_rgba(250,204,21,0.15)]">

            <!-- 🟡 LOGO -->
            <div class="flex justify-center mb-4">
                <img src="/logo.png" alt="Logo" class="w-24 h-24 rounded-full border-4 border-yellow-400 shadow-lg">
            </div>

            <!-- 🔐 TITULO -->
            <h1 class="text-2xl font-extrabold text-yellow-400 text-center mb-2">
                Panel Admin
            </h1>

            <p class="text-center text-yellow-200 text-sm mb-5">
                Acceso seguro
            </p>

            <!-- ERROR -->
            @if(session('error'))
                <div class="bg-red-500/90 text-white p-3 rounded-xl mb-4 text-center text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- FORM -->
            <form method="POST" action="/admin/login" class="space-y-4">
                @csrf

                <input type="password" name="password" placeholder="🔒 Contraseña"
                    class="w-full p-4 rounded-xl text-white text-lg bg-[#0B0B0B] border border-yellow-400 outline-none focus:ring-2 focus:ring-yellow-400">

                <button class="w-full bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-500 
                            text-black py-4 text-lg rounded-xl font-bold 
                            hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 shadow-lg">

                    🚀 Ingresar
                </button>
            </form>

            <!-- FOOTER -->
            <p class="text-center text-xs text-gray-400 mt-5">
                Sistema seguro • Sorteos PY
            </p>

        </div>

    </div>
    <style>
        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #0B0B0B inset !important;
            -webkit-text-fill-color: white !important;
        }
    </style>
@endsection