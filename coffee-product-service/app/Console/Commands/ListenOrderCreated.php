<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\Product;

class ListenOrderCreated extends Command
{
    // Tên lệnh để chạy trong terminal
    protected $signature = 'redis:listen-orders';
    protected $description = 'Lắng nghe sự kiện tạo đơn hàng từ Redis để trừ kho';

    public function handle()
    {
        $this->info("Đang lắng nghe kênh 'order.created'...");

        // Lắng nghe (Subscribe) kênh 'order.created'
        Redis::subscribe(['order.created'], function ($message) {
            $this->info("Đã nhận tin nhắn mới!");
            
            // Giải mã JSON
            $data = json_decode($message, true);

            // Duyệt qua các món đã đặt và trừ kho
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->decrement('stock_quantity', $item['quantity']);
                    $this->info("-> Đã trừ {$item['quantity']} ly cho SP: " . $product->name);
                }
            }
        });
    }
}