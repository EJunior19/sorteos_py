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
`✅ *¡Se completó!*

🎟️ *${raffle_name}* ya no tiene números disponibles.

Gracias a todos por participar 🙌
Ahora a esperar el sorteo 🏆`,

`🔥 *¡Se llenó!*

Todos los números de *${raffle_name}* tienen dueño.

Gracias por la confianza 🙌
Atentos al resultado 📲`,

`🏁 *Cupo completo*

*${raffle_name}* cerró las reservas.

¡Tremenda respuesta, gracias a todos!
Se viene el sorteo 🏆 🍀`,

`🎊 *Sold out total*

*${raffle_name}* no tiene más lugares.

Gracias por participar y acompañar 🙌
Atentos al grupo para el resultado 📣`,

`🙌 *¡Gracias, grupo!*

*${raffle_name}* está completo.

Todos los números fueron reservados 🎟️
Ahora a cruzar los dedos 🍀`,

`📣 *Números agotados*

El sorteo *${raffle_name}* cerró.

Gracias a los que se movieron rápido 🔥
Publicamos el resultado muy pronto 🏆`,
        ];

    } else if (numerosLibres <= 5) {
        mensajeUrgencia = [
`🔥 *Últimos ${numerosLibres} números*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

📩 Elegí y mandá comprobante. Se sortea apenas se complete ✅`,

`⏰ *¡Ahora o nunca!*

Solo quedan *${numerosLibres}* número(s) disponibles.

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Primero en llegar, primero en participar 🎯`,

`🚨 *Se van los últimos*

*${numerosLibres}* números sin dueño en *${raffle_name}*:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

Avisá a tus amigos antes que se vayan 📲`,

`🏃 *No te quedes afuera*

Solo *${numerosLibres}* número(s) disponibles en *${raffle_name}*.

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

🍀 Tu número te está esperando — no lo dejes pasar`,
        ];

    } else if (porcentajeVendido >= 70) {
        mensajeUrgencia = [
`⚠️ *Se acerca el final*

Ya se vendió el *${porcentajeVendido}%* de *${raffle_name}*.

🎫 Disponibles:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Todavía hay tiempo, pero poco ⏳`,

`🔥 *Momento crítico*

*${porcentajeVendido}% vendido* y subiendo 📈

🎫 Lo que queda:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

Cada minuto hay menos números. Anotate ya 🏆`,

`📢 *Última llamada*

Solo quedan *${numerosLibres}* números en *${raffle_name}* 😬

${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

Participá antes que sea tarde 🍀`,

`⏳ *El reloj corre*

*${porcentajeVendido}%* completado y quedan pocos lugares.

🎫 Disponibles:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

Animate ahora 🙌`,
        ];

    } else if (porcentajeVendido >= 40) {
        mensajeUrgencia = [
`⚡ *Se vende rápido*

*${porcentajeVendido}% vendido* en *${raffle_name}* 📈

🎫 Disponibles:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Anotate antes que se agoten ✅`,

`🚀 *Están volando los números*

Ya vendimos *${numerosVendidos}* de *${totalNumeros}* 🔥

🎫 Libres todavía:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

¡Avisá a quien falta, que no se quede nadie afuera! 📣`,

`👀 *Mirá cómo vuelan*

*${porcentajeVendido}%* del sorteo ya reservado 😮

${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

Elegí el tuyo y transferí ya 🎯`,

`💨 *A este ritmo no duran*

Ya somos *${numerosVendidos}* participantes en *${raffle_name}*.

🎫 Disponibles:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

No te quedes mirando 🏃 Participá y podés ganar 🏆`,
        ];

    } else {
        mensajeUrgencia = [
`🎉 *Todavía hay números*

🎫 Disponibles:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Elegí tu número favorito y participá 🍀`,

`🌟 *Inscripción abierta*

🎟️ *${raffle_name}*

Los números están disponibles 👀
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

El tuyo te está esperando 🍀`,

`🎊 *Unite al grupo ganador*

El sorteo está en marcha.
Quedan *${numerosLibres}* números disponibles.
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

Cada número es una oportunidad 🏆`,

`💫 *Tu suerte te espera*

Participá en *${raffle_name}* 🎟️

🎫 Números libres:
${numerosDisponiblesLista}

💰 Gs. ${precioFormato} — Alias: *${alias}*

¿Y si hoy es tu día? No lo sabrás si no participás 🍀`,
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
`🎟️ *${raffle_name}*

Todavía hay buenos números disponibles 👀

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Elegí tranquilo y participá ✅`,

`🌟 *Arrancamos con todo*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Sumate desde el inicio 🙌`,

`🎉 *Abierto — todavía hay lugar*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

📩 Elegí y mandá comprobante para confirmar`,

`📌 *Entrá con tiempo*

🎟️ *${raffle_name}*

Hay números para elegir y podés hacerlo tranquilo 👌

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Elegí, transferí y quedás participando ✅`,

`🍀 *Tu chance está disponible*

🎟️ *${raffle_name}* — ${porcentajeVendido}% vendido

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Participá desde ahora y elegí bien 🙌`,

`🎁 *Hay para elegir todavía*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Entrá hoy antes que se mueva más 🔥`,

`🙌 *Sumate desde el arranque*

🎟️ *${raffle_name}*

${listaPremios}
💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Asegurá tu lugar hoy 🍀`,
    ];

    const urgenciaNivel2 = [
`⚡ *Ya vamos ${porcentajeVendido}% vendido*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Elegí antes de que se achique más la lista 🔥`,

`📣 *No te quedes atrás*

Vendidos: *${numerosVendidos}/${totalNumeros}* — Libres: *${numerosLibres}*

🎟️ *${raffle_name}*

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Entrá ahora y asegurá tu participación 🙌`,

`🔥 *Se está moviendo lindo*

*${raffle_name}* ya no está quieto.

Quedan *${numerosLibres}* números disponibles 👀

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

No esperes a que se achique más la lista 📲`,

`📈 *Ya pasamos la mitad*

*${raffle_name}* sigue sumando participantes.

📊 Avance: *${porcentajeVendido}%* — Libres: *${numerosLibres}*

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

No te quedes mirando desde afuera 🔥`,

`🎯 *El grupo ya está entrando*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

📩 Mandá comprobante y quedás adentro ✅`,

`🟡 *Buen momento para decidir*

El sorteo avanza y todavía hay oportunidad.

🎟️ *${raffle_name}*
📊 *${porcentajeVendido}% vendido*

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Elegí tu número hoy 🙌`,

`🚀 *Números volando*

Ya vamos *${numerosVendidos} de ${totalNumeros}* reservados.

🎟️ *${raffle_name}*

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

¡Avisá a quien falta — que no se quede nadie afuera! 📣`,
    ];

    const urgenciaNivel3 = [
`🔥 *Últimos ${numerosLibres} números*

🎟️ *${raffle_name}*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

📩 Elegí y mandá comprobante.
Se sortea apenas se complete ✅`,

`⏳ *El sorteo se está cerrando*

Ya hay *${numerosVendidos}* números tomados.
Quedan *${numerosLibres}* disponibles.

🎟️ *${raffle_name}*

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

No esperes a que queden solo los últimos 🙌`,

`🏁 *Recta final*

*${raffle_name}* va en *${porcentajeVendido}%* vendido.

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Este es el momento de entrar sin pensarlo 📲`,

`⚠️ *Casi completo*

🎟️ *${raffle_name}*

🎫 ${numerosLibres > 0 ? `Disponibles:\n${numerosDisponiblesLista || 'Consultá por acá'}` : 'Cupo completo'}

${numerosLibres > 0 ? `💰 Gs. ${precioFormato}\n💳 Alias: *${alias}*\n\nTransferí y mandá comprobante ✅` : 'Gracias a todos. Atentos al sorteo 🏆'}`,

`🚨 *Se cierra apenas se complete*

*${raffle_name}* está en la parte final.

Quedan *${numerosLibres}* números.
${numerosLibres > 0 ? (numerosDisponiblesLista || '') : 'Cupo completo'}

${numerosLibres > 0 ? `💰 Gs. ${precioFormato} — Alias: *${alias}*` : '🍀 Quedamos atentos al sorteo'}`,

`🎯 *Cada vez más cerca del cierre*

🎟️ *${raffle_name}*

📊 *${porcentajeVendido}% vendido*

🎫 Disponibles:
${numerosDisponiblesLista || 'Consultá números disponibles por acá'}

💰 Gs. ${precioFormato}
💳 Alias: *${alias}*

Elegí, transferí y mandá comprobante ✅`,

`🏆 *Atentos al cierre*

*${raffle_name}* está por completarse.

📊 *${porcentajeVendido}%* — Libres: *${numerosLibres}*

${numerosLibres > 0 ? `💰 Gs. ${precioFormato}\n💳 Alias: *${alias}*\n\nÚltimos lugares, no lo dejes pasar 📲` : 'Números completos. Se viene el sorteo 🙌'}`,
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
