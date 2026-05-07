<?php

use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePrize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a winning number is excluded from following prizes but the same customer can win with another number', function () {
    $raffle = Raffle::create([
        'name' => 'Sorteo de prueba',
        'price' => 10000,
        'total_numbers' => 2,
        'status' => 'active',
        'prizes_count' => 2,
    ]);

    RaffleNumber::create([
        'raffle_id' => $raffle->id,
        'number' => '1',
        'status' => 'sold',
        'customer_name' => 'Cliente Uno',
        'paid' => true,
        'reserved_at' => now(),
    ]);

    RaffleNumber::create([
        'raffle_id' => $raffle->id,
        'number' => '2',
        'status' => 'sold',
        'customer_name' => 'Cliente Uno',
        'paid' => true,
        'reserved_at' => now(),
    ]);

    RafflePrize::create([
        'raffle_id' => $raffle->id,
        'order' => 1,
        'name' => 'Premio 2',
        'winner_number' => '1',
        'winner_name' => 'Cliente Uno',
    ]);

    RafflePrize::create([
        'raffle_id' => $raffle->id,
        'order' => 2,
        'name' => 'Premio 1',
    ]);

    $response = $this
        ->withSession(['role' => 'admin'])
        ->postJson(route('admin.sortear', $raffle->id), [
            'prize_order' => 2,
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'winner_number' => '2',
            'winner_name' => 'Cliente Uno',
            'all_drawn' => true,
        ]);

    expect(RafflePrize::where('raffle_id', $raffle->id)->pluck('winner_number')->all())
        ->toBe(['1', '2']);
});
