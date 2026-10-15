<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}
require 'db.php';

// Handle Delete Daily Record
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    $stmtDate = $pdo->prepare("SELECT summary_date FROM daily_summary WHERE id = ?");
    $stmtDate->execute([$id]);
    $rowDate = $stmtDate->fetch(PDO::FETCH_ASSOC);

    if ($rowDate) {
        $summary_date = $rowDate['summary_date'];
        $year = date('Y', strtotime($summary_date));
        $month = date('n', strtotime($summary_date));

        $stmt = $pdo->prepare("DELETE FROM daily_summary WHERE id = ?");
        $stmt->execute([$id]);

        $sumStmt = $pdo->prepare("SELECT SUM(total_income) as ti, SUM(total_expense) as te, SUM(net_profit) as np FROM daily_summary WHERE YEAR(summary_date)=? AND MONTH(summary_date)=?");
        $sumStmt->execute([$year, $month]);
        $res = $sumStmt->fetch(PDO::FETCH_ASSOC);

        $ti = $res['ti'] ?? 0;
        $te = $res['te'] ?? 0;
        $np = $res['np'] ?? 0;

        if ($ti > 0 || $te > 0) {
            $insMonthly = $pdo->prepare("INSERT INTO monthly_summary (year, month, total_monthly_income, total_monthly_expense, net_monthly_profit) 
                                     VALUES (?, ?, ?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE total_monthly_income=?, total_monthly_expense=?, net_monthly_profit=?");
            $insMonthly->execute([$year, $month, $ti, $te, $np, $ti, $te, $np]);
        } else {
            $delM = $pdo->prepare("DELETE FROM monthly_summary WHERE year = ? AND month = ?");
            $delM->execute([$year, $month]);
        }
    }

    header('Location: index.php');
    exit;
}

// Handle Delete Monthly Record
if (isset($_GET['delete_monthly'])) {
    $m_key = $_GET['delete_monthly'];
    list($year, $month) = explode('-', $m_key);
    
    $stmt = $pdo->prepare("DELETE FROM monthly_summary WHERE year = ? AND month = ?");
    $stmt->execute([$year, $month]);

    header('Location: index.php');
    exit;
}

$dailyRecords = $pdo->query("SELECT * FROM daily_summary ORDER BY summary_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$monthlyRecords = $pdo->query("SELECT * FROM monthly_summary ORDER BY year DESC, month DESC")->fetchAll(PDO::FETCH_ASSOC);

$rate = 4000;
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រព័ន្ធគ្រប់គ្រងហាងកាហ្វេ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Kantumruy Pro', sans-serif; } </style>
    <script>
        setTimeout(function(){
            location.reload();
        }, 15000);
    </script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen pb-12">
    <!-- Navbar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <span class="text-xl">☕</span>
                <span class="font-bold text-base sm:text-lg text-gray-950">Coffee & Food </span>
            </div>
            <div class="flex items-center space-x-2">
                <a href="form.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition shadow-sm flex items-center space-x-1">
                    <span>+ បញ្ចូលថ្មី</span>
                </a>
                <a href="menu.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition shadow-sm flex items-center space-x-1">
                    <span>+ LEAP COFFEE POS System</span>
                </a>
                <a href="login.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs sm:text-sm font-medium transition">
                    ចាកចេញ
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 space-y-8">
        
        <!-- Monthly Summary Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50/80">
                <h3 class="font-bold text-base sm:text-lg text-gray-900">📊 ការបូកសរុបប្រចាំខែ និងការចំណាយលម្អិត ($ & ៛)</h3>
                <span class="text-xs text-gray-500 bg-gray-200 px-2.5 py-1 rounded-full font-medium">Monthly</span>
            </div>
            
            <!-- Mobile View: Monthly Cards -->
            <div class="block md:hidden divide-y divide-gray-100">
                <?php if (empty($monthlyRecords)): ?>
                    <div class="text-center py-8 text-gray-400 text-sm">ពុំទាន់មានទិន្នន័យបូកសរុបប្រចាំខែ</div>
                <?php else: foreach ($monthlyRecords as $m): 
                    $m_year = $m['year'];
                    $m_month = $m['month'];
                    
                    $stmtMExp = $pdo->prepare("SELECT 
                        SUM(expense_chicken) as ec, SUM(expense_fish) as ef, SUM(expense_vegetable) as ev, 
                        SUM(expense_egg) as ee, SUM(expense_meat) as em, SUM(expense_misc_food) as emf, 
                        SUM(expense_soap) as esoap, SUM(expense_gasoline) as egas, SUM(expense_gas_tank) as egt, 
                        SUM(expense_electricity) as eel, SUM(expense_coffee_beans) as ecb, SUM(expense_milk) as emilk, 
                        SUM(expense_coconut) as ecoc, SUM(expense_water) as ewat, SUM(expense_staff_salary) as ess, 
                        SUM(expense_others) as eoth 
                        FROM daily_summary WHERE YEAR(summary_date) = ? AND MONTH(summary_date) = ?");
                    $stmtMExp->execute([$m_year, $m_month]);
                    $mExpData = $stmtMExp->fetch(PDO::FETCH_ASSOC);

                    $stmtMNotes = $pdo->prepare("SELECT summary_date, expense_others_note FROM daily_summary WHERE YEAR(summary_date) = ? AND MONTH(summary_date) = ? AND expense_others_note IS NOT NULL AND expense_others_note != ''");
                    $stmtMNotes->execute([$m_year, $m_month]);
                    $mNotes = $stmtMNotes->fetchAll(PDO::FETCH_ASSOC);

                    $m_inc_r = $m['total_monthly_income']; $m_inc_d = $m_inc_r / $rate;
                    $m_exp_r = $m['total_monthly_expense']; $m_exp_d = $m_exp_r / $rate;
                    $m_pro_r = $m['net_monthly_profit']; $m_pro_d = $m_pro_r / $rate;
                ?>
                <div class="p-4 space-y-3 bg-white">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-900 text-sm">📊 <?= $m_year ?> / ខែ <?= $m_month ?></span>
                        <a href="index.php?delete_monthly=<?= $m_year . '-' . $m_month ?>" onclick="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យសរុបប្រចាំខែនេះមែនទេ?')" class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-lg text-xs font-medium">លុប</a>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 bg-gray-50 p-2.5 rounded-xl text-center text-xs">
                        <div>
                            <span class="text-gray-500 block">សរុបចំណូល</span>
                            <span class="text-emerald-600 font-bold">$<?= number_format($m_inc_d, 2) ?><br><?= number_format($m_inc_r, 0) ?>៛</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">សរុបចំណាយ</span>
                            <span class="text-rose-600 font-bold">$<?= number_format($m_exp_d, 2) ?><br><?= number_format($m_exp_r, 0) ?>៛</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">ចំណេញសុទ្ធ</span>
                            <span class="font-bold text-blue-600">$<?= number_format($m_pro_d, 2) ?><br><?= number_format($m_pro_r, 0) ?>៛</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Desktop View: Monthly Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 uppercase tracking-wider border-b border-gray-200">
                            <th class="py-3 px-4">ឆ្នាំ / ខែ</th>
                            <th class="py-3 px-4">សរុបចំណូល</th>
                            <th class="py-3 px-4">សរុបចំណាយ</th>
                            <th class="py-3 px-4">ចំណាយលម្អិត និង Note ប្រចាំខែ</th>
                            <th class="py-3 px-4">ចំណេញសុទ្ធ</th>
                            <th class="py-3 px-4 text-center">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($monthlyRecords)): ?>
                            <tr><td colspan="6" class="text-center py-6 text-gray-400">ពុំទាន់មានទិន្នន័យបូកសរុបប្រចាំខែ</td></tr>
                        <?php else: foreach ($monthlyRecords as $m): 
                            $m_year = $m['year'];
                            $m_month = $m['month'];
                            
                            $stmtMExp = $pdo->prepare("SELECT 
                                SUM(expense_chicken) as ec, SUM(expense_fish) as ef, SUM(expense_vegetable) as ev, 
                                SUM(expense_egg) as ee, SUM(expense_meat) as em, SUM(expense_misc_food) as emf, 
                                SUM(expense_soap) as esoap, SUM(expense_gasoline) as egas, SUM(expense_gas_tank) as egt, 
                                SUM(expense_electricity) as eel, SUM(expense_coffee_beans) as ecb, SUM(expense_milk) as emilk, 
                                SUM(expense_coconut) as ecoc, SUM(expense_water) as ewat, SUM(expense_staff_salary) as ess, 
                                SUM(expense_others) as eoth 
                                FROM daily_summary WHERE YEAR(summary_date) = ? AND MONTH(summary_date) = ?");
                            $stmtMExp->execute([$m_year, $m_month]);
                            $mExpData = $stmtMExp->fetch(PDO::FETCH_ASSOC);

                            $stmtMNotes = $pdo->prepare("SELECT summary_date, expense_others_note FROM daily_summary WHERE YEAR(summary_date) = ? AND MONTH(summary_date) = ? AND expense_others_note IS NOT NULL AND expense_others_note != ''");
                            $stmtMNotes->execute([$m_year, $m_month]);
                            $mNotes = $stmtMNotes->fetchAll(PDO::FETCH_ASSOC);

                            $m_inc_r = $m['total_monthly_income']; $m_inc_d = $m_inc_r / $rate;
                            $m_exp_r = $m['total_monthly_expense']; $m_exp_d = $m_exp_r / $rate;
                            $m_pro_r = $m['net_monthly_profit']; $m_pro_d = $m_pro_r / $rate;

                            $m_expenses_list = [
                                'មាន់' => $mExpData['ec'], 'ត្រី' => $mExpData['ef'], 
                                'បន្លែ' => $mExpData['ev'], 'ពង់' => $mExpData['ee'], 
                                'សាច់' => $mExpData['em'], 'ចាយម្ហូប' => $mExpData['emf'], 
                                'សាប៊ូ' => $mExpData['esoap'], 'សាំង' => $mExpData['egas'], 
                                'ហ្គាស' => $mExpData['egt'], 'អគ្គិសនី(អង្គ)' => $mExpData['eel'], 
                                'គ្រាប់កាហ្វេ' => $mExpData['ecb'], 'ទឹកដោះគោ' => $mExpData['emilk'], 
                                'ដូង' => $mExpData['ecoc'], 'ទឹក' => $mExpData['ewat'], 
                                'ប្រាក់ខែ' => $mExpData['ess'], 'ផ្សេងៗ' => $mExpData['eoth']
                            ];
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-4 px-4 font-medium text-gray-900 whitespace-nowrap"><?= $m_year ?> / ខែ <?= $m_month ?></td>
                            <td class="py-4 px-4 text-emerald-600 font-semibold whitespace-nowrap">$<?= number_format($m_inc_d, 2) ?><br><span class="text-xs text-gray-500"><?= number_format($m_inc_r, 0) ?> ៛</span></td>
                            <td class="py-4 px-4 text-rose-600 font-semibold whitespace-nowrap">$<?= number_format($m_exp_d, 2) ?><br><span class="text-xs text-gray-500"><?= number_format($m_exp_r, 0) ?> ៛</span></td>
                            <td class="py-4 px-4 text-gray-600 text-xs space-y-1 min-w-[320px]">
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                                    <?php 
                                    foreach($m_expenses_list as $mk => $mval):
                                        $mval_d = $mval / $rate;
                                    ?>
                                    <div>• <?= $mk ?>: <b>$<?= number_format($mval_d, 2) ?> (<?= number_format($mval, 0) ?>៛)</b></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if(!empty($mNotes)): ?>
                                    <div class="mt-1 text-amber-700 bg-amber-50 px-2 py-1 rounded border border-amber-200 space-y-0.5">
                                        <div class="font-semibold">📝 Note ប្រចាំខែ៖</div>
                                        <?php foreach($mNotes as $noteRow): ?>
                                            <div>- [<?= $noteRow['summary_date'] ?>]: <?= htmlspecialchars($noteRow['expense_others_note']) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 font-bold whitespace-nowrap text-blue-600">
                                $<?= number_format($m_pro_d, 2) ?><br><span class="text-xs font-normal text-gray-500"><?= number_format($m_pro_r, 0) ?> ៛</span>
                            </td>
                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                <a href="index.php?delete_monthly=<?= $m_year . '-' . $m_month ?>" onclick="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យសរុបប្រចាំខែនេះមែនទេ?')" class="inline-block bg-rose-50 text-rose-700 hover:bg-rose-100 px-3 py-1.5 rounded-lg text-xs font-medium transition">លុប</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Summary Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50/80">
                <h3 class="font-bold text-base sm:text-lg text-gray-900">📅 ចំណូល (កាហ្វេ & បាយ) - ចំណាយ និងលម្អិតប្រចាំថ្ងៃ </h3>
                <span class="text-xs text-gray-500 bg-gray-200 px-2.5 py-1 rounded-full font-medium">Daily</span>
            </div>

            <!-- Mobile View: Cards Layout -->
            <div class="block md:hidden divide-y divide-gray-100">
                <?php if (empty($dailyRecords)): ?>
                    <div class="text-center py-8 text-gray-400 text-sm">ពុំទាន់មានទិន្នន័យនៅឡើយទេ</div>
                <?php else: foreach ($dailyRecords as $row): 
                    // បែងចែកចំណូលកាហ្វេ និងបាយ (ប្រសិនបើក្នុង DB ឈ្មោះ column គឺ income_coffee និង income_food)
                    $inc_coffee_r = $row['income_coffee'] ?? 0; $inc_coffee_d = $inc_coffee_r / $rate;
                    $inc_food_r = $row['income_food'] ?? 0; $inc_food_d = $inc_food_r / $rate;
                    
                    $tot_inc_r = $row['total_income']; $tot_inc_d = $tot_inc_r / $rate;
                    $tot_exp_r = $row['total_expense']; $tot_exp_d = $tot_exp_r / $rate;
                    $net_p_r = $row['net_profit']; $net_p_d = $net_p_r / $rate;
                ?>
                <div class="p-4 space-y-3 bg-white">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-900 text-sm">📅 <?= $row['summary_date'] ?></span>
                        <div class="space-x-1">
                            <a href="form.php?id=<?= $row['id'] ?>" class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-lg text-xs font-medium">កែប្រែ</a>
                            <a href="index.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ?')" class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-lg text-xs font-medium">លុប</a>
                        </div>
                    </div>

                    <!-- Breakdown of Incomes -->
                    <div class="grid grid-cols-2 gap-2 bg-emerald-50/50 p-2.5 rounded-xl text-center text-xs border border-emerald-100">
                        <div>
                            <span class="text-emerald-800 block font-medium">☕ ចំណូលកាហ្វេ</span>
                            <span class="text-emerald-600 font-bold">$<?= number_format($inc_coffee_d, 2) ?><br><?= number_format($inc_coffee_r, 0) ?>៛</span>
                        </div>
                        <div>
                            <span class="text-amber-800 block font-medium">🍛 ចំណូលបាយ</span>
                            <span class="text-amber-600 font-bold">$<?= number_format($inc_food_d, 2) ?><br><?= number_format($inc_food_r, 0) ?>៛</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 bg-gray-50 p-2.5 rounded-xl text-center text-xs">
                        <div>
                            <span class="text-gray-500 block">សរុបចំណូល</span>
                            <span class="text-emerald-600 font-bold">$<?= number_format($tot_inc_d, 2) ?><br><?= number_format($tot_inc_r, 0) ?>៛</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">សរុបចំណាយ</span>
                            <span class="text-rose-600 font-bold">$<?= number_format($tot_exp_d, 2) ?><br><?= number_format($tot_exp_r, 0) ?>៛</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">ចំណេញសុទ្ធ</span>
                            <span class="font-bold <?= $net_p_r >= 0 ? 'text-blue-600' : 'text-rose-600' ?>">$<?= number_format($net_p_d, 2) ?><br><?= number_format($net_p_r, 0) ?>៛</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Desktop View: Table Layout -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider border-b border-gray-200">
                            <th class="py-3 px-4">កាលបរិច្ឆេទ</th>
                            <th class="py-3 px-4">ចំណូលកាហ្វេ</th>
                            <th class="py-3 px-4">ចំណូលបាយ</th>
                            <th class="py-3 px-4">ចំណូលសរុប</th>
                            <th class="py-3 px-4">ចំណាយសរុប</th>
                            <th class="py-3 px-4">ចំណាយលម្អិត និង Note</th>
                            <th class="py-3 px-4">ចំណេញសុទ្ធ</th>
                            <th class="py-3 px-4 text-center">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-xs sm:text-sm">
                        <?php if (empty($dailyRecords)): ?>
                            <tr><td colspan="8" class="text-center py-6 text-gray-400">ពុំទាន់មានទិន្នន័យនៅឡើយទេ</td></tr>
                        <?php else: foreach ($dailyRecords as $row): 
                            $inc_coffee_r = $row['income_coffee'] ?? 0; $inc_coffee_d = $inc_coffee_r / $rate;
                            $inc_food_r = $row['income_food'] ?? 0; $inc_food_d = $inc_food_r / $rate;
                            
                            $tot_inc_r = $row['total_income']; $tot_inc_d = $tot_inc_r / $rate;
                            $tot_exp_r = $row['total_expense']; $tot_exp_d = $tot_exp_r / $rate;
                            $net_p_r = $row['net_profit']; $net_p_d = $net_p_r / $rate;
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-4 px-4 font-medium text-gray-900 whitespace-nowrap"><?= $row['summary_date'] ?></td>
                            
                            <!-- Coffee Income -->
                            <td class="py-4 px-4 text-emerald-700 font-semibold whitespace-nowrap bg-emerald-50/20">
                                $<?= number_format($inc_coffee_d, 2) ?><br><span class="text-xs text-gray-500"><?= number_format($inc_coffee_r, 0) ?> ៛</span>
                            </td>
                            
                            <!-- Food Income -->
                            <td class="py-4 px-4 text-amber-700 font-semibold whitespace-nowrap bg-amber-50/20">
                                $<?= number_format($inc_food_d, 2) ?><br><span class="text-xs text-gray-500"><?= number_format($inc_food_r, 0) ?> ៛</span>
                            </td>

                            <td class="py-4 px-4 text-emerald-600 font-semibold whitespace-nowrap">$<?= number_format($tot_inc_d, 2) ?><br><span class="text-xs text-gray-500"><?= number_format($tot_inc_r, 0) ?> ៛</span></td>
                            <td class="py-4 px-4 text-rose-600 font-semibold whitespace-nowrap">$<?= number_format($tot_exp_d, 2) ?><br><span class="text-xs text-gray-500"><?= number_format($tot_exp_r, 0) ?> ៛</span></td>
                            
                            <td class="py-4 px-4 text-gray-600 text-xs space-y-1 min-w-[280px]">
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                                    <?php 
                                    $expenses = [
                                        'មាន់' => $row['expense_chicken'], 'ត្រី' => $row['expense_fish'], 
                                        'បន្លែ' => $row['expense_vegetable'], 'ពង់' => $row['expense_egg'], 
                                        'សាច់' => $row['expense_meat'], 'ចាយម្ហូប' => $row['expense_misc_food'], 
                                        'សាប៊ូ' => $row['expense_soap'], 'សាំង' => $row['expense_gasoline'], 
                                        'ហ្គាស' => $row['expense_gas_tank'], 'អគ្គិសនី(អង្គ)' => $row['expense_electricity'], 
                                        'គ្រាប់កាហ្វេ' => $row['expense_coffee_beans'], 'ទឹកដោះគោ' => $row['expense_milk'], 
                                        'ដូង' => $row['expense_coconut'], 'ទឹក' => $row['expense_water'], 
                                        'ប្រាក់ខែ' => $row['expense_staff_salary'], 'ផ្សេងៗ' => $row['expense_others']
                                    ];
                                    foreach($expenses as $k => $val):
                                        $val_d = $val / $rate;
                                    ?>
                                    <div>• <?= $k ?>: <b>$<?= number_format($val_d, 2) ?> (<?= number_format($val, 0) ?>៛)</b></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if(!empty($row['expense_others_note'])): ?>
                                    <div class="mt-1 text-amber-700 bg-amber-50 px-2 py-1 rounded border border-amber-200">
                                        📝 Note: <?= htmlspecialchars($row['expense_others_note']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 px-4 font-bold whitespace-nowrap <?= $net_p_r >= 0 ? 'text-blue-600' : 'text-rose-600' ?>">
                                $<?= number_format($net_p_d, 2) ?><br><span class="text-xs font-normal"><?= number_format($net_p_r, 0) ?> ៛</span>
                            </td>
                            <td class="py-4 px-4 text-center space-x-1 whitespace-nowrap">
                                <a href="form.php?id=<?= $row['id'] ?>" class="inline-block bg-amber-50 text-amber-700 hover:bg-amber-100 px-2.5 py-1.5 rounded-lg text-xs font-medium transition">កែប្រែ</a>
                                <a href="index.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ?')" class="inline-block bg-rose-50 text-rose-700 hover:bg-rose-100 px-2.5 py-1.5 rounded-lg text-xs font-medium transition">លុប</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>