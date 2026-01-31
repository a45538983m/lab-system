<?php
// pages/patients/patients_view.php
// Просмотр анализов пациента для очереди: список анализов за день,
// выбор (чекбоксы), подсчёт суммы из patient_analysis_items.price,
// сохранение итога в patient_queue.total_amount и смена статуса на done.

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Анализы пациента (очередь)';

$queueId   = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : 0;
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$queueId) {
    die('Не указан ID очереди.');
}

// Загружаем запись очереди + пациента
$sqlQueue = "
    SELECT
        q.*,
        p.first_name,
        p.last_name,
        p.sex
    FROM patient_queue q
    JOIN patients p ON p.id = q.patient_id
    WHERE q.id = :qid
    LIMIT 1
";

$stmtQ = $pdo->prepare($sqlQueue);
$stmtQ->execute([':qid' => $queueId]);
$queueRow = $stmtQ->fetch();

if (!$queueRow) {
    die('Запись в очереди не найдена.');
}

// Если patient_id в GET передан, проверим совпадение (на всякий случай)
if ($patientId && (int)$queueRow['patient_id'] !== $patientId) {
    $patientId = (int)$queueRow['patient_id'];
} else {
    $patientId = (int)$queueRow['patient_id'];
}

$visitDate = $queueRow['visit_date']; // YYYY-MM-DD

// Информация о пациенте
$patientFullName = trim($queueRow['last_name'] . ' ' . $queueRow['first_name']);
$patientSexLabel = '';
if ($queueRow['sex'] === 'M') {
    $patientSexLabel = 'Муж';
} elseif ($queueRow['sex'] === 'F') {
    $patientSexLabel = 'Жен';
}

// Загружаем анализы этого пациента за дату visit_date
// и считаем сумму из patient_analysis_items.price
$sqlAnalyses = "
    SELECT
        pa.id,
        pa.created_at,
        t.name AS type_name,
        t.code AS type_code,
        COALESCE(SUM(i.price), 0) AS analysis_sum
    FROM patient_analyses pa
    LEFT JOIN analysis_types t
        ON pa.analysis_type_id = t.id
    LEFT JOIN patient_analysis_items i
        ON i.patient_analysis_id = pa.id
    WHERE pa.patient_id = :patient_id
      AND DATE(pa.created_at) = :visit_date
    GROUP BY pa.id, pa.created_at, t.name, t.code
    ORDER BY pa.created_at, pa.id
";

$stmtA = $pdo->prepare($sqlAnalyses);
$stmtA->execute([
    ':patient_id' => $patientId,
    ':visit_date' => $visitDate,
]);
$analyses = $stmtA->fetchAll();

