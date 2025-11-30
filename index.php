<?php
// index.php — главный вход в систему и роутер

// 1. Подключаем общие функции и базу данных
require_once __DIR__ . '/includes/functions.php';

// 2. Определяем, какую страницу хотят открыть через ?page=...
//    Например: index.php?page=login  → $page = 'login'
$page = $_GET['page'] ?? '';

// 3. Если параметр ?page не передан (зашли просто на /lab-system/ )
if ($page === '') {
    if (is_auth()) {
        // Пользователь уже залогинен
        if (is_admin()) {
            // Если это админ (главврач) — в админ-панель
            $page = 'admin_dashboard';
        } else {
            // Если это обычный врач — на его главную страницу
            $page = 'doctor_main';
        }
    } else {
        // Никто не вошёл — показываем страницу входа
        $page = 'login';
    }
}

// 4. В зависимости от значения $page подключаем нужный файл
switch ($page) {

    // --- АВТОРИЗАЦИЯ ---

    case 'login':
        require __DIR__ . '/pages/auth/login.php';
        break;

    case 'register_doctor':
        // Регистрация нового врача
        require __DIR__ . '/pages/auth/register_doctor.php';
        break;

    case 'logout':
        require __DIR__ . '/pages/auth/logout.php';
        break;


    // --- ВРАЧ ---

    case 'doctor_main':
        require_auth();
        require __DIR__ . '/pages/doctor/main.php';
        break;

    case 'patient_register':
        // Регистрация пациента
        require_auth();
        require __DIR__ . '/pages/patients/register.php';
        break;



    // --- АДМИН ---

    case 'admin_dashboard':
        require_admin();
        require __DIR__ . '/pages/admin/admin_dashboard.php';
        break;


    // --- ОШИБКА / НЕИЗВЕСТНАЯ СТРАНИЦА ---
    case 'ba':
    require_auth();
    require __DIR__ . '/pages/doctor/ba.php';
    break;
        case 'analysis_view':
    require_auth();
    require __DIR__ . '/pages/doctor/analysis_view.php';
    break;
    case 'analysis_export':
    require_auth();
    require __DIR__ . '/pages/doctor/analysis_export.php';
    break;
        case 'patient_select':
    require_auth();
    require __DIR__ . '/pages/patients/select.php';
    break;

   case 'tuh':
        require_auth();
        require __DIR__ . '/pages/doctor/tuh.php';
        break;
    case 'tup':
        require_auth();
        require __DIR__ . '/pages/doctor/tup.php';
        break;
      case 'ifa':
        require_auth();
        require __DIR__ . '/pages/doctor/ifa.php';
        break;
                // --- ВРАЧ ---


case 'analyses_list':        // <-- ДОБАВИЛИ
    require_auth();
    require __DIR__ . '/pages/doctor/analyses_list.php';
    break;
    // --- ОТЧЁТЫ ПО ПАЦИЕНТАМ ---
    case 'reports':
        require_auth();
        require __DIR__ . '/pages/doctor/reports.php';
        break;
        case 'sum':
        require_auth();
        require __DIR__ . '/pages/doctor/sum.php';
        break;
            // --- АДМИН ---

    case 'admin_dashboard':
        require_admin();
        require __DIR__ . '/pages/admin/admin_dashboard.php';
        break;

    case 'admin_patients':
        require_admin();
        require __DIR__ . '/pages/admin/admin_patients.php';
        break;

    case 'admin_indicators':
        require_admin();
        require __DIR__ . '/pages/admin/admin_indicators.php';
        break;

    case 'admin_users':
        require_admin();
        require __DIR__ . '/pages/admin/admin_users.php';
        break;
        case 'admin_user_edit':
        require_admin();
        require __DIR__ . '/pages/admin/admin_user_edit.php';
        break;
            case 'admin_analysis_edit':
        require_admin();
        require __DIR__ . '/pages/admin/admin_analysis_edit.php';
        break;
        case 'admin_stats':
        require_admin();
        require __DIR__ . '/pages/admin/admin_stats.php';
        break;
            case 'admin_users_export':
        require_admin();
        require __DIR__ . '/pages/admin/admin_users_export.php';
        break;
    default:
        require __DIR__ . '/pages/errors/404.php';
        break;
}
