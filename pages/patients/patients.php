<?php
// pages/patients/patients.php
// Очередь пациентов + поиск по имени и дате (из таблицы patient_queue)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Пациенты — очередь';

// ===== Удаление из очереди (помечаем как cancelled) =====
if (isset($_GET['delete_queue_id'])) {
    $deleteId = (int)$_GET['delete_queue_id'];

    $stmtDel = $pdo->prepare("UPDATE patient_queue SET status = 'cancelled' WHERE id = :id");
    $stmtDel->execute([':id' => $deleteId]);

    // После удаления — редирект без параметра delete_queue_id
    header('Location: /lab-system/index.php?page=patients');
    exit;
}

// ===== Фильтры =====
$q        = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// Если даты не заданы — очередь на сегодня
if ($dateFrom === '' && $dateTo === '') {
    $today    = date('Y-m-d');
    $dateFrom = $today;
    $dateTo   = $today;
}

$where  = [];
$params = [];

// Поиск по имени (ФИО) пациента
if ($q !== '') {
    $where[]          = '(p.first_name LIKE :q1 OR p.last_name LIKE :q2)';
    $params[':q1']    = '%' . $q . '%';
    $params[':q2']    = '%' . $q . '%';
}


// Фильтр по дате очереди (visit_date из patient_queue)
if ($dateFrom !== '') {
    $where[]              = 'q.visit_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]            = 'q.visit_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

// Показываем только активные/обслуженные записи, но НЕ отменённые
$where[] = "q.status IN ('waiting', 'done')";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Берём данные ИЗ ОЧЕРЕДИ + пациента
$sql = "
    SELECT
        q.id          AS queue_id,
        q.visit_date,
        q.status,
        q.total_amount,
        q.created_at  AS queue_created_at,
        p.id          AS patient_id,
        p.first_name,
        p.last_name,
        p.sex
    FROM patient_queue q
    JOIN patients p ON p.id = q.patient_id
    $whereSql
    ORDER BY q.visit_date ASC, q.created_at ASC, q.id ASC
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$queueRows = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">

    <!-- Шапка + кнопки -->
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">Очередь пациентов</div>
                <div class="ba-header-meta">
                    Очередь пациентов за выбранный день. Пациенты могут узнать здесь свой номер в очереди.
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/lab-system/index.php?page=patient_register" class="btn btn-sm btn-accent-primary">
                    + Регистрация пациента
                </a>
                <a href="/lab-system/index.php?page=patient_select1" class="btn btn-sm btn-outline-light">
                    + Добавить в очередь
                </a>
            </div>
        </div>
    </div>

    <!-- Фильтр -->
    <div class="panel p-3 mb-3">
        <h2 class="ba-section-title mb-3">Фильтр очереди</h2>

        <form method="get" action="/lab-system/index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="patients">

            <div class="col-12 col-md-4">
                <label class="form-label">Имя или фамилия</label>
                <input
                    type="text"
                    name="q"
                    class="form-control form-control-sm"
                    placeholder="Например: Саидов"
                    value="<?php echo htmlspecialchars($q); ?>"
                >
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label">Дата с</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label">Дата по</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >
            </div>

            <div class="col-12 col-md-2 d-grid d-md-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    Поиск
                </button>
                <a href="/lab-system/index.php?page=patients" class="btn btn-outline-light btn-sm flex-fill">
                    Сброс
                </a>
            </div>
        </form>
    </div>

    <!-- Таблица очереди -->
    <div class="panel p-3">
        <h2 class="ba-section-title mb-3">
            Очередь пациентов за выбранный период
            <span class="text-muted-soft small">
                (показываются только те, кто добавлен в очередь)
            </span>
        </h2>

        <div class="table-responsive">
            <table class="table table-sm table-dark table-striped align-middle ba-result-table mb-0">
                <thead>
                    <tr>
                        <th style="width:60px;">№ очереди</th>
                        <th style="width:180px;">Дата заявки</th>
                        <th>Пациент</th>
                        <th style="width:80px;">Пол</th>
                        <th style="width:160px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($queueRows): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($queueRows as $row): ?>
                            <?php
                                $created = $row['queue_created_at'] ?? null;
                                $createdFormatted = $created ? date('d.m.Y H:i', strtotime($created)) : '—';

                                $sexLabel = '';
                                if ($row['sex'] === 'M') {
                                    $sexLabel = 'Муж';
                                } elseif ($row['sex'] === 'F') {
                                    $sexLabel = 'Жен';
                                }

                                $fullName = trim($row['last_name'] . ' ' . $row['first_name']);

                                // Строка потемнее, если уже обслужен (status = done)
                                $rowClass = ($row['status'] === 'done') ? 'opacity-75' : '';
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($createdFormatted); ?></td>
                                <td><?php echo htmlspecialchars($fullName); ?></td>
                                <td><?php echo htmlspecialchars($sexLabel ?: '—'); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a
                                            href="/lab-system/index.php?page=patients_view&queue_id=<?php echo (int)$row['queue_id']; ?>&patient_id=<?php echo (int)$row['patient_id']; ?>"
                                            class="btn btn-sm btn-outline-info"
                                        >
                                            Данные
                                        </a>

                                        <?php if ($row['status'] === 'waiting'): ?>
                                            <a
                                                href="/lab-system/index.php?page=patients&delete_queue_id=<?php echo (int)$row['queue_id']; ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Удалить этого пациента из очереди?');"
                                            >
                                                Удалить
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary align-self-center">
                                                Обслужен
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Очередь за выбранный период пуста.
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
