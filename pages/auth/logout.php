<?php
// pages/auth/logout.php
// Выход из системы и переход на страницу логина/регистрации

// ВАЖНО: поднимаемся на два уровня вверх: из pages/auth -> в lab-system -> includes
require_once __DIR__ . '/../../includes/functions.php';

// functions.php, скорее всего, уже делает session_start(), так что второй раз вызывать не надо

// Очищаем все данные сессии
$_SESSION = [];

// Уничтожаем cookie сессии (на всякий случай)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Уничтожаем сессию
session_destroy();

// 🔁 Куда отправляем пользователя после выхода:

// Вариант 1: на страницу логина
header('Location: /lab-system/index.php?page=login');
exit;

// Если захочешь сразу на регистрацию врача — просто замени строку выше на:
// header('Location: /lab-system/index.php?page=register_doctor');
// exit;
