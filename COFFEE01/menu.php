<?php
$host = 'localhost';
$dbname = 'coffee_shop_db';
$username = 'root';
$password = '123456789';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // បង្កើតតារាងប្រសិនបើមិនទាន់មាន
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_number VARCHAR(10) NOT NULL DEFAULT 'តុលេខ 1',
        customer_name VARCHAR(100) DEFAULT NULL,
        items_json TEXT NOT NULL,
        total_usd DECIMAL(10,2) NOT NULL,
        total_riel INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $tableNumber = $_POST['table_number'] ?? 'តុលេខ 1';
        $redirectUrl = $_SERVER['PHP_SELF'] . "?table=" . urlencode($tableNumber);

        // 1. រក្សាទុកតុ (ឬលុបការកុម្ម៉ង់ចោលប្រសិនបើកន្ត្រកទទេ)
        if ($action === 'save_table_order') {
            $itemsJson = $_POST['items_json'] ?? '[]';
            $decodedItems = json_decode($itemsJson, true);
            $totalUSD = floatval($_POST['total_usd'] ?? 0);
            $totalRiel = intval($_POST['total_riel'] ?? 0);
            
            $stmtCheck = $pdo->prepare("SELECT id FROM daily_orders WHERE table_number = ? AND status = 'pending' AND DATE(order_date) = CURDATE()");
            $stmtCheck->execute([$tableNumber]);
            $existingOrder = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (empty($decodedItems)) {
                // ប្រសិនបើកន្ត្រកទទេ លុបទិន្នន័យ pending ចេញពី Database
                if ($existingOrder) {
                    $stmtDel = $pdo->prepare("DELETE FROM daily_orders WHERE id = ?");
                    $stmtDel->execute([$existingOrder['id']]);
                }
            } else {
                if ($existingOrder) {
                    $stmtUpdate = $pdo->prepare("UPDATE daily_orders SET items_json = ?, total_usd = ?, total_riel = ? WHERE id = ?");
                    $stmtUpdate->execute([$itemsJson, $totalUSD, $totalRiel, $existingOrder['id']]);
                } else {
                    $stmtInsert = $pdo->prepare("INSERT INTO daily_orders (table_number, items_json, total_usd, total_riel, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmtInsert->execute([$tableNumber, $itemsJson, $totalUSD, $totalRiel]);
                }
            }
            header("Location: " . $redirectUrl);
            exit;
        }

        // 2. ទូទាត់ប្រាក់
        if ($action === 'checkout_order') {
            $itemsJson = $_POST['items_json'] ?? '[]';
            $totalUSD = floatval($_POST['total_usd'] ?? 0);
            $totalRiel = intval($_POST['total_riel'] ?? 0);
            
            $stmtCheck = $pdo->prepare("SELECT id FROM daily_orders WHERE table_number = ? AND status = 'pending' AND DATE(order_date) = CURDATE()");
            $stmtCheck->execute([$tableNumber]);
            $existingOrder = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingOrder) {
                $stmt = $pdo->prepare("UPDATE daily_orders SET status = 'paid', items_json = ?, total_usd = ?, total_riel = ? WHERE id = ?");
                $stmt->execute([$itemsJson, $totalUSD, $totalRiel, $existingOrder['id']]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO daily_orders (table_number, items_json, total_usd, total_riel, status) VALUES (?, ?, ?, ?, 'paid')");
                $stmtInsert->execute([$tableNumber, $itemsJson, $totalUSD, $totalRiel]);
            }
            header("Location: " . $redirectUrl);
            exit;
        }

        // 3. កត់ត្រាជំពាក់
        if ($action === 'debt_order') {
            $itemsJson = $_POST['items_json'] ?? '[]';
            $totalUSD = floatval($_POST['total_usd'] ?? 0);
            $totalRiel = intval($_POST['total_riel'] ?? 0);
            $customerName = trim($_POST['customer_name'] ?? 'អតិថិជនមិនស្គាល់ឈ្មោះ');
            
            $stmtCheck = $pdo->prepare("SELECT id FROM daily_orders WHERE table_number = ? AND status = 'pending' AND DATE(order_date) = CURDATE()");
            $stmtCheck->execute([$tableNumber]);
            $existingOrder = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingOrder) {
                $stmt = $pdo->prepare("UPDATE daily_orders SET status = 'debt', customer_name = ?, items_json = ?, total_usd = ?, total_riel = ? WHERE id = ?");
                $stmt->execute([$customerName, $itemsJson, $totalUSD, $totalRiel, $existingOrder['id']]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO daily_orders (table_number, customer_name, items_json, total_usd, total_riel, status) VALUES (?, ?, ?, ?, ?, 'debt')");
                $stmtInsert->execute([$tableNumber, $customerName, $itemsJson, $totalUSD, $totalRiel]);
            }
            header("Location: " . $redirectUrl);
            exit;
        }

        // 4. សងប្រាក់ជំពាក់ (ប្តូរពី debt ទៅ paid) - មុខងារថ្មី
        if ($action === 'pay_debt_order') {
            $orderId = intval($_POST['order_id'] ?? 0);
            $stmtPay = $pdo->prepare("UPDATE daily_orders SET status = 'paid', order_date = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtPay->execute([$orderId]);
            header("Location: " . $redirectUrl);
            exit;
        }

        // 5. លុបប្រវត្តិការលក់
        if ($action === 'delete_order') {
            $orderId = intval($_POST['order_id'] ?? 0);
            $stmtDelete = $pdo->prepare("DELETE FROM daily_orders WHERE id = ?");
            $stmtDelete->execute([$orderId]);
            header("Location: " . $redirectUrl);
            exit;
        }

        // 6. កែសម្រួលប្រវត្តិការលក់
        if ($action === 'edit_order') {
            $orderId = intval($_POST['order_id'] ?? 0);
            $newTable = $_POST['table_number'] ?? 'តុលេខ 1';
            $newCustomer = trim($_POST['customer_name'] ?? '');
            $newUSD = floatval($_POST['total_usd'] ?? 0);
            $newRiel = intval($_POST['total_riel'] ?? 0);

            $stmtEdit = $pdo->prepare("UPDATE daily_orders SET table_number = ?, customer_name = ?, total_usd = ?, total_riel = ? WHERE id = ?");
            $stmtEdit->execute([$newTable, $newCustomer ?: null, $newUSD, $newRiel, $orderId]);
            header("Location: " . $redirectUrl);
            exit;
        }
    }

    // ទាញទិន្នន័យសរុបដែលលក់បានថ្ងៃនេះ
    $stmtDaily = $pdo->query("SELECT SUM(total_usd) as sum_usd, SUM(total_riel) as sum_riel FROM daily_orders WHERE status = 'paid' AND DATE(order_date) = CURDATE()");
    $initialDaily = $stmtDaily->fetch(PDO::FETCH_ASSOC);
    $initUSD = floatval($initialDaily['sum_usd'] ?? 0);

    // ទាញទិន្នន័យជំពាក់សរុប (គ្រប់ថ្ងៃទាំងអស់ ដើម្បីកុំឱ្យបាត់បំណុលចាស់)
    $stmtDebtAll = $pdo->query("SELECT SUM(total_usd) as sum_usd, SUM(total_riel) as sum_riel FROM daily_orders WHERE status = 'debt'");
    $initialDebt = $stmtDebtAll->fetch(PDO::FETCH_ASSOC);
    $initDebtUSD = floatval($initialDebt['sum_usd'] ?? 0);

    // ទាញបញ្ជីការកុម្ម៉ង់ថ្ងៃនេះ និងបញ្ជីជំពាក់ទាំងអស់
    $stmtOrders = $pdo->query("SELECT * FROM daily_orders WHERE DATE(order_date) = CURDATE() OR status = 'debt' ORDER BY order_date DESC");
    $initialOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . htmlspecialchars($e->getMessage()));
}

