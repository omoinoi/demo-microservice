<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::insert([
            [
                'name' => 'Cà phê Đen Đá',
                'description' => 'Cà phê truyền thống đậm đà.',
                'price' => 25000,
                'stock_quantity' => 100,
            ],
            [
                'name' => 'Cà phê Sữa Đá',
                'description' => 'Sự kết hợp hoàn hảo giữa cà phê và sữa đặc.',
                'price' => 30000,
                'stock_quantity' => 80,
            ],
            [
                'name' => 'Bạc Xỉu',
                'description' => 'Nhiều sữa ít cà phê, thơm béo.',
                'price' => 35000,
                'stock_quantity' => 50,
            ],
        ]);
    }
}