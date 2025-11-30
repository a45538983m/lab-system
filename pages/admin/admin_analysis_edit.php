<?php
// pages/admin/admin_analysis_edit.php
// Редактирование одного анализа главврачом:
// - смена пациента и врача
// - редактирование результатов и цен по строкам

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Редактирование анализа';

$analysisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$analysisId) {
    die('Не указан ID анализа.');
}

// --- Загружаем список пациентов и врачей для выбора ---
// пациенты
$stmtPat = $pdo->query("SELECT id, first_name, last_name, sex FROM patients ORDER BY last_name, first_name");
$allPatients = $stmtPat->fetchAll();

// врачи/пользователи (можно сузить по роли, если есть поле role)
$stmtDoc = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name");
$allDoctors = $stmtDoc->fetchAll();

// --- Функция загрузки шапки и строк анализа ---
function admin_loadAnalysisHeader(PDO $pdo, int $analysisId)
{
    $sqlHeader = "
        SELECT
            pa.*,
            p.first_name   AS patient_first_name,
            p.last_name    AS patient_last_name,
            p.sex          AS patient_sex,
            u.full_name    AS doctor_name,
            t.name         AS analysis_type_name,
            t.code         AS analysis_type_code
        FROM patient_analyses pa
        LEFT JOIN patients p   ON pa.patient_id = p.id
        LEFT JOIN users u      ON pa.doctor_id = u.id
        LEFT JOIN analysis_types t ON pa.analysis_type_id = t.id
        WHERE pa.id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sqlHeader);
    $stmt->execute([':id' => $analysisId]);
    return $stmt->fetch();
}

function admin_loadAnalysisItems(PDO $pdo, int $analysisId)
{
    $sqlItems = "
        SELECT
            i.id,
            i.indicator_id,
            i.result_value,
            i.price AS item_price,
            ai.name      AS indicator_name,
            ai.norm_text AS norm_text
        FROM patient_analysis_items i
        JOIN analysis_indicators ai
            ON i.indicator_id = ai.id
        WHERE i.patient_analysis_id = :id
        ORDER BY ai.id
    ";
    $stmt = $pdo->prepare($sqlItems);
    $stmt->execute([':id' => $analysisId]);
    return $stmt->fetchAll();
}

$errorMsg   = '';
$successMsg = '';

// --- Сохранение изменений (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $analysisId = isset($_POST['analysis_id']) ? (int)$_POST['analysis_id'] : 0;
    if (!$analysisId) {
        $errorMsg = 'Не передан идентификатор анализа.';
    } else {
        $newPatientId = isset($_POST['patient_id']) && $_POST['patient_id'] !== ''
            ? (int)$_POST['patient_id']
            : null;

        $newDoctorId = isset($_POST['doctor_id']) && $_POST['doctor_id'] !== ''
            ? (int)$_POST['doctor_id']
            : null;

        if (!$newDoctorId) {
            $errorMsg = 'Выберите врача для анализа.';
        } else {
            $itemIds    = array_map('intval', $_POST['item_ids'] ?? []);
            $resultsArr = $_POST['results'] ?? [];
            $pricesArr  = $_POST['prices'] ?? [];

            if (!$itemIds || !$resultsArr || !$pricesArr ||
                count($itemIds) !== count($resultsArr) ||
                count($itemIds) !== count($pricesArr)
            ) {
                $errorMsg = 'Некорректные данные показателей.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Пересчитываем общую сумму
                    $newTotal = 0.0;

                    // Обновляем строки анализа
                    $stmtUpdItem = $pdo->prepare('
                        UPDATE patient_analysis_items
                        SET result_value = :result_value,
                            price        = :price
                        WHERE id = :id AND patient_analysis_id = :analysis_id
                    ');

                    foreach ($itemIds as $idx => $id) {
                        $resultVal = (float)str_replace(',', '.', $resultsArr[$idx]);
                        $priceVal  = (float)str_replace(',', '.', $pricesArr[$idx]);

                        $stmtUpdItem->execute([
                            ':result_value' => $resultVal,
                            ':price'        => $priceVal,
                            ':id'           => $id,
                            ':analysis_id'  => $analysisId,
                        ]);

                        $newTotal += $priceVal;
                    }

                    // Обновляем шапку анализа (пациент, врач, сумма)
                    $stmtUpdHeader = $pdo->prepare('
                        UPDATE patient_analyses
                        SET patient_id  = :patient_id,
                            doctor_id   = :doctor_id,
                            total_price = :total_price
                        WHERE id = :id
                    ');
                    $stmtUpdHeader->execute([
                        ':patient_id'  => $newPatientId,
                        ':doctor_id'   => $newDoctorId,
                        ':total_price' => $newTotal,
                        ':id'          => $analysisId,
                    ]);

                    $pdo->commit();
                    $successMsg = 'Анализ успешно обновлён.';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errorMsg = 'Ошибка при сохранении анализа: ' . $e->getMessage();
                }
            }
        }
    }
}

// После возможного сохранения — заново загружаем актуальные данные
$header = admin_loadAnalysisHeader($pdo, $analysisId);
if (!$header) {
    die('Анализ не найден.');
}
$items = admin_loadAnalysisItems($pdo, $analysisId);

