<?php
// includes/header.php
// Общий верх страницы (шапка + подключение стилей)

if (!isset($pageTitle)) {
    $pageTitle = 'Лабораторная система';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS (локально) -->
    <link rel="stylesheet" href="/lab-system/public/bootstrap/css/bootstrap.min.css">

    <!-- Наш общий CSS -->
    <link rel="stylesheet" href="/lab-system/public/css/base.css">
</head>
<body>
<div class="main-wrapper">
    <header class="site-header">
        <div class="container d-flex justify-content-between align-items-center gap-3">
            <!-- ЛОГОТИП БОЛЬНИЦЫ / СИСТЕМЫ -->
            <div class="d-flex align-items-center gap-2">
                <div class="hospital-logo d-flex align-items-center justify-content-center">
                    <span class="hospital-logo-cross">+</span>
                </div>
                <div class="brand">
                    Лабораторная система
                    <div class="brand-subtitle">больничные анализы и отчёты</div>
                </div>
            </div>

            <!-- НАВИГАЦИЯ -->
            <nav class="d-none d-md-flex align-items-center gap-3 site-nav">
                <a href="/lab-system/index.php" class="nav-link-small">
                    Главная
                </a>

                <!-- Список анализов (общий для всех врачей) -->
                <a href="/lab-system/index.php?page=analyses_list" class="nav-link-small">
                    Анализы
                </a>

                <!-- Если это админ (главврач) — показываем ссылку в админ-панель -->
                <?php if (function_exists('is_admin') && is_admin()): ?>
                    <a href="/lab-system/index.php?page=admin_dashboard" class="nav-link-small">
                        Админ-панель
                    </a>
                <?php else: ?>
                <!-- Для обычного врача "Отчёты" ведут в сводку sum.php -->
                       <a href="/lab-system/index.php?page=sum" class="nav-link-small">
                          Отчёты
                       </a>
        <?php endif; ?>

            </nav>

            <!-- БЛОК ПОЛЬЗОВАТЕЛЯ -->
            <div class="d-flex align-items-center gap-2">
                <?php if (is_auth()): ?>
                    <div class="user-pill">
                        <div class="user-pill-name">
                            <?php echo htmlspecialchars(current_user_name()); ?>
                        </div>
                        <div class="user-pill-role">
                            <?php echo current_user_role() === 'admin' ? 'Главврач' : 'Врач'; ?>
                        </div>
                    </div>
                    <a href="/lab-system/index.php?page=logout" class="btn btn-sm btn-outline-light">
                        Выход
                    </a>
                <?php else: ?>
                    <a href="/lab-system/index.php?page=login" class="btn btn-sm btn-outline-light me-1">
                        Войти
                    </a>
                    <a href="/lab-system/index.php?page=register_doctor" class="btn btn-sm btn-accent-primary">
                        Регистрация врача
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-content">
