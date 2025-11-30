<?php
// pages/doctor/sum.php
// Сводный отчёт по анализам за период

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Сводка анализов';

$doctorId = current_user_id();
$isAdmin  = function_exists('is_admin') && is_admin();

// ====== ФИЛЬТР ПО ДАТАМ ======

// Ожидаем формат YYYY-MM-DD
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$today = date('Y-m-d');

// По умолчанию: с первого дня текущего месяца по сегодня
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = $today;
}

// Преобразуем в границы дат для SQL
$fromDateTime = $dateFrom . ' 00:00:00';
$toDateTime   = $dateTo   . ' 23:59:59';

// ====== ФУНКЦИЯ ПОЛУЧЕНИЯ СВОДКИ ======

function loadSummary(PDO $pdo, string $fromDateTime, string $toDateTime, bool $isAdmin, int $doctorId): array
{
    $sql = "
        SELECT
            DATE(pa.created_at) AS d,
            GROUP_CONCAT(DISTINCT at.name ORDER BY at.name SEPARATOR ', ') AS analyses_list,
            COUNT(DISTINCT pa.patient_id) AS patients_count,
            COUNT(*) AS analyses_count
        FROM patient_analyses pa
        JOIN analysis_types at ON pa.analysis_type_id = at.id
        WHERE pa.created_at BETWEEN :from AND :to
    ";

    $params = [
        ':from' => $fromDateTime,
        ':to'   => $toDateTime,
    ];

    // Для врача — только его анализы; для главврача — все
    if (!$isAdmin) {
        $sql .= " AND pa.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctorId;
    }

    $sql .= "
        GROUP BY DATE(pa.created_at)
        ORDER BY d DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ====== ЕСЛИ НАЖАТА КНОПКА ЭКСПОРТА В EXCEL ======

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $rows = loadSummary($pdo, $fromDateTime, $toDateTime, $isAdmin, $doctorId);

    // Название файла
    $fileName = 'analyses_summary_' . $dateFrom . '_to_' . $dateTo . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<meta charset="UTF-8">';

    ?>
    <table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:12px;">
        <thead>
            <tr style="background:#0f172a; color:#ffffff; font-weight:bold;">
                <th style="text-align:center; min-width:40px;">№</th>
                <th style="text-align:center; min-width:90px;">Дата</th>
                <th style="text-align:left;  min-width:220px;">Анализы (типы)</th>
                <th style="text-align:center; min-width:90px;">Пациенты</th>
                <th style="text-align:center; min-width:120px;">Количество анализов</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows): ?>
                <?php $i = 1; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td style="text-align:center;"><?php echo $i++; ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($r['d']); ?></td>
                        <td style="text-align:left;"><?php echo htmlspecialchars($r['analyses_list']); ?></td>
                        <td style="text-align:center;"><?php echo (int)$r['patients_count']; ?></td>
                        <td style="text-align:center;"><?php echo (int)$r['analyses_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; color:#6b7280;">
                        Нет данных за выбранный период.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    exit;
}

// ====== ОТЧЁТ ДЛЯ ЭКРАНА ======

$rows = loadSummary($pdo, $fromDateTime, $toDateTime, $isAdmin, $doctorId);

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
            <div>
                <h1 class="ba-header-title h4 mb-1">Сводка анализов</h1>
                <div class="ba-header-meta small text-muted-soft">
                    Период: с <strong><?php echo htmlspecialchars($dateFrom); ?></strong>
                    по <strong><?php echo htmlspecialchars($dateTo); ?></strong>
                    <?php if ($isAdmin): ?>
                        <br><span class="text-warning">Роль: Главврач — отображаются анализы всех врачей.</span>
                    <?php else: ?>
                        <br><span class="text-muted-soft">Роль: Врач — отображаются только ваши анализы.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Фильтр по датам -->
        <form method="get" action="/lab-system/index.php" class="row gy-2 gx-3 align-items-end mb-3">
            <input type="hidden" name="page" value="sum">

            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label">Дата начала</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >
            </div>

            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label">Дата конца</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >
            </div>

            <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    Фильтровать
                </button>
                <button
                    type="submit"
                    name="export"
                    value="excel"
                    class="btn btn-success btn-sm flex-fill"
                >
                    ⬇ В Excel
                </button>
            </div>
        </form>

        <!-- Таблица сводки -->
        <div class="table-responsive">
            <table class="table table-sm table-dark table-striped align-middle ba-result-table mb-0">
                <thead>
                    <tr>
                        <th style="width:60px; text-align:center;">№</th>
                        <th style="width:110px; text-align:center;">Дата</th>
                        <th>Анализы (типы)</th>
                        <th style="width:140px; text-align:center;">Пациенты</th>
                        <th style="width:180px; text-align:center;">Количество анализов</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="text-center"><?php echo $i++; ?></td>
                                <td class="text-center">
                                    <?php echo htmlspecialchars($r['d']); ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <?php echo htmlspecialchars($r['analyses_list']); ?>
                                    </div>
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo (int)$r['patients_count']; ?>
                                </td>
                                <td class="text-center fw-bold">
                                    <?php echo (int)$r['analyses_count']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted-soft py-3">
                                Нет данных за выбранный период. Попробуйте изменить даты фильтра.
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