$activeTable = $_GET['table'] ?? 'តុលេខ 1';
?>
<!DOCTYPE html>
<html lang="km" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEAP COFFEE និង POS - Modern Coffee Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,300..700;1,300..700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kantumruy Pro', sans-serif; scroll-behavior: smooth; }
        .kh-text { font-family: 'Kantumruy Pro', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #1c1411; }
        ::-webkit-scrollbar-thumb { background: #b45309; border-radius: 4px; }
        .smooth-transition { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="bg-[#1c1411] text-amber-50 h-full flex flex-col antialiased selection:bg-amber-600 selection:text-white">

    <header class="bg-[#261b17]/90 backdrop-blur-md border-b border-amber-900/40 sticky top-0 z-50 px-4 lg:px-8 py-3 flex items-center justify-between shadow-xl">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-600 to-yellow-500 flex items-center justify-center shadow-lg shadow-amber-600/30 text-white font-bold text-xl">☕</div>
            <div>
                <h1 class="font-bold text-lg text-amber-100 tracking-wide kh-text"> LEAP COFFEE <span class="text-xs px-2 py-0.5 bg-amber-900/60 text-amber-300 rounded-full border border-amber-700/40 ml-1">POS System</span></h1>
                <p class="text-xs text-amber-400/80">Traditional Taste & Modern Experience</p>
            </div>
        </div>
        <div class="flex items-center space-x-2.5">
            <div class="flex items-center space-x-2 bg-[#1c1411] px-3.5 py-1.5 rounded-xl border border-amber-900/50 text-xs text-amber-300">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="kh-text">លក់ថ្ងៃនេះ: <strong class="text-amber-200">$<?= number_format($initUSD, 2) ?></strong> | ជំពាក់សរុប: <strong class="text-rose-400">$<?= number_format($initDebtUSD, 2) ?></strong></span>
            </div>

            <button onclick="openSalesModal()" class="text-xs bg-amber-700 hover:bg-amber-600 text-white px-3.5 py-2 rounded-xl smooth-transition kh-text shadow-md flex items-center space-x-1 cursor-pointer">
                <span>📋 ប្រវត្តិលក់</span>
            </button>

            <button onclick="openDebtListModal()" class="text-xs bg-rose-900 hover:bg-rose-800 text-white px-3.5 py-2 rounded-xl smooth-transition kh-text shadow-md flex items-center space-x-1 cursor-pointer">
                <span>📝 បញ្ជីជំពាក់</span>
            </button>

            <button onclick="toggleCartDrawerMobile()" class="relative md:hidden bg-amber-600 hover:bg-amber-500 text-white p-2.5 rounded-xl smooth-transition shadow-md flex items-center justify-center active:scale-95 cursor-pointer">
                🛒
                <span id="mobileCartCount" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center font-bold">0</span>
            </button>
        </div>
    </header>

    <div class="flex-1 flex flex-col md:flex-row overflow-hidden relative">
        <main class="flex-1 flex flex-col h-full overflow-y-auto p-4 lg:p-6 bg-gradient-to-b from-[#1c1411] to-[#16100d]">
            
            <div class="mb-6 bg-[#261b17] border border-amber-900/50 rounded-2xl p-4 shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-amber-200 kh-text flex items-center space-x-2">
                        <span>🪑</span> <span>គ្រប់គ្រងតុទាំង 8 (Tables Status):</span>
                    </h3>
                    <span class="text-xs text-amber-400 kh-text">តុបច្ចុប្បន្ន: <strong id="selectedTableBadge" class="text-white bg-amber-700 px-2 py-0.5 rounded-lg"><?= htmlspecialchars($activeTable) ?></strong></span>
                </div>
                <div id="tableButtonsContainer" class="grid grid-cols-4 sm:grid-cols-8 gap-2"></div>
            </div>

            <!-- Category Filter Buttons -->
            <div class="mb-6 bg-[#261b17] border border-amber-900/50 p-3 rounded-2xl shadow-lg flex flex-wrap gap-2">
                <button onclick="filterCategory('all')" id="cat-btn-all" class="cat-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-medium smooth-transition bg-amber-600 text-white shadow-md kh-text active:scale-95 cursor-pointer">ទាំងអស់ (All)</button>
                <button onclick="filterCategory('coffee')" id="cat-btn-coffee" class="cat-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-medium smooth-transition bg-[#1c1411] hover:bg-amber-900/50 text-amber-200 border border-amber-900/60 kh-text active:scale-95 cursor-pointer">☕ កាហ្វេ (Coffee)</button>
                <button onclick="filterCategory('tea')" id="cat-btn-tea" class="cat-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-medium smooth-transition bg-[#1c1411] hover:bg-amber-900/50 text-amber-200 border border-amber-900/60 kh-text active:scale-95 cursor-pointer">🍵 ប្រភេទតែ (Tea)</button>
                <button onclick="filterCategory('drink')" id="cat-btn-drink" class="cat-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-medium smooth-transition bg-[#1c1411] hover:bg-amber-900/50 text-amber-200 border border-amber-900/60 kh-text active:scale-95 cursor-pointer">🥤 ភេសជ្ជៈ (Drinks)</button>
                <button onclick="filterCategory('food')" id="cat-btn-food" class="cat-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-medium smooth-transition bg-[#1c1411] hover:bg-amber-900/50 text-amber-200 border border-amber-900/60 kh-text active:scale-95 cursor-pointer">🍛 ម្ហូបអាហារ (Food)</button>
            </div>

            <div class="flex items-center justify-between mb-6 gap-4">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-amber-500">🔍</span>
                    <input type="text" id="searchInput" oninput="debounceSearch()" placeholder="ស្វែងរកឈ្មោះមុខទំនិញ... (Search menu)" class="w-full pl-10 pr-4 py-2.5 bg-[#261b17] border border-amber-900/50 rounded-xl text-sm text-amber-100 placeholder-amber-500/50 focus:outline-none focus:border-amber-500 smooth-transition kh-text">
                </div>
            </div>

            <div id="productGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 pb-12"></div>
        </main>

        <aside id="cartDrawer" class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-[#221713] border-l border-amber-900/50 flex flex-col transform translate-x-full md:translate-x-0 md:static smooth-transition duration-300 shadow-2xl">
            <div class="p-4 border-b border-amber-900/40 flex items-center justify-between bg-[#2a1d17]">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">🛍️</span>
                    <h3 class="font-bold text-lg text-amber-100 kh-text">កន្ត្រកទំនិញ (<span id="cartTableLabel"><?= htmlspecialchars($activeTable) ?></span>)</h3>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="saveTableOrder()" class="text-xs bg-amber-700 hover:bg-amber-600 text-white px-2.5 py-1 rounded-lg smooth-transition kh-text shadow cursor-pointer">💾 រក្សាទុកតុ</button>
                    <button onclick="clearCart()" class="text-xs text-rose-400 hover:text-rose-300 bg-rose-950/40 border border-rose-900/40 px-2.5 py-1 rounded-lg smooth-transition kh-text cursor-pointer">សម្អាត</button>
                    <button onclick="toggleCartDrawerMobile()" class="md:hidden text-amber-400 hover:text-white p-1 cursor-pointer">✕</button>
                </div>
            </div>

            <div id="cartItemsContainer" class="flex-1 overflow-y-auto p-4 space-y-3 divide-y divide-amber-900/20"></div>

            <div class="p-4 bg-[#261b17] border-t border-amber-900/40 space-y-3">
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between text-amber-300/80">
                        <span class="kh-text">ទឹកប្រាក់ជារៀល (Riel):</span>
                        <span id="cartTotalRiel" class="font-semibold text-amber-200">0 ៛</span>
                    </div>
                    <div class="flex justify-between text-base font-bold text-amber-100 pt-2 border-t border-amber-900/40">
                        <span class="kh-text">ទឹកប្រាក់សរុប (USD):</span>
                        <span id="cartTotalUSD" class="text-amber-400 text-lg">$0.00</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-1.5">
                    <button onclick="saveTableOrder()" class="py-2.5 bg-amber-800 hover:bg-amber-700 text-white font-bold rounded-xl shadow smooth-transition kh-text text-[11px] cursor-pointer" title="រក្សាទុកបណ្តោះអាសន្ន">
                        💾 រក្សាទុក
                    </button>
                    <button onclick="openDebtModal()" id="debtBtn" disabled class="py-2.5 bg-rose-900 hover:bg-rose-800 text-white font-bold rounded-xl shadow smooth-transition disabled:opacity-50 disabled:cursor-not-allowed kh-text text-[11px] cursor-pointer" title="កត់ត្រាជំពាក់ប្រាក់">
                        📝 ជំពាក់
                    </button>
                    <button onclick="openCheckoutModal()" id="checkoutBtn" disabled class="py-2.5 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-500 hover:to-green-500 text-white font-bold rounded-xl shadow-lg smooth-transition disabled:opacity-50 disabled:cursor-not-allowed kh-text text-[11px] cursor-pointer" title="ទូទាត់ប្រាក់ស្រេច">
                        💳 ទូទាត់
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <form id="posActionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="table_number" id="formTableNumber">
        <input type="hidden" name="customer_name" id="formCustomerName">
        <input type="hidden" name="items_json" id="formItemsJson">
        <input type="hidden" name="total_usd" id="formTotalUSD">
        <input type="hidden" name="total_riel" id="formTotalRiel">
        <input type="hidden" name="order_id" id="formOrderId">
    </form>

    <!-- Modal: រើស Options & Toppings -->
    <div id="optionsModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-[#261b17] border border-amber-900/60 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-amber-900/40 pb-3">
                <h3 class="text-base font-bold text-amber-100 kh-text">⚙️ ជម្រើស និងការបន្ថែម (Options)</h3>
                <button onclick="closeOptionsModal()" class="text-amber-400 hover:text-white text-lg font-bold cursor-pointer">✕</button>
            </div>
            <div class="space-y-4 text-xs">
                <div id="sugarSection">
                    <label class="block text-amber-300 mb-1.5 kh-text font-semibold">កម្រិតស្ករ (Sugar Level):</label>
                    <div class="grid grid-cols-5 gap-1.5" id="sugarLevelContainer">
                        <button type="button" onclick="setSugar('0%')" class="sugar-btn py-1.5 bg-[#1c1411] border border-amber-900/50 rounded-lg text-amber-200 hover:bg-amber-800 kh-text active:scale-95">0%</button>
                        <button type="button" onclick="setSugar('25%')" class="sugar-btn py-1.5 bg-[#1c1411] border border-amber-900/50 rounded-lg text-amber-200 hover:bg-amber-800 kh-text active:scale-95">25%</button>
                        <button type="button" onclick="setSugar('50%')" class="sugar-btn py-1.5 bg-amber-600 border border-amber-500 rounded-lg text-white kh-text active:scale-95 font-bold">50%</button>
                        <button type="button" onclick="setSugar('75%')" class="sugar-btn py-1.5 bg-[#1c1411] border border-amber-900/50 rounded-lg text-amber-200 hover:bg-amber-800 kh-text active:scale-95">75%</button>
                        <button type="button" onclick="setSugar('100%')" class="sugar-btn py-1.5 bg-[#1c1411] border border-amber-900/50 rounded-lg text-amber-200 hover:bg-amber-800 kh-text active:scale-95">100%</button>
                    </div>
                </div>

                <div id="shotSection" class="space-y-2">
                    <label class="block text-amber-300 mb-1 kh-text font-semibold">បន្ថែមគ្រាប់កាហ្វេ (Coffee Shots):</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <label class="flex items-center space-x-1.5 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer hover:border-amber-700">
                            <input type="radio" name="shotOption" value="normal" class="accent-amber-600" checked>
                            <span class="text-amber-200 kh-text text-[11px]">កាហ្វេដើម (+$0.00)</span>
                        </label>
                        <label class="flex items-center space-x-1.5 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer hover:border-amber-700">
                            <input type="radio" name="shotOption" value="oneshot" class="accent-amber-600">
                            <span class="text-amber-200 kh-text text-[11px]">1 Shot (+$0.50)</span>
                        </label>
                        <label class="flex items-center space-x-1.5 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer hover:border-amber-700">
                            <input type="radio" name="shotOption" value="twoshots" class="accent-amber-600">
                            <span class="text-amber-200 kh-text text-[11px]">2 Shots (+$1.00)</span>
                        </label>
                    </div>
                </div>

                <div id="coconutSection" class="space-y-2">
                    <label class="block text-amber-300 mb-1 kh-text font-semibold">ទំហំដូង (Coconut Size):</label>
                    <div class="space-y-1.5">
                        <label class="flex items-center space-x-2 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer">
                            <input type="radio" name="coconutSize" value="small" class="accent-amber-600" checked>
                            <span class="flex-1 text-amber-200 kh-text">ដូងតូច (3,500៛ / $0.88)</span>
                        </label>
                        <label class="flex items-center space-x-2 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer">
                            <input type="radio" name="coconutSize" value="medium" class="accent-amber-600">
                            <span class="flex-1 text-amber-200 kh-text">ដូងធំ (4,000៛ / $1.00)</span>
                        </label>
                        <label class="flex items-center space-x-2 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer">
                            <input type="radio" name="coconutSize" value="large" class="accent-amber-600">
                            <span class="flex-1 text-amber-200 kh-text">ដូងធំពិសេស (5,000៛ / $1.25)</span>
                        </label>
                    </div>
                </div>

                <div id="riceSection" class="space-y-2">
                    <label class="block text-amber-300 mb-1 kh-text font-semibold">ជម្រើសបាយ (Rice Options):</label>
                    <div class="space-y-1.5">
                        <label class="flex items-center space-x-2 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer">
                            <input type="radio" name="riceOption" value="normal" class="accent-amber-600" checked>
                            <span class="flex-1 text-amber-200 kh-text">បាយធម្មតា</span>
                        </label>
                        <label class="flex items-center space-x-2 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer">
                            <input type="radio" name="riceOption" value="extra" class="accent-amber-600">
                            <span class="flex-1 text-amber-200 kh-text">បន្ថែមបាយ (+$0.50 / 2,000៛)</span>
                        </label>
                        <label class="flex items-center space-x-2 bg-[#1c1411] p-2 rounded-xl border border-amber-900/40 cursor-pointer">
                            <input type="radio" name="riceOption" value="remove" class="accent-amber-600">
                            <span class="flex-1 text-rose-300 kh-text">បាយលុប (ដកបាយចេញ)</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex space-x-2 pt-2">
                <button onclick="closeOptionsModal()" class="flex-1 py-2 bg-stone-800 text-amber-200 rounded-xl text-xs font-semibold kh-text cursor-pointer">បោះបង់</button>
                <button onclick="confirmAddToCart()" class="flex-1 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold kh-text cursor-pointer">បញ្ជាក់បន្ថែម ✓</button>
            </div>
        </div>
    </div>

    <!-- Modal: បញ្ចូលឈ្មោះអ្នកជំពាក់ -->
    <div id="debtModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-[#261b17] border border-amber-900/60 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-amber-900/40 pb-3">
                <h3 class="text-base font-bold text-amber-100 kh-text">📝 កត់ត្រាឈ្មោះអ្នកជំពាក់</h3>
                <button onclick="closeDebtModal()" class="text-amber-400 hover:text-white text-lg font-bold cursor-pointer">✕</button>
            </div>
            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-amber-300 mb-1 kh-text">ឈ្មោះអតិថិជនជំពាក់ (Customer Name):</label>
                    <input type="text" id="customerNameInput" placeholder="បញ្ចូលឈ្មោះ..." class="w-full bg-[#1c1411] border border-amber-900/50 rounded-xl px-3 py-2.5 text-amber-100 text-sm focus:outline-none focus:border-amber-500">
                </div>
            </div>
            <div class="flex space-x-2 pt-2">
                <button onclick="closeDebtModal()" class="flex-1 py-2 bg-stone-800 text-amber-200 rounded-xl text-xs font-semibold kh-text cursor-pointer">បោះបង់</button>
                <button onclick="submitDebtOrder()" class="flex-1 py-2 bg-rose-900 hover:bg-rose-800 text-white rounded-xl text-xs font-bold kh-text cursor-pointer">បញ្ជាក់ជំពាក់ ✓</button>
            </div>
        </div>
    </div>

    <!-- Modal: ប្រវត្តិការលក់ -->
    <div id="salesModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-[#261b17] border border-amber-900/60 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-5 flex flex-col max-h-[85vh]">
            <div class="flex justify-between items-center border-b border-amber-900/40 pb-3">
                <h3 class="text-lg font-bold text-amber-100 kh-text">📋 បញ្ជីប្រវត្តិការលក់ថ្ងៃនេះ (Paid Orders)</h3>
                <button onclick="closeSalesModal()" class="text-amber-400 hover:text-white text-lg font-bold cursor-pointer">✕</button>
            </div>
            <div class="flex-1 overflow-y-auto space-y-3 pr-1" id="salesListContainer"></div>
            <div class="border-t border-amber-900/40 pt-3 flex justify-end">
                <button onclick="closeSalesModal()" class="px-5 py-2 bg-stone-800 hover:bg-stone-700 text-amber-200 rounded-xl text-sm font-semibold smooth-transition kh-text cursor-pointer">បិទ</button>
            </div>
        </div>
    </div>

    <!-- Modal: បញ្ជីជំពាក់ -->
    <div id="debtListModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-[#261b17] border border-amber-900/60 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-5 flex flex-col max-h-[85vh]">
            <div class="flex justify-between items-center border-b border-amber-900/40 pb-3">
                <h3 class="text-lg font-bold text-rose-300 kh-text">📝 បញ្ជីអតិថិជនជំពាក់ប្រាក់ (All Active Debts)</h3>
                <button onclick="closeDebtListModal()" class="text-amber-400 hover:text-white text-lg font-bold cursor-pointer">✕</button>
            </div>
            <div class="flex-1 overflow-y-auto space-y-3 pr-1" id="debtListContainer"></div>
            <div class="border-t border-amber-900/40 pt-3 flex justify-end">
                <button onclick="closeDebtListModal()" class="px-5 py-2 bg-stone-800 hover:bg-stone-700 text-amber-200 rounded-xl text-sm font-semibold smooth-transition kh-text cursor-pointer">បិទ</button>
            </div>
        </div>
    </div>

    <!-- Modal: កែសម្រួលប្រវត្តិ -->
    <div id="editOrderModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-[#261b17] border border-amber-900/60 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-amber-900/40 pb-3">
                <h3 class="text-base font-bold text-amber-100 kh-text">✏️ កែសម្រួលប្រវត្តិការលក់</h3>
                <button onclick="closeEditModal()" class="text-amber-400 hover:text-white text-lg font-bold cursor-pointer">✕</button>
            </div>
            <input type="hidden" id="editOrderId">
            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-amber-300 mb-1 kh-text">ឈ្មោះតុ:</label>
                    <input type="text" id="editTableNumber" class="w-full bg-[#1c1411] border border-amber-900/50 rounded-xl px-3 py-2 text-amber-100">
                </div>
                <div>
                    <label class="block text-amber-300 mb-1 kh-text">ឈ្មោះអតិថិជន (ប្រសិនបើមាន):</label>
                    <input type="text" id="editCustomerName" class="w-full bg-[#1c1411] border border-amber-900/50 rounded-xl px-3 py-2 text-amber-100">
                </div>
                <div>
                    <label class="block text-amber-300 mb-1 kh-text">ទឹកប្រាក់សរុប (USD):</label>
                    <input type="number" step="0.01" id="editTotalUSD" oninput="syncEditRiel()" class="w-full bg-[#1c1411] border border-amber-900/50 rounded-xl px-3 py-2 text-amber-100">
                </div>
                <div>
                    <label class="block text-amber-300 mb-1 kh-text">ទឹកប្រាក់សរុប (Riel):</label>
                    <input type="number" id="editTotalRiel" class="w-full bg-[#1c1411] border border-amber-900/50 rounded-xl px-3 py-2 text-amber-100">
                </div>
            </div>
            <div class="flex space-x-2 pt-2">
                <button onclick="closeEditModal()" class="flex-1 py-2 bg-stone-800 text-amber-200 rounded-xl text-xs font-semibold kh-text cursor-pointer">បោះបង់</button>
                <button onclick="submitEditOrder()" class="flex-1 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold kh-text cursor-pointer">រក្សាទុក ✓</button>
            </div>
        </div>
    </div>

    <!-- Modal: ទូទាត់ប្រាក់ -->
    <div id="checkoutModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-[#261b17] border border-amber-900/60 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
            <div class="flex justify-between items-center border-b border-amber-900/40 pb-3">
                <h3 class="text-lg font-bold text-amber-100 kh-text">ការទូទាត់ប្រាក់ (<span id="modalTableTitle">តុលេខ 1</span>)</h3>
                <button onclick="closeCheckoutModal()" class="text-amber-400 hover:text-white text-lg font-bold cursor-pointer">✕</button>
            </div>
            <div class="space-y-4">
                <div class="bg-[#1c1411] p-4 rounded-xl border border-amber-900/40 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-amber-300/80 kh-text">សរុបត្រូវបង់ (USD):</span>
                        <span id="modalTotalUSD" class="font-bold text-amber-300">$0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-amber-300/80 kh-text">សរុបត្រូវបង់ (Riel):</span>
                        <span id="modalTotalRiel" class="font-bold text-amber-300">0 ៛</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-amber-300 mb-1.5 kh-text">ប្រាក់ទទួលបានពីអតិថិជន (Cash Received):</label>
                    <div class="flex space-x-2">
                        <input type="number" id="cashReceived" oninput="calculateChange()" placeholder="បញ្ចូលទឹកប្រាក់..." class="flex-1 bg-[#1c1411] border border-amber-900/50 rounded-xl px-4 py-2.5 text-amber-100 text-sm focus:outline-none focus:border-amber-500">
                        <select id="currencyType" onchange="calculateChange()" class="bg-[#1c1411] border border-amber-900/50 rounded-xl px-3 py-2.5 text-amber-300 text-sm">
                            <option value="USD">USD ($)</option>
                            <option value="RIEL">៛ (Riel)</option>
                        </select>
                    </div>
                </div>
                <div id="changeResult" class="bg-[#1c1411] p-3 rounded-xl border border-amber-900/45 text-sm hidden">
                    <div class="flex justify-between text-emerald-400 font-bold">
                        <span class="kh-text">ប្រាក់អាប់សល់ (Change):</span>
                        <span id="changeAmount">$0.00 / 0 ៛</span>
                    </div>
                </div>
                <div class="flex space-x-3 pt-2">
                    <button onclick="closeCheckoutModal()" class="flex-1 py-2.5 bg-stone-800 hover:bg-stone-700 text-amber-200 rounded-xl text-sm font-semibold kh-text cursor-pointer">បោះបង់</button>
                    <button onclick="completeCheckout()" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold shadow-lg kh-text cursor-pointer">បញ្ជាក់ការទូទាត់ ✓</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const EXCHANGE_RATE = 4000;
        let todayOrders = <?= json_encode($initialOrders) ?>;
        let currentSelectedTable = <?= json_encode($activeTable) ?>;
        let tableCarts = {};
        let currentProductForOptions = null;
        let selectedSugar = '50%';

        // អនុគមន៍ការពារ XSS (HTML Escaping)
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        for (let i = 1; i <= 8; i++) {
            tableCarts[`តុលេខ ${i}`] = [];
        }

        todayOrders.forEach(order => {
            if (order.status === 'pending' && tableCarts[order.table_number]) {
                try {
                    tableCarts[order.table_number] = JSON.parse(order.items_json);
                } catch(e) {}
            }
        });

        if (!tableCarts[currentSelectedTable]) {
            currentSelectedTable = 'តុលេខ 1';
        }
        let cart = tableCarts[currentSelectedTable];

        const menuItems = [
            // --- ប្រភេទកាហ្វេ (Coffee) ---
            { id: 'coffee_01', category: 'coffee', no: 'C01', nameKh: 'កាហ្វេ ទឹកដោះគោ', priceUSD: 1.75, priceRiel: 7000, image: 'https://media.istockphoto.com/id/1409749018/photo/iced-latte-coffee-on-plastic-glass-and-tube-sucking-isolated-white-background-summer-drink.jpg?s=2048x2048&w=is&k=20&c=9ZQiYm3oCOfZt4R1e0McZXoGb2uIUpwjnVzbsaF1tIo=' },
            { id: 'coffee_02', category: 'coffee', no: 'C02', nameKh: 'កាហ្វេទឹកកក', priceUSD: 1.25, priceRiel: 5000, image: 'https://media.istockphoto.com/id/2259827356/photo/iced-black-coffee-americano-in-plastic-cup.jpg?s=2048x2048&w=is&k=20&c=QtSXOsgCwBAAb7jO4JAJkRUC_RzHaeobN7VM6mM7oXc=' },
            { id: 'coffee_03', category: 'coffee', no: 'C03', nameKh: 'Ice Latte', priceUSD: 1.75, priceRiel: 7000, image: 'https://media.istockphoto.com/id/2260062751/photo/iced-caffe-latte.jpg?s=2048x2048&w=is&k=20&c=8YSGgpgafZWUkd_ZJWSNoOSMVEILVo0Qzmksz0rCo7E=' },
            { id: 'coffee_04', category: 'coffee', no: 'C04', nameKh: 'កាហ្វេក្ដៅ', priceUSD: 1.25, priceRiel: 5000, image: 'https://media.istockphoto.com/id/1152767411/photo/cup-of-coffee-latte-isolated-on-white-background-with-clipping-path.jpg?s=2048x2048&w=is&k=20&c=iCRPmhBf201Wt2Sk_Phqb2LuUjUEocr4GjqfejSWPFM=' },
            { id: 'coffee_05', category: 'coffee', no: 'C05', nameKh: 'Espresso', priceUSD: 1.00, priceRiel: 4000, image: 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?auto=format&fit=crop&w=400&q=80' },

            // --- ប្រភេទតែ (Tea) ---
            { id: 'tea_01', category: 'tea', no: 'T01', nameKh: 'តែបៃតង', priceUSD: 1.25, priceRiel: 5000, image: 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=400&q=80' },
            { id: 'tea_02', category: 'tea', no: 'T02', nameKh: 'Matcha', priceUSD: 1.75, priceRiel: 7000, image: 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&w=400&q=80' },
            { id: 'tea_03', category: 'tea', no: 'T03', nameKh: 'តែក្រូចឆ្មា', priceUSD: 1.25, priceRiel: 5000, image: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=400&q=80' },
            { id: 'tea_04', category: 'tea', no: 'T04', nameKh: 'តែក្រហម', priceUSD: 1.25, priceRiel: 5000, image: 'https://images.unsplash.com/photo-1597481499750-3e6b22637e12?auto=format&fit=crop&w=400&q=80' },
            { id: 'tea_05', category: 'tea', no: 'T05', nameKh: 'តែបៃតងក្រឡុក', priceUSD: 2.50, priceRiel: 10000, image: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?auto=format&fit=crop&w=400&q=80' },

            // --- ភេសជ្ជៈផ្សេងៗ (Drinks) ---
            { id: 'drink_01', category: 'drink', no: 'D01', nameKh: 'ទឹកដោះគោស្រស់', priceUSD: 1.50, priceRiel: 6000, image: 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_02', category: 'drink', no: 'D02', nameKh: 'សារ៉ូដោះគោ', priceUSD: 1.75, priceRiel: 7000, image: 'https://images.unsplash.com/photo-1541658016709-82535e94bc69?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_03', category: 'drink', no: 'D03', nameKh: 'អូវ៉ាន់ទីន', priceUSD: 1.25, priceRiel: 5000, image: 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_04', category: 'drink', no: 'D04', nameKh: 'អូវ៉ាន់ទីនក្រឡុក', priceUSD: 2.25, priceRiel: 9000, image: 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_05', category: 'drink', no: 'D05', nameKh: 'កូកា', priceUSD: 0.75, priceRiel: 3000, image: 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_06', category: 'drink', no: 'D06', nameKh: 'បាកាស', priceUSD: 0.88, priceRiel: 3500, image: 'https://images.unsplash.com/photo-1622543925917-763c34d1a86e?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_07', category: 'drink', no: 'D07', nameKh: 'ដូងក្រឡុក', priceUSD: 2.50, priceRiel: 10000, image: 'https://images.unsplash.com/photo-1525385133512-2f3bdd039054?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_08', category: 'drink', no: 'D08', nameKh: 'ប័រក្រឡុក', priceUSD: 2.50, priceRiel: 10000, image: 'https://images.unsplash.com/photo-1546173159-315724a31696?auto=format&fit=crop&w=400&q=80' },
            { id: 'drink_09', category: 'drink', no: 'D09', nameKh: 'ដូង', priceUSD: 1.00, priceRiel: 4000, image: 'https://media.istockphoto.com/id/178510758/photo/coconut.jpg?s=2048x2048&w=is&k=20&c=6oeg0ZjLdCB9VrqAfw0kuUyaOivVI2A2E6ZrqiIxS-4=' },
            { id: 'drink_10', category: 'drink', no: 'D10', nameKh: 'ផាសិន ទឹកដោះគោ', priceUSD: 1.75, priceRiel: 7000, image: 'https://media.istockphoto.com/id/1536520278/photo/pure-passion-alcoholic-cocktail-with-syrup-lime-orange-juice-soda-ice-and-passion-fruit.jpg?s=2048x2048&w=is&k=20&c=RtRqvSeuJriVH8AD3V7y4q8E15ue52BQnCttAbVcl6k=' },
            { id: 'drink_11', category: 'drink', no: 'D11', nameKh: 'ផាសិន សូដា', priceUSD: 1.50, priceRiel: 6000, image: 'https://media.istockphoto.com/id/1403087288/photo/a-glass-of-iced-passion-fruit-soda-with-lemon-and-passion-fruit-half-slice-on-grey-background.jpg?s=2048x2048&w=is&k=20&c=Z8uvssL9aw8V_zI-BB1XKi0xqufzshYuRffh-_T96O4=' },
            { id: 'drink_12', category: 'drink', no: 'D12', nameKh: 'ទឹកសុទ្ធ', priceUSD: 0.25, priceRiel: 1000, image: 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?auto=format&fit=crop&w=400&q=80' },

            // --- ម្ហូបអាហារ (Food) ---
            { id: 'food_01', category: 'food', no: 'F01', nameKh: 'បាយឆាសាច់គោ', priceUSD: 2.00, priceRiel: 8000, image: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=400&q=80' },
            { id: 'food_02', category: 'food', no: 'F02', nameKh: 'បាយឆាពងមាន់', priceUSD: 1.25, priceRiel: 5000, image: 'https://media.istockphoto.com/id/1390969031/photo/close-up-asian-chicken-fried-rice-popular-take-out-food.jpg?s=2048x2048&w=is&k=20&c=sxQ3IbHPBuKf8xoTZBplhui5tm1RnU1xhs1P3ytW4Nk=' },
            { id: 'food_03', category: 'food', no: 'F03', nameKh: 'បាយឆាឡុកឡាក់សាច់គោ', priceUSD: 3.00, priceRiel: 12000, image: 'https://media.istockphoto.com/id/873821694/photo/beef-lok-lak-with-rice-cambodian-cuisine.jpg?s=2048x2048&w=is&k=20&c=b8f87A9_mhocr8B78lEfRG3UNYvconFBAMIN-oi38Yg=' },
            { id: 'food_04', category: 'food', no: 'F04', nameKh: 'បាយត្រីចៀន', priceUSD: 2.50, priceRiel: 10000, image: 'https://media.istockphoto.com/id/1440067818/photo/grilled-whole-sea-bream-with-rice-tomato-and-lemon-served-in-dish-isolated-on-table-side-view.jpg?s=2048x2048&w=is&k=20&c=-hKllXif9emYg4hmVBDZpV_uxbLiu3ZlEA705EZgFWE=' },
            { id: 'food_05', category: 'food', no: 'F05', nameKh: 'បាយមាន់ចៀន', priceUSD: 3.00, priceRiel: 12000, image: 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?auto=format&fit=crop&w=400&q=80' },
            { id: 'food_06', category: 'food', no: 'F06', nameKh: 'បាយផ្អកចៀន', priceUSD: 1.75, priceRiel: 7000, image: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=400&q=80' },
            { id: 'food_07', category: 'food', no: 'F07', nameKh: 'បាយស្ងោរសាច់មាន់', priceUSD: 3.00, priceRiel: 12000, image: 'https://images.unsplash.com/photo-1569058242253-92a9c755a0ec?auto=format&fit=crop&w=400&q=80' },
            { id: 'food_08', category: 'food', no: 'F08', nameKh: 'នំក្រូសង់', priceUSD: 1.00, priceRiel: 4000, image: 'https://media.istockphoto.com/id/2226025641/photo/isolated-fresh-baked-croissant-french-pastry.jpg?s=2048x2048&w=is&k=20&c=YbiM2nWI8iGMVv8nbViknO80WJsY1uEKhJR31IEt9y4=' },
            { id: 'food_09', category: 'food', no: 'F09', nameKh: 'នំខុកគី', priceUSD: 1.50, priceRiel: 5000, image: 'https://media.istockphoto.com/id/1087483522/photo/homemade-lemon-curd-in-tartlets-with-fresh-lemon-and-mint-leaves.jpg?s=1024x1024&w=is&k=20&c=oPDLWHSNMKWMolGfFLoVL1GHekyjou171rLCuFvd5yQ=' }
        ];

        window.onload = function() {
            renderTableButtons();
            renderProducts(menuItems);
            updateCartUI();
        };

        function renderTableButtons() {
            const container = document.getElementById('tableButtonsContainer');
            let html = '';
            for (let i = 1; i <= 8; i++) {
                let tableName = `តុលេខ ${i}`;
                let isActive = currentSelectedTable === tableName;
                let hasItems = tableCarts[tableName] && tableCarts[tableName].length > 0;
                
                let btnClass = isActive 
                    ? 'bg-amber-600 text-white border-amber-500 shadow-md shadow-amber-600/30' 
                    : (hasItems ? 'bg-amber-950/80 text-amber-300 border-amber-600/60 ring-1 ring-amber-500/50' : 'bg-[#1c1411] text-amber-200 border-amber-900/60 hover:bg-amber-900/40');

                html += `
                    <button onclick="selectTable('${tableName}')" class="py-2.5 px-2 rounded-xl text-xs font-bold kh-text smooth-transition border relative ${btnClass} cursor-pointer">
                        🪑 តុ ${i}
                        ${hasItems ? '<span class="absolute -top-1 -right-1 w-3 h-3 bg-amber-400 rounded-full animate-ping"></span>' : ''}
                    </button>
                `;
            }
            container.innerHTML = html;
        }

        function selectTable(tableName) {
            tableCarts[currentSelectedTable] = cart;
            currentSelectedTable = tableName;
            cart = tableCarts[tableName];

            document.getElementById('selectedTableBadge').textContent = tableName;
            document.getElementById('cartTableLabel').textContent = tableName;
            renderTableButtons();
            updateCartUI();
        }

        function renderProducts(items) {
            const grid = document.getElementById('productGrid');
            grid.innerHTML = items.map(item => `
                <div class="bg-[#261b17] border border-amber-900/40 rounded-2xl overflow-hidden hover:border-amber-600/60 smooth-transition group flex flex-col justify-between shadow-xl">
                    <div class="relative h-36 overflow-hidden bg-stone-900">
                        <img src="${item.image}" onerror="this.src='https://placehold.co/400x300/261b17/fbbf24?text=LEAP+COFFEE'" alt="${escapeHtml(item.nameKh)}" class="w-full h-full object-cover group-hover:scale-105 smooth-transition opacity-90">
                        <span class="absolute top-2 left-2 bg-black/60 text-amber-300 text-xs px-2 py-0.5 rounded font-bold">${item.no}</span>
                    </div>
                    <div class="p-3.5 flex-1 flex flex-col justify-between space-y-2">
                        <h4 class="font-bold text-amber-100 text-sm kh-text">${escapeHtml(item.nameKh)}</h4>
                        <div class="flex items-center justify-between pt-1 border-t border-amber-900/40">
                            <div>
                                <div class="text-amber-400 font-extrabold text-xs">$${item.priceUSD.toFixed(2)}</div>
                                <div class="text-amber-200/60 text-[10px]">${item.priceRiel.toLocaleString()} ៛</div>
                            </div>
                            <button onclick="openOptionsModal('${item.id}')" class="bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow kh-text active:scale-95 cursor-pointer">+ បន្ថែម</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function filterCategory(cat) {
            document.querySelectorAll('.cat-btn').forEach(btn => {
                btn.classList.remove('bg-amber-600', 'text-white', 'shadow-md');
                btn.classList.add('bg-[#1c1411]', 'text-amber-200');
            });
            let activeBtn = document.getElementById(`cat-btn-${cat}`);
            if(activeBtn) {
                activeBtn.classList.remove('bg-[#1c1411]', 'text-amber-200');
                activeBtn.classList.add('bg-amber-600', 'text-white', 'shadow-md');
            }

            let filtered = cat === 'all' ? menuItems : menuItems.filter(i => i.category === cat);
            renderProducts(filtered);
        }

        function debounceSearch() {
            let q = document.getElementById('searchInput').value.toLowerCase().trim();
            let filtered = menuItems.filter(i => i.nameKh.toLowerCase().includes(q) || i.no.toLowerCase().includes(q));
            renderProducts(filtered);
        }

        function openOptionsModal(id) {
            currentProductForOptions = menuItems.find(i => i.id === id);
            if (!currentProductForOptions) return;

            let isCoffee = currentProductForOptions.category === 'coffee';
            let isTea = currentProductForOptions.category === 'tea';
            let isCoconut = currentProductForOptions.id === 'drink_09';
            let isRiceFood = currentProductForOptions.category === 'food' && currentProductForOptions.nameKh.includes('បាយ');

            // ប្រសិនបើទំនិញគ្មានជម្រើសបន្ថែមទេ បញ្ចូលទៅក្នុងកន្ត្រកផ្ទាល់តែម្តង
            if (!isCoffee && !isTea && !isCoconut && !isRiceFood) {
                addItemDirectly(currentProductForOptions, currentProductForOptions.priceUSD, currentProductForOptions.priceRiel, '');
                return;
            }

            selectedSugar = '50%';
            updateSugarButtons();

            let sugarSec = document.getElementById('sugarSection');
            let shotSec = document.getElementById('shotSection');
            let coconutSec = document.getElementById('coconutSection');
            let riceSec = document.getElementById('riceSection');

            sugarSec.style.display = (isCoffee || isTea) ? 'block' : 'none';
            shotSec.style.display = isCoffee ? 'block' : 'none';
            coconutSec.style.display = isCoconut ? 'block' : 'none';
            riceSec.style.display = isRiceFood ? 'block' : 'none';

            if (isCoffee) {
                let normalShot = document.querySelector('input[name="shotOption"][value="normal"]');
                if(normalShot) normalShot.checked = true;
            }
            if (isCoconut) {
                let firstCoconut = document.querySelector('input[name="coconutSize"][value="small"]');
                if(firstCoconut) firstCoconut.checked = true;
            }
            if (isRiceFood) {
                let firstRice = document.querySelector('input[name="riceOption"][value="normal"]');
                if(firstRice) firstRice.checked = true;
            }

            document.getElementById('optionsModal').classList.remove('hidden');
            document.getElementById('optionsModal').classList.add('flex');
        }

        function closeOptionsModal() {
            document.getElementById('optionsModal').classList.remove('flex');
            document.getElementById('optionsModal').classList.add('hidden');
        }

        function setSugar(level) {
            selectedSugar = level;
            updateSugarButtons();
        }

        function updateSugarButtons() {
            document.querySelectorAll('.sugar-btn').forEach(btn => {
                if (btn.textContent.trim() === selectedSugar) {
                    btn.className = 'sugar-btn py-1.5 bg-amber-600 border border-amber-500 rounded-lg text-white kh-text active:scale-95 font-bold';
                } else {
                    btn.className = 'sugar-btn py-1.5 bg-[#1c1411] border border-amber-900/50 rounded-lg text-amber-200 hover:bg-amber-800 kh-text active:scale-95';
                }
            });
        }

        function addItemDirectly(product, finalPriceUSD, finalPriceRiel, optionsStr) {
            let cartItemId = `${product.id}_${btoa(encodeURIComponent(optionsStr))}`;
            const existing = cart.find(item => item.cartItemId === cartItemId);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    cartItemId: cartItemId,
                    id: product.id,
                    nameKh: product.nameKh,
                    priceUSD: finalPriceUSD,
                    priceRiel: finalPriceRiel,
                    optionsText: optionsStr,
                    quantity: 1
                });
            }
            tableCarts[currentSelectedTable] = cart;
            updateCartUI();
            renderTableButtons();
        }

        function confirmAddToCart() {
            if (!currentProductForOptions) return;

            let finalPriceUSD = currentProductForOptions.priceUSD;
            let finalPriceRiel = currentProductForOptions.priceRiel;
            let toppingsDesc = [];

            if (currentProductForOptions.category === 'coffee' || currentProductForOptions.category === 'tea') {
                toppingsDesc.push(`ស្ករ: ${selectedSugar}`);
            }

            if (currentProductForOptions.category === 'coffee') {
                let shotEl = document.querySelector('input[name="shotOption"]:checked');
                if (shotEl) {
                    let shotVal = shotEl.value;
                    if (shotVal === 'oneshot') {
                        finalPriceUSD += 0.50;
                        finalPriceRiel += 2000;
                        toppingsDesc.push('Add One Shot');
                    } else if (shotVal === 'twoshots') {
                        finalPriceUSD += 1.00;
                        finalPriceRiel += 4000;
                        toppingsDesc.push('Add Two Shots');
                    } else {
                        toppingsDesc.push('កាហ្វេដើម');
                    }
                }
            }

            if (currentProductForOptions.id === 'drink_09') {
                let coconutEl = document.querySelector('input[name="coconutSize"]:checked');
                if (coconutEl) {
                    let sizeVal = coconutEl.value;
                    if (sizeVal === 'small') {
                        finalPriceUSD = 0.88; finalPriceRiel = 3500;
                        toppingsDesc.push('ដូងតូច');
                    } else if (sizeVal === 'medium') {
                        finalPriceUSD = 1.00; finalPriceRiel = 4000;
                        toppingsDesc.push('ដូងធំ');
                    } else if (sizeVal === 'large') {
                        finalPriceUSD = 1.25; finalPriceRiel = 5000;
                        toppingsDesc.push('ដូងធំពិសេស');
                    }
                }
            }

            if (currentProductForOptions.category === 'food' && document.getElementById('riceSection').style.display !== 'none') {
                let riceEl = document.querySelector('input[name="riceOption"]:checked');
                if (riceEl) {
                    let riceVal = riceEl.value;
                    if (riceVal === 'extra') {
                        finalPriceUSD += 0.50;
                        finalPriceRiel += 2000;
                        toppingsDesc.push('បន្ថែមបាយ (Extra Rice)');
                    } else if (riceVal === 'remove') {
                        toppingsDesc.push('បាយលុប (ដកបាយចេញ)');
                    }
                }
            }

            let optionsStr = toppingsDesc.join(' | ');
            addItemDirectly(currentProductForOptions, finalPriceUSD, finalPriceRiel, optionsStr);
            closeOptionsModal();
        }

        function updateQuantity(cartItemId, change) {
            const item = cart.find(i => i.cartItemId === cartItemId);
            if (!item) return;
            item.quantity += change;
            if (item.quantity <= 0) {
                cart = cart.filter(i => i.cartItemId !== cartItemId);
            }
            tableCarts[currentSelectedTable] = cart;
            updateCartUI();
            renderTableButtons();
        }

        function clearCart() {
            if (cart.length === 0) return;
            if (confirm(`តើអ្នកពិតជាចង់សម្អាតកន្ត្រកសម្រាប់ ${currentSelectedTable} មែនទេ?`)) {
                cart = [];
                tableCarts[currentSelectedTable] = cart;
                updateCartUI();
                renderTableButtons();
                // រក្សាទុកស្ថានភាពទទេទៅក្នុង Database ផងដែរ ប្រសិនបើតុនេះធ្លាប់បានរក្សាទុកពីមុន
                let hadSavedPending = todayOrders.some(o => o.status === 'pending' && o.table_number === currentSelectedTable);
                if (hadSavedPending) {
                    saveTableOrder(true);
                }
            }
        }

        function updateCartUI() {
            const container = document.getElementById('cartItemsContainer');
            const totalRielEl = document.getElementById('cartTotalRiel');
            const totalUSDEl = document.getElementById('cartTotalUSD');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const debtBtn = document.getElementById('debtBtn');
            const mobileCartCount = document.getElementById('mobileCartCount');

            let totalItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            mobileCartCount.textContent = totalItemsCount;

            if (cart.length === 0) {
                container.innerHTML = `<div class="text-center py-12 text-amber-500/60 kh-text"><p class="text-3xl mb-2">🛒</p><p class="text-sm">កន្ត្រកទំនិញរបស់អ្នកទទេរ</p></div>`;
                totalRielEl.textContent = '0 ៛';
                totalUSDEl.textContent = '$0.00';
                checkoutBtn.disabled = true;
                debtBtn.disabled = true;
                return;
            }

            checkoutBtn.disabled = false;
            debtBtn.disabled = false;
            let sumUSD = 0, sumRiel = 0;

            container.innerHTML = cart.map(item => {
                sumUSD += item.priceUSD * item.quantity;
                sumRiel += item.priceRiel * item.quantity;
                return `
                    <div class="py-2.5 flex items-center justify-between space-x-2">
                        <div class="flex-1 pr-1">
                            <h5 class="font-bold text-amber-100 text-xs kh-text">${escapeHtml(item.nameKh)}</h5>
                            <div class="text-[10px] text-amber-300/80 kh-text">${escapeHtml(item.optionsText || '')}</div>
                            <div class="text-[11px] text-amber-400 font-semibold">$${item.priceUSD.toFixed(2)} / ${item.priceRiel.toLocaleString()} ៛</div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="updateQuantity('${item.cartItemId}', -1)" class="w-6 h-6 rounded bg-amber-900/60 text-amber-200 cursor-pointer">-</button>
                            <span class="text-xs font-bold text-amber-100 w-5 text-center">${item.quantity}</span>
                            <button onclick="updateQuantity('${item.cartItemId}', 1)" class="w-6 h-6 rounded bg-amber-900/60 text-amber-200 cursor-pointer">+</button>
                        </div>
                    </div>
                `;
            }).join('');

            totalUSDEl.textContent = `$${sumUSD.toFixed(2)}`;
            totalRielEl.textContent = `${sumRiel.toLocaleString()} ៛`;
        }

        function saveTableOrder(allowEmpty = false) {
            if (cart.length === 0 && !allowEmpty) return;
            let sumUSD = cart.reduce((s, i) => s + (i.priceUSD * i.quantity), 0);
            let sumRiel = cart.reduce((s, i) => s + (i.priceRiel * i.quantity), 0);

            document.getElementById('formAction').value = 'save_table_order';
            document.getElementById('formTableNumber').value = currentSelectedTable;
            document.getElementById('formItemsJson').value = JSON.stringify(cart);
            document.getElementById('formTotalUSD').value = sumUSD;
            document.getElementById('formTotalRiel').value = sumRiel;
            document.getElementById('posActionForm').submit();
        }

        function openDebtModal() {
            if (cart.length === 0) return;
            document.getElementById('customerNameInput').value = '';
            document.getElementById('debtModal').classList.remove('hidden');
            document.getElementById('debtModal').classList.add('flex');
        }

        function closeDebtModal() {
            document.getElementById('debtModal').classList.remove('flex');
            document.getElementById('debtModal').classList.add('hidden');
        }

        function submitDebtOrder() {
            let customerName = document.getElementById('customerNameInput').value.trim();
            if (!customerName) {
                alert('សូមបញ្ចូលឈ្មោះអតិថិជនដែលជំពាក់!');
                return;
            }

            let sumUSD = cart.reduce((s, i) => s + (i.priceUSD * i.quantity), 0);
            let sumRiel = cart.reduce((s, i) => s + (i.priceRiel * i.quantity), 0);

            document.getElementById('formAction').value = 'debt_order';
            document.getElementById('formTableNumber').value = currentSelectedTable;
            document.getElementById('formCustomerName').value = customerName;
            document.getElementById('formItemsJson').value = JSON.stringify(cart);
            document.getElementById('formTotalUSD').value = sumUSD;
            document.getElementById('formTotalRiel').value = sumRiel;
            document.getElementById('posActionForm').submit();
        }

        function openCheckoutModal() {
            if (cart.length === 0) return;
            let sumUSD = cart.reduce((s, i) => s + (i.priceUSD * i.quantity), 0);
            let sumRiel = cart.reduce((s, i) => s + (i.priceRiel * i.quantity), 0);

            document.getElementById('modalTableTitle').textContent = currentSelectedTable;
            document.getElementById('modalTotalUSD').textContent = `$${sumUSD.toFixed(2)}`;
            document.getElementById('modalTotalRiel').textContent = `${sumRiel.toLocaleString()} ៛`;
            document.getElementById('cashReceived').value = '';
            document.getElementById('changeResult').classList.add('hidden');

            document.getElementById('checkoutModal').classList.remove('hidden');
            document.getElementById('checkoutModal').classList.add('flex');
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').classList.remove('flex');
            document.getElementById('checkoutModal').classList.add('hidden');
        }

        function calculateChange() {
            let cash = parseFloat(document.getElementById('cashReceived').value);
            let type = document.getElementById('currencyType').value;
            let sumUSD = cart.reduce((s, i) => s + (i.priceUSD * i.quantity), 0);
            if (isNaN(cash) || cash <= 0) { document.getElementById('changeResult').classList.add('hidden'); return; }

            let changeUSD = type === 'USD' ? cash - sumUSD : (cash / EXCHANGE_RATE) - sumUSD;
            let resEl = document.getElementById('changeResult');
            resEl.classList.remove('hidden');
            document.getElementById('changeAmount').textContent = changeUSD < 0 ? 'ប្រាក់មិនទាន់គ្រប់គ្រាន់' : `$${changeUSD.toFixed(2)} / ${Math.round(changeUSD * EXCHANGE_RATE).toLocaleString()} ៛`;
        }

        function completeCheckout() {
            let sumUSD = cart.reduce((s, i) => s + (i.priceUSD * i.quantity), 0);
            let sumRiel = cart.reduce((s, i) => s + (i.priceRiel * i.quantity), 0);

            document.getElementById('formAction').value = 'checkout_order';
            document.getElementById('formTableNumber').value = currentSelectedTable;
            document.getElementById('formItemsJson').value = JSON.stringify(cart);
            document.getElementById('formTotalUSD').value = sumUSD;
            document.getElementById('formTotalRiel').value = sumRiel;
            document.getElementById('posActionForm').submit();
        }

        function openSalesModal() {
            const container = document.getElementById('salesListContainer');
            let paidOrders = todayOrders.filter(o => o.status === 'paid');

            if (paidOrders.length === 0) {
                container.innerHTML = `<p class="text-center text-amber-500/60 py-8 kh-text">ពុំមានប្រវត្តិការលក់ដែលបានទូទាត់សម្រាប់ថ្ងៃនេះទេ</p>`;
            } else {
                container.innerHTML = paidOrders.map(o => renderOrderCard(o)).join('');
            }
            document.getElementById('salesModal').classList.remove('hidden');
            document.getElementById('salesModal').classList.add('flex');
        }

        function closeSalesModal() {
            document.getElementById('salesModal').classList.remove('flex');
            document.getElementById('salesModal').classList.add('hidden');
        }

        function openDebtListModal() {
            const container = document.getElementById('debtListContainer');
            let debtOrders = todayOrders.filter(o => o.status === 'debt');

            if (debtOrders.length === 0) {
                container.innerHTML = `<p class="text-center text-amber-500/60 py-8 kh-text">ពុំមានបញ្ជីជំពាក់ប្រាក់ទេ</p>`;
            } else {
                container.innerHTML = debtOrders.map(o => renderOrderCard(o)).join('');
            }
            document.getElementById('debtListModal').classList.remove('hidden');
            document.getElementById('debtListModal').classList.add('flex');
        }

        function closeDebtListModal() {
            document.getElementById('debtListModal').classList.remove('flex');
            document.getElementById('debtListModal').classList.add('hidden');
        }

        function renderOrderCard(o) {
            let itemsList = [];
            try {
                itemsList = JSON.parse(o.items_json);
            } catch(e) {}

            let itemsHtml = itemsList.map(item => `
                <div class="text-[11px] text-amber-100/90 flex justify-between border-b border-amber-900/20 py-1">
                    <div>
                        <span class="kh-text font-bold">- ${escapeHtml(item.nameKh)} (ចំនួន: ${item.quantity})</span>
                        ${item.optionsText ? `<div class="text-[10px] text-amber-300/70 ml-3">${escapeHtml(item.optionsText)}</div>` : ''}
                    </div>
                    <span class="font-semibold text-amber-300">$${(item.priceUSD * item.quantity).toFixed(2)}</span>
                </div>
            `).join('');

            let safeCustomerName = escapeHtml(o.customer_name || 'អត់ឈ្មោះ');
            let statusBadge = o.status === 'paid' 
                ? '<span class="px-2 py-0.5 bg-emerald-950 text-emerald-400 border border-emerald-900 rounded text-[10px]">បានទូទាត់ (Paid)</span>'
                : `<span class="px-2 py-0.5 bg-rose-950 text-rose-400 border border-rose-900 rounded text-[10px]">ជំពាក់: <strong>${safeCustomerName}</strong></span>`;

            let payDebtBtn = o.status === 'debt'
                ? `<button onclick="payDebtOrder(${o.id})" class="px-2 py-0.5 bg-emerald-700 hover:bg-emerald-600 text-white rounded-lg text-[11px] kh-text cursor-pointer">💵 សងប្រាក់</button>`
                : '';

            return `
                <div class="bg-[#1c1411] border border-amber-900/40 p-3.5 rounded-xl space-y-2">
                    <div class="flex items-center justify-between border-b border-amber-900/30 pb-2">
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-bold text-amber-200 kh-text">${escapeHtml(o.table_number)}</span>
                            ${statusBadge}
                            <span class="text-[10px] text-amber-500/70">${escapeHtml(o.order_date || '')}</span>
                        </div>
                        <div class="flex items-center space-x-1.5">
                            ${payDebtBtn}
                            <button onclick="openEditModal(${o.id})" class="px-2 py-0.5 bg-amber-700 hover:bg-amber-600 text-white rounded-lg text-[11px] kh-text cursor-pointer">✏️ កែប្រែ</button>
                            <button onclick="deleteOrder(${o.id})" class="px-2 py-0.5 bg-rose-950/80 hover:bg-rose-900 text-rose-300 border border-rose-900/50 rounded-lg text-[11px] kh-text cursor-pointer">🗑️ លុប</button>
                        </div>
                    </div>
                    <div class="space-y-1 bg-[#261b17] p-2.5 rounded-lg border border-amber-900/25">
                        <div class="text-[10px] font-bold text-amber-400 kh-text mb-1">🛒 មុខទំនិញដែលបានកុម្ម៉ង់៖</div>
                        ${itemsHtml}
                    </div>
                    <div class="flex justify-between text-xs font-bold text-amber-300 pt-1 border-t border-amber-900/20">
                        <span class="kh-text">ទឹកប្រាក់សរុបប្រចាំតុ:</span>
                        <span class="text-amber-200">$${parseFloat(o.total_usd).toFixed(2)} / ${parseInt(o.total_riel).toLocaleString()} ៛</span>
                    </div>
                </div>
            `;
        }

        function payDebtOrder(orderId) {
            if (confirm('តើអតិថិជននេះពិតជាបានទូទាត់ប្រាក់ជំពាក់រួចរាល់មែនទេ?')) {
                document.getElementById('formAction').value = 'pay_debt_order';
                document.getElementById('formTableNumber').value = currentSelectedTable;
                document.getElementById('formOrderId').value = orderId;
                document.getElementById('posActionForm').submit();
            }
        }

        function deleteOrder(orderId) {
            if (confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យប្រវត្តិនេះមែនទេ?')) {
                document.getElementById('formAction').value = 'delete_order';
                document.getElementById('formTableNumber').value = currentSelectedTable;
                document.getElementById('formOrderId').value = orderId;
                document.getElementById('posActionForm').submit();
            }
        }

        function openEditModal(id) {
            let order = todayOrders.find(o => o.id == id);
            if (!order) return;

            document.getElementById('editOrderId').value = order.id;
            document.getElementById('editTableNumber').value = order.table_number;
            document.getElementById('editCustomerName').value = order.customer_name || '';
            document.getElementById('editTotalUSD').value = order.total_usd;
            document.getElementById('editTotalRiel').value = order.total_riel;
            
            document.getElementById('editOrderModal').classList.remove('hidden');
            document.getElementById('editOrderModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editOrderModal').classList.remove('flex');
            document.getElementById('editOrderModal').classList.add('hidden');
        }

        function syncEditRiel() {
            let usd = parseFloat(document.getElementById('editTotalUSD').value) || 0;
            document.getElementById('editTotalRiel').value = Math.round(usd * EXCHANGE_RATE);
        }

        function submitEditOrder() {
            let orderId = document.getElementById('editOrderId').value;
            let tableNum = document.getElementById('editTableNumber').value;
            let customerName = document.getElementById('editCustomerName').value;
            let usd = document.getElementById('editTotalUSD').value;
            let riel = document.getElementById('editTotalRiel').value;

            document.getElementById('formAction').value = 'edit_order';
            document.getElementById('formOrderId').value = orderId;
            document.getElementById('formTableNumber').value = tableNum;
            document.getElementById('formCustomerName').value = customerName;
            document.getElementById('formTotalUSD').value = usd;
            document.getElementById('formTotalRiel').value = riel;
            document.getElementById('posActionForm').submit();
        }

        function toggleCartDrawerMobile() {
            document.getElementById('cartDrawer').classList.toggle('translate-x-full');
        }
    </script>
</body>
</html>