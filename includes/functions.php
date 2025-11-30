<?php
// functions.php
// Общие функции проекта: сессии, авторизация, текущий врач/пациент и т.п.

// 1. Стартуем сессию (один раз на всё приложение)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Подключаем базу данных (PDO $pdo)
require_once __DIR__ . '/db.php';

/**
 * Залогинить пользователя (врача или админа).
 * $user — это массив с полями из таблицы users.
 */
function login_user(array $user): void
{
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
}

/**
 * Выйти из системы (очистить сессию).
 */
function logout_user(): void
{
    // Очищаем данные сессии
    $_SESSION = [];

    // Удаляем cookie сессии
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

    // Разрушаем сессию
    session_destroy();
}

/**
 * Проверить, авторизован ли кто-то (врач или админ).
 */
function is_auth(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Если пользователь НЕ авторизован — отправить на страницу входа
 * и остановить скрипт.
 */
function require_auth(): void
{
    if (!is_auth()) {
        header('Location: /lab-system/index.php?page=login');
        exit;
    }
}

/**
 * Проверить, является ли текущий пользователь админом (главврачом).
 */
function is_admin(): bool
{
    return is_auth() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Если пользователь не админ — отправить на страницу входа.
 */
function require_admin(): void
{
    if (!is_admin()) {
        header('Location: /lab-system/index.php?page=login');
        exit;
    }
}

/**
 * Получить ID текущего пользователя (врача/админа) или null.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Получить имя текущего пользователя.
 */
function current_user_name(): string
{
    return $_SESSION['user_name'] ?? '';
}

/**
 * Получить роль текущего пользователя (admin/doctor).
 */
function current_user_role(): string
{
    return $_SESSION['user_role'] ?? '';
}

/* --------- Работа с текущим пациентом --------- */

/**
 * Установить выбранного пациента (ID) в сессию.
 */
function set_current_patient_id(int $patientId): void
{
    $_SESSION['current_patient_id'] = $patientId;
}

/**
 * Получить ID текущего выбранного пациента или null.
 */
function current_patient_id(): ?int
{
    return isset($_SESSION['current_patient_id'])
        ? (int)$_SESSION['current_patient_id']
        : null;
}

/**
 * Сбросить выбор пациента.
 */
function clear_current_patient(): void
{
    unset($_SESSION['current_patient_id']);
}

/**
 * Получить информацию о текущем пациенте из БД
 * (или null, если не выбран).
 */
function get_current_patient(PDO $pdo): ?array
{
    $patientId = current_patient_id();
    if (!$patientId) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM patients WHERE id = :id');
    $stmt->execute(['id' => $patientId]);
    $patient = $stmt->fetch();

    return $patient ?: null;
}

/**
 * Преобразовать sex (M/F) в текст для отображения: Муж / Жен.
 */
function format_sex_label(?string $sex): string
{
    if ($sex === 'M') {
        return 'Муж';
    }
    if ($sex === 'F') {
        return 'Жен';
    }
    return '';
}