// Формируем отображаемые значения
$patientName = 'Не указан';
$patientSexLabel = '';

if (!empty($header['patient_last_name']) || !empty($header['patient_first_name'])) {
    $patientName = trim($header['patient_last_name'] . ' ' . $header['patient_first_name']);
}
if (!empty($header['patient_sex'])) {
    if ($header['patient_sex'] === 'M') {
        $patientSexLabel = 'Муж';
    } elseif ($header['patient_sex'] === 'F') {
        $patientSexLabel = 'Жен';
    }
}

$doctorName       = $header['doctor_name'] ?? '—';
$analysisTypeName = $header['analysis_type_name'] ?? 'Анализ';
$analysisTypeCode = $header['analysis_type_code'] ?? '';
$createdAt        = $header['created_at'] ?? null;
$createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';
$checkNumber      = $header['check_number'] ?? '';
$totalPrice       = (float)$header['total_price'];

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">
                    Редактирование анализа №<?php echo (int)$analysisId; ?>
                </div>
                <div class="ba-header-meta">
                    Номер чека: <?php echo htmlspecialchars($checkNumber); ?>,
                    создан: <?php echo htmlspecialchars($createdAtFormatted); ?>
                </div>
            </div>
            <div class="text-md-end small text-muted-soft">
                Тип анализа:
                <?php
                    if ($analysisTypeCode === 'BA') {
                        echo 'Биохимический анализ крови (БА)';
                    } elseif ($analysisTypeCode === 'TUH') {
                        echo 'Общий анализ крови (ТУХ)';
                    } elseif ($analysisTypeCode === 'TUP') {
                        echo 'Общий анализ мочи (ТУП)';
                    } else {
                        echo htmlspecialchars($analysisTypeName);
                    }
                ?>
            </div>
        </div>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger py-2">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
        <div class="alert alert-success py-2">
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/lab-system/index.php?page=admin_analysis_edit&id=<?php echo (int)$analysisId; ?>">
        <input type="hidden" name="analysis_id" value="<?php echo (int)$analysisId; ?>">

        <!-- Блок: пациент и врач -->
        <div class="panel p-3 mb-3">
            <h2 class="ba-section-title mb-3">Основные данные анализа</h2>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Пациент</label>
                    <select name="patient_id" class="form-select form-select-sm">
                        <option value="">— Не указан —</option>
                        <?php foreach ($allPatients as $p): ?>
                            <?php
                                $pid = (int)$p['id'];
                                $sex = $p['sex'] ?? '';
                                $sexLabel = ($sex === 'M') ? 'Муж' : (($sex === 'F') ? 'Жен' : '');
                                $label = trim($p['last_name'] . ' ' . $p['first_name']);
                                if ($sexLabel) {
                                    $label .= ' (' . $sexLabel . ')';
                                }
                            ?>
                            <option value="<?php echo $pid; ?>"
                                <?php echo ($header['patient_id'] == $pid) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Врач</label>
                    <select name="doctor_id" class="form-select form-select-sm" required>
                        <option value="">— Выберите врача —</option>
                        <?php foreach ($allDoctors as $d): ?>
                            <?php $did = (int)$d['id']; ?>
                            <option value="<?php echo $did; ?>"
                                <?php echo ($header['doctor_id'] == $did) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Таблица показателей -->
        <div class="panel p-3 mb-3">
            <h2 class="ba-section-title mb-3">Показатели анализа</h2>

            <div class="table-responsive mb-2">
                <table class="table table-sm table-dark table-striped align-middle ba-result-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">№</th>
                            <th>Показатель</th>
                            <th style="width: 140px;">Результат</th>
                            <th>Норма</th>
                            <th style="width: 120px;">Цена</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items): ?>
                            <?php $i = 1; ?>
                            <?php foreach ($items as $idx => $row): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['indicator_name']); ?>
                                        <input type="hidden" name="item_ids[]" value="<?php echo (int)$row['id']; ?>">
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="results[]"
                                            value="<?php echo htmlspecialchars(number_format((float)$row['result_value'], 2, '.', ' ')); ?>"
                                            class="form-control form-control-sm"
                                        >
                                    </td>
                                    <td><?php echo htmlspecialchars($row['norm_text']); ?></td>
                                    <td>
                                        <input
                                            type="text"
                                            name="prices[]"
                                            value="<?php echo htmlspecialchars(number_format((float)$row['item_price'], 2, '.', ' ')); ?>"
                                            class="form-control form-control-sm"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Для этого анализа нет показателей.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-end text-muted-soft small">
                Текущая сумма по анализу:
                <strong><?php echo number_format($totalPrice, 2, '.', ' '); ?></strong><br>
                После сохранения сумма будет пересчитана по отредактированным ценам.
            </div>
        </div>

        <!-- Кнопки -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="/lab-system/index.php?page=admin_dashboard" class="btn btn-outline-light btn-sm">
                ← Назад к отчётам
            </a>
            <button type="submit" class="btn btn-success">
                Сохранить изменения
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
