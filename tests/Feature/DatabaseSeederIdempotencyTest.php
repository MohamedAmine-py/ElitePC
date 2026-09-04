<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_and_preserves_existing_stock(): void
    {
        $this->seed(DatabaseSeeder::class);

        DB::table('produits')
            ->where('nom', 'Slayer-X Gaming Desktop')
            ->update(['stock' => 2]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, DB::table('categories')->count());
        $this->assertSame(16, DB::table('produits')->count());
        $this->assertSame(1, DB::table('users')->where('email', 'admin@ecommerce.test')->count());
        $this->assertSame(1, DB::table('users')->where('email', 'client@ecommerce.test')->count());
        $this->assertSame(2, DB::table('produits')->where('nom', 'Slayer-X Gaming Desktop')->value('stock'));
    }
}
