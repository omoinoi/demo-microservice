<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use App\Models\Order;

class KafkaListenOrderFailed extends Command
{
    protected $signature = 'kafka:listen-order-failed';
    protected $description = 'Lắng nghe tín hiệu hủy đơn hàng từ Product Service';

    public function handle()
    {
        $this->info("Đang lắng nghe Kafka topic 'order.failed'...");

        $consumer = Kafka::consumer(['order.failed'])
            ->withConsumerGroupId('order-service-group') 
            ->withHandler(function($message) { 
                $data = $message->getBody();
                $orderId = $data['order_id'];
                $reason = $data['reason'];
				$traceId = $data['trace_id'] ?? 'NO-TRACE'; // <--- Lấy mã vạch
                
                // Gắn mác vào log
                $this->error("[Trace: {$traceId}] Nhận tín hiệu báo lỗi cho Đơn hàng ID: {$orderId}");
                $this->line("[Trace: {$traceId}] Lý do: {$reason}");

                // Tìm đơn hàng và cập nhật trạng thái thành 'canceled'
                $order = Order::find($orderId);
                if ($order) {
                    $order->update(['status' => 'canceled']);
                    $this->info("[Trace: {$traceId}] -> Đã cập nhật trạng thái đơn hàng {$orderId} thành CANCELED.");
                }
            })
            ->build();

        $consumer->consume();
    }
}