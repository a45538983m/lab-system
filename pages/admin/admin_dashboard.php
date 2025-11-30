<?php
// pages/admin/admin_dashboard.php
// Админ-панель (главврач):
// - быстрые ссылки на управление
// - отчёты по анализам с фильтром по дате и экспортом в Excel

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Админ-панель — отчёты и управление';

// ==== ФИЛЬТРЫ ПО ДАТЕ ====

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

if ($dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-d');
}

// ==== ЗАГРУЖАЕМ АНАЛИЗЫ ====

$where  = [];
$params = [];

if ($dateFrom !== '') {
    $where[]              = 'pa.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[]            = 'pa.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        pa.*,
        p.first_name   AS patient_first_name,
        p.last_name    AS patient_last_name,
        u.full_name    AS doctor_name,
        t.name         AS analysis_type_name,
        t.code         AS analysis_type_code
    FROM patient_analyses pa
    LEFT JOIN patients p   ON pa.patient_id = p.id
    LEFT JOIN users u      ON pa.doctor_id = u.id
    LEFT JOIN analysis_types t ON pa.analysis_type_id = t.id
    $whereSql
    ORDER BY pa.created_at DESC, pa.id DESC
    LIMIT 300
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$analyses = $stmt->fetchAll();

// общая сумма и количество пациентов
$grandTotal     = 0.0;
$patientsSet    = [];
foreach ($analyses as $row) {
    $grandTotal += (float)$row['total_price'];

    if (!empty($row['patient_id'])) {
        $patientsSet[(int)$row['patient_id']] = true;
    }
}
$patientsCount  = count($patientsSet);
$analysesCount  = count($analyses);

// ====== ЭКСПОРТ В EXCEL (сводка) ======

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $fileName = 'admin_report_' . $dateFrom . '_to_' . $dateTo . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<meta charset="UTF-8">';

    ?>
    <table border="0" cellspacing="0" cellpadding="4" style="font-family:Arial, sans-serif; font-size:12px;">
        <tr>
            <td colspan="4" style="
                font-size:16px;
                font-weight:bold;
                padding:8px 4px;
                background:#020617;
                color:#e5e7eb;
                border-bottom:2px solid #0f172a;
            ">
                Финансовый отчёт по анализам
            </td>
        </tr>
        <tr>
            <td colspan="4" style="padding:6px 4px; background:#0f172a; color:#9ca3af;">
                Период: <strong style="color:#e5e7eb;"><?php echo htmlspecialchars($dateFrom); ?></strong>
                —
                <strong style="color:#e5e7eb;"><?php echo htmlspecialchars($dateTo); ?></strong>
            </td>
        </tr>
    </table>

    <table border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:12px; margin-top:8px;">
        <thead>
            <tr style="background:#0f172a; color:#ffffff; font-weight:bold;">
                <th style="min-width:160px; text-align:center;">Период</th>
                <th style="min-width:120px; text-align:center;">Кол-во анализов</th>
                <th style="min-width:140px; text-align:center;">Кол-во пациентов</th>
                <th style="min-width:140px; text-align:center;">Общая сумма</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align:center;">
                    <?php echo htmlspecialchars($dateFrom); ?> — <?php echo htmlspecialchars($dateTo); ?>
                </td>
                <td style="text-align:center; font-weight:bold;">
                    <?php echo (int)$analysesCount; ?>
                </td>
                <td style="text-align:center; font-weight:bold;">
                    <?php echo (int)$patientsCount; ?>
                </td>
                <td style="text-align:center; font-weight:bold;">
                    <?php echo number_format($grandTotal, 2, '.', ' '); ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
    exit;
}

// ====== Обычный HTML-вывод ======

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">

    <!-- Шапка админ-панели -->
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">Админ-панель главврача</div>
                <div class="ba-header-meta">
                    Вы вошли как: <?php echo htmlspecialchars(current_user_name()); ?> (Главврач)
                </div>
            </div>
            <div class="text-md-end text-muted-soft small">
                Управление пациентами, показателями, врачами и просмотр расширенных отчётов.
            </div>
        </div>
    </div>

    <!-- Быстрые действия -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4 col-lg-3">
            <a href="/lab-system/index.php?page=admin_patients" class="dashboard-tile">
                <div class="dashboard-tile-type">Пациенты</div>
                <div class="dashboard-tile-title">Управление пациентами</div>
                <div class="dashboard-tile-desc">
                    Изменение ФИО, пола и других данных пациентов.
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
            <a href="/lab-system/index.php?page=admin_indicators" class="dashboard-tile">
                <div class="dashboard-tile-type">Показатели</div>
                <div class="dashboard-tile-title">Анализы и цены</div>
                <div class="dashboard-tile-desc">
                    Редактирование названий показателей, норм и цен.
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
            <a href="/lab-system/index.php?page=admin_users" class="dashboard-tile">
                <div class="dashboard-tile-type">Врачи</div>
                <div class="dashboard-tile-title">Учётные записи</div>
                <div class="dashboard-tile-desc">
                    Изменение имён, логинов и паролей врачей.
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
            <a href="#reports-block" class="dashboard-tile">
                <div class="dashboard-tile-type">Отчёты</div>
                <div class="dashboard-tile-title">Финансовый отчёт</div>
                <div class="dashboard-tile-desc">
                    Список всех анализов за период и итоговая сумма.
                </div>
            </a>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
            <a href="/lab-system/index.php?page=admin_stats" class="dashboard-tile">
                <div class="dashboard-tile-type">Статистика</div>
                <div class="dashboard-tile-title">Статистика анализов</div>
                <div class="dashboard-tile-desc">
                    Количество анализов и показателей по типам за период.
                </div>
            </a>
        </div>
    </div>

    <!-- Фильтр по дате -->
    <div class="panel p-3 mb-3" id="reports-block">
        <h2 class="ba-section-title mb-3">Фильтр по дате (отчёты)</h2>

        <form method="get" action="/lab-system/index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="admin_dashboard">

            <div class="col-12 col-md-4">
                <label class="form-label">С даты</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label">По дату</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >
            </div>

            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm align-self-end flex-fill">
                    Применить
                </button>

                <a href="/lab-system/index.php?page=admin_dashboard" class="btn btn-outline-light btn-sm align-self-end">
                    Сбросить
                </a>

                <!-- Кнопка Excel -->
                <button
                    type="submit"
                    name="export"
                    value="excel"
                    class="btn btn-success btn-sm align-self-end flex-fill"
                >
                    ⬇ В Excel
                </button>
            </div>
        </form>
    </div>

    <!-- Итоги по периоду -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="panel p-3">
                <div class="text-muted-soft small">Период</div>
                <div class="fs-6 mt-1">
                    <?php echo htmlspecialchars($dateFrom); ?> —
                    <?php echo htmlspecialchars($dateTo); ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <div class="panel p-3">
                <div class="text-muted-soft small">Кол-во анализов</div>
                <div class="fs-4 mt-1">
                    <?php echo $analysesCount; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <div class="panel p-3">
                <div class="text-muted-soft small">Кол-во пациентов</div>
                <div class="fs-4 mt-1">
                    <?php echo $patientsCount; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
            <div class="panel p-3">
                <div class="text-muted-soft small">Общая сумма за период</div>
                <div class="fs-4 mt-1">
                    <?php echo number_format($grandTotal, 2, '.', ' '); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица анализов -->
    <div class="panel p-3">
        <h2 class="ba-section-title mb-3">Список анализов за период</h2>

        <div class="table-responsive">
            <table class="table table-sm table-dark table-striped align-middle ba-result-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">№</th>
                        <th style="width: 140px;">Дата и время</th>
                        <th>Пациент</th>
                        <th>Тип анализа</th>
                        <th>Врач</th>
                        <th style="width: 120px;">Сумма</th>
                        <th style="width: 260px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($analyses): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($analyses as $row): ?>
                            <?php
                                $patientName = 'Не указан';
                                if (!empty($row['patient_last_name']) || !empty($row['patient_first_name'])) {
                                    $patientName = trim($row['patient_last_name'] . ' ' . $row['patient_first_name']);
                                }

                                $createdAt          = $row['created_at'] ?? null;
                                $createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';

                                $typeLabel = $row['analysis_type_code'] ?? '';
                                if ($typeLabel === 'BA') {
                                    $typeLabel = 'Биохимия (БА)';
                                } elseif ($typeLabel === 'TUH') {
                                    $typeLabel = 'Общий анализ крови (ТУХ)';
                                } elseif ($typeLabel === 'TUP') {
                                    $typeLabel = 'Общий анализ мочи (ТУП)';
                                } else {
                                    $typeLabel = $row['analysis_type_name'] ?: 'Анализ';
                                }
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($createdAtFormatted); ?></td>
                                <td><?php echo htmlspecialchars($patientName); ?></td>
                                <td><?php echo htmlspecialchars($typeLabel); ?></td>
                                <td><?php echo htmlspecialchars($row['doctor_name'] ?? '—'); ?></td>
                                <td><?php echo number_format((float)$row['total_price'], 2, '.', ' '); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <!-- 1) Редактировать анализ (веб-форма главврача) -->
                                        <a
                                            href="/lab-system/index.php?page=admin_analysis_edit&id=<?php echo (int)$row['id']; ?>"
                                            class="btn btn-sm btn-warning"
                                        >
                                            Редактировать
                                        </a>

                                        <!-- 2) Чек (Excel, как раньше) -->
                                        <a
                                            href="/lab-system/pages/doctor/analysis_export.php?id=<?php echo (int)$row['id']; ?>&mode=check"
                                            class="btn btn-sm btn-outline-success"
                                        >
                                            Чек
                                        </a>

                                        <!-- 3) Полный анализ (главврач, с ценами) -->
                                        <a
                                            href="/lab-system/pages/doctor/analysis_export.php?id=<?php echo (int)$row['id']; ?>&mode=full_admin"
                                            class="btn btn-sm btn-outline-info"
                                        >
                                            Полный (главврач)
                                        </a>

                                        <!-- 4) Полный анализ (пациент, без цен) -->
                                        <a
                                            href="/lab-system/pages/doctor/analysis_export.php?id=<?php echo (int)$row['id']; ?>&mode=full_patient"
                                            class="btn btn-sm btn-outline-light"
                                        >
                                            Полный (пациент)
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                За выбранный период анализы не найдены.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
