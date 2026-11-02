<?php

use App\Models\Raffle;
use App\Models\RaffleNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearSorteo(array $attrs = [], int $cantidad = 20): Raffle
{
    $raffle = Raffle::create(array_merge([
        'name'          => 'Sorteo test',
        'price'         => 10000,
        'total_numbers' => $cantidad,
        'status'        => 'active',
        'prizes_count'  => 1,
    ], $attrs));

    for ($i = 1; $i <= $cantidad; $i++) {
        RaffleNumber::create(['raffle_id' => $raffle->id, 'number' => $i, 'status' => 'free']);
    }

    return $raffle;
}

function numero(Raffle $raffle, int $n): RaffleNumber
{
    return RaffleNumber::where('raffle_id', $raffle->id)->where('number', (string) $n)->firstOrFail();
}

function reservar($test, Raffle $raffle, array $numbers, string $name = 'Juan')
{
    return $test->postJson('/reservar', [
        'raffle_id' => $raffle->id,
        'numbers'   => $numbers,
        'name'      => $name,
    ]);
}

// ── Reserva ─────────────────────────────────────────────────────────────

test('reserva los números y setea reserved_at y expires_at a 15 minutos', function () {
    $this->freezeSecond();
    $raffle = crearSorteo();

    reservar($this, $raffle, ['3', '1', '3'])
        ->assertOk()
        ->assertJson(['success' => true, 'numbers' => [3, 1]]);

    foreach ([1, 3] as $n) {
        $num = numero($raffle, $n);
        expect($num->status)->toBe('reserved')
            ->and($num->customer_name)->toBe('Juan')
            ->and($num->paid)->toBeFalse()
            ->and($num->reserved_at->equalTo(now()))->toBeTrue()
            ->and($num->expires_at->equalTo(now()->addMinutes(15)))->toBeTrue();
    }

    expect(numero($raffle, 2)->status)->toBe('free');
});

test('sorteo gratis: queda vendido, pagado y sin vencimiento', function () {
    $raffle = crearSorteo(['price' => 0]);

    reservar($this, $raffle, [5])->assertOk();

    $num = numero($raffle, 5);
    expect($num->status)->toBe('sold')
        ->and($num->paid)->toBeTrue()
        ->and($num->expires_at)->toBeNull();
});

test('rechaza más de 10 números por pedido', function () {
    $raffle = crearSorteo();

    reservar($this, $raffle, range(1, 11))
        ->assertStatus(422)
        ->assertJsonValidationErrors('numbers');

    expect(RaffleNumber::where('status', '!=', 'free')->count())->toBe(0);
});

test('acepta exactamente 10 números', function () {
    $raffle = crearSorteo();

    reservar($this, $raffle, range(1, 10))->assertOk();

    expect(RaffleNumber::where('status', 'reserved')->count())->toBe(10);
});

test('rechaza reservar en un sorteo que no está activo', function (string $status) {
    $raffle = crearSorteo(['status' => $status]);

    reservar($this, $raffle, [1])
        ->assertStatus(400)
        ->assertJson(['error' => 'Este sorteo no está disponible para reservas']);

    expect(numero($raffle, 1)->status)->toBe('free');
})->with(['draft', 'finished']);

test('si un número ya está tomado no reserva ninguno del pedido', function () {
    $raffle = crearSorteo();
    reservar($this, $raffle, [2], 'Ana')->assertOk();

    reservar($this, $raffle, [1, 2, 3], 'Juan')
        ->assertStatus(400)
        ->assertJson(['error' => 'El número 2 ya fue reservado o vendido']);

    expect(numero($raffle, 1)->status)->toBe('free')
        ->and(numero($raffle, 3)->status)->toBe('free')
        ->and(numero($raffle, 2)->customer_name)->toBe('Ana');
});

test('rechaza un número que no existe en el sorteo', function () {
    $raffle = crearSorteo([], 5);

    reservar($this, $raffle, [1, 99])
        ->assertStatus(400)
        ->assertJson(['error' => 'El número 99 no existe']);

    expect(numero($raffle, 1)->status)->toBe('free');
});

test('no toca números con el mismo valor de otro sorteo', function () {
    $a = crearSorteo();
    $b = crearSorteo();

    reservar($this, $a, [1])->assertOk();

    expect(numero($b, 1)->status)->toBe('free');
});

test('throttle: el pedido 11 en un minuto devuelve 429', function () {
    $raffle = crearSorteo();

    for ($i = 1; $i <= 10; $i++) {
        reservar($this, $raffle, [$i])->assertOk();
    }

    reservar($this, $raffle, [11])->assertStatus(429);
});

// ── reservas:liberar ────────────────────────────────────────────────────

