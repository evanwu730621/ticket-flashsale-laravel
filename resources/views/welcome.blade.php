<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel 高併發搶票模擬器</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
        <div class="bg-gradient-to-r from-red-600 to-rose-700 p-6 text-white text-center">
            <h1 class="text-3xl font-extrabold tracking-wide">⚡ Laravel 11 + Redis 秒殺模擬器</h1>
            <p class="text-rose-100 text-sm mt-1">Laravel Queue + Redis Lua + Worker 削峰架構</p>
        </div>

        <div class="p-6 space-y-6">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">2026 世界巡迴演唱會門票</h2>
                    <p class="text-slate-500 text-sm mt-1">活動 ID: <span class="font-mono font-semibold text-slate-700">1</span></p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    ● 火熱搶購中
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">使用者 ID (User ID)</label>
                    <input type="number" id="userId" value="101" 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-red-500 outline-none text-slate-800 font-mono">
                </div>
                <div class="flex items-end space-x-2">
                    <button onclick="randomizeUser()" class="w-1/3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-sm font-semibold transition">
                        隨機
                    </button>
                    <button onclick="buyTicket()" id="buyBtn" class="w-2/3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold transition flex items-center justify-center shadow-md">
                        立即搶票
                    </button>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-200">
                <button onclick="simulateHighTraffic()" id="batchBtn" class="w-full py-2.5 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white font-bold rounded-lg transition shadow">
                    🔥 一鍵模擬 10 人瞬間併發搶票
                </button>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-2">執行日誌與狀態：</h3>
                <div id="logs" class="bg-slate-900 text-emerald-400 font-mono text-xs p-4 rounded-xl h-64 overflow-y-auto space-y-1 shadow-inner">
                    <div class="text-slate-500">> 系統就緒，等待搶票請求...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function log(message, type = 'info') {
            const logs = document.getElementById('logs');
            const time = new Date().toLocaleTimeString();
            let color = 'text-emerald-400';
            if (type === 'error') color = 'text-red-400';
            if (type === 'warn') color = 'text-amber-300';
            if (type === 'process') color = 'text-cyan-300';

            const item = document.createElement('div');
            item.className = color;
            item.innerHTML = `<span class="text-slate-600">[${time}]</span> ${message}`;
            logs.appendChild(item);
            logs.scrollTop = logs.scrollHeight;
        }

        function randomizeUser() {
            const randomId = Math.floor(Math.random() * 9000) + 1000;
            document.getElementById('userId').value = randomId;
            log(`已切換為隨機使用者 ID: ${randomId}`);
        }

        async function buyTicket() {
            const userId = parseInt(document.getElementById('userId').value, 10);
            const btn = document.getElementById('buyBtn');

            btn.disabled = true;
            btn.classList.add('opacity-50');
            log(`[發送請求] 使用者 ${userId} 正在搶票...`, 'process');

            try {
                const res = await fetch('/api/ticket/buy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ ticket_id: 1, user_id: userId })
                });

                const data = await res.json();

                if (res.status === 202) {
                    log(`✔ Redis 受理成功！訂單: ${data.order_sn} (排入 Laravel Queue)`, 'info');
                    pollOrderStatus(data.order_sn, userId);
                } else {
                    log(`✖ 搶購失敗: ${data.message} (HTTP ${res.status})`, 'error');
                    btn.disabled = false;
                    btn.classList.remove('opacity-50');
                }
            } catch (err) {
                log(`系統通訊失敗: ${err.message}`, 'error');
                btn.disabled = false;
                btn.classList.remove('opacity-50');
            }
        }

        async function pollOrderStatus(orderSn, userId, attempt = 1) {
            if (attempt > 10) {
                log(`[查詢逾時] 請稍後查詢`, 'warn');
                document.getElementById('buyBtn').disabled = false;
                document.getElementById('buyBtn').classList.remove('opacity-50');
                return;
            }

            try {
                const res = await fetch(`/api/ticket/order?order_sn=${orderSn}`);
                const data = await res.json();

                if (data.status === 'completed') {
                    log(`🎉 [落庫成功] Laravel Worker 已將訂單 ${orderSn} 寫入 MySQL！`, 'info');
                    document.getElementById('buyBtn').disabled = false;
                    document.getElementById('buyBtn').classList.remove('opacity-50');
                } else {
                    setTimeout(() => pollOrderStatus(orderSn, userId, attempt + 1), 400);
                }
            } catch (err) {
                setTimeout(() => pollOrderStatus(orderSn, userId, attempt + 1), 500);
            }
        }

        async function simulateHighTraffic() {
            const batchBtn = document.getElementById('batchBtn');
            batchBtn.disabled = true;
            batchBtn.classList.add('opacity-50');
            log(`⚡ 發起 10 筆併發秒殺請求...`, 'warn');

            const requests = [];
            for (let i = 0; i < 10; i++) {
                const fakeUserId = Math.floor(Math.random() * 8000) + 2000;
                requests.push(
                    fetch('/api/ticket/buy', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ ticket_id: 1, user_id: fakeUserId })
                    }).then(r => r.json().then(d => ({ status: r.status, data: d, user: fakeUserId })))
                );
            }

            const results = await Promise.all(requests);
            results.forEach(res => {
                if (res.status === 202) {
                    log(`[併發受理] 使用者 ${res.user} -> 排入 Laravel 佇列`, 'info');
                } else {
                    log(`[併發攔截] 使用者 ${res.user} -> ${res.data.message}`, 'error');
                }
            });

            batchBtn.disabled = false;
            batchBtn.classList.remove('opacity-50');
        }
    </script>
</body>
</html>