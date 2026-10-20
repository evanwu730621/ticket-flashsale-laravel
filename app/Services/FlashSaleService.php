<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class FlashSaleService
{
    private string $luaScript = <<<LUA
        if redis.call('sismember', KEYS[2], ARGV[1]) == 1 then
            return -1
        end
        local stock = tonumber(redis.call('get', KEYS[1]))
        if (not stock) or (stock <= 0) then
            return 0
        end
        redis.call('decr', KEYS[1])
        redis.call('sadd', KEYS[2], ARGV[1])
        return 1
    LUA;

    /**
     * @return array [bool $success, int $httpCode, string $message, ?string $orderSn]
     */
    public function execute(int $ticketId, int $userId): array
    {
        $stockKey = "ticket:stock:{$ticketId}";
        $userKey  = "ticket:users:{$ticketId}";

        // 執行 Lua 原子操作 (2 代表有兩個 KEYS)
        $result = (int) Redis::eval($this->luaScript, 2, $stockKey, $userKey, (string)$userId);

        if ($result === -1) {
            return [false, 400, '您已經搶購過此票券，每人限購一張！', null];
        }

        if ($result === 0) {
            return [false, 410, '很遺憾，票券已全數售罄！', null];
        }

        // 搶購成功，產生訂單編號
        $orderSn = 'ORD' . date('YmdHis') . bin2hex(random_bytes(3));

        return [true, 202, '搶購請求已受理，佇列排隊中！', $orderSn];
    }
}