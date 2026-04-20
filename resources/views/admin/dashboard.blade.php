@extends('layouts.app')

@section('title', 'Admin')

@section('content')

@if(!isset($raffle) || !$raffle)

    <div class="text-center text-yellow-400 mt-10">
        No hay sorteos creados aún
    </div>

    <div class="text-center mt-4">
        <a href="/admin/create" class="bg-yellow-400 text-black px-4 py-2 rounded-lg font-bold">
            Crear primer sorteo
        </a>
    </div>

@else

    <!-- HEADER -->
    <div class="flex justify-between mb-4 items-center gap-2">
        <a href="/admin/create" class="bg-yellow-400 text-black px-3 py-2 rounded text-sm font-bold">
            + Crear
        </a>
        <button
            id="btn-whatsapp"
            onclick="enviarWhatsapp({{ $raffle->id }})"
            class="bg-green-500 hover:bg-green-400 text-white font-bold py-2 px-4 rounded-lg transition flex items-center gap-2 text-sm">
            📲 WhatsApp
        </button>
        <a href="/admin/logout" class="bg-red-500 px-3 py-2 rounded text-sm font-bold text-white">
            Salir
        </a>
    </div>

    <!-- INFO SORTEO -->
    <div class="bg-[#1A1A1A] p-4 rounded-xl mb-4 text-center shadow-lg">
        <h2 class="text-yellow-400 font-bold text-lg">{{ $raffle->name }}</h2>
        <p class="text-yellow-300 text-sm">
            💰 Gs. {{ number_format($raffle->price, 0, ',', '.') }}
        </p>
        @if($raffle->prizes && $raffle->prizes->count() > 0)
            <p class="text-gray-400 text-xs mt-1">
                🏆 {{ $raffle->prizes->count() }} premios
            </p>
        @endif
        <div class="mt-3">
            <div class="w-full bg-black rounded-full h-3">
                <div class="bg-yellow-400 h-3 rounded-full" style="width: {{ $progress ?? 0 }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ $progress ?? 0 }}% vendido</p>
        </div>
    </div>

    <!-- GANADORES (multi-premio) -->
    @if($raffle->prizes && $raffle->prizes->count() > 0)
        @php
            $hasWinners = $raffle->prizes->filter(fn($p) => !is_null($p->winner_number))->count() > 0;
        @endphp
        @if($hasWinners)
            <div class="bg-[#1a1a1a] border border-yellow-500/30 rounded-xl p-4 mb-4 space-y-2">
                <h3 class="text-yellow-400 font-bold text-sm mb-3">🏆 Resultados del sorteo</h3>
                @foreach($raffle->prizes->sortByDesc('order') as $prize)
                    @if($prize->winner_number)
                        <div class="flex justify-between items-center bg-green-900/40 border border-green-500/30 rounded-lg px-3 py-2">
                            <div>
                                <div class="text-green-400 font-bold text-sm">{{ $prize->name }}</div>
                                @if($prize->description)
                                    <div class="text-gray-400 text-xs">{{ $prize->description }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-white font-black">Nº {{ $prize->winner_number }}</div>
                                <div class="text-yellow-300 text-xs">{{ $prize->winner_name ?? 'N/A' }}</div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-between items-center bg-gray-800/40 border border-gray-600/30 rounded-lg px-3 py-2 opacity-60">
                            <div class="text-gray-400 text-sm">{{ $prize->name }}</div>
                            <div class="text-gray-500 text-xs">Pendiente</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

    @elseif($raffle->winner_number)
        <div class="bg-green-500 text-white p-4 rounded-xl text-center font-bold mb-4 text-lg shadow-lg">
            🏆 GANADOR: {{ $raffle->winner_number }}
            @if($raffle->winner_name)
                <div class="text-sm font-normal mt-1">{{ $raffle->winner_name }}</div>
            @endif
        </div>
    @endif

    <!-- GANADORES PROMO -->
    @if($raffle->promoResults && $raffle->promoResults->count() > 0)
        <div class="bg-[#1a1a1a] border border-yellow-500/30 rounded-xl p-4 mb-4 space-y-2">
            <h3 class="text-yellow-400 font-bold text-sm mb-3">🎁 Ganadores de la Promo</h3>
            @foreach($raffle->promoResults as $r)
                <div class="flex justify-between items-center bg-yellow-900/20 border border-yellow-500/20 rounded-lg px-3 py-2">
                    <div class="text-yellow-300 text-sm font-bold">{{ $r->prize_text }}</div>
                    <div class="text-right">
                        <div class="text-white font-black text-sm">Nº {{ str_pad($r->raffleNumber->number ?? '?', 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-gray-300 text-xs">{{ $r->customer_name }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- BOTON SORTEO -->
    @if(($free ?? 1) == 0 && ($raffle->status ?? '') == 'active')
        <a href="{{ route('admin.roulette', $raffle->id) }}"
           class="block w-full bg-green-500 text-white py-3 rounded-xl font-bold shadow-lg text-center mb-4">
            🎯 REALIZAR SORTEO
        </a>
    @endif

    <!-- GRID NUMEROS -->
    @if($raffle->numbers && $raffle->numbers->count() > 0)
        <div class="grid grid-cols-5 gap-2">
            @foreach($raffle->numbers as $n)
                <div class="text-center text-xs">
                    <div class="p-3 rounded font-bold transition-all
                        @if($n->status == 'free') bg-green-500
                        @elseif($n->status == 'reserved') bg-yellow-400 text-black
                        @else bg-red-500
                        @endif">
                        {{ $n->number }}
                    </div>
                    <div class="text-[10px] mt-1 truncate text-gray-300">
                        {{ $n->customer_name ?? '-' }}
                    </div>
                    @if($n->status == 'reserved')
                        <form method="POST" action="/admin/confirmar/{{ $n->id }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="bg-blue-500 text-xs mt-1 px-2 py-1 rounded w-full text-white hover:bg-blue-600 transition">
                                ✔ Confirmar
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

@endif

<!-- Modal mensajes WhatsApp -->
<div id="modal-whatsapp" class="fixed inset-0 z-50 hidden items-center justify-center px-3" style="background-color: rgba(0,0,0,0.95);">
    <div class="bg-gradient-to-b from-[#0a0a0a] to-[#000000] border border-green-500/60 rounded-2xl w-full max-w-md shadow-2xl max-h-[90vh] flex flex-col">

        <!-- Header fijo -->
        <div class="flex justify-between items-center p-4 border-b border-green-500/20 bg-[#111] rounded-t-2xl shrink-0">
            <h2 class="text-green-400 font-bold text-lg">📲 Mensajes WhatsApp</h2>
            <button onclick="cerrarModal()" class="text-gray-400 hover:text-white text-2xl leading-none p-1 rounded">×</button>
        </div>

        <!-- Loading -->
        <div id="modal-loading" class="p-8 text-center text-gray-400 shrink-0">
            <div class="text-4xl mb-3">⏳</div>
            <p>Generando mensajes...</p>
        </div>

        <!-- Lista scrolleable — incluye el botón imagen al final -->
        <div id="modal-contenido" class="hidden flex-1 overflow-y-auto p-4 space-y-2 pb-6"></div>

    </div>
</div>

<script>
const RAFFLE_STATUS = '{{ $raffle->status ?? "active" }}';
const RAFFLE_IMAGE  = '{{ $raffle->image ?? "" }}';

const ETIQUETAS = {
    mensaje_completo:   { titulo: '📋 Completo',           color: 'blue'   },
    urgencia:           { titulo: '🔥 Urgencia',           color: 'red'    },
    recordatorio_pago:  { titulo: '💸 Recordatorio',       color: 'yellow' },
    invitacion:         { titulo: '🎉 Invitación',         color: 'purple' },
    flash:              { titulo: '⚡ Flash',              color: 'green'  },
    ultima_oportunidad: { titulo: '🚨 Última Oportunidad', color: 'red'    },
    anuncio_ganadores:  { titulo: '🏆 Ganadores',          color: 'gold'   },
    agradecimiento:     { titulo: '🙏 Gracias',            color: 'pink'   },
    compartir_grupo:    { titulo: '🔗 Compartir Grupo',    color: 'teal'   },
};

const COLOR_MAP = {
    blue:   'border-blue-500/40 bg-blue-900/20',
    red:    'border-red-500/40 bg-red-900/20',
    yellow: 'border-yellow-500/40 bg-yellow-900/20',
    purple: 'border-purple-500/40 bg-purple-900/20',
    green:  'border-green-500/40 bg-green-900/20',
    gold:   'border-yellow-400/40 bg-yellow-900/20',
    pink:   'border-pink-500/40 bg-pink-900/20',
    teal:   'border-teal-500/40 bg-teal-900/20',
};

const BTN_MAP = {
    blue:   'bg-blue-600 hover:bg-blue-500',
    red:    'bg-red-600 hover:bg-red-500',
    yellow: 'bg-yellow-600 hover:bg-yellow-500',
    purple: 'bg-purple-600 hover:bg-purple-500',
    green:  'bg-green-600 hover:bg-green-500',
    gold:   'bg-yellow-500 hover:bg-yellow-400',
    pink:   'bg-pink-600 hover:bg-pink-500',
    teal:   'bg-teal-600 hover:bg-teal-500',
};

function cerrarModal() {
    document.getElementById('modal-whatsapp').classList.add('hidden');
    document.getElementById('modal-whatsapp').classList.remove('flex');
}

async function copiarImagenSorteo(imagenPath) {
    if (!imagenPath) { alert('El sorteo no tiene imagen'); return; }
    const btn = document.getElementById('btn-copiar-imagen-modal');
    const original = btn.innerHTML;
    btn.innerHTML = '⏳ Copiando...';
    btn.disabled = true;
    try {
        const response = await fetch(`/storage/${imagenPath}`);
        const blob = await response.blob();
        const img = new Image();
        img.onload = async function() {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            canvas.getContext('2d').drawImage(img, 0, 0);
            canvas.toBlob(async (pngBlob) => {
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob })]);
                    btn.innerHTML = '✅ Imagen copiada!';
                    setTimeout(() => {
                        btn.innerHTML = original;
                        btn.disabled = false;
                    }, 1500);
                } catch (e) {
                    alert('Error: ' + e.message);
                    btn.innerHTML = original;
                    btn.disabled = false;
                }
            }, 'image/png');
        };
        img.onerror = () => {
            alert('No se pudo cargar la imagen');
            btn.innerHTML = original;
            btn.disabled = false;
        };
        img.src = URL.createObjectURL(blob);
    } catch (e) {
        alert('Error: ' + e.message);
        btn.innerHTML = original;
        btn.disabled = false;
    }
}

