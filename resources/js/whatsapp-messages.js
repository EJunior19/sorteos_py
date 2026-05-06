window.generarMensajes = function generarMensajes(data) {
    const {
        raffle_name = 'Sorteo',
        price = 0,
        titular_name = 'Junior Enciso',
        alias = '7130138',
        prizes = [],
        numbers = [],
        discount_active = false,
        discount_pct = 0,
        promo_enabled = false,
        promo_type = null,
        promo_prize_text = null,
    } = data;

    const fmt = v => new Intl.NumberFormat('es-PY', { useGrouping: true, minimumFractionDigits: 0 }).format(v).replace(/\s/g, '.');
    const precioFormato  = fmt(price);
    const precioPromo    = discount_active ? fmt(Math.round(price * (1 - discount_pct / 100))) : null;
    const ahorro         = discount_active ? fmt(price - Math.round(price * (1 - discount_pct / 100))) : null;

    const totalNumeros      = numbers.length;
    const numerosVendidos   = numbers.filter(n => n.status === 'sold' || n.status === 'reserved').length;
    const numerosLibres     = numbers.filter(n => n.status === 'free').length;
    const porcentajeVendido = totalNumeros > 0 ? Math.round((numerosVendidos / totalNumeros) * 100) : 0;

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

    if (promo_enabled && promo_prize_text) {
        if (promo_type === 'most_numbers') {
            listaPremios +=
`\n🎁 *Promo especial incluida*
También participás por un premio extra:
${promo_prize_text}
🏆 Gana esta promo la persona que compre la mayor cantidad de números del sorteo.
🔥 Cuantos más números reservás, más cerca estás de llevarte el premio principal y la promo especial.\n`;
        } else {
            listaPremios +=
`\n🎁 *Promo especial incluida*
También participás por un premio extra:
${promo_prize_text}
🏆 Los primeros en reservar y confirmar pago participan por esta promo especial.\n`;
        }
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

    let mensajeUrgencia = [];
    if (numerosLibres === 0) {
        mensajeUrgencia = [
`🔥🔥🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 *¡SE AGOTÓ!* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya no tiene números disponibles

¡Gracias a todos los que participaron! 🙏
🍀 ¡Hasta el próximo sorteo!`,

`🎊 *¡SOLD OUT TOTAL!* 🎊
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está LLENO 🙌

¡Todos los números tienen dueño!
💪 ¡Qué grupo más comprometido!

🔔 Mantenete atento para el próximo sorteo
🍀 ¡Buena suerte a todos!`,

`✅ *CUPO COMPLETO* ✅
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

Ya se reservaron todos los números 🎉
Gracias por sumarse tan rápido 🙌

🏆 Ahora solo queda esperar el sorteo
🍀 *¡Muchísima suerte a todos!*`,

`🚀 *LISTO, SE COMPLETÓ* 🚀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* llegó al 100%.

No quedan números disponibles.
Gracias por la confianza y por moverse rápido 🙌

🏆 Ahora viene la parte más esperada
🍀 *¡Éxitos a todos!*`,

`🎯 *OBJETIVO CUMPLIDO* 🎯
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Todos los números de *${raffle_name}* ya tienen participante.

Gracias por estar atentos y sumarse.
El sorteo queda cerrado para nuevas reservas ✅

🔔 Pendientes al resultado
🍀 *¡Que gane la suerte!*`,

`📣 *YA NO HAY LUGARES* 📣
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* se completó.

El cupo está cerrado y todos los números fueron tomados.
Gracias por la tremenda respuesta 🔥

🏆 Próximo paso: sorteo
🍀 *Mucha suerte!*`,

`🔥 *SE LLENÓ RAPIDÍSIMO* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya no tiene números libres.

Gracias a todos los que reservaron y confirmaron 🙌

📲 Atentos al grupo
🏆 *Se viene el momento del ganador!*`,

`🏁 *CIERRE TOTAL* 🏁
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo *${raffle_name}* queda completo.

No se aceptan más reservas para este sorteo.
Gracias por participar y acompañar 🙏

🍀 *Ahora a esperar el resultado!*`,

`🎊 *GRACIAS, GRUPO* 🎊
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está completo.

Todos los números ya fueron reservados.
La respuesta fue excelente 🔥

🏆 Se viene el sorteo
🍀 *Suerte para todos!*`,

`✅ *PARTICIPANTES COMPLETOS* ✅
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya tenemos todos los lugares ocupados en:
🎟️ *${raffle_name}*

Gracias por sumarse a tiempo.
Ahora solo queda esperar el gran momento 🏆

🍀 *¡A cruzar los dedos!*`,
        ];

    } else if (numerosLibres <= 5) {
        mensajeUrgencia = [
`🔥 *¡ÚLTIMOS ${numerosLibres} NÚMEROS!* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💸 Transferí y mandá el comprobante
✅ ¡Y ya estás adentro!
🍀 ¡No te pierdas esta oportunidad!`,

`⏰ *¡AHORA O NUNCA!* ⏰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
😱 Solo quedan *${numerosLibres}* número(s)!
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El que deja para mañana, se queda sin nada 😤
🏆 *¡RESERVÁ AHORA MISMO!*`,

`💥 *¡CORRAN QUE SE VAN!* 💥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Quedan *${numerosLibres}* número(s) sin dueño 👀
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 Avisá a tus amigos AHORA MISMO
👉 Primero en llegar, primero en participar!`,

`🏃 *¡NO TE QUEDES AFUERA!* 🏃
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️ Solo *${numerosLibres}* número(s) sin reservar!
${numerosDisponiblesLista}
Transferí Gs. ${precioFormato} — Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍀 *¡Tu número de la suerte te está esperando!* 🍀`,
        ];

    } else if (porcentajeVendido >= 70) {
        mensajeUrgencia = [
`⚠️ *¡SE ACERCA EL FINAL!* ⚠️
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Ya se vendió el *${porcentajeVendido}%*!
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ¡Todavía hay tiempo, pero poco!
🍀 ¡Buena suerte!`,

`🔥 *¡MOMENTO CRÍTICO!* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${porcentajeVendido}% vendido* y subiendo 📈
🎫 *LO QUE QUEDA:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚡ ¡Cada minuto que pasa hay menos números!
🏆 *¡ANOTATE YA!*`,

`📢 *¡ÚLTIMA LLAMADA!* 📢
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Solo quedan *${numerosLibres}* números disponibles 😬
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚨 No esperes más, el cupo se llena!
🍀 ¡Participá antes que sea tarde!`,

`⏳ *¡EL RELOJ CORRE!* ⏳
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¡Ya casi listo! *${porcentajeVendido}%* completado 🎯
🎫 *QUEDAN:*
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️ ¡Los que no se apuren, se pierden el sorteo!
🍀 *¡Animate ahora!*`,
        ];

    } else if (porcentajeVendido >= 40) {
        mensajeUrgencia = [
`⚡ *¡SE VENDE RÁPIDO!* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 *${porcentajeVendido}% vendido* — ¡vamos bien!
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ¡Anotate antes que se agoten!
🍀 ¡Buena suerte!`,

`🚀 *¡ESTÁN VOLANDO LOS NÚMEROS!* 🚀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya vendimos *${numerosVendidos}* de *${totalNumeros}* 🔥
🎫 *LIBRES TODAVÍA:*
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¡Avisá a quien falta! 📣
👉 *¡Que no se quede nadie afuera!*`,

`👀 *¡MIRÁ CÓMO VUELAN!* 👀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${porcentajeVendido}%* del sorteo ya reservado 😮
Todavía podés entrar:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 ¡Elegí el tuyo y transferí ya!
🍀 *¡La suerte está de tu lado!*`,

`💨 *¡A ESTE RITMO NO DURAN!* 💨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📈 Ya somos *${numerosVendidos}* participantes!
Números disponibles:
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🏃 ¡No te quedes mirando!
🏆 *¡Participá y podés ganar!*`,
        ];

    } else {
        mensajeUrgencia = [
`🎉 *¡TODAVÍA HAY NÚMEROS!* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 *DISPONIBLES:*
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👉 ¡Elegí tu número favorito y participá!
🍀 *¡Buena suerte a todos!*`,

`🌟 *¡ABIERTA LA INSCRIPCIÓN!* 🌟
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
¡Los números están disponibles!
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔥 ¡Empezá el año ganando!
🍀 *¡El tuyo te está esperando!*`,

`🎊 *¡UNITE AL GRUPO GANADOR!* 🎊
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📣 ¡El sorteo está en marcha!
Quedan *${numerosLibres}* números disponibles:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¡Cada número es una oportunidad de ganar! 🏆
💪 *¡Animense, que todavía hay lugar!*`,

`💫 *¡TU SUERTE TE ESPERA HOY!* 💫
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No esperes más, participá en *${raffle_name}* 🎰
Números libres:
${numerosDisponiblesLista}
💳 Alias: *${alias}* — Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🌈 ¿Y si hoy es tu día de suerte?
🍀 *¡No lo sabrás si no participás!*`,
        ];
    }

    if (numerosLibres > 0) {
        const marketingUrgenciaExtra = [
`🎯 *EL NÚMERO QUE ELEGÍS HOY PUEDE CAMBIAR TODO* 🎯
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

Quedan *${numerosLibres}* números disponibles.
No es solo participar: es elegir tu oportunidad antes que otro la tome.

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📩 Mandá tu número y comprobante
🍀 *Tu suerte no espera para siempre*`,

`🔥 *HAY MOMENTOS QUE NO SE REPITEN* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* sigue abierto, pero cada vez con menos lugares.

🎫 Disponibles:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚡ El que decide rápido, elige mejor
🏆 *Entrá antes de mirar desde afuera*`,

`👀 *ESTE ES EL MENSAJE QUE DESPUÉS DICEN "NO VI"* 👀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Todavía podés entrar a:
🎟️ *${raffle_name}*

Quedan *${numerosLibres}* números y el grupo se está moviendo.
💳 Alias: *${alias}*
💰 Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📲 Respondé con tu número
🍀 *Después no digas que no avisamos*`,

`🚀 *NO COMPRES DESPUÉS, ELEGÍ AHORA* 🚀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

Los números buenos no esperan.
Disponibles:
${numerosDisponiblesLista}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
🏆 *Tu oportunidad está en la lista*`,

`📣 *ATENCIÓN: EL SORTEO ESTÁ TOMANDO RITMO* 📣
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya vamos *${numerosVendidos}/${totalNumeros}* números reservados.

🎫 Quedan:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔥 Entrá antes de que se acelere más
🍀 *Hoy puede ser tu turno*`,

`💥 *UN NÚMERO, UNA CHANCE, UNA DECISIÓN* 💥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

No hace falta pensarlo tanto:
1️⃣ Elegí número
2️⃣ Transferí Gs. ${precioFormato}
3️⃣ Mandá comprobante

💳 Alias: *${alias}*
🏆 *Y ya estás compitiendo*`,

`⏳ *LOS QUE ESPERAN, ELIGEN LO QUE SOBRA* ⏳
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Quedan *${numerosLibres}* números para *${raffle_name}*.

🎫 Disponibles:
${numerosDisponiblesLista}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
🔥 *Reservá antes de que otro elija por vos*`,

`🏆 *EL PREMIO YA ESTÁ, FALTA TU NÚMERO* 🏆
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
Quedan *${numerosLibres}* chances disponibles.

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
🍀 *Entrá y jugá tu posibilidad*`,

`⚡ *MENSAJE RÁPIDO PARA LOS QUE DECIDEN RÁPIDO* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

Disponibles ahora:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📩 Elegí, transferí y quedás adentro
🔥 *Simple y directo*`,

`🎰 *NO ES SUERTE SI NI SIQUIERA PARTICIPÁS* 🎰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Todavía quedan números en:
🎟️ *${raffle_name}*

🎫 Libres:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato} — Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🍀 *La suerte empieza cuando elegís tu número*`,
        ];

        mensajeUrgencia = [...mensajeUrgencia, ...marketingUrgenciaExtra].slice(0, 10);
    }

    const urgenciaNivel1 = [
`🎟️ *TODAVÍA HAY BUENOS NÚMEROS* 🎟️
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está abierto y todavía hay varias opciones para elegir.

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí tranquilo, pero no dejes pasar tu número favorito 🍀`,

`🌟 *ARRANCAMOS CON TODO* 🌟
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo *${raffle_name}* ya está disponible.

Todavía hay buenos números libres y todos tienen chance.
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Participá y sumate desde el inicio 🙌`,

`🎉 *BUEN MOMENTO PARA ENTRAR* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

Hay números disponibles y podés elegir con calma.
${listaPremios}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tu número puede estar esperando ahí 👀`,

`📌 *ENTRÁ CON TIEMPO* 📌
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Todavía hay buenos números para *${raffle_name}*.

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí, transferí y quedás participando ✅`,

`🍀 *TU CHANCE ESTÁ DISPONIBLE* 🍀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo recién está tomando forma y hay lugar para sumarte.

🎟️ *${raffle_name}*
📊 ${porcentajeVendido}% vendido
💰 Gs. ${precioFormato}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Participá desde ahora y elegí mejor 🙌`,

`🎁 *HAY PARA ELEGIR TODAVÍA* 🎁
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* tiene números libres y buenos premios en juego.

🎫 Números:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Entrá con calma, pero entrá hoy ✅`,

`🙌 *SUMATE DESDE EL ARRANQUE* 🙌
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Todavía estamos en buena etapa para elegir número en *${raffle_name}*.

${listaPremios}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Participá y asegurá tu lugar 🍀`,
    ];

    const urgenciaNivel2 = [
`🚀 *EL SORTEO YA ESTÁ AVANZANDO* 🚀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya vamos *${porcentajeVendido}% vendido* en *${raffle_name}*.

Todavía hay lugar, pero el movimiento ya empezó.
🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} — Alias: *${alias}*`,

`📣 *NO TE QUEDES ATRÁS* 📣
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está tomando ritmo.

Vendidos: *${numerosVendidos}/${totalNumeros}*
Libres: *${numerosLibres}*
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí el tuyo antes de que se achique la lista 🔥`,

`⚡ *YA HAY MOVIMIENTO EN EL GRUPO* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo está avanzando y cada número reservado cuenta.

🎟️ *${raffle_name}*
📊 *${porcentajeVendido}% vendido*
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Entrá ahora y asegurá tu participación 🍀`,

`🔥 *SE ESTÁ MOVIENDO LINDO* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya no está quieto.

Vendidos: *${numerosVendidos}/${totalNumeros}*
Quedan: *${numerosLibres}*
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No esperes a que se achique más la lista 👀`,

`🎯 *EL GRUPO YA ESTÁ ENTRANDO* 🎯
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo va avanzando y todavía podés elegir.

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
📩 Mandá comprobante y quedás adentro`,

`📈 *YA PASAMOS LA PRIMERA PARTE* 📈
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* sigue sumando participantes.

📊 Avance: *${porcentajeVendido}%*
🎫 Libres: *${numerosLibres}*
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No te quedes mirando desde afuera 🔥`,

`🟡 *BUEN MOMENTO PARA DECIDIR* 🟡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo ya se está moviendo, pero todavía hay oportunidad.

🎟️ *${raffle_name}*
🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí tu número y participá hoy 🙌`,
    ];

    const urgenciaNivel3 = [
`🔥 *QUEDAN MENOS NÚMEROS* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya pasó el *${porcentajeVendido}% vendido*.

🎫 Lo que queda:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Este es el momento de entrar sin pensarlo tanto 👀`,

`⏳ *EL SORTEO SE ESTÁ CERRANDO* ⏳
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya hay *${numerosVendidos}* números tomados.
Quedan *${numerosLibres}* disponibles.

🎟️ *${raffle_name}*
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No esperes a que queden solo los últimos 🙌`,

`🎯 *CADA VEZ MÁS CERCA DEL CIERRE* 🎯
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

📊 Avance: *${porcentajeVendido}%*
🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí, transferí y mandá comprobante ✅`,

`🏁 *RECTA FINAL* 🏁
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya estamos en *${porcentajeVendido}% vendido*.

Si querés participar en *${raffle_name}*, este es el momento.
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Últimos números, sin vueltas 📲`,

`⚠️ *CASI COMPLETO* ⚠️
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está en cierre.

🎫 Disponibles:
${numerosLibres > 0 ? (numerosDisponiblesLista || 'Consultá por acá') : 'Ya no quedan números disponibles'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${numerosLibres > 0 ? `Transferí Gs. ${precioFormato} al alias *${alias}* y mandá comprobante ✅` : 'Gracias a todos los que participaron. Atentos al sorteo 🏆'}`,

`🚨 *SE CIERRA APENAS SE COMPLETE* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está en la parte final.

Quedan *${numerosLibres}* números.
${numerosLibres > 0 ? (numerosDisponiblesLista || '') : 'Cupo completo'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${numerosLibres > 0 ? `Gs. ${precioFormato} — Alias: *${alias}*` : 'Ahora quedamos atentos al sorteo 🍀'}`,

`🏆 *ATENTOS AL CIERRE* 🏆
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ya estamos muy cerca de completar *${raffle_name}*.

📊 Avance: *${porcentajeVendido}%*
🎫 Libres: *${numerosLibres}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${numerosLibres > 0 ? 'Últimos lugares para participar 📲' : 'Números completos. Se viene el sorteo 🙌'}`,
    ];

    if (numerosLibres === 0 || porcentajeVendido >= 90) {
        mensajeUrgencia = urgenciaNivel3;
    } else if (porcentajeVendido >= 40) {
        mensajeUrgencia = urgenciaNivel2;
    } else {
        mensajeUrgencia = urgenciaNivel1;
    }

    const bloqueNumerosDisponibles = numerosLibres > 0
        ? `🎫 *NÚMEROS DISPONIBLES:*\n${numerosDisponiblesLista || 'Consultá números disponibles por acá'}`
        : '🎫 *NÚMEROS DISPONIBLES:*\nYa no quedan números disponibles';

    mensajeUrgencia = mensajeUrgencia.map(message => {
        if (
            message.includes('NÚMEROS DISPONIBLES') ||
            message.includes('Disponibles:') ||
            message.includes('Números:') ||
            message.includes('Lo que queda:') ||
            message.includes('Cupo completo') ||
            message.includes('Ya no quedan números disponibles')
        ) {
            return message;
        }

        return `${message}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n${bloqueNumerosDisponibles}`;
    });

    const mensajesGenerados = {
        mensaje_completo: [
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
📊 *Estado:* ${porcentajeVendido}% vendido (${numerosVendidos}/${totalNumeros})
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
🍀 *¡Buena suerte a todos!* 🍀
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 *Los premios se sortean de menor a mayor*`,

`📋 *INFO COMPLETA DEL SORTEO* 📋
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 *Valor por número:* Gs. ${precioFormato}
💳 *Titular:* ${titular_name}
🔑 *Alias:* ${alias}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 *Avance:* ${numerosVendidos}/${totalNumeros} vendidos (${porcentajeVendido}%)
🎫 *NÚMEROS:*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${listaNumeros}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💵 Pagado confirmado
✅ Elegí, transferí y mandá comprobante
🍀 *¡Vamos por ese premio!*`,

`🔥 *SORTEO ACTIVO - PARTICIPÁ YA* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} por número
💳 Titular: *${titular_name}*
🔑 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📈 ${porcentajeVendido}% vendido
🎫 Lista actual:
${listaNumeros}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📩 Mandá tu número y comprobante
🏆 *Los premios se sortean de menor a mayor*`,
        ],

        urgencia: mensajeUrgencia,

        promocion: (discount_active && numerosLibres > 0 ? [
`🎁 *¡OFERTA ESPECIAL — ${discount_pct}% OFF!* 🎁
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*
Solo quedan *${numerosLibres} números* y queremos cerrar con todos adentro! 🙌
💥 Normal: ~~Gs. ${precioFormato}~~ → *Gs. ${precioPromo}*
Ahorrás *Gs. ${ahorro}* por número! 💸
${listaPremios}💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📩 Transferí *Gs. ${precioPromo}* y mandá el comprobante
⏰ Válido hasta completar el cupo — *¡ya mismo!*`,

`🔥 *¡${discount_pct}% OFF — CERRAMOS HOY!* 🔥
*${raffle_name}*
${numerosLibres} números 👉 *Gs. ${precioPromo}* c/u *(antes Gs. ${precioFormato})*
${listaPremios}💳 Alias: *${alias}*
⚡ Elegí, transferí, confirmado ✅
🚨 *Solo ${numerosLibres} lugares — se va rápido!*`,

`😱 *¡NO LO VAS A CREER!* 😱
*${discount_pct}% de descuento* en *${raffle_name}*
🏷️ Gs. ${precioFormato} → *Gs. ${precioPromo}*
${listaPremios}💳 *${alias}* — ¡ya mismo!
🍀 *Últimos ${numerosLibres} números — animate!*`,

`💣 *¡BOMBA!* 💣
*${raffle_name}* — últimos *${numerosLibres}* números
💰 *Gs. ${precioPromo}* *(${discount_pct}% OFF)*
${listaPremios}Alias: *${alias}*
🚨 *Transferí ahora y ya sos del sorteo!* 🚨`,

`⚡ *ÚLTIMO MOMENTO* ⚡
${numerosLibres} números en *${raffle_name}*
🔥 *${discount_pct}% OFF = Gs. ${precioPromo}*
~~Normal: Gs. ${precioFormato}~~
Alias: *${alias}* 💳
👇 *¡Mandá el comprobante y listo!*`,
        ] : null),

        recordatorio_pago: [
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

`👋 *HOLA, TE RECORDAMOS TU RESERVA* 👋
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tenés pendiente la confirmación de pago para:
🎟️ *${raffle_name}*

💰 Importe: *Gs. ${precioFormato}*
💳 Titular: *${titular_name}*
🔑 Alias: *${alias}*

📩 Enviá el comprobante por este chat
✅ Apenas confirmamos, tu número queda asegurado

Si ya pagaste, podés ignorar este mensaje 🙌`,

`⏰ *PAGO PENDIENTE* ⏰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tu participación en *${raffle_name}* todavía no figura como pagada.

💸 Transferí *Gs. ${precioFormato}*
💳 Alias: *${alias}*
👤 Titular: *${titular_name}*

📲 Mandanos el comprobante y te confirmamos
🍀 *Queremos que estés dentro del sorteo!*`,
        ],

        invitacion: [
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

`🎯 *¡TU MOMENTO ES AHORA!* 🎯
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
¿Cuándo vas a animarte si no es hoy? 😏
👉 Elegí, transferí, y ganás!
🍀 *¡La suerte favorece a los valientes!*`,

`🤩 *¿TE ANIMÁS A GANAR?* 🤩
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* ya está en marcha 🚀

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💸 Solo Gs. ${precioFormato} y podés llevarte todo
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Imaginate ganando esto 🏆
¡Mandá tu número y entrá al juego!
🍀 *¡Buena vibra para todos!*`,

`🌟 *¡PROBÁ TU SUERTE HOY!* 🌟
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
No hay excusas para no participar 😄
🎟️ *${raffle_name}*

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} — 💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔥 ¡Avisá a tus amigos, que hay para todos!
🍀 *¡Hoy puede ser tu gran día!*`,
        ],

        flash: [
`⚡ *FLASH* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎰 *${raffle_name}*

📊 ${porcentajeVendido}% vendido — *${numerosLibres}* libres
💰 Gs. ${precioFormato} por número
🎁 ¡Premios increíbles!
🚀 ¡Participá ahora!

💳 Alias: *${alias}*

Compartí con quien quieras 📢`,

`💬 *ATENCIÓN GRUPO* 💬
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* — ${porcentajeVendido}% vendido 🔥
*${numerosLibres}* números todavía disponibles!

💰 Gs. ${precioFormato} — Alias: *${alias}*

¡Los que no se anotan hoy, se arrepienten mañana! 😤
🏆 *¡Sumate al grupo ganador!*`,

`🎰 *AL TOQUE* 🎰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*
📈 ${porcentajeVendido}% listo | *${numerosLibres}* quedan
💰 Gs. ${precioFormato} | Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 Rápido, elegí y transferí
¡Estamos esperando tu comprobante! 📩`,
        ],

        ultima_oportunidad: [
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

`🔥 *SE CIERRA EL SORTEO* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

Quedan apenas *${numerosLibres}* números disponibles.
Después ya no entran más participantes ⚠️

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

📩 Elegí tu número y mandá comprobante
🍀 *Este puede ser el tuyo!*`,

`⏳ *ÚLTIMO AVISO* ⏳
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}* está por completarse.

🎫 Disponibles:
${numerosDisponiblesLista}
💰 Gs. ${precioFormato}
🔑 Alias: *${alias}*

🚀 Si querés participar, este es el momento
🏆 *No lo dejes pasar!*`,
        ],

        anuncio_ganadores: [
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

`🎉 *RESULTADOS DEL SORTEO* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaGanadores}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🏆 Felicitaciones a los ganadores
🙌 Gracias a todos por participar
📲 Estén atentos al próximo sorteo`,

`📣 *GANADORES CONFIRMADOS* 📣
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

