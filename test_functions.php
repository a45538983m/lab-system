<?php
require_once __DIR__ . '/includes/functions.php';

echo "<pre>";

// Проверим, что PDO есть
echo "Тип \$pdo: " . get_class($pdo) . "\n";

// Проверим работу авторизации (тестово подделаем пользователя)
$fakeUser = [
    'id' => 1,
    'full_name' => 'Тестовый Врач',
    'role' => 'doctor',
];

login_user($fakeUser);

echo "Авторизован? " . (is_auth() ? 'Да' : 'Нет') . "\n";
echo "Имя пользователя: " . current_user_name() . "\n";
echo "Роль: " . current_user_role() . "\n";

// Проверим форматирование пола
echo "Пол M: " . format_sex_label('M') . "\n";
echo "Пол F: " . format_sex_label('F') . "\n";

echo "</pre>";
