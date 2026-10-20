<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTicketOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 任務最大嘗試次數
    public int $tries = 3;

    public function __construct(
        public int $ticketId,
        public int $userId,
        public string $orderSn
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // 1. 樂觀鎖扣減 MySQL 庫存 (確保 remaining_stock > 0)
            $affected = Ticket::where('id', $this->ticketId)
                ->where('remaining_stock', '>', 0)
                ->decrement('remaining_stock');

            if ($affected === 0) {
                throw new \Exception("MySQL 庫存不足，訂單 {$this->orderSn} 落庫中止！");
            }

            // 2. 寫入訂單
            Order::create([
                'order_sn'  => $this->orderSn,
                'ticket_id' => $this->ticketId,
                'user_id'   => $this->userId,
                'status'    => 1,
            ]);
        });

        Log::info("訂單落庫成功: {$this->orderSn} (User: {$this->userId})");
    }

    /**
     * 當重試全部失敗時的處置 (死信/補償)
     */
    public function failed(Throwable $exception): void
    {
        Log::error("訂單落庫失敗，觸發異常: {$exception->getMessage()}");
        // 這裡可加發 Slack/Mail 通知工程師
    }
}