# ⚡ Laravel 11 高併發搶票與秒殺系統 (Ticket Flash-Sale)

基於 **PHP 8.3 + Laravel 11 + Redis 訊息佇列 + CLI Worker + MySQL** 的高併發搶票架構，解決瞬間流量衝垮資料庫與超賣問題。

---

## 🌟 核心特色與架構
- **Redis Lua 原子扣庫存**：將「防重複購票檢查」與「庫存扣除」封裝於單一 Lua 腳本，徹底杜絕併發超賣。
- **Laravel Queue 異步削峰**：Web API 扣庫成功後立即將任務推入 Redis 佇列並回傳 HTTP 202，瞬間釋放連線。
- **獨立 CLI Queue Worker**：背景程式定速從 Redis 佇列消費任務，透過 MySQL 交易與樂觀鎖安全落盤。
- **快取預熱 Artisan 指令**：提供 `ticket:init` 指令，活動開跑前自動同步 MySQL 庫存至 Redis，保護資料庫。
- **前端互動模擬器**：內建即時輪詢與一鍵併發搶票面板，直觀驗證佇列削峰效果。

---

## 🚀 快速啟動指南

### 1. 安裝套件
```bash
composer install

2. 環境設定
複製環境變數範本並產生安全金鑰：
cp .env.example .env
php artisan key:generate

請確認 .env 中的連線設定：
DB_DATABASE=flash_sale
QUEUE_CONNECTION=redis
SESSION_DRIVER=file
REDIS_HOST=127.0.0.1

3. 資料庫遷移（自動建表）

php artisan migrate

4. 快取預熱（載入 100 張票至 Redis）

php artisan ticket:init 1 100


5. 啟動系統
請開啟兩個終端機視窗分別執行：
視窗 1（背景 Queue Worker）：
php artisan queue:work redis --queue=ticket_orders

視窗 2（Web 伺服器）：
php artisan serve
6. 瀏覽器測試
開啟瀏覽器訪問：
👉 http://127.0.0.1:8000