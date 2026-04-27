<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <!-- 📱 MOBILE -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sorteos Seguros PY')</title>

    <!-- 🎨 ESTILO -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- 📲 PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#facc15">
    <link rel="apple-touch-icon" href="/logo.png">

    <!-- 📷 html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>

</head>

<body class="bg-[#0B0B0B] text-white min-h-screen">

    <!-- 🔥 CONTENEDOR APP (IMPORTANTE) -->
    <div class="max-w-md mx-auto relative min-h-screen">

        <!-- 🔥 HEADER -->
        <div class="bg-black border-b border-yellow-500/30 p-4 flex justify-between items-center sticky top-0 z-50">

            <a href="/" class="text-lg font-bold text-yellow-400">
                🎰 SorteosPY
            </a>

            <div class="flex gap-3 text-sm">
                <a href="/" class="text-yellow-300 hover:text-yellow-400 transition">Inicio</a>
                <a href="/admin" class="text-yellow-300 hover:text-yellow-400 transition">Admin</a>
            </div>

        </div>

        <!-- 📦 CONTENIDO -->
        <div class="p-4 pb-24">
            @yield('content')
        </div>

    </div>

    <!-- 📲 BOTÓN INSTALAR APP -->
    <button id="installBtn"
        class="fixed bottom-4 right-4 bg-yellow-400 text-black px-4 py-3 rounded-full shadow-lg hidden hover:scale-105 transition z-50">
        📲 Instalar
    </button>

    <!-- ⚙️ SCRIPTS -->
    <script>

        // 🔥 SERVICE WORKER
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(() => console.log('Service Worker registrado'))
                .catch(err => console.log('SW error', err));
        }

        // 📲 INSTALAR APP
        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('installBtn').classList.remove('hidden');
        });

        document.getElementById('installBtn').addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt = null;
            }
        });

    </script>

</body>

</html>