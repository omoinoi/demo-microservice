<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate request (kiểm tra dữ liệu đầu vào)
        $request->validate([
            'customer_name' => 'required|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $productServiceUrl = env('PRODUCT_SERVICE_URL');
        $totalAmount = 0;
        $orderItemsData = [];

        // DB Transaction để đảm bảo nếu lỗi thì không lưu order dang dở
        DB::beginTransaction();

        try {
            // 2. Tạo đơn hàng (tạm thời tổng tiền = 0)
            $order = Order::create([
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email ?? 'khachhang@example.com',
                'status' => 'pending',
                'total_amount' => 0
            ]);

            // 3. Duyệt qua từng món khách đặt
            foreach ($request->items as $item) {
                // *** MICROSERVICE COMMUNICATION ***
                // Gọi API sang Product Service để lấy thông tin sản phẩm
                $response = Http::get("{$productServiceUrl}/api/products/{$item['product_id']}");

                if ($response->failed() || $response->status() == 404) {
                    throw new \Exception("Sản phẩm ID {$item['product_id']} không tồn tại hoặc Product Service đang lỗi.");
                }

                $productData = $response->json();
                $price = $productData['price'];

                // Tính tiền món này và cộng vào tổng
                $subTotal = $price * $item['quantity'];
                $totalAmount += $subTotal;

                // Chuẩn bị dữ liệu lưu vào bảng order_items
                $orderItemsData[] = [
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 4. Lưu chi tiết các món vào bảng order_items
            OrderItem::insert($orderItemsData);

            // 5. Cập nhật lại tổng tiền cho đơn hàng
            $order->update(['total_amount' => $totalAmount]);
            
            // ==========================================
            // GIAO TIẾP BẤT ĐỒNG BỘ QUA KAFKA
            // ==========================================
            
            // 1. Chuẩn bị dữ liệu muốn gửi
			// TẠO MÃ THEO DÕI DUY NHẤT CHO REQUEST NÀY
            $traceId = Str::uuid()->toString();
            $data = [
				'trace_id' => $traceId,
                'order_id' => $order->id,
                'items' => $request->items
            ];

            // 2. Đóng gói dữ liệu vào class Message 
            $message = new Message(body: $data);

            // Chốt giao dịch Database TRƯỚC để chắc chắn 100% dữ liệu đã hiện hình
            DB::commit();
            
            // RỒI MỚI bắn tin nhắn đi báo cho các phòng ban khác
            Kafka::publish(env('KAFKA_BROKERS', 'kafka:9092'))
                ->onTopic('order.created')
                ->withMessage($message)
                ->send();

            return response()->json([
                'message' => 'Yêu cầu đang được xử lý!',
				'trace_id' => $traceId,
                'order_id' => $order->id,
                'total_amount' => $totalAmount
            ], 201);

        } catch (\Exception $e) {
            // Hủy giao dịch nếu có lỗi
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi tạo đơn hàng.',
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    // Lấy danh sách đơn hàng (để test xem lưu thành công chưa)
    public function index()
    {
         return response()->json(Order::with('items')->get());
    }
}