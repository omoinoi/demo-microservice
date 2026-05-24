<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            
            // QUAN TRỌNG: Chúng ta lưu product_id từ Product Service, 
            // nhưng không tạo khóa ngoại (foreign key) ở đây vì 2 database khác nhau.
            $table->unsignedBigInteger('product_id'); 
            
            $table->integer('quantity');
            $table->decimal('price', 8, 2); // Giá lúc đặt (đề phòng giá sản phẩm thay đổi sau này)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
