<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

// 首頁測試畫面
Route::get('/', function () {
    return view('welcome');
});

// 秒殺 API 路由
Route::post('/api/ticket/buy', [TicketController::class, 'buy']);
Route::get('/api/ticket/order', [TicketController::class, 'checkOrder']);