async function enviarWhatsapp(raffleId) {
    if (!raffleId) return;
    const btn = document.getElementById('btn-whatsapp');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Cargando...';

    const modal = document.getElementById('modal-whatsapp');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('modal-loading').classList.remove('hidden');
    document.getElementById('modal-contenido').classList.add('hidden');
    document.getElementById('modal-contenido').innerHTML = '';

    try {
        const response = await fetch(`/admin/whatsapp/${raffleId}`);
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Error del servidor');

        const mensajes = generarMensajes(data);
        const contenido = document.getElementById('modal-contenido');

        const ordenActive = [
            'mensaje_completo',
            'urgencia',
            'recordatorio_pago',
            'invitacion',
            'flash',
            'ultima_oportunidad',
            'compartir_grupo',
        ];

        const ordenFinished = [
            'anuncio_ganadores',
            'agradecimiento',
            'compartir_grupo',
        ];

        const orden = RAFFLE_STATUS === 'finished' ? ordenFinished : ordenActive;

        for (const key of orden) {
            if (!mensajes[key]) continue;
            const meta = ETIQUETAS[key];
            contenido.innerHTML += `
                <div class="flex justify-between items-center p-3 rounded-lg border ${COLOR_MAP[meta.color]}">
                    <span class="text-white text-sm font-bold">${meta.titulo}</span>
                    <button onclick="copiarMensaje('${key}', this)"
                        class="${BTN_MAP[meta.color]} text-white text-xs font-bold px-4 py-2 rounded-lg whitespace-nowrap">
                        Copiar
                    </button>
                    <div id="msg-${key}" style="display:none;">${escapeHtml(mensajes[key])}</div>
                </div>`;
        }

        // Botón copiar imagen AL FINAL de la lista
        contenido.innerHTML += `
            <div class="pt-2 border-t border-green-500/20 mt-2">
                <button
                    id="btn-copiar-imagen-modal"
                    onclick="copiarImagenSorteo('${RAFFLE_IMAGE}')"
                    class="w-full bg-blue-500 hover:bg-blue-400 text-white font-bold py-3 px-4 rounded-lg transition flex items-center justify-center gap-2">
                    🖼️ Copiar Imagen del Sorteo
                </button>
            </div>`;

        document.getElementById('modal-loading').classList.add('hidden');
        document.getElementById('modal-contenido').classList.remove('hidden');

    } catch (error) {
        document.getElementById('modal-loading').innerHTML = `
            <div class="text-4xl mb-3">❌</div>
            <p class="text-red-400">Error: ${error.message}</p>
            <button onclick="cerrarModal()" class="mt-4 bg-red-500 text-white px-4 py-2 rounded">Cerrar</button>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
    }
}

function generarMensajes(data) {
    const {
        raffle_name = 'Sorteo',
        price = 0,
        titular_name = 'Junior Enciso',
        alias = '7130138',
        prizes = [],
        numbers = []
    } = data;

    const precioFormato = new Intl.NumberFormat('es-PY', {
        useGrouping: true, minimumFractionDigits: 0
    }).format(price).replace(/\s/g, '.');

    const totalNumeros      = numbers.length;
    const numerosAsignados  = numbers.filter(n => n.customer_name && n.customer_name.trim() !== '').length;
    const numerosLibres     = totalNumeros - numerosAsignados;
    const porcentajeVendido = totalNumeros > 0 ? Math.round((numerosAsignados / totalNumeros) * 100) : 0;

    const medallas = ['🥇','🥈','🥉','🎁','🎀','🌟','💫','✨','🎯','🎪'];

    let listaPremios = '🏆 *PREMIOS:*\n';
    if (prizes.length > 0) {
        prizes.forEach((p, i) => {
            listaPremios += `${medallas[i] || '🎁'} *${i+1}° Premio:* ${p.name || 'Premio'}`;
            if (p.description) listaPremios += ` ${p.description}`;
            listaPremios += '\n';
        });
    } else {
        listaPremios += '🎁 *Premios sorpresa!*\n';
    }

    let listaNumeros = '';
    numbers.forEach(num => {
        const nombre = num.customer_name || '';
        if (num.status === 'sold' || num.paid) {
            listaNumeros += `${num.number} - ${nombre} 💵\n`;
        } else if (nombre) {
            listaNumeros += `${num.number} - ${nombre}\n`;
        } else {
            listaNumeros += `${num.number}\n`;
        }
    });

    const disponibles = numbers.filter(n => n.status === 'free');
    let numerosDisponiblesLista = '';
    disponibles.forEach((num, idx) => {
        numerosDisponiblesLista += num.number;
        if ((idx + 1) % 10 === 0 || idx === disponibles.length - 1) {
            numerosDisponiblesLista += '\n';
        } else {
            numerosDisponiblesLista += ', ';
        }
    });

    let listaGanadores = '';
    if (prizes.length > 0) {
        prizes.forEach((p, i) => {
            const ganador = p.winner_name || 'Por sortear';
            const numero  = p.winner_number ? `Nº ${p.winner_number}` : '---';
            listaGanadores += `${medallas[i] || '🎁'} *${i+1}° Premio:* ${p.name || p.description || 'Premio'}\n`;
            listaGanadores += `   👤 ${ganador} — ${numero}\n`;
        });
    } else {
        listaGanadores = '🎁 Sin premios registrados\n';
    }

    let mensajeUrgencia = '';
    if (numerosLibres === 0) {
        mensajeUrgencia =
`🔥🔥🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 *¡SE AGOTÓ!* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya no tiene números disponibles

¡Gracias a todos los que participaron! 🙏
🍀 ¡Hasta el próximo sorteo!`;

    } else if (numerosLibres <= 5) {
        mensajeUrgencia =
`🔥 *¡ÚLTIMOS ${numerosLibres} NÚMEROS!* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *NÚMEROS DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💸 Transferí y envianos el comprobante
✅ ¡Y ya estás dentro!
🍀 ¡No te pierdas esta oportunidad!`;

    } else if (porcentajeVendido >= 70) {
        mensajeUrgencia =
`⚠️ *¡SE ACERCA EL FINAL!* ⚠️
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *NÚMEROS DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ¡Todavía hay tiempo, pero poco!
🍀 ¡Buena suerte!`;

    } else if (porcentajeVendido >= 40) {
        mensajeUrgencia =
`⚡ *¡SE VENDE RÁPIDO!* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *NÚMEROS DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ¡Anotate antes que se agoten!
🍀 ¡Buena suerte!`;

    } else {
        mensajeUrgencia =
`🎉 *¡TODAVÍA HAY NÚMEROS!* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *NÚMEROS DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍀 ¡Elegí tu número favorito!`;
    }

    return {
        mensaje_completo:
`🎰✨ *¡SORTEO EN CURSO!* ✨🎰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 *Precio:* Gs. ${precioFormato}
💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 *Estado:* ${porcentajeVendido}% vendido (${numerosAsignados}/${totalNumeros})
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *LISTA DE NÚMEROS:*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaNumeros}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💵 = Pagado confirmado
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 *¿QUERÉS PARTICIPAR?*
👉 Elegí tu número
💸 Realizá tu transferencia
📩 Envianos el comprobante
✅ ¡Y listo!
🍀 *¡Buena suerte a todos!* 🍀`,

        urgencia: mensajeUrgencia,

        recordatorio_pago:
`💸 *RECORDATORIO DE PAGO* 💸
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Hola! 👋

Aún no confirmamos tu pago para el
🎟️ *${raffle_name}*

💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}

