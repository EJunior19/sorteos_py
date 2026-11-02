# Módulos — Sorteo PY

> Actualizado: 2026-11-02 (fase 1: endurecimiento de la reserva).
> Stack: Laravel 13 · PHP 8.4 · PostgreSQL · Blade + Tailwind (Vite).

## Rutas

### Públicas
| Método | Ruta | Controlador | Notas |
|---|---|---|---|
| GET | `/` | `RaffleController@index` | Sorteos `active` y `finished` + últimos ganadores |
| GET | `/winners` | `RaffleController@winners` | Últimos 10 sorteos terminados |
| GET | `/sorteo/{id}/play` | `RaffleController@play` | Solo sorteos `active` |
| GET | `/sorteo/{id}/numeros` | `RaffleController@numbers` | Grilla para elegir números (solo `active`) |
| GET | `/sorteo/{id}` | `RaffleController@show` | Resultados (solo `finished`) |
| POST | `/reservar` | `ReservationController@reservar` | JSON. **throttle:10,1** (10 pedidos por minuto por IP) |

### Admin
Login: `GET/POST /admin/login` (POST con `throttle:5,15`), `GET /admin/logout`.

Grupo `admin.auth`:
| Método | Ruta | Acción |
|---|---|---|
| GET | `/admin` | `dashboard` — último sorteo creado, grilla con Confirmar / Liberar |
| POST | `/admin/confirmar/{id}` | `confirmarPago` — `reserved` → `sold` |
| POST | `/admin/liberar/{id}` | `liberarReserva` — `reserved` → `free` (**nuevo**) |
| GET | `/admin/whatsapp/{id}` | `generarMensajeWhatsapp` — datos para armar mensajes en el front |
| POST/DELETE | `/admin/descuento/{id}` | Activar / desactivar descuento (`discount_active`, `discount_pct`) |
| POST | `/admin/costos/{id}` | Guardar costo de cada premio |

Subgrupo `admin.only`: crear sorteo (`/admin/create`), borradores del análisis automático (`/admin/borradores/...`), ruleta (`/admin/roulette/{id}`), `POST /admin/sortear/{id}`, `POST /admin/sortear-promo/{id}`.

### Roles (estado real)
- Hay **un solo rol**: el login compara la contraseña con `ADMIN_PASSWORD_HASH` y guarda `session('role') = 'admin'`.
- `AdminAuth` y `AdminOnly` exigen los dos `role === 'admin'`. Los comentarios "admin y colaborador" de `routes/web.php` **no reflejan el código**: hoy no existe el rol colaborador.

## Controladores
- **ReservationController** — reserva pública de números (ver `logica-negocio.md`). Constante `MAX_NUMEROS_POR_PEDIDO = 10`.
- **RaffleController** — vistas públicas.
- **AdminController** — dashboard, confirmar/liberar, creación de sorteos, sorteo (legacy de 1 premio y múltiples premios con peso), promos, descuentos, costos y webhook al ERP.
- **DraftController** — aprueba o rechaza las propuestas de `raffle_candidate_analyses` y crea el sorteo con sus números.
- **AuthController** — login y logout del admin.

## Modelos
| Modelo | Tabla | Relaciones / métodos |
|---|---|---|
| `Raffle` | `raffles` | `numbers()`, `prizes()` (ordenados por `order`), `promoResults()`, `usesMultiplePrizes()` |
| `RaffleNumber` | `raffle_numbers` | `raffle()`, `payments()`, **`liberar(string $motivo)`** (nuevo) |
| `RafflePrize` | `raffle_prizes` | `raffle()`, `hasWinner()`, `positionLabel()` |
| `RafflePromoResult` | `raffle_promo_results` | ganadores de la promo |
| `RaffleCandidateAnalysis` | `raffle_candidate_analyses` | propuestas automáticas |
| `Payment` | `payments` | **sin uso**: ningún flujo escribe en esta tabla |

No existe modelo `Ticket`: el número/boleto es `RaffleNumber`.

## Comandos y scheduler
Lo que manda es `bootstrap/app.php` (`withSchedule`). `app/Console/Kernel.php` es un resto de versiones viejas de Laravel y no se usa.

| Comando | Programación | Qué hace |
|---|---|---|
| `reservas:liberar` | cada minuto, `withoutOverlapping` | Libera reservas vencidas **solo si** `SORTEOS_LIBERACION_AUTOMATICA=true`. `--force` lo ejecuta igual. |
| `raffles:analyze-candidates` | cada hora | Genera propuestas de sorteos |

⚠️ Al 2026-11-02 **no hay cron** `schedule:run` para este proyecto en el servidor, así que ninguna tarea programada se ejecuta. Línea a agregar (crontab de `www-data`):
```
* * * * * cd /var/www/sorteos_py && php artisan schedule:run >> /dev/null 2>&1
```

## Configuración (`config/sorteos.php`)
| Variable `.env` | Default | Uso |
|---|---|---|
| `SORTEOS_RESERVA_MINUTOS` | `15` | Duración de una reserva sin pagar (`expires_at`) |
| `SORTEOS_LIBERACION_AUTOMATICA` | `false` | Activa la liberación automática de reservas vencidas |

Otras: `ADMIN_PASSWORD_HASH`, `ERP_TREASURY_URL`, `HOLDING_API_TOKEN` (webhook al ERP).

## Integraciones
- **Webhook ERP (tesorería):** cuando se sortean todos los premios, `AdminController::notifyErpTreasury` hace `POST {ERP_TREASURY_URL}/api/treasury/sorteos-income` con `raffle_id`, `raffle_name`, `amount` (ganancia real) y `token`. Si falla, solo queda en el log y no frena el sorteo. Hoy se llama dentro de la transacción del sorteo.

## Tests
`tests/Feature/ReservationTest.php` (reserva, límite, estado del sorteo, throttle, liberación manual y automática, confirmar pago) y `RaffleDrawTest.php`.

⚠️ `phpunit.xml` usa SQLite en memoria, pero el servidor no tiene `pdo_sqlite` y producción está instalada sin dependencias de desarrollo (Pest no está en `vendor/`). Para correr los tests: copiar el repo aparte, `composer install` y apuntar `DB_*` a un PostgreSQL descartable.
