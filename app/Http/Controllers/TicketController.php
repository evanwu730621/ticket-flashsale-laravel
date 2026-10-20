<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTicketOrder;
use App\Models\Order;
use App\Services\FlashSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    // 搶票 API
    public function buy(Request $request, FlashSaleService $flashSaleService): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => 'required|integer',
            'user_id'   => 'required|integer',
        ]);

        [$success, $code, $message, $orderSn] = $flashSaleService->execute(
            (int)$validated['ticket_id'],
            (int)$validated['user_id']
        );

        if (!$success) {
            return response()->json(['status' => false, 'message' => $message], $code);
        }

        // 推入 Laravel Redis 佇列 (指定 queue 名稱為 ticket_orders)
        ProcessTicketOrder::dispatch(
            (int)$validated['ticket_id'],
            (int)$validated['user_id'],
            $orderSn
        )->onQueue('ticket_orders');

        return response()->json([
            'status'   => true,
            'message'  => $message,
            'order_sn' => $orderSn
        ], 202);
    }

    // 輪詢訂單狀態 API
    public function checkOrder(Request $request): JsonResponse
    {
        $orderSn = $request->query('order_sn');

        if (!$orderSn) {
            return response()->json(['status' => 'error', 'message' => '缺少 order_sn'], 400);
        }

        $order = Order::where('order_sn', $orderSn)->first();

        if ($order) {
            return response()->json(['status' => 'completed', 'data' => $order]);
        }

        return response()->json(['status' => 'processing', 'message' => '訂單排隊寫入中...']);
    }
}