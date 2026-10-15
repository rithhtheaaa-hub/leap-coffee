<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}
require 'db.php';

$id = $_GET['id'] ?? null;
$data = [
    'summary_date' => date('Y-m-d'),
    'income_coffee' => 0, 'income_coconut' => 0, 'income_food' => 0,
    'expense_chicken' => 0, 'expense_fish' => 0, 'expense_vegetable' => 0, 'expense_egg' => 0,
    'expense_meat' => 0, 'expense_misc_food' => 0, 'expense_soap' => 0, 'expense_gasoline' => 0,
    'expense_gas_tank' => 0, 'expense_electricity' => 0, 'expense_coffee_beans' => 0,
    'expense_milk' => 0, 'expense_coconut' => 0, 'expense_others' => 0
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM daily_summary WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $summary_date = $_POST['summary_date'];
    
    $inc_coffee = $_POST['income_coffee'] ?? 0;
    $inc_coconut = $_POST['income_coconut'] ?? 0;
    $inc_food = $_POST['income_food'] ?? 0;

    $exp_chicken = $_POST['expense_chicken'] ?? 0;
    $exp_fish = $_POST['expense_fish'] ?? 0;
    $exp_veg = $_POST['expense_vegetable'] ?? 0;
    $exp_egg = $_POST['expense_egg'] ?? 0;
    $exp_meat = $_POST['expense_meat'] ?? 0;
    $exp_misc = $_POST['expense_misc_food'] ?? 0;
    $exp_soap = $_POST['expense_soap'] ?? 0;
    $exp_gasoline = $_POST['expense_gasoline'] ?? 0;
    $exp_gastank = $_POST['expense_gas_tank'] ?? 0;
    $exp_elec = $_POST['expense_electricity'] ?? 0;
    $exp_beans = $_POST['expense_coffee_beans'] ?? 0;
    $exp_milk = $_POST['expense_milk'] ?? 0;
    $exp_cococost = $_POST['expense_coconut'] ?? 0;
    $exp_others = $_POST['expense_others'] ?? 0;

    if ($id) {
        $sql = "UPDATE daily_summary SET summary_date=?, income_coffee=?, income_coconut=?, income_food=?, 
                expense_chicken=?, expense_fish=?, expense_vegetable=?, expense_egg=?, expense_meat=?, 
                expense_misc_food=?, expense_soap=?, expense_gasoline=?, expense_gas_tank=?, 
                expense_electricity=?, expense_coffee_beans=?, expense_milk=?, expense_coconut=?, expense_others=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$summary_date, $inc_coffee, $inc_coconut, $inc_food, $exp_chicken, $exp_fish, $exp_veg, $exp_egg, $exp_meat, $exp_misc, $exp_soap, $exp_gasoline, $exp_gastank, $exp_elec, $exp_beans, $exp_milk, $exp_cococost, $exp_others, $id]);
    } else {
        $sql = "INSERT INTO daily_summary (summary_date, income_coffee, income_coconut, income_food, 
                expense_chicken, expense_fish, expense_vegetable, expense_egg, expense_meat, 
                expense_misc_food, expense_soap, expense_gasoline, expense_gas_tank, 
                expense_electricity, expense_coffee_beans, expense_milk, expense_coconut, expense_others) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$summary_date, $inc_coffee, $inc_coconut, $inc_food, $exp_chicken, $exp_fish, $exp_veg, $exp_egg, $exp_meat, $exp_misc, $exp_soap, $exp_gasoline, $exp_gastank, $exp_elec, $exp_beans, $exp_milk, $exp_cococost, $exp_others]);
    }

    // Auto calculate monthly summary
    $year = date('Y', strtotime($summary_date));
    $month = date('n', strtotime($summary_date));
    
    $sumStmt = $pdo->prepare("SELECT SUM(total_income) as ti, SUM(total_expense) as te, SUM(net_profit) as np FROM daily_summary WHERE YEAR(summary_date)=? AND MONTH(summary_date)=?");
    $sumStmt->execute([$year, $month]);
    $res = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $insMonthly = $pdo->prepare("INSERT INTO monthly_summary (year, month, total_monthly_income, total_monthly_expense, net_monthly_profit) 
                                 VALUES (?, ?, ?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE total_monthly_income=?, total_monthly_expense=?, net_monthly_profit=?");
    $insMonthly->execute([$year, $month, $res['ti'], $res['te'], $res['np'], $res['ti'], $res['te'], $res['np']]);

    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'កែប្រែទិន្នន័យ' : 'បញ្ចូលទិន្នន័យថ្មី' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Kantumruy Pro', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">
                    <?= $id ? '✏️ កែប្រែទិន្នន័យប្រចាំថ្ងៃ' : '➕ បញ្ចូលទិន្នន័យថ្មីប្រចាំថ្ងៃ' ?>
                </h2>
                <a href="index.php" class="text-sm text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition">← ត្រឡប់ក្រោយ</a>
            </div>

            <form method="POST" class="space-y-6">
                <!-- Date Input -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">កាលបរិច្ឆេទ (Date)</label>
                    <input type="date" name="summary_date" value="<?= $data['summary_date'] ?>" required
                        class="w-full sm:w-1/2 px-4 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">
                    <!-- Income Section -->
                    <div class="bg-emerald-50/50 p-6 rounded-2xl border border-emerald-100">
                        <h3 class="font-bold text-emerald-800 mb-4 flex items-center space-x-2">
                            <span>🟢</span> <span>ផ្នែកចំណូល (Income)</span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">កាហ្វេ</label>
                                <input type="number" step="0.01" name="income_coffee" value="<?= $data['income_coffee'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">ដូង</label>
                                <input type="number" step="0.01" name="income_coconut" value="<?= $data['income_coconut'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">បាយ</label>
                                <input type="number" step="0.01" name="income_food" value="<?= $data['income_food'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>

                    <!-- Expenses Section -->
                    <div class="bg-rose-50/50 p-6 rounded-2xl border border-rose-100">
                        <h3 class="font-bold text-rose-800 mb-4 flex items-center space-x-2">
                            <span>🔴</span> <span>ផ្នែកចំណាយ (Expenses)</span>
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="text-[11px] font-semibold text-gray-600">មាន់</label><input type="number" step="0.01" name="expense_chicken" value="<?= $data['expense_chicken'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ត្រី</label><input type="number" step="0.01" name="expense_fish" value="<?= $data['expense_fish'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">បន្លែ</label><input type="number" step="0.01" name="expense_vegetable" value="<?= $data['expense_vegetable'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ពង់</label><input type="number" step="0.01" name="expense_egg" value="<?= $data['expense_egg'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">សាច់</label><input type="number" step="0.01" name="expense_meat" value="<?= $data['expense_meat'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ចាយម្ហូបផ្សេងៗ</label><input type="number" step="0.01" name="expense_misc_food" value="<?= $data['expense_misc_food'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">សាប៊ូ</label><input type="number" step="0.01" name="expense_soap" value="<?= $data['expense_soap'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">សាំង</label><input type="number" step="0.01" name="expense_gasoline" value="<?= $data['expense_gasoline'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ហ្គាស</label><input type="number" step="0.01" name="expense_gas_tank" value="<?= $data['expense_gas_tank'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">អគ្គិសនី (អង្គ)</label><input type="number" step="0.01" name="expense_electricity" value="<?= $data['expense_electricity'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">គ្រាប់កាហ្វេ</label><input type="number" step="0.01" name="expense_coffee_beans" value="<?= $data['expense_coffee_beans'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ទឹកដោះគោ</label><input type="number" step="0.01" name="expense_milk" value="<?= $data['expense_milk'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ដូង (ទិញចូល)</label><input type="number" step="0.01" name="expense_coconut" value="<?= $data['expense_coconut'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-gray-600">ផ្សេងៗ</label><input type="number" step="0.01" name="expense_others" value="<?= $data['expense_others'] ?>" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg bg-white text-sm"></div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-200 flex items-center space-x-4">
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-medium px-6 py-3 rounded-xl transition shadow-lg shadow-amber-600/20">
                        រក្សាទុក (Save)
                    </button>
                    <a href="index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-6 py-3 rounded-xl transition">
                        បោះបង់
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>