function reservarVencido(Raffle $raffle, int $n, array $extra = []): RaffleNumber
{
    $num = numero($raffle, $n);
    $num->update(array_merge([
        'status'        => 'reserved',
        'customer_name' => 'Juan',
        'reserved_at'   => now()->subMinutes(20),
        'expires_at'    => now()->subMinutes(5),
        'paid'          => false,
    ], $extra));

    return $num;
}

test('con la liberación automática desactivada no libera nada', function () {
    config(['sorteos.liberacion_automatica' => false]);
    $raffle = crearSorteo();
    reservarVencido($raffle, 1);

    $this->artisan('reservas:liberar')->assertSuccessful();

    expect(numero($raffle, 1)->status)->toBe('reserved');
});

test('libera solo las reservas vencidas, sin pagar, de sorteos activos', function () {
    config(['sorteos.liberacion_automatica' => true]);
    $activo    = crearSorteo();
    $terminado = crearSorteo(['status' => 'finished']);

    reservarVencido($activo, 1);                                           // se libera
    reservarVencido($activo, 2, ['expires_at' => now()->addMinutes(5)]);   // todavía vigente
    reservarVencido($activo, 3, ['expires_at' => null]);                   // reserva vieja sin vencimiento
    reservarVencido($activo, 4, ['status' => 'sold', 'paid' => true]);     // vendido
    reservarVencido($terminado, 1);                                        // sorteo terminado

    $this->artisan('reservas:liberar')
        ->expectsOutput('Reservas liberadas: 1')
        ->assertSuccessful();

    $liberado = numero($activo, 1);
    expect($liberado->status)->toBe('free')
        ->and($liberado->customer_name)->toBeNull()
        ->and($liberado->reserved_at)->toBeNull()
        ->and($liberado->expires_at)->toBeNull()
        ->and($liberado->paid)->toBeFalse();

    expect(numero($activo, 2)->status)->toBe('reserved')
        ->and(numero($activo, 3)->status)->toBe('reserved')
        ->and(numero($activo, 4)->status)->toBe('sold')
        ->and(numero($terminado, 1)->status)->toBe('reserved');
});

test('--force libera aunque la liberación automática esté desactivada', function () {
    config(['sorteos.liberacion_automatica' => false]);
    $raffle = crearSorteo();
    reservarVencido($raffle, 1);

    $this->artisan('reservas:liberar --force')
        ->expectsOutput('Reservas liberadas: 1')
        ->assertSuccessful();

    expect(numero($raffle, 1)->status)->toBe('free');
});

test('un número liberado se puede volver a reservar', function () {
    config(['sorteos.liberacion_automatica' => true]);
    $raffle = crearSorteo();
    reservarVencido($raffle, 1);

    $this->artisan('reservas:liberar')->assertSuccessful();

    reservar($this, $raffle, [1], 'Ana')->assertOk();
    expect(numero($raffle, 1)->customer_name)->toBe('Ana');
});

// ── Liberación manual (admin) ───────────────────────────────────────────

test('admin libera una reserva manualmente', function () {
    $raffle = crearSorteo();
    $num = reservarVencido($raffle, 1, ['expires_at' => now()->addMinutes(10)]);

    $this->withSession(['role' => 'admin'])
        ->from('/admin')
        ->post("/admin/liberar/{$num->id}")
        ->assertRedirect('/admin')
        ->assertSessionHas('success', 'Número 1 liberado');

    expect($num->fresh()->status)->toBe('free')
        ->and($num->fresh()->customer_name)->toBeNull();
});

test('admin no puede liberar un número vendido ni de un sorteo terminado', function () {
    $raffle    = crearSorteo();
    $terminado = crearSorteo(['status' => 'finished']);
    $vendido   = reservarVencido($raffle, 1, ['status' => 'sold', 'paid' => true]);
    $otro      = reservarVencido($terminado, 1);

    foreach ([$vendido, $otro] as $num) {
        $this->withSession(['role' => 'admin'])
            ->post("/admin/liberar/{$num->id}")
            ->assertSessionHas('error');
    }

    expect($vendido->fresh()->status)->toBe('sold')
        ->and($otro->fresh()->status)->toBe('reserved');
});

test('sin sesión de admin no se puede liberar', function () {
    $raffle = crearSorteo();
    $num = reservarVencido($raffle, 1);

    $this->post("/admin/liberar/{$num->id}")->assertRedirect('/admin/login');

    expect($num->fresh()->status)->toBe('reserved');
});

test('confirmar pago sigue funcionando', function () {
    $raffle = crearSorteo();
    $num = reservarVencido($raffle, 1);

    $this->withSession(['role' => 'admin'])
        ->post("/admin/confirmar/{$num->id}")
        ->assertSessionHas('success', 'Pago confirmado');

    expect($num->fresh()->status)->toBe('sold')
        ->and($num->fresh()->paid)->toBeTrue()
        ->and($num->fresh()->expires_at)->toBeNull();
});
