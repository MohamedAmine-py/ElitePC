<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo credentials are convenient locally but must never create known
        // accounts when this seeder is run in staging or production.
        if (app()->environment(['local', 'testing'])) {
            User::updateOrCreate(
                ['email' => env('SEED_ADMIN_EMAIL', 'admin@ecommerce.test')],
                [
                    'nom' => env('SEED_ADMIN_NAME', 'Administrateur'),
                    'mot_de_passe' => Hash::make(env('SEED_ADMIN_PASSWORD', 'admin123')),
                    'role' => 'admin',
                ]
            );

            User::updateOrCreate(
                ['email' => env('SEED_CLIENT_EMAIL', 'client@ecommerce.test')],
                [
                    'nom' => env('SEED_CLIENT_NAME', 'Client de démonstration'),
                    'mot_de_passe' => Hash::make(env('SEED_CLIENT_PASSWORD', 'client123')),
                    'role' => 'client',
                ]
            );
        }

        // Create categories
        $gamerPcs = Categorie::updateOrCreate(['nom' => 'Gamer PCs'], ['description' => 'High-performance gaming desktops']);
        $workstations = Categorie::updateOrCreate(['nom' => 'Workstations'], ['description' => 'Professional productivity machines']);
        $components = Categorie::updateOrCreate(['nom' => 'Components'], ['description' => 'PC parts (CPU, GPU, RAM, Motherboards)']);
        $peripherals = Categorie::updateOrCreate(['nom' => 'Peripherals'], ['description' => 'Mice, keyboards, and headsets']);
        $accessories = Categorie::updateOrCreate(['nom' => 'PC Accessories'], ['description' => 'Cases, cooling, and power supplies']);

        $products = [
            // Gamer PCs
            [
                'nom' => 'Slayer-X Gaming Desktop', 'prix' => 4299.99, 'stock' => 5, 'categorie_id' => $gamerPcs->id,
                'description' => 'The ultimate 4K gaming machine built for uncompromising performance.',
                'image' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=800&h=800&fit=crop',
                'brand' => 'Elite PC', 'processor' => 'Intel Core i9-14900K', 'graphics_card' => 'NVIDIA RTX 4090 24GB',
                'ram_details' => '32GB DDR5-6000 Corsair Dominator', 'storage_details' => '2TB Samsung 990 Pro NVMe SSD', 'is_custom_build' => true,
            ],
            [
                'nom' => 'Phantom-Z System', 'prix' => 2899.99, 'stock' => 10, 'categorie_id' => $gamerPcs->id,
                'description' => 'Exceptional 1440p and 4K performance with full AMD synergy.',
                'image' => 'https://images.unsplash.com/photo-1624701928517-44c8ac49d93c?w=800&h=800&fit=crop',
                'brand' => 'Elite PC', 'processor' => 'AMD Ryzen 9 7950X', 'graphics_card' => 'AMD Radeon RX 7900 XTX 24GB',
                'ram_details' => '32GB DDR5-6000 G.Skill Trident Z5', 'storage_details' => '2TB WD Black SN850X', 'is_custom_build' => true,
            ],
            [
                'nom' => 'Nova Strike eSports Build', 'prix' => 1499.99, 'stock' => 15, 'categorie_id' => $gamerPcs->id,
                'description' => 'High-framerate 1080p/1440p gaming optimized for competitive titles.',
                'image' => 'https://images.unsplash.com/photo-1603481588273-2f908a9a7a1b?w=800&h=800&fit=crop',
                'brand' => 'Elite PC', 'processor' => 'Intel Core i5-13600K', 'graphics_card' => 'NVIDIA RTX 4070 12GB',
                'ram_details' => '16GB DDR5-5600 Corsair Vengeance', 'storage_details' => '1TB Crucial P5 Plus NVMe', 'is_custom_build' => true,
            ],

            // Workstations
            [
                'nom' => 'TitanWS Pro Studio', 'prix' => 6999.99, 'stock' => 2, 'categorie_id' => $workstations->id,
                'description' => 'Unrivaled rendering and AI workload performance.',
                'image' => 'https://images.unsplash.com/photo-1547082299-de196ea013d6?w=800&h=800&fit=crop',
                'brand' => 'Elite Pro', 'processor' => 'Intel Xeon w9-3495X', 'graphics_card' => 'NVIDIA RTX 6000 Ada Generation',
                'ram_details' => '128GB DDR5 ECC Registered', 'storage_details' => '4TB Gen5 NVMe Array', 'is_custom_build' => true,
            ],
            [
                'nom' => 'Creator-7 Master', 'prix' => 3499.99, 'stock' => 4, 'categorie_id' => $workstations->id,
                'description' => 'Perfect for 3D modeling, video editing, and complex simulations.',
                'image' => 'https://images.unsplash.com/photo-1598986646512-93e5db240d7d?w=800&h=800&fit=crop',
                'brand' => 'Elite Pro', 'processor' => 'AMD Ryzen Threadripper 7970X', 'graphics_card' => 'NVIDIA RTX 4080 SUPER 16GB',
                'ram_details' => '64GB DDR5-5200 Pro', 'storage_details' => '2TB Gen4 NVMe SSD', 'is_custom_build' => true,
            ],

            // Components
            [
                'nom' => 'NVIDIA GeForce RTX 4090 Founders Edition', 'prix' => 1599.99, 'stock' => 3, 'categorie_id' => $components->id,
                'description' => 'The ultimate GPU. Bring a massive leap in performance, efficiency, and AI-powered graphics.',
                'image' => 'https://images.unsplash.com/photo-1678129712036-f0fec2679dc6?w=800&h=800&fit=crop',
                'brand' => 'NVIDIA', 'processor' => null, 'graphics_card' => 'RTX 4090 24GB GDDR6X', 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'Intel Core i9-14900K', 'prix' => 589.99, 'stock' => 20, 'categorie_id' => $components->id,
                'description' => '24 cores (8 P-cores + 16 E-cores) and 32 threads. Up to 6.0 GHz.',
                'image' => 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=800&h=800&fit=crop',
                'brand' => 'Intel', 'processor' => 'i9-14900K 6.0GHz', 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'Corsair Vengeance 32GB (2x16GB) DDR5-6000', 'prix' => 114.99, 'stock' => 45, 'categorie_id' => $components->id,
                'description' => 'High performance DDR5 memory optimized for Intel and AMD motherboards.',
                'image' => 'https://images.unsplash.com/photo-1563158114-e4f6479612c6?w=800&h=800&fit=crop',
                'brand' => 'Corsair', 'processor' => null, 'graphics_card' => null, 'ram_details' => '32GB DDR5-6000 CL30', 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'Samsung 990 PRO 2TB NVMe SSD', 'prix' => 169.99, 'stock' => 30, 'categorie_id' => $components->id,
                'description' => 'PCIe 4.0 NVMe with up to 7450 MB/s read and 6900 MB/s write.',
                'image' => 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=800&h=800&fit=crop',
                'brand' => 'Samsung', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => '2TB PCIe Gen4 NVMe', 'is_custom_build' => false,
            ],
            [
                'nom' => 'ASUS ROG Crosshair X670E Hero', 'prix' => 699.99, 'stock' => 8, 'categorie_id' => $components->id,
                'description' => 'Premium AM5 motherboard with robust power delivery and PCIe 5.0.',
                'image' => 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=800&h=800&fit=crop',
                'brand' => 'ASUS ROG', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],

            // Peripherals
            [
                'nom' => 'Logitech G Pro X Superlight 2', 'prix' => 159.00, 'stock' => 25, 'categorie_id' => $peripherals->id,
                'description' => 'Ultra-lightweight wireless gaming mouse with HERO 2 sensor and 2K polling.',
                'image' => 'https://images.unsplash.com/photo-1615663245857-ac93bb7c3c9c?w=800&h=800&fit=crop',
                'brand' => 'Logitech G', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'SteelSeries Arctis Nova Pro Wireless', 'prix' => 349.99, 'stock' => 12, 'categorie_id' => $peripherals->id,
                'description' => 'Premium gaming headset with Active Noise Cancellation and hot-swappable batteries.',
                'image' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&h=800&fit=crop',
                'brand' => 'SteelSeries', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'Wooting 60HE+ Analog Keyboard', 'prix' => 174.99, 'stock' => 5, 'categorie_id' => $peripherals->id,
                'description' => 'Analog mechanical keyboard with Rapid Trigger for competitive edge.',
                'image' => 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=800&h=800&fit=crop',
                'brand' => 'Wooting', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],

            // PC Accessories
            [
                'nom' => 'NZXT H9 Elite Dual-Chamber Case', 'prix' => 239.99, 'stock' => 10, 'categorie_id' => $accessories->id,
                'description' => 'Showcase your build with uninterrupted glass panels and premium airflow.',
                'image' => 'https://images.unsplash.com/photo-1555617781-db2d2e1ebc12?w=800&h=800&fit=crop',
                'brand' => 'NZXT', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'be quiet! Dark Power Pro 13 1300W', 'prix' => 399.90, 'stock' => 6, 'categorie_id' => $accessories->id,
                'description' => 'ATX 3.0 titanium efficiency power supply for high-end systems.',
                'image' => 'https://images.unsplash.com/photo-1587202372585-cfc54ee92015?w=800&h=800&fit=crop',
                'brand' => 'be quiet!', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
            [
                'nom' => 'Corsair iCUE H150i ELITE LCD XT AIO', 'prix' => 289.99, 'stock' => 14, 'categorie_id' => $accessories->id,
                'description' => '360mm liquid CPU cooler with customizable LCD screen.',
                'image' => 'https://images.unsplash.com/photo-1624701928517-44c8ac49d93c?w=800&h=800&fit=crop',
                'brand' => 'Corsair', 'processor' => null, 'graphics_card' => null, 'ram_details' => null, 'storage_details' => null, 'is_custom_build' => false,
            ],
        ];

        foreach ($products as $product) {
            // Existing inventory may reflect real local orders; repeated seeding
            // must not reset stock or overwrite catalog edits.
            Produit::firstOrCreate(['nom' => $product['nom']], $product);
        }
    }
}
