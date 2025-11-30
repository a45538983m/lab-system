<?php
// pages/doctor/reports.php
// Отчёты по пациентам: суммы анализов за период

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Отчёты по пациентам';

// Текущий пользователь
$doctorId = current_user_id();
$isAdmin  = is_admin();

// ---- ФИЛЬТРЫ (GET) ----
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

// ---- СТРОИМ WHERE + ПАРАМЕТРЫ ----
$where  = ' WHERE 1=1 ';
$params = [];

// Если не админ — только анализы текущего врача
if (!$isAdmin) {
    $where .= ' AND pa.doctor_id = :doctor_id ';
    $params['doctor_id'] = $doctorId;
}

// Фильтр по дате "с"
if ($dateFrom !== '') {
    $where .= ' AND DATE(pa.created_at) >= :date_from ';
    $params['date_from'] = $dateFrom; // YYYY-MM-DD
}

// Фильтр по дате "по"
if ($dateTo !== '') {
    $where .= ' AND DATE(pa.created_at) <= :date_to ';
    $params['date_to'] = $dateTo; // YYYY-MM-DD
}

// ---- ГРУППИРОВКА ПО ПАЦИЕНТАМ ----
$sql = "
    SELECT
        pa.patient_id,
        p.first_name,
        p.last_name,
        COUNT(*)              AS analyses_count,
        MIN(pa.created_at)    AS first_date,
        MAX(pa.created_at)    AS last_date,
        SUM(pa.total_price)   AS sum_total
    FROM patient_analyses pa
    LEFT JOIN patients p ON pa.patient_id = p.id
    $where
    GROUP BY pa.patient_id, p.first_name, p.last_name
    ORDER BY sum_total DESC, last_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- ОБЩИЙ ИТОГ ПО ВСЕМ ПАЦИЕНТАМ ----
$sqlTotal = "
    SELECT SUM(pa.total_price) AS grand_total
    FROM patient_analyses pa
    $where
";
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($params);
$totalRow   = $stmtTotal->fetch();
$grandTotal = $totalRow && $totalRow['grand_total'] !== null
    ? (float)$totalRow['grand_total']
    : 0.0;

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/doctor.css">

<div class="container py-4 doctor-main">
    <div class="row mb-3">
        <div class="col-12 col-lg-8">
            <h1 class="h4 mb-1">Отчёты по пациентам</h1>
            <p class="doctor-subtitle mb-0">
                Список пациентов, по которым были оформлены анализы
                <?php if ($isAdmin): ?>
                    за выбранный период по всей больнице.
                <?php else: ?>
                    за выбранный период, оформленные вами.
                <?php endif; ?>
            </p>
        </div>
        <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0">
            <a href="/lab-system/index.php?page=doctor_main" class="btn btn-outline-light btn-sm">
                ⬅ В панель врача
            </a>
        </div>
    </div>

    <!-- Блок фильтров -->
    <div class="doctor-panel mb-3">
        <h2 class="doctor-panel-title mb-3">Фильтр по дате</h2>

        <form method="get" action="/lab-system/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="reports">

            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm">Дата с</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm">Дата по</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >
            </div>

            <div class="col-12 col-md-3 mt-2 mt-md-0">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    🔍 Показать отчёт
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица пациентов -->
    <div class="doctor-panel">
        <h2 class="doctor-panel-title mb-3">Принятые пациенты и суммы анализов</h2>

        <div class="table-responsive">
            <table class="table table-sm table-dark align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID пациента</th>
                        <th>Пациент</th>
                        <th style="width: 120px;">Кол-во анализов</th>
                        <th>Период</th>
                        <th class="text-end" style="width: 140px;">Сумма по пациенту</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $patientIdRow = $row['patient_id'];
                                $patientFullName = 'Не указан';
                                if (!empty($row['last_name']) || !empty($row['first_name'])) {
                                    $patientFullName = trim($row['last_name'] . ' ' . $row['first_name']);
                                }

                                $firstDate = $row['first_date']
                                    ? date('d.m.Y', strtotime($row['first_date']))
                                    : '';
                                $lastDate  = $row['last_date']
                                    ? date('d.m.Y', strtotime($row['last_date']))
                                    : '';
                            ?>
                            <tr>
                                <td>
                                    <?php echo $patientIdRow ? (int)$patientIdRow : '—'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($patientFullName); ?></td>
                                <td><?php echo (int)$row['analyses_count']; ?></td>
                                <td>
                                    <?php if ($firstDate && $lastDate): ?>
                                        <?php echo htmlspecialchars($firstDate); ?> &mdash; <?php echo htmlspecialchars($lastDate); ?>
                                    <?php elseif ($firstDate): ?>
                                        <?php echo htmlspecialchars($firstDate); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format((float)$row['sum_total'], 2, '.', ' '); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Нет данных по выбранному периоду.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Общая сумма по всем пациентам:</th>
                        <th class="text-end">
                            <?php echo number_format($grandTotal, 2, '.', ' '); ?>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
