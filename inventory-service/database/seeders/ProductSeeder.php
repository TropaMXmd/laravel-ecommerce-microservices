<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['sku' => 'ONS-0001', 'name' => 'Cotton Baby Onesie 0-3M', 'price' => 12.99, 'qty' => 100],
            ['sku' => 'SWD-0002', 'name' => 'Swaddle Blanket - Organic', 'price' => 18.50, 'qty' => 60],
            ['sku' => 'HAT-0003', 'name' => 'Baby Sun Hat', 'price' => 8.25, 'qty' => 40],
        ];

        foreach ($products as $p) {
            $product = Product::create([
                'sku' => $p['sku'],
                'name' => $p['name'],
                'price' => $p['price'],
                'currency' => 'USD',
                'is_active' => true,
            ]);

            Stock::create([
                'sku' => $product->sku,
                'quantity' => $p['qty'],
                'reserved_quantity' => 0,
            ]);
        }
    }
}