📌 *Pasos:*
1️⃣ Transferí Gs. ${precioFormato}
2️⃣ Compartí el comprobante aquí
3️⃣ ¡Listo! Ya estás confirmado ✅

😊 Si ya transferiste, ignorá este mensaje

¡Gracias! 🙌`,

        invitacion:
`🎉 *¡TE INVITAMOS A PARTICIPAR!* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
💰 Solo Gs. ${precioFormato} por número

📲 *¿Cómo participar?*
1️⃣ Elegí tu número favorito
2️⃣ Realizá tu transferencia
3️⃣ Envianos el comprobante

🏆 ¡Todos tenemos chance de ganar!
🍀 *¡Buena suerte!* 🍀`,

        flash:
`⚡ *FLASH* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎰 *${raffle_name}*

📊 ${porcentajeVendido}% vendido
💰 Gs. ${precioFormato} por número
🎁 ¡Premios increíbles!
🚀 ¡Participá ahora!

💳 Alias: *${alias}*

Compartí con quien quieras 📢`,

        ultima_oportunidad:
`🚨 *¡ÚLTIMA OPORTUNIDAD!* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

📍 *Quedan: ${numerosLibres} números*
⏰ *¡El tiempo se acaba!*

Los que no reserven ahora
se quedan sin participar ❌

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👇 *¡RESERVÁ AHORA!* 👇`,

        anuncio_ganadores:
`🏆✨ *¡TENEMOS GANADORES!* ✨🏆
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎉 *RESULTADOS OFICIALES:*

