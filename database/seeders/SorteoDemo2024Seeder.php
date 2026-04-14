<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Raffle;
use App\Models\RaffleNumber;
use App\Models\RafflePrize;
use Carbon\Carbon;

class SorteoDemo2024Seeder extends Seeder
{
    public function run(): void
    {
        // ── SORTEO ──────────────────────────────────────────────────────────
        $raffle = Raffle::create([
            'name'          => 'Sorteo Demo 2024 🎉',
            'price'         => 50000,
            'total_numbers' => 50,
            'status'        => 'active',
            'prizes_count'  => 3,
            'image'         => null,
        ]);

        // ── PREMIOS (order = posición real: 3=1er, 2=2do, 1=3er) ──────────
        RafflePrize::insert([
            [
                'raffle_id'   => $raffle->id,
                'order'       => 3,
                'name'        => 'iPhone 15 Pro Max',
                'description' => '256 GB Titanio Negro',
                'winner_number' => null,
                'winner_name'   => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'raffle_id'   => $raffle->id,
                'order'       => 2,
                'name'        => 'Smart TV Samsung 55"',
                'description' => '4K QLED',
                'winner_number' => null,
                'winner_name'   => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'raffle_id'   => $raffle->id,
                'order'       => 1,
                'name'        => 'AirPods Pro 2da Gen',
                'description' => 'Con estuche MagSafe',
                'winner_number' => null,
                'winner_name'   => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        // ── NÚMEROS ──────────────────────────────────────────────────────────
        // 50 números:
        //   01-22 → vendidos (paid=true, status=sold, con nombre)
        //   23-35 → reservados (status=reserved, con nombre, sin pagar)
        //   36-50 → libres (status=free, sin nombre)

        $nombresPagados = [
            'Ana Enciso',       'Carlos Benítez',   'Sofía Ramírez',
            'Diego Martínez',   'Valentina López',  'Mateo García',
            'Lucía Fernández',  'Sebastián Torres', 'Camila Rodríguez',
            'Nicolás Herrera',  'Isabella Vargas',  'Alejandro Soto',
            'Martina Díaz',     'Emilio Reyes',     'Antonella Chávez',
            'Rodrigo Salazar',  'Renata Molina',    'Facundo Núñez',
            'Paula Jiménez',    'Tomás Romero',     'Florencia Castro',
            'Agustín Moreno',
        ];

        $nombresReservados = [
            'Josefina Ruiz',    'Joaquín Álvarez',  'Micaela Peralta',
            'Gonzalo Ramos',    'Bianca Guerrero',  'Leandro Medina',
            'Ximena Paredes',   'Ignacio Cabrera',  'Giuliana Pinto',
            'Máximo Guzmán',    'Soledad Acosta',   'Ezequiel Rojas',
            'Celeste Mendoza',
        ];

        $numbers = [];
        $now = Carbon::now();

        for ($i = 1; $i <= 50; $i++) {
            $numero = str_pad($i, 2, '0', STR_PAD_LEFT);

            if ($i <= 22) {
                // Pagado y vendido
                $numbers[] = [
                    'raffle_id'     => $raffle->id,
                    'number'        => $numero,
                    'status'        => 'sold',
                    'customer_name' => $nombresPagados[$i - 1],
                    'paid'          => true,
                    'reserved_at'   => $now->copy()->subHours(rand(2, 48)),
                    'expires_at'    => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            } elseif ($i <= 35) {
                // Reservado sin pagar
                $numbers[] = [
                    'raffle_id'     => $raffle->id,
                    'number'        => $numero,
                    'status'        => 'reserved',
                    'customer_name' => $nombresReservados[$i - 23],
                    'paid'          => false,
                    'reserved_at'   => $now->copy()->subMinutes(rand(1, 10)),
                    'expires_at'    => $now->copy()->addMinutes(rand(5, 15)),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            } else {
                // Libre sin nombre
                $numbers[] = [
                    'raffle_id'     => $raffle->id,
                    'number'        => $numero,
                    'status'        => 'free',
                    'customer_name' => null,
                    'paid'          => false,
                    'reserved_at'   => null,
                    'expires_at'    => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
        }

        RaffleNumber::insert($numbers);

        $this->command->info("✅ Sorteo creado: [{$raffle->id}] {$raffle->name}");
        $this->command->info("   🏆 3 premios  |  🔴 22 vendidos  |  🟡 13 reservados  |  🟢 15 libres");
    }
}
