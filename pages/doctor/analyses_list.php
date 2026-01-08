<?php
// pages/doctor/analyses_list.php
// Список сохранённых анализов (для врача и админа)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Сохранённые анализы';

// Текущий пользователь
$doctorId = current_user_id();
$isAdmin  = is_admin();

// ---- ФИЛЬТРЫ (GET) ----
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$typeCode   = trim($_GET['type_code'] ?? '');
$patientQ   = trim($_GET['patient_q'] ?? '');

// ---- БАЗОВЫЙ SQL + ДИНАМИЧЕСКИЕ УСЛОВИЯ ----
$sql = "
    SELECT
        pa.id,
        pa.check_number,
        pa.created_at,
        pa.total_price,
        t.code         AS analysis_type_code,
        t.name         AS analysis_type_name,
        p.first_name   AS patient_first_name,
        p.last_name    AS patient_last_name,
        u.full_name    AS doctor_name
    FROM patient_analyses pa
    JOIN analysis_types t ON pa.analysis_type_id = t.id
    LEFT JOIN patients p   ON pa.patient_id = p.id
    LEFT JOIN users u      ON pa.doctor_id = u.id
    WHERE 1=1
";

$params = [];

// Если не админ — показываем только анализы текущего врача
if (!$isAdmin) {
    $sql .= " AND pa.doctor_id = :doctor_id";
    $params['doctor_id'] = $doctorId;
}

// Фильтр по дате "с"
if ($dateFrom !== '') {
    $sql .= " AND DATE(pa.created_at) >= :date_from";
    $params['date_from'] = $dateFrom; // формат YYYY-MM-DD
}

// Фильтр по дате "по"
if ($dateTo !== '') {
    $sql .= " AND DATE(pa.created_at) <= :date_to";
    $params['date_to'] = $dateTo; // формат YYYY-MM-DD
}

// Фильтр по типу анализа (BA, TUH, TUP, IFA и т.д.)
if ($typeCode !== '') {
    $sql .= " AND t.code = :type_code";
    $params['type_code'] = $typeCode;
}

// Поиск по пациенту (имя / фамилия)
if ($patientQ !== '') {
    $sql .= " AND (p.first_name LIKE :patient_q1 OR p.last_name LIKE :patient_q2)";
    $params['patient_q1'] = '%' . $patientQ . '%';
    $params['patient_q2'] = '%' . $patientQ . '%';
}

$sql .= " ORDER BY pa.created_at DESC, pa.id DESC LIMIT 200";

// Подготовка и выполнение
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$analyses = $stmt->fetchAll();

// Список типов анализов для фильтра (чтобы показывать BA / TUH / TUP / IFA)
$stmtTypes = $pdo->query("SELECT code, name FROM analysis_types ORDER BY name");
$types = $stmtTypes->fetchAll();

foreach ($analyses as &$row) {
    $code = $row['analysis_type_code'] ?? '';
    if ($code === 'TUP' || $code === 'TUH') {
        $row['total_price'] = 20.00;
    }
}
unset($row); // на всякий случай
// Общая сумма по найденным анализам
$grandTotal = 0.0;
foreach ($analyses as $row) {
    $grandTotal += (float)$row['total_price'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/doctor.css">

<div class="container py-4 doctor-main">
    <div class="row mb-3">
        <div class="col-12 col-lg-8">
            <h1 class="h4 mb-1">Сохранённые анализы</h1>
            <p class="doctor-subtitle mb-0">
                Здесь отображаются все анализы
                <?php if ($isAdmin): ?>
                    по больнице
                <?php else: ?>
                    оформленные вами
                <?php endif; ?>.
            </p>
        </div>
        <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0">
            <a href="/lab-system/index.php?page=doctor_main" class="btn btn-outline-light btn-sm">
                ⬅ В панель врача
            </a>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="doctor-panel mb-3">
        <h2 class="doctor-panel-title mb-3">Фильтр анализов</h2>

        <form method="get" action="/lab-system/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="analyses_list">

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

            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm">Тип анализа</label>
                <select name="type_code" class="form-select form-select-sm">
                    <option value="">— Все —</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['code']); ?>"
                            <?php echo ($typeCode === $t['code']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['code'] . ' — ' . $t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm">Пациент</label>
                <input
                    type="text"
                    name="patient_q"
                    class="form-control form-control-sm"
                    placeholder="Имя или фамилия"
                    value="<?php echo htmlspecialchars($patientQ); ?>"
                >
            </div>

            <div class="col-12 col-md-3 mt-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    🔍 Применить фильтр
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица анализов -->
    <div class="doctor-panel">
        <h2 class="doctor-panel-title mb-3">Результаты поиска</h2>

        <div class="table-responsive">
            <table class="table table-sm table-dark align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Дата / время</th>
                        <th>Тип анализа</th>
                        <th>Пациент</th>
                        <th>Врач</th>
                        <th class="text-end" style="width: 110px;">Сумма</th>
                        <th style="width: 200px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($analyses): ?>
                        <?php foreach ($analyses as $row): ?>
                            <?php
                                $patientFullName = 'Не указан';
                                if (!empty($row['patient_last_name']) || !empty($row['patient_first_name'])) {
                                    $patientFullName = trim($row['patient_last_name'] . ' ' . $row['patient_first_name']);
                                }

                                $dt = $row['created_at']
                                    ? date('d.m.Y H:i', strtotime($row['created_at']))
                                    : '';
                            ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo htmlspecialchars($dt); ?></td>
                                <td>
                                    <span class="badge bg-secondary me-1">
                                        <?php echo htmlspecialchars($row['analysis_type_code']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($row['analysis_type_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($patientFullName); ?></td>
                                <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                <td class="text-end">
                                    <?php echo number_format((float)$row['total_price'], 2, '.', ' '); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a
                                            href="/lab-system/index.php?page=analysis_view&id=<?php echo (int)$row['id']; ?>"
                                            class="btn btn-outline-light btn-sm"
                                        >
                                            Открыть
                                        </a>
                                        <a
                                            href="/lab-system/pages/doctor/analysis_export.php?id=<?php echo (int)$row['id']; ?>&mode=check"
                                            class="btn btn-outline-success btn-sm"
                                        >
                                            Чек
                                        </a>
                                        <a
                                            href="/lab-system/pages/doctor/analysis_export.php?id=<?php echo (int)$row['id']; ?>&mode=full"
                                            class="btn btn-outline-success btn-sm"
                                        >
                                            Отчёт
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                Анализы по указанным фильтрам не найдены.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ИТОГО + Excel -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-3 gap-2">
            <div class="text-muted-soft small">
                Найдено анализов: <strong><?php echo count($analyses); ?></strong><br>
                Итоговая сумма по фильтру:
                <strong><?php echo number_format($grandTotal, 2, '.', ' '); ?></strong>
            </div>

            <!-- Кнопка Excel: выгрузка именно этих данных по фильтру -->
            <form method="get" action="/lab-system/pages/doctor/analyses_list_export.php" class="d-inline-block">
                <input type="hidden" name="date_from"  value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="hidden" name="date_to"    value="<?php echo htmlspecialchars($dateTo); ?>">
                <input type="hidden" name="type_code"  value="<?php echo htmlspecialchars($typeCode); ?>">
                <input type="hidden" name="patient_q"  value="<?php echo htmlspecialchars($patientQ); ?>">
                <button type="submit" class="btn btn-sm btn-outline-success">
                    ⬇ Выгрузить в Excel (текущий фильтр)
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
