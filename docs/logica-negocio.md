# Lógica de negocio — Sorteo PY

> Actualizado: 2026-11-02 (fase 1: endurecimiento de la reserva).

## Estados

**Sorteo (`raffles.status`)**: `draft` → `active` → `finished`.
Solo se puede reservar en sorteos `active`. Pasa a `finished` cuando se sortea el último premio (o el único, en el sistema legacy).

**Número (`raffle_numbers.status`)**
```
free ──reservar──▶ reserved ──confirmarPago──▶ sold
  ▲                   │
  └──liberar──────────┘   (manual siempre; automático solo si está activado)
```
- En un sorteo gratis (`price = 0`) la reserva pasa directo a `sold` con `paid = true` y sin `expires_at`.
- `paid` repite lo que ya indica `status` (`sold` equivale a pagado). Se mantiene por compatibilidad.

## Reserva (`POST /reservar`)
1. Validación: `raffle_id` existente, `numbers` de 1 a **10** enteros (los repetidos se ignoran) y `name` obligatorio.
2. Throttle: 10 pedidos por minuto por IP. Después responde 429 y el front muestra "Demasiados intentos".
3. Dentro de una transacción:
   - Si el sorteo no está `active` → 400 "Este sorteo no está disponible para reservas".
   - Bloquea **todos** los números pedidos en una sola consulta `lockForUpdate` ordenada por `id`. Como todas las reservas toman los locks en el mismo orden, dos pedidos simultáneos con números en común no se bloquean entre sí: uno espera al otro.
   - Si falta algún número → 400 "El número N no existe". Si alguno no está `free` → 400 "El número N ya fue reservado o vendido". **No se reserva nada del pedido** (todo o nada).
   - Marca los números: `status = reserved`, `customer_name`, `reserved_at = now()`, `expires_at = now() + SORTEOS_RESERVA_MINUTOS` (15 por defecto).

## Vencimiento y liberación
- **Toda reserva nueva** guarda `expires_at`.
- **Liberación automática: DESACTIVADA** (`SORTEOS_LIBERACION_AUTOMATICA=false`). Motivo: hasta que exista la subida de comprobante (fase 2), el comprador no tiene forma de frenar el reloj. Liberar a los 15 minutos dejaría sin número a alguien que ya transfirió y todavía no fue confirmado. Se activa junto con la fase 2, cuando el comprobante pase el número a "en verificación".
- `reservas:liberar` (con la opción activada, o con `--force`) libera solo números que cumplan todo esto: `status = reserved`, `paid = false`, `expires_at` no nulo y vencido, y sorteo `active`. Revalida cada fila con el lock tomado, por si el pago se confirmó mientras corría.
- Las reservas anteriores al 2026-11-02 tienen `expires_at = null` y **nunca** se liberan solas.
- **Liberación manual:** botón "Liberar" en el dashboard (`POST /admin/liberar/{id}`), con confirmación. Solo para números `reserved` sin pagar de un sorteo `active`.
- Al liberar (por cualquier vía), `RaffleNumber::liberar()` primero deja en el log de Laravel el número, el sorteo, el nombre, `reserved_at`, `expires_at` y el motivo (`manual` o `vencida`). Después deja `status = free` y vacía `customer_name`, `reserved_at`, `expires_at` y `paid`.

## Confirmación de pago
`POST /admin/confirmar/{id}`: bloquea la fila, verifica que siga `reserved` y la pasa a `sold` con `paid = true` y `expires_at = null`. Si con eso quedan todos vendidos, redirige a la ruleta. No crea registros en `payments`.

## Sorteo
- Solo se puede sortear con **todos** los números en `sold`.
- **Múltiples premios** (`raffle_prizes`): se sortea premio por premio (`prize_order`) con lock del sorteo y del premio, y si se repite la llamada devuelve el resultado guardado. Un número ganador no vuelve a ganar, y un mismo cliente (identificado por `customer_name`) gana como máximo 2 premios.
- **Sorteo ponderado** (`weighted_draw_enabled`, `win_weights`): el peso de cada número baja según cuántos premios ya ganó su dueño (default `1.0 / 0.5 / 0.25 / 0`).
- **Legacy (1 premio)**: guarda `winner_number` y `winner_name` en `raffles`. ⚠️ Esta ruta no tiene lock.
- Al terminar todos los premios: `real_profit_gs = vendidos × precio − Σ costo de premios`, sorteo `finished` y webhook al ERP.

## Promos
- `early_numbers`: entre los primeros `promo_limit` números reservados (por `reserved_at`), elige al azar `promo_winner_count` que estén `sold`. Requiere que el sorteo principal haya terminado.
- `most_numbers`: gana quien más números compró (agrupado por `customer_name`); el empate se rompe por la compra más antigua. Se puede ejecutar antes de terminar el sorteo.
- El resultado se guarda en `raffle_promo_results`, y si se vuelve a llamar devuelve lo guardado. ⚠️ No tiene lock ni restricción única.

## Descuentos
`discount_active` + `discount_pct` en el sorteo; el admin los activa y desactiva desde el dashboard.

## Pendientes conocidos (no resueltos en la fase 1)
- Sorteo legacy y promo sin lock (un doble clic puede dar resultados duplicados o pisados).
- Webhook al ERP dentro de la transacción del sorteo.
- Clientes identificados por el nombre escrito a mano (fase 2: teléfono normalizado).
- `raffle_numbers.number` es `varchar`. Los sorteos 1, 2 y 3 tienen números guardados como "01"–"09"; los demás, sin ceros a la izquierda.
- Sorteos `finished` con números todavía `free` o `reserved` (37 y 5 al 2026-11-02).
- Azar con `mt_rand` / `array_rand` (no criptográfico).
