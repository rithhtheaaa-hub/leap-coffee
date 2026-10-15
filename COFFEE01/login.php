<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pass = $_POST['password'] ?? '';
    if ($pass === '123') {
        $_SESSION['authenticated'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'ពាក្យសម្ងាត់មិនត្រឹមត្រូវ!';
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលប្រើប្រាស់ - Coffee Shop System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Kantumruy Pro', sans-serif; } </style>
</head>
<body class="bg-gradient-to-br from-amber-50 to-orange-100 h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-amber-100 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 text-amber-800 rounded-full text-2xl mb-4">
            ☕
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">ប្រព័ន្ធគ្រប់គ្រងហាងកាហ្វេ</h2>
        <p class="text-sm text-gray-500 mb-6">សូមបញ្ចូលពាក្យសម្ងាត់ដើម្បីបន្ត</p>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-200"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4 text-left">
                <label class="block text-gray-700 text-sm font-medium mb-2">លេខសម្ងាត់ (Password)</label>
                <input type="password" name="password" placeholder="••••••••" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
            </div>
            <button type="submit" 
                class="w-full bg-amber-600 hover:bg-amber-700 text-white font-medium py-3 rounded-xl transition shadow-lg shadow-amber-600/20">
                ចូល (Login)
            </button>
        </form>
    </div>
</body>
</html>