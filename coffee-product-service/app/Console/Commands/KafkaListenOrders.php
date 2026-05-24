<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message; // Thêm dòng này để tạo tin nhắn mới
use App\Models\Product;

class KafkaListenOrders extends Command
{
    protected $signature = 'kafka:listen-orders';
    protected $description = 'Lắng nghe đơn hàng từ Kafka';

    public function handle()
    {
        $this->info("Đang lắng nghe Kafka topic 'order.created'...");

        $consumer = Kafka::consumer(['order.created'])
            ->withConsumerGroupId('product-service-group') 
            ->withHandler(function($message) { 
                $data = $message->getBody();
                $orderId = $data['order_id'];
				$traceId = $data['trace_id'] ?? 'NO-TRACE'; // <--- Lấy mã vạch ra
                // IN MÃ VẠCH RA MÀN HÌNH ĐỂ TRUY VẾT
                $this->info("[Trace: {$traceId}] Đã nhận yêu cầu xử lý kho cho Đơn hàng ID: " . $orderId);

                $outOfStock = false;
                $reason = "";

                // BƯỚC 1: GOM NHÓM SỐ LƯỢNG (Chống hack gửi trùng sản phẩm)
                $requestedQuantities = [];
                foreach ($data['items'] as $item) {
                    $id = $item['product_id'];
                    if (!isset($requestedQuantities[$id])) {
                        $requestedQuantities[$id] = 0;
                    }
                    $requestedQuantities[$id] += $item['quantity'];
                }

                // BƯỚC 2: KIỂM TRA ĐỒNG LOẠT (All or Nothing)
                foreach ($requestedQuantities as $id => $totalQtyNeeded) {
                    $product = Product::find($id);
                    
                    if (!$product) {
                        $outOfStock = true;
                        $reason = "Sản phẩm ID {$id} không tồn tại.";
                        break; // Dừng ngay lập tức nếu phát hiện lỗi
                    }
                    
                    if ($product->stock_quantity < $totalQtyNeeded) {
                        $outOfStock = true;
                        $reason = "Sản phẩm {$product->name} không đủ số lượng (Cần {$totalQtyNeeded}, chỉ còn {$product->stock_quantity}).";
                        break; // Dừng ngay lập tức nếu phát hiện 1 món thiếu
                    }
                }

                // NẾU CÓ BẤT KỲ LỖI NÀO -> BẮN TÍN HIỆU HỦY ĐƠN VỀ KAFKA VÀ DỪNG LẠI
                if ($outOfStock) {
                    $this->error("[Trace: {$traceId}] -> TỪ CHỐI ĐƠN HÀNG {$orderId}: {$reason}");
                    
                    $failedMessage = new \Junges\Kafka\Message\Message(body: [
						'trace_id' => $traceId, // <--- Phải kẹp mã vạch trả về cho bên Order
                        'order_id' => $orderId,
                        'reason' => $reason
                    ]);

                    Kafka::publish(env('KAFKA_BROKERS', 'kafka:9092'))
                        ->onTopic('order.failed')
                        ->withMessage($failedMessage)
                        ->send();
                        
                    return; // Chặn đứng, tuyệt đối không chạy xuống code trừ kho bên dưới
                }

                // BƯỚC 3: TRỪ KHO AN TOÀN (Chỉ chạy đến đây khi 100% các món đều đủ hàng)
                foreach ($requestedQuantities as $id => $totalQtyNeeded) {
                    $product = Product::find($id);
                    $product->decrement('stock_quantity', $totalQtyNeeded);
                    $this->line("[Trace: {$traceId}] -> Đã trừ {$totalQtyNeeded} ly cho SP: {$product->name}");
                }
            })
            ->build();

        $consumer->consume();
    }
}