${listaGanadores}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎊 *¡Felicitaciones a los ganadores!*
📞 Serán contactados a la brevedad

🙏 Gracias a todos los participantes
🍀 *¡Hasta el próximo sorteo!*`,

        agradecimiento:
`🙏 *¡GRACIAS A TODOS!* 🙏
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Queremos agradecer a cada uno de ustedes
por su confianza y participación ❤️

🌟 Sin ustedes esto no sería posible
🤝 Cada número es una muestra
   de su apoyo incondicional

💪 Seguimos trabajando para traerles
   más y mejores sorteos

🔔 *¡Estén atentos al próximo sorteo!*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍀 *¡Hasta pronto y buena suerte!* 🍀`,

        compartir_grupo:
`🔗 *¡UNITE A NUESTRO GRUPO!* 🔗
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎰 *${raffle_name}*

¿Querés participar en nuestros sorteos?
¡Unite al grupo y enterate de todo! 👇

📲 *GRUPO OFICIAL:*
https://chat.whatsapp.com/JTTNQCrB8cjDKMukLUTY9G?mode=gi_t

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Sorteos en vivo
✅ Resultados al instante
✅ Premios increíbles
✅ ¡100% confiable!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Compartí con tus amigos 📢
🍀 *¡Buena suerte a todos!* 🍀`
    };
}

async function copiarMensaje(key, btnEl) {
    const texto = document.getElementById('msg-' + key)?.innerText || '';
    if (!texto) { alert('No hay texto para copiar'); return; }
    try {
        await navigator.clipboard.writeText(texto);
        const original = btnEl.innerHTML;
        btnEl.innerHTML = '✅';
        setTimeout(() => { btnEl.innerHTML = original; }, 800);
    } catch (e) {
        alert('Error al copiar: ' + e.message);
    }
}

function escapeHtml(str) {
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>

@endsection