<?php
require_once __DIR__ . '/includes/db.php';

echo "<pre>";
echo "Подключение к базе прошло успешно!\n";

// Проверим, что таблица analysis_types существует и прочитаем из неё данные:
$stmt = $pdo->query("SELECT id, code, name FROM analysis_types");
$types = $stmt->fetchAll();

echo "Список типов анализов:\n";
foreach ($types as $row) {
    echo "- {$row['id']}: {$row['code']} — {$row['name']}\n";
}
echo "</pre>";
