<?php
$host = 'localhost';
$dbname = 'coffee_shop_db';
$username = 'root';
$password = '123456789'; // បញ្ចូល Password របស់ MySQL របស់អ្នកបើមាន

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>