${listaGanadores}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎊 ¡Felicitaciones!
Gracias por confiar y participar 🙏
🍀 *Nos vemos en el próximo sorteo!*`,
        ],

        agradecimiento: [
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

`🙌 *GRACIAS POR PARTICIPAR* 🙌
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

Gracias por la confianza y por acompañar este sorteo.
Cada número reservado ayuda a que sigamos haciendo más premios 🎁

🔔 Pronto se vienen más oportunidades
🍀 *Gracias de corazón!*`,

`💛 *APRECIAMOS MUCHO SU APOYO* 💛
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

Gracias a todos los que participaron, compartieron y confiaron.
Fue un gusto tenerlos en este sorteo 🙏

🏆 Seguimos preparando más premios
📲 Estén atentos al grupo
🍀 *Hasta el próximo!*`,
        ],

        compartir_grupo: [
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
🍀 *¡Buena suerte a todos!* 🍀`,

`📲 *ENTRÁ AL GRUPO OFICIAL* 📲
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ahí avisamos sorteos, resultados y novedades.

🎟️ Sorteo actual: *${raffle_name}*

👉 https://chat.whatsapp.com/JTTNQCrB8cjDKMukLUTY9G?mode=gi_t

✅ Avisos rápidos
✅ Resultados al instante
✅ Nuevas oportunidades para ganar

Compartí el enlace con quien quiera participar 🍀`,

