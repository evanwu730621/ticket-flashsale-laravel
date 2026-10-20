<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class InitTicketStock extends Command
{
    protected $signature = 'ticket:init {id=1} {stock=100}';
    protected $description = '初始化 MySQL 票券並預熱至 Redis 快取';

    public function handle(): int
    {
        $id = (int)$this->argument('id');
        $stock = (int)$this->argument('stock');

        $this->info("=== 開始初始化與快取預熱 ===");

        // 1. 更新或建立 MySQL 票券
        $ticket = Ticket::updateOrCreate(
            ['id' => $id],
            ['title' => '2026 世界巡迴演唱會門票', 'total_stock' => $stock, 'remaining_stock' => $stock]
        );

        // 2. 預熱至 Redis
        $stockKey = "ticket:stock:{$id}";
        $userKey  = "ticket:users:{$id}";

        Redis::set($stockKey, (string)$stock);
        Redis::del($userKey);

        $this->info("✔ MySQL 票券已就緒：ID={$id}, 庫存={$stock}");
        $this->info("✔ Redis 快取 Key [{$stockKey}] 已同步");
        $this->info("=== 預熱完成！可以開搶 ===");

        return 0;
    }
}