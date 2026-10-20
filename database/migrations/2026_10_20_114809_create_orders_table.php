<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_sn', 64)->unique();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('status')->default(1)->comment('1: 成功, 2: 取消');
            $table->timestamps();

            // 關鍵唯一索引：防重複下單兜底
            $table->unique(['ticket_id', 'user_id'], 'uk_ticket_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};