`🎉 *SUMATE A LA COMUNIDAD* 🎉
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Querés enterarte primero de los sorteos?
Unite al grupo oficial 👇

https://chat.whatsapp.com/JTTNQCrB8cjDKMukLUTY9G?mode=gi_t

🎰 Sorteos activos
🏆 Ganadores publicados
📣 Promos y avisos importantes

Nos vemos en el grupo 🙌`
        ]
    };

    const variantesExtra = {
        mensaje_completo: [
`🎰 *SORTEO DISPONIBLE* 🎰
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 Gs. ${precioFormato} por número
💳 Titular: *${titular_name}*
🔑 Alias: *${alias}*
📊 ${numerosVendidos}/${totalNumeros} vendidos (${porcentajeVendido}%)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎫 Números:
${listaNumeros}
🍀 Participá eligiendo tu número y enviando comprobante`,

`📌 *DATOS DEL SORTEO* 📌
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

${listaPremios}
💰 Precio: *Gs. ${precioFormato}*
💳 Alias: *${alias}*
👤 Titular: *${titular_name}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📈 Avance: *${porcentajeVendido}%*
🎫 Lista:
${listaNumeros}
✅ Mandá comprobante para confirmar`,
        ],
        recordatorio_pago: [
`📌 *TE FALTA CONFIRMAR EL PAGO* 📌
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tu reserva para *${raffle_name}* sigue pendiente.

💰 Monto: *Gs. ${precioFormato}*
💳 Alias: *${alias}*
👤 Titular: *${titular_name}*

📩 Enviá el comprobante y dejamos tu número confirmado ✅`,

`💳 *RECORDATORIO RÁPIDO* 💳
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Para quedar confirmado en *${raffle_name}*:

1️⃣ Transferí *Gs. ${precioFormato}*
2️⃣ Alias: *${alias}*
3️⃣ Mandá comprobante por acá

Si ya enviaste, ignorá este mensaje 🙌`,
        ],
        invitacion: [
`📣 *SUMATE AL SORTEO* 📣
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaPremios}
💰 Gs. ${precioFormato} por número
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí tu número y participá 🍀`,
        ],
        flash: [
`⚡ *AVISO RÁPIDO* ⚡
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

📊 ${porcentajeVendido}% vendido
🎫 Libres: *${numerosLibres}*
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Participá antes que se mueva más 🔥`,

`🔥 *FLASH DEL GRUPO* 🔥
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Todavía quedan números para *${raffle_name}*.

💰 Gs. ${precioFormato}
🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá por acá'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Elegí y confirmá con comprobante ✅`,
        ],
        ultima_oportunidad: [
`🚨 *ESTAMOS EN CIERRE* 🚨
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

Quedan *${numerosLibres}* números.
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Si querés entrar, este es el momento 📲`,

`⚠️ *ÚLTIMO EMPUJÓN* ⚠️
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo está por completarse.

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá por acá'}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Se cierra apenas se complete ✅`,
        ],
        anuncio_ganadores: [
`🎊 *RESULTADO PUBLICADO* 🎊
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎟️ *${raffle_name}*

${listaGanadores}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Felicitaciones a los ganadores y gracias a todos por participar 🙌`,

`🏆 *GANADORES DEL SORTEO* 🏆
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

${listaGanadores}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gracias por acompañar. Pronto se vienen más premios 🍀`,
        ],
        agradecimiento: [
`🙏 *GRACIAS POR LA CONFIANZA* 🙏
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
*${raffle_name}*

Gracias a cada persona que participó y compartió.
Seguimos preparando más sorteos y premios para el grupo 🙌`,

`🙌 *GRACIAS, GRUPO* 🙌
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
El sorteo *${raffle_name}* fue posible gracias a ustedes.

Gracias por participar, confiar y estar atentos.
Nos vemos en el próximo sorteo 🍀`,
        ],
        compartir_grupo: [
`🔗 *COMPARTÍ EL GRUPO* 🔗
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Entrá y enterate de sorteos, premios y resultados:

https://chat.whatsapp.com/JTTNQCrB8cjDKMukLUTY9G?mode=gi_t

Invitá a quien quiera participar 🙌`,

`📲 *GRUPO DE SORTEOS* 📲
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Unite para ver novedades, resultados y próximos premios:

https://chat.whatsapp.com/JTTNQCrB8cjDKMukLUTY9G?mode=gi_t

Te esperamos en el grupo 🍀`,
        ],
    };

    Object.keys(variantesExtra).forEach(key => {
        if (!mensajesGenerados[key]) return;
        const base = Array.isArray(mensajesGenerados[key]) ? mensajesGenerados[key] : [mensajesGenerados[key]];
        mensajesGenerados[key] = [...base, ...variantesExtra[key]].slice(0, 5);
    });

    return mensajesGenerados;
}
