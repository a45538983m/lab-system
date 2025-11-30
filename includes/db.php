<?php 
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $dbHost = 'localhost';
    $dbName = 'lab_system';
    $dbUser = 'root';
    $dbPass = '';
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

   try {
    // СОЗДАЁМ ОБЪЕКТ PDO
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ошибки как исключения
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // выборка в виде ассоциативных массивов
        PDO::ATTR_EMULATE_PREPARES   => false,                  // настоящие prepared statements
    ]);
} catch (PDOException $e) {
    // ЕСЛИ НЕ УДАЛОСЬ ПОДКЛЮЧИТЬСЯ — ПОКА ПРОСТО ОСТАНОВИМ СКРИПТ
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}