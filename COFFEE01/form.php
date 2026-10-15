<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}
require 'db.php';

$id = $_GET['id'] ?? null;
$error_message = ''; 

$rate = 4000; // អត្រាប្តូរប្រាក់ 1 ដុល្លារ = 4000 រៀល

$data = [
    'summary_date' => date('Y-m-d'),
    'income_coffee' => 0, 'income_coconut' => 0, 'income_food' => 0, 'income_water' => 0,
    'expense_chicken' => 0, 'expense_fish' => 0, 'expense_vegetable' => 0, 'expense_egg' => 0,
    'expense_meat' => 0, 'expense_misc_food' => 0, 'expense_soap' => 0, 'expense_gasoline' => 0,
    'expense_gas_tank' => 0, 'expense_electricity' => 0, 'expense_coffee_beans' => 0,
    'expense_milk' => 0, 'expense_coconut' => 0, 'expense_water' => 0, 
    'expense_staff_salary' => 20000,
    'expense_others' => 0,
    'expense_others_note' => ''
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM daily_summary WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $summary_date = $_POST['summary_date'];
    $currency_type = $_POST['currency_type'] ?? 'riel'; 

    $multiplier = ($currency_type === 'usd') ? $rate : 1;

    $inc_coffee = (isset($_POST['income_coffee']) && $_POST['income_coffee'] !== '' ? $_POST['income_coffee'] : 0) * $multiplier;
    $inc_coconut = (isset($_POST['income_coconut']) && $_POST['income_coconut'] !== '' ? $_POST['income_coconut'] : 0) * $multiplier;
    $inc_food = (isset($_POST['income_food']) && $_POST['income_food'] !== '' ? $_POST['income_food'] : 0) * $multiplier;
    $inc_water = (isset($_POST['income_water']) && $_POST['income_water'] !== '' ? $_POST['income_water'] : 0) * $multiplier;

    $exp_chicken = (isset($_POST['expense_chicken']) && $_POST['expense_chicken'] !== '' ? $_POST['expense_chicken'] : 0) * $multiplier;
    $exp_fish = (isset($_POST['expense_fish']) && $_POST['expense_fish'] !== '' ? $_POST['expense_fish'] : 0) * $multiplier;
    $exp_veg = (isset($_POST['expense_vegetable']) && $_POST['expense_vegetable'] !== '' ? $_POST['expense_vegetable'] : 0) * $multiplier;
    $exp_egg = (isset($_POST['expense_egg']) && $_POST['expense_egg'] !== '' ? $_POST['expense_egg'] : 0) * $multiplier;
    $exp_meat = (isset($_POST['expense_meat']) && $_POST['expense_meat'] !== '' ? $_POST['expense_meat'] : 0) * $multiplier;
    $exp_misc = (isset($_POST['expense_misc_food']) && $_POST['expense_misc_food'] !== '' ? $_POST['expense_misc_food'] : 0) * $multiplier;
    $exp_soap = (isset($_POST['expense_soap']) && $_POST['expense_soap'] !== '' ? $_POST['expense_soap'] : 0) * $multiplier;
    $exp_gasoline = (isset($_POST['expense_gasoline']) && $_POST['expense_gasoline'] !== '' ? $_POST['expense_gasoline'] : 0) * $multiplier;
    $exp_gastank = (isset($_POST['expense_gas_tank']) && $_POST['expense_gas_tank'] !== '' ? $_POST['expense_gas_tank'] : 0) * $multiplier;
    $exp_elec = (isset($_POST['expense_electricity']) && $_POST['expense_electricity'] !== '' ? $_POST['expense_electricity'] : 0) * $multiplier;
    $exp_beans = (isset($_POST['expense_coffee_beans']) && $_POST['expense_coffee_beans'] !== '' ? $_POST['expense_coffee_beans'] : 0) * $multiplier;
    $exp_milk = (isset($_POST['expense_milk']) && $_POST['expense_milk'] !== '' ? $_POST['expense_milk'] : 0) * $multiplier;
    $exp_cococost = (isset($_POST['expense_coconut']) && $_POST['expense_coconut'] !== '' ? $_POST['expense_coconut'] : 0) * $multiplier;
    $exp_water = (isset($_POST['expense_water']) && $_POST['expense_water'] !== '' ? $_POST['expense_water'] : 0) * $multiplier;
    $exp_salary = (isset($_POST['expense_staff_salary']) && $_POST['expense_staff_salary'] !== '' ? $_POST['expense_staff_salary'] : 0) * $multiplier;
    $exp_others = (isset($_POST['expense_others']) && $_POST['expense_others'] !== '' ? $_POST['expense_others'] : 0) * $multiplier;
    $exp_others_note = $_POST['expense_others_note'] ?? '';

    if ($id) {
        $sql = "UPDATE daily_summary SET summary_date=?, income_coffee=?, income_coconut=?, income_food=?, income_water=?, 
                expense_chicken=?, expense_fish=?, expense_vegetable=?, expense_egg=?, expense_meat=?, 
                expense_misc_food=?, expense_soap=?, expense_gasoline=?, expense_gas_tank=?, 
                expense_electricity=?, expense_coffee_beans=?, expense_milk=?, expense_coconut=?, expense_water=?, expense_staff_salary=?, expense_others=?, expense_others_note=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$summary_date, $inc_coffee, $inc_coconut, $inc_food, $inc_water, $exp_chicken, $exp_fish, $exp_veg, $exp_egg, $exp_meat, $exp_misc, $exp_soap, $exp_gasoline, $exp_gastank, $exp_elec, $exp_beans, $exp_milk, $exp_cococost, $exp_water, $exp_salary, $exp_others, $exp_others_note, $id]);
        
        updateMonthlySummary($pdo, $summary_date);
        header('Location: index.php');
        exit;
    } else {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_summary WHERE summary_date = ?");
        $checkStmt->execute([$summary_date]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $error_message = "⚠️ សូមអភ័យទោស! ទិន្នន័យសម្រាប់កាលបរិច្ឆេទទី ($summary_date) នេះមានរួចរាល់ហើយក្នុងប្រព័ន្ធ។";
            $data = $_POST; 
        } else {
            $sql = "INSERT INTO daily_summary (summary_date, income_coffee, income_coconut, income_food, income_water, 
                    expense_chicken, expense_fish, expense_vegetable, expense_egg, expense_meat, 
                    expense_misc_food, expense_soap, expense_gasoline, expense_gas_tank, 
                    expense_electricity, expense_coffee_beans, expense_milk, expense_coconut, expense_water, expense_staff_salary, expense_others, expense_others_note) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$summary_date, $inc_coffee, $inc_coconut, $inc_food, $inc_water, $exp_chicken, $exp_fish, $exp_veg, $exp_egg, $exp_meat, $exp_misc, $exp_soap, $exp_gasoline, $exp_gastank, $exp_elec, $exp_beans, $exp_milk, $exp_cococost, $exp_water, $exp_salary, $exp_others, $exp_others_note]);

            updateMonthlySummary($pdo, $summary_date);
            header('Location: index.php');
            exit;
        }
    }
}

function updateMonthlySummary($pdo, $summary_date) {
    $year = date('Y', strtotime($summary_date));
    $month = date('n', strtotime($summary_date));
    
    $sumStmt = $pdo->prepare("SELECT SUM(total_income) as ti, SUM(total_expense) as te, SUM(net_profit) as np FROM daily_summary WHERE YEAR(summary_date)=? AND MONTH(summary_date)=?");
    $sumStmt->execute([$year, $month]);
    $res = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $ti = $res['ti'] ?? 0;
    $te = $res['te'] ?? 0;
    $np = $res['np'] ?? 0;

    $insMonthly = $pdo->prepare("INSERT INTO monthly_summary (year, month, total_monthly_income, total_monthly_expense, net_monthly_profit) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE total_monthly_income=?, total_monthly_expense=?, net_monthly_profit=?");
    $insMonthly->execute([$year, $month, $ti, $te, $np, $ti, $te, $np]);
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
    <script>
        const rate = <?= $rate ?>;

        function changeCurrencyType(select) {
            let type = select.value;
            let inputs = document.querySelectorAll('.money-input');
            
            inputs.forEach(input => {
                let valRiel = parseFloat(input.getAttribute('data-riel')) || 0;
                if (type === 'usd') {
                    input.value = valRiel > 0 ? (valRiel / rate).toFixed(2) : '';
                    input.setAttribute('step', '0.01');
                } else {
                    input.value = valRiel > 0 ? Math.round(valRiel) : '';
                    input.setAttribute('step', '1');
                }
                updateConversion(input);
            });
        }

        function updateConversion(input) {
            let type = document.getElementById('currency_type').value;
            let val = parseFloat(input.value) || 0;
            let spanId = 'conv_' + input.name;
            let span = document.getElementById(spanId);
            if (!span) return;

            let rielVal = 0;
            if (type === 'usd') {
                rielVal = val * rate;
                span.innerText = '៛' + rielVal.toLocaleString();
                input.setAttribute('data-riel', rielVal);
            } else {
                rielVal = val;
                let usdVal = rielVal / rate;
                span.innerText = '$' + usdVal.toFixed(2);
                input.setAttribute('data-riel', rielVal);
            }
        }

        function distributeDrink(input) {
            let total = parseFloat(input.value) || 0;
            let coffeeInput = document.querySelector('input[name="income_coffee"]');
            if (coffeeInput) {
                coffeeInput.value = total;
                updateConversion(coffeeInput);
            }
        }

        function distributeFood(input) {
            let total = parseFloat(input.value) || 0;
            let foodInput = document.querySelector('input[name="income_food"]');
            if (foodInput) {
                foodInput.value = total;
                updateConversion(foodInput);
            }
        }
    </script>
</head>
<body class="bg-gray-100 text-gray-900 min-h-screen py-4 px-2 sm:px-6">
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-10">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 pb-4 border-b border-gray-200 gap-4">
            <h2 class="text-2xl sm:text-3xl font-black text-gray-950">
                <?= $id ? '✏️ កែប្រែទិន្នន័យ' : '➕ បញ្ចូលទិន្នន័យថ្មី' ?>
            </h2>
            <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto justify-between sm:justify-end">
                <div class="flex items-center bg-gray-50 border-2 border-gray-300 rounded-xl px-3.5 py-2 shadow-sm">
                    <label class="text-base sm:text-lg font-black text-gray-800 mr-2">💱 ប្រភេទ៖</label>
                    <select id="currency_type" name="currency_type" onchange="changeCurrencyType(this)" class="bg-transparent text-lg font-black text-amber-600 focus:outline-none cursor-pointer">
                        <option value="riel" <?= (isset($_POST['currency_type']) && $_POST['currency_type'] == 'usd') ? '' : 'selected' ?>>រៀល (៛)</option>
                        <option value="usd" <?= (isset($_POST['currency_type']) && $_POST['currency_type'] == 'usd') ? 'selected' : '' ?>>ដុល្លារ ($)</option>
                    </select>
                </div>
                <a href="index.php" class="text-base sm:text-lg text-gray-800 bg-gray-100 hover:bg-gray-200 px-4 py-2.5 rounded-xl font-bold transition">← ត្រឡប់</a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-amber-50 border-2 border-amber-300 text-amber-900 rounded-xl font-bold text-base sm:text-lg flex items-center space-x-3 shadow-sm">
                <span class="text-2xl">⚠️</span>
                <span><?= $error_message ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="currency_type" id="hidden_currency_type" value="riel">

            <div>
                <label class="block text-lg sm:text-xl font-black text-gray-950 mb-2">📅 កាលបរិច្ឆេទ (Date)</label>
                <input type="date" name="summary_date" value="<?= htmlspecialchars($data['summary_date'] ?? '') ?>" required
                    class="w-full sm:w-1/2 px-4 py-4 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 text-xl sm:text-2xl font-black text-gray-900">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 pt-2">
                
                <!-- Income Section -->
                <div class="bg-emerald-50/80 p-5 sm:p-8 rounded-2xl border-2 border-emerald-300 shadow-sm space-y-6">
                    <h3 class="font-black text-emerald-950 text-xl sm:text-2xl flex items-center space-x-2">
                        <span>🟢</span> <span>ផ្នែកចំណូល (Income)</span>
                    </h3>

                    
                        <?php 
                        $incomes = [
                            'income_coffee' => ['label' => '☕ កាហ្វេ', 'val' => $data['income_coffee'] ?? 0],
                            'income_food' => ['label' => '🍲 បាយ', 'val' => $data['income_food'] ?? 0]
                        ];
                        foreach($incomes as $name => $inf):
                            $val_riel = $inf['val'];
                            $val_display = $val_riel; 
                        ?>
                        <div class="bg-white p-4 rounded-xl border-2 border-emerald-200 shadow-sm space-y-2">
                            <div class="flex justify-between items-center">
                                <label class="text-lg sm:text-xl font-black text-gray-950"><?= $inf['label'] ?></label>
                                <span id="conv_<?= $name ?>" class="text-base sm:text-lg font-black text-emerald-800 bg-emerald-100 px-3 py-1 rounded-xl">$<?= number_format($val_riel / $rate, 2) ?></span>
                            </div>
                            <input type="number" step="1" name="<?= $name ?>" value="<?= round($val_display) ?>" data-riel="<?= $val_riel ?>" oninput="updateConversion(this)" class="money-input w-full px-4 py-4 border-2 border-emerald-400 rounded-xl bg-white text-2xl sm:text-3xl font-black text-emerald-950 focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Expenses Section -->
                <div class="bg-rose-50/80 p-5 sm:p-8 rounded-2xl border-2 border-rose-300 shadow-sm space-y-6">
                    <h3 class="font-black text-rose-950 text-xl sm:text-2xl flex items-center space-x-2">
                        <span>🔴</span> <span>ផ្នែកចំណាយ (Expenses)</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php 
                        $expenses_form = [
                            'expense_chicken' => '🍗 មាន់', 'expense_fish' => '🐟 ត្រី', 
                            'expense_vegetable' => '🥬 បន្លែ', 'expense_egg' => '🥚 ពង់', 
                            'expense_meat' => '🥩 សាច់', 'expense_misc_food' => '🛒 ចាយម្ហូបផ្សេងៗ', 
                            'expense_soap' => '🧼 សាប៊ូ', 'expense_gasoline' => '⛽ សាំង', 
                            'expense_gas_tank' => '🔵 ហ្គាស', 'expense_electricity' => '⚡ អគ្គិសនី (អង្គ)', 
                            'expense_coffee_beans' => '🫘 គ្រាប់កាហ្វេ', 'expense_milk' => '🥛 ទឹកដោះគោ', 
                            'expense_coconut' => '🥥 ដូង (ទិញចូល)', 'expense_water' => '💧 ទឹក (ចំណាយ)', 
                            'expense_staff_salary' => '👨‍💼 ប្រាក់ខែបុគ្គលិក'
                        ];
                        foreach($expenses_form as $ename => $elabel):
                            $eval = $data[$ename] ?? 0;
                        ?>
                        <div class="bg-white p-3.5 sm:p-4 rounded-xl border-2 border-rose-200 shadow-sm">
                            <div class="flex justify-between items-center mb-1.5">
                                <label class="text-sm sm:text-base font-black text-gray-900"><?= $elabel ?></label>
                                <span id="conv_<?= $ename ?>" class="text-xs sm:text-sm font-black text-rose-800 bg-rose-100 px-2 py-0.5 rounded-lg">$<?= number_format($eval / $rate, 2) ?></span>
                            </div>
                            <input type="number" step="1" name="<?= $ename ?>" value="<?= round($eval) ?>" data-riel="<?= $eval ?>" oninput="updateConversion(this)" class="money-input w-full px-3.5 py-3 border-2 border-rose-300 rounded-xl text-xl sm:text-2xl font-black text-rose-950 focus:ring-2 focus:ring-rose-500">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bg-white p-4 sm:p-5 rounded-xl border-2 border-rose-300 shadow-sm space-y-3 mt-4">
                        <?php 
                        $oth_val = $data['expense_others'] ?? 0;
                        ?>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-base sm:text-lg font-black text-gray-950">📌 ផ្សេងៗ (Others)</label>
                            <span id="conv_expense_others" class="text-sm sm:text-base font-black text-rose-800 bg-rose-100 px-2.5 py-1 rounded-lg">$<?= number_format($oth_val / $rate, 2) ?></span>
                        </div>
                        <input type="number" step="1" name="expense_others" value="<?= round($oth_val) ?>" data-riel="<?= $oth_val ?>" oninput="updateConversion(this)" class="money-input w-full px-3.5 py-3.5 border-2 border-rose-300 rounded-xl text-xl sm:text-2xl font-black text-rose-950 focus:ring-2 focus:ring-rose-500 mb-3">
                        
                        <div>
                            <label class="block text-xs sm:text-sm font-black text-gray-800 mb-1.5">📝 កំណត់ចំណាំ (Note សម្រាប់ផ្សេងៗ)</label>
                            <input type="text" name="expense_others_note" value="<?= htmlspecialchars($data['expense_others_note'] ?? '') ?>" placeholder="ឧ. ទិញសម្ភារៈការិយាល័យ..." class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl text-base sm:text-lg font-bold text-gray-900 focus:ring-2 focus:ring-rose-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-6 border-t-2 border-gray-200 flex flex-col sm:flex-row gap-4">
                <button type="submit" onclick="document.getElementById('hidden_currency_type').value = document.getElementById('currency_type').value;" class="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white font-black text-xl sm:text-2xl px-12 py-5 rounded-2xl transition shadow-xl shadow-amber-600/30 text-center">
                    💾 រក្សាទុកទិន្នន័យ (Save)
                </button>
                <a href="index.php" class="w-full sm:w-auto text-center bg-gray-200 hover:bg-gray-300 text-gray-900 font-black text-xl sm:text-2xl px-10 py-5 rounded-2xl transition">
                    បោះបង់
                </a>
            </div>
        </form>
    </div>
    
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            let select = document.getElementById('currency_type');
            if (select.value === 'usd') {
                changeCurrencyType(select);
            }
        });
    </script>
</body>
</html>