<?php
// pages/admin/admin_stats.php
// Статистика анализов по типам за выбранный период:
// - количество анализов по каждому типу
// - количество показателей (каждый показатель отдельно) за период
// + кнопка выгрузки этой статистики в Excel

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Статистика анализов по типам';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// Если даты не заданы — по умолчанию текущий месяц
if ($dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-d');
}

// Собираем WHERE и параметры (по pa.created_at)
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

// --- 1) Количество анализов по типам ---
$sqlAnalyses = "
    SELECT
        t.id,
        t.code,
        t.name,
        COUNT(pa.id) AS analyses_count
    FROM analysis_types t
    LEFT JOIN patient_analyses pa
        ON pa.analysis_type_id = t.id
        " . ($whereSql ? $whereSql : '') . "
    GROUP BY t.id, t.code, t.name
    HAVING analyses_count > 0
    ORDER BY analyses_count DESC
";

$stmtAnalyses = $pdo->prepare($sqlAnalyses);
$stmtAnalyses->execute($params);
$analysesStats = $stmtAnalyses->fetchAll();

// Общий итог по количеству анализов
$totalAnalyses = 0;
foreach ($analysesStats as $row) {
    $totalAnalyses += (int)$row['analyses_count'];
}

// --- 2) Количество показателей (каждый показатель отдельно) ---
// Здесь уже считаем не по типу анализа, а по каждому indicator'у (analysis_indicators)
$sqlIndicators = "
    SELECT
        ai.id,
        ai.name       AS indicator_name,
        ai.norm_text  AS norm_text,
        t.code        AS type_code,
        t.name        AS type_name,
        COUNT(i.id)   AS indicators_count
    FROM analysis_indicators ai
    JOIN patient_analysis_items i
        ON i.indicator_id = ai.id
    JOIN patient_analyses pa
        ON pa.id = i.patient_analysis_id
    LEFT JOIN analysis_types t
        ON ai.analysis_type_id = t.id
        " . ($whereSql ? $whereSql : '') . "
    GROUP BY ai.id, ai.name, ai.norm_text, t.code, t.name
    HAVING indicators_count > 0
    ORDER BY indicators_count DESC
";

$stmtIndicators = $pdo->prepare($sqlIndicators);
$stmtIndicators->execute($params);
$indicatorsStats = $stmtIndicators->fetchAll();

// Общий итог по количеству показателей (всех строк)
$totalIndicators = 0;
foreach ($indicatorsStats as $row) {
    $totalIndicators += (int)$row['indicators_count'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">

    <!-- Шапка страницы -->
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">
                    Статистика анализов по типам
                </div>
                <div class="ba-header-meta">
                    Вы вошли как: <?php echo htmlspecialchars(current_user_name()); ?> (Главврач)
                </div>
            </div>
            <div class="text-md-end text-muted-soft small">
                Обзор количества анализов и отдельных показателей (БА, ТУП, ТУХ, ИФА и др.) за выбранный период.
            </div>
        </div>
    </div>

    <!-- Фильтр по дате -->
    <div class="panel p-3 mb-3">
        <h2 class="ba-section-title mb-3">Фильтр по дате (статистика)</h2>

        <form method="get" action="/lab-system/index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="admin_stats">

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
                <button type="submit" class="btn btn-primary btn-sm align-self-end">
                    Применить
                </button>

                <a href="/lab-system/index.php?page=admin_stats" class="btn btn-outline-light btn-sm align-self-end">
                    Сбросить
                </a>
            </div>
        </form>
    </div>

    <!-- Итоговые карточки -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="panel p-3">
                <div class="text-muted-soft small">Общее количество анализов за период</div>
                <div class="fs-4 mt-1">
                    <?php echo (int)$totalAnalyses; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="panel p-3">
                <div class="text-muted-soft small">Общее количество показателей (строк) за период</div>
                <div class="fs-4 mt-1">
                    <?php echo (int)$totalIndicators; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица: количество анализов по типам -->
    <div class="panel p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="ba-section-title mb-0">Количество анализов по типам</h2>
            <span class="text-muted-soft small">
                Сортировка по убыванию, самые частые анализы сверху.
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-dark table-striped align-middle ba-result-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">№</th>
                        <th style="width: 80px;">Код</th>
                        <th>Тип анализа</th>
                        <th style="width: 160px;">Кол-во анализов</th>
                        <th style="width: 160px;">Доля от всех, %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($analysesStats): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($analysesStats as $row): ?>
                            <?php
                                $count = (int)$row['analyses_count'];
                                $percent = $totalAnalyses > 0
                                    ? round($count * 100 / $totalAnalyses, 1)
                                    : 0;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($row['code']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $count; ?></td>
                                <td><?php echo $percent; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                За выбранный период анализы не найдены.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Таблица: количество показателей (каждый показатель отдельно) -->
    <div class="panel p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="ba-section-title mb-0">Количество показателей (по каждому показателю)</h2>
            <span class="text-muted-soft small">
                Каждый показатель и сколько раз он был выполнен за период.
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-dark table-striped align-middle ba-result-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">№</th>
                        <th style="width: 80px;">Код типа</th>
                        <th>Тип анализа</th>
                        <th>Показатель</th>
                        <th style="width: 220px;">Норма</th>
                        <th style="width: 160px;">Кол-во раз</th>
                        <th style="width: 160px;">Доля от всех, %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($indicatorsStats): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($indicatorsStats as $row): ?>
                            <?php
                                $count = (int)$row['indicators_count'];
                                $percent = $totalIndicators > 0
                                    ? round($count * 100 / $totalIndicators, 1)
                                    : 0;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <?php if (!empty($row['type_code'])): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($row['type_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['type_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['indicator_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['norm_text']); ?></td>
                                <td><?php echo $count; ?></td>
                                <td><?php echo $percent; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                За выбранный период показатели не найдены.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Кнопка Excel -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted-soft small">
                Вы можете выгрузить эту статистику в Excel.
            </div>
            <form method="get" action="/lab-system/pages/admin/admin_stats_export.php" class="d-inline-block">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="hidden" name="date_to"   value="<?php echo htmlspecialchars($dateTo); ?>">
                <button type="submit" class="btn btn-sm btn-outline-success">
                    ⬇ Выгрузить статистику в Excel
                </button>
            </form>
        </div>
    </div>

    <div class="mb-4">
        <a href="/lab-system/index.php?page=admin_dashboard" class="btn btn-outline-light btn-sm">
            ← Назад в админ-панель
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