// Обработка сохранения (POST)
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedIds = isset($_POST['analysis_ids']) ? (array)$_POST['analysis_ids'] : [];
    $selectedIds = array_map('intval', $selectedIds);

    $totalAmount = 0.0;

    if ($analyses && $selectedIds) {
        foreach ($analyses as $a) {
            if (in_array((int)$a['id'], $selectedIds, true)) {

                // Базовая сумма из БД
                $sum = (float)$a['analysis_sum'];

                // <<< ДЛЯ TUP / TUH ПЕРЕПИСЫВАЕМ СУММУ НА 20
                if ($a['type_code'] === 'TUP' || $a['type_code'] === 'TUH') {
                    $sum = 20.00;
                }

                $totalAmount += $sum;
            }
        }
    }

    // Сохраняем итог в очереди и отмечаем как done
    $stmtUpd = $pdo->prepare("
        UPDATE patient_queue
        SET total_amount = :total_amount, status = 'done'
        WHERE id = :qid
    ");
    $stmtUpd->execute([
        ':total_amount' => $totalAmount,
        ':qid'          => $queueId,
    ]);

    // Обновим данные в массиве, чтобы отобразить на странице сразу
    $queueRow['total_amount'] = $totalAmount;
    $queueRow['status']       = 'done';

    $successMsg = 'Сумма по выбранным анализам сохранена в очереди.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">

    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">
                    Анализы пациента — очередь №<?php echo (int)$queueId; ?>
                </div>
                <div class="ba-header-meta">
                    Пациент: <?php echo htmlspecialchars($patientFullName); ?>
                    <?php if ($patientSexLabel): ?>
                        (<?php echo htmlspecialchars($patientSexLabel); ?>)
                    <?php endif; ?>
                    · Дата визита: <?php echo htmlspecialchars($visitDate); ?>
                </div>
            </div>
            <div class="text-md-end text-muted-soft small">
                Статус очереди:
                <strong>
                    <?php
                        if ($queueRow['status'] === 'done') {
                            echo 'Обслужен';
                        } elseif ($queueRow['status'] === 'waiting') {
                            echo 'Ожидает';
                        } else {
                            echo htmlspecialchars($queueRow['status']);
                        }
                    ?>
                </strong><br>
                Текущий итог по очереди:
                <strong>
                    <?php
                        $savedTotal = isset($queueRow['total_amount']) ? (float)$queueRow['total_amount'] : 0.0;
                        echo number_format($savedTotal, 2, '.', ' ');
                    ?>
                </strong>
            </div>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success py-2">
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger py-2">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <div class="panel p-3 mb-3">
        <h2 class="ba-section-title mb-3">
            Анализы за дату визита (<?php echo htmlspecialchars($visitDate); ?>)
        </h2>

        <?php if ($analyses): ?>
            <form method="post" action="/lab-system/index.php?page=patients_view&queue_id=<?php echo (int)$queueId; ?>&patient_id=<?php echo (int)$patientId; ?>">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-dark table-striped align-middle ba-result-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px;">№</th>
                                <th style="width:160px;">Дата и время анализа</th>
                                <th>Тип анализа</th>
                                <th style="width:140px;" class="text-end">Сумма анализа</th>
                                <th style="width:80px;" class="text-center">Учесть</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; $calculatedTotal = 0.0; ?>
                            <?php foreach ($analyses as $a): ?>
                                <?php
                                    $dt        = $a['created_at'] ? date('d.m.Y H:i', strtotime($a['created_at'])) : '—';
                                    $typeLabel = $a['type_name'] ?: 'Анализ';

                                    // Базовая сумма из БД
                                    $sum = (float)$a['analysis_sum'];

                                    // <<< ДЛЯ TUP / TUH ПЕРЕПИСЫВАЕМ СУММУ НА 20 И В ОТОБРАЖЕНИИ
                                    if ($a['type_code'] === 'TUP' || $a['type_code'] === 'TUH') {
                                        $sum = 20.00;
                                    }

                                    // Общая сумма всех анализов (если все чекбоксы будут отмечены)
                                    $calculatedTotal += $sum;
                                ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($dt); ?></td>
                                    <td><?php echo htmlspecialchars($typeLabel); ?></td>
                                    <td class="text-end">
                                        <?php echo number_format($sum, 2, '.', ' '); ?>
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            name="analysis_ids[]"
                                            value="<?php echo (int)$a['id']; ?>"
                                            checked
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div class="text-muted-soft small">
                        Сумма всех анализов (если все отмечены): 
                        <strong><?php echo number_format($calculatedTotal, 2, '.', ' '); ?></strong>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="/lab-system/index.php?page=patients" class="btn btn-outline-light btn-sm">
                            ← Назад к очереди пациентов
                        </a>
                        <button type="submit" class="btn btn-success btn-sm">
                            💾 Сохранить сумму в очереди
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                Для этого пациента в выбранный день (<?php echo htmlspecialchars($visitDate); ?>)
                анализы не найдены.
            </div>
            <div class="mt-3">
                <a href="/lab-system/index.php?page=patients" class="btn btn-outline-light btn-sm">
                    ← Назад к очереди пациентов
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
