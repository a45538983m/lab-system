<?php
// pages/admin/admin_analysis_edit.php
// Редактирование анализов главврачом:
// - обычные анализы и комбинированные анализы
// - смена пациента и врача
// - редактирование результатов и цен по строкам

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Редактирование анализа';

$analysisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$analysisType = isset($_GET['type']) ? trim($_GET['type']) : 'regular'; // regular или combined

if (!$analysisId) {
    die('Не указан ID анализа.');
}

// --- Загружаем список пациентов и врачей для выбора ---
// пациенты
$stmtPat = $pdo->query("SELECT id, first_name, last_name, sex FROM patients ORDER BY last_name, first_name");
$allPatients = $stmtPat->fetchAll();

// врачи/пользователи
$stmtDoc = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name");
$allDoctors = $stmtDoc->fetchAll();

// --- Функция загрузки шапки анализа ---
function admin_loadAnalysisHeader(PDO $pdo, int $analysisId, string $analysisType = 'regular')
{
    if ($analysisType === 'combined') {
        $sqlHeader = "
            SELECT
                ca.*,
                p.first_name   AS patient_first_name,
                p.last_name    AS patient_last_name,
                p.sex          AS patient_sex,
                u.full_name    AS doctor_name,
                'combined'     AS analysis_type_code,
                'Комбинированный анализ' AS analysis_type_name
            FROM combined_analyses ca
            LEFT JOIN patients p   ON ca.patient_id = p.id
            LEFT JOIN users u      ON ca.doctor_id = u.id
            WHERE ca.id = :id
            LIMIT 1
        ";
    } else {
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
    }
    
    $stmt = $pdo->prepare($sqlHeader);
    $stmt->execute([':id' => $analysisId]);
    $header = $stmt->fetch();
    
    if ($header) {
        $header['analysis_type'] = $analysisType;
    }
    
    return $header;
}

// --- Функция загрузки элементов анализа ---
function admin_loadAnalysisItems(PDO $pdo, int $analysisId, string $analysisType = 'regular')
{
    if ($analysisType === 'combined') {
        // Сначала загружаем все включенные анализы через combined_analysis_items
        $sqlCombinedItems = "
            SELECT 
                ci.*,
                pa.*,
                t.name AS analysis_type_name,
                t.code AS analysis_type_code
            FROM combined_analysis_items ci
            JOIN patient_analyses pa ON ci.analysis_id = pa.id
            JOIN analysis_types t ON pa.analysis_type_id = t.id
            WHERE ci.combined_analysis_id = :combined_id
            ORDER BY 
                CASE t.code 
                    WHEN 'BA' THEN 1
                    WHEN 'TUH' THEN 2
                    WHEN 'TUP' THEN 3
                    ELSE 4
                END
        ";
        
        $stmtCombined = $pdo->prepare($sqlCombinedItems);
        $stmtCombined->execute([':combined_id' => $analysisId]);
        $combinedItems = $stmtCombined->fetchAll();
        
        $allItems = [];
        $analysisCounter = 0;
        
        foreach ($combinedItems as $combinedItem) {
            $analysisCounter++;
            $analysisTypeCode = $combinedItem['analysis_type_code'] ?? '';
            $analysisIdInCombined = $combinedItem['analysis_id'];
            
            // Определяем правильную цену в зависимости от типа анализа
            $isFixedPrice = in_array($analysisTypeCode, ['TUP', 'TUH']);
            $fixedPrice = $isFixedPrice ? 20.00 : 0;
            
            // Загружаем реальные показатели для анализа
            $sqlItems = "
                SELECT
                    i.id,
                    i.indicator_id,
                    i.result_value,
                    i.price AS item_price,
                    ai.name      AS indicator_name,
                    ai.norm_text AS norm_text,
                    :analysis_number AS analysis_number,
                    :analysis_type_code AS analysis_type_code,
                    :analysis_name AS analysis_name,
                    :combined_item_id AS combined_item_id
                FROM patient_analysis_items i
                JOIN analysis_indicators ai ON i.indicator_id = ai.id
                WHERE i.patient_analysis_id = :analysis_id
                ORDER BY ai.id
            ";
            
            $stmtItems = $pdo->prepare($sqlItems);
            $stmtItems->execute([
                ':analysis_id' => $analysisIdInCombined,
                ':analysis_number' => $analysisCounter,
                ':analysis_type_code' => $analysisTypeCode,
                ':analysis_name' => $combinedItem['analysis_type_name'],
                ':combined_item_id' => $combinedItem['id']
            ]);
            
            $items = $stmtItems->fetchAll();
            
            // Если для TUP/TUH нет показателей (они могут быть пустыми), создаем одну запись
            if (empty($items) && $isFixedPrice) {
                $items[] = [
                    'id' => 0,
                    'indicator_id' => 0,
                    'result_value' => 0,
                    'item_price' => $fixedPrice,
                    'indicator_name' => $combinedItem['analysis_type_name'] . ' (фиксированная цена)',
                    'norm_text' => 'Нормальные показатели',
                    'analysis_number' => $analysisCounter,
                    'analysis_type_code' => $analysisTypeCode,
                    'analysis_name' => $combinedItem['analysis_type_name'],
                    'combined_item_id' => $combinedItem['id']
                ];
            }
            
            // Добавляем информацию о цене всего анализа как отдельный элемент
            if ($isFixedPrice) {
                array_unshift($items, [
                    'id' => -1 * $analysisCounter, // Отрицательный ID для идентификации
                    'indicator_id' => 0,
                    'result_value' => 0,
                    'item_price' => $fixedPrice,
                    'indicator_name' => 'Общая стоимость ' . $combinedItem['analysis_type_name'],
                    'norm_text' => 'Фиксированная цена',
                    'analysis_number' => $analysisCounter,
                    'analysis_type_code' => $analysisTypeCode,
                    'analysis_name' => $combinedItem['analysis_type_name'],
                    'combined_item_id' => $combinedItem['id'],
                    'is_total_price' => true
                ]);
            }
            
            $allItems = array_merge($allItems, $items);
        }
        
        return $allItems;
    } else {
        // Для обычного анализа
        $sqlItems = "
            SELECT
                i.id,
                i.indicator_id,
                i.result_value,
                i.price AS item_price,
                ai.name      AS indicator_name,
                ai.norm_text AS norm_text,
                1 AS analysis_number,
                NULL AS analysis_type_code,
                NULL AS analysis_name,
                0 AS combined_item_id
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
}

$errorMsg   = '';
$successMsg = '';

// --- Сохранение изменений (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $analysisId = isset($_POST['analysis_id']) ? (int)$_POST['analysis_id'] : 0;
    $analysisType = isset($_POST['analysis_type']) ? trim($_POST['analysis_type']) : 'regular';
    
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

                    // Обновляем только реальные записи (id > 0)
                    $stmtUpdItem = $pdo->prepare('
                        UPDATE patient_analysis_items
                        SET result_value = :result_value,
                            price        = :price
                        WHERE id = :id AND id > 0
                    ');

                    foreach ($itemIds as $idx => $id) {
                        // Пропускаем фиктивные и суммарные записи
                        if ($id <= 0) {
                            continue;
                        }
                        
                        $resultVal = (float)str_replace(',', '.', $resultsArr[$idx]);
                        $priceVal  = (float)str_replace(',', '.', $pricesArr[$idx]);

                        $stmtUpdItem->execute([
                            ':result_value' => $resultVal,
                            ':price'        => $priceVal,
                            ':id'           => $id,
                        ]);
                    }

                    // Обновляем шапку анализа (пациент, врач, сумма)
                    if ($analysisType === 'combined') {
                        // Пересчитываем общую сумму из всех включенных анализов
                        $sqlRecalc = "
                            SELECT COALESCE(SUM(
                                CASE 
                                    WHEN t.code IN ('TUP', 'TUH') THEN 20.00
                                    ELSE pa.total_price
                                END
                            ), 0) as total_sum
                            FROM combined_analysis_items ci
                            JOIN patient_analyses pa ON ci.analysis_id = pa.id
                            JOIN analysis_types t ON pa.analysis_type_id = t.id
                            WHERE ci.combined_analysis_id = :id
                        ";
                        $stmtRecalc = $pdo->prepare($sqlRecalc);
                        $stmtRecalc->execute([':id' => $analysisId]);
                        $recalcResult = $stmtRecalc->fetch();
                        $newTotal = (float)$recalcResult['total_sum'];
                        
                        $stmtUpdHeader = $pdo->prepare('
                            UPDATE combined_analyses
                            SET patient_id  = :patient_id,
                                doctor_id   = :doctor_id,
                                total_price = :total_price
                            WHERE id = :id
                        ');
                    } else {
                        // Для обычного анализа пересчитываем сумму из показателей
                        $sqlRecalcSingle = "
                            SELECT COALESCE(SUM(price), 0) as total_sum
                            FROM patient_analysis_items
                            WHERE patient_analysis_id = :id
                        ";
                        $stmtRecalcSingle = $pdo->prepare($sqlRecalcSingle);
                        $stmtRecalcSingle->execute([':id' => $analysisId]);
                        $recalcSingle = $stmtRecalcSingle->fetch();
                        $newTotal = (float)$recalcSingle['total_sum'];
                        
                        $stmtUpdHeader = $pdo->prepare('
                            UPDATE patient_analyses
                            SET patient_id  = :patient_id,
                                doctor_id   = :doctor_id,
                                total_price = :total_price
                            WHERE id = :id
                        ');
                    }
                    
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
$header = admin_loadAnalysisHeader($pdo, $analysisId, $analysisType);
if (!$header) {
    die('Анализ не найден.');
}

// Дополнительно загружаем информацию о включенных анализах для комбинированного
if ($analysisType === 'combined') {
    $sqlIncludedAnalyses = "
        SELECT 
            ci.id as combined_item_id,
            pa.id as analysis_id,
            pa.analysis_type_id,
            t.code AS analysis_type_code,
            t.name AS analysis_type_name,
            pa.total_price,
            pa.check_number,
            (
                SELECT COUNT(*) 
                FROM patient_analysis_items pai 
                WHERE pai.patient_analysis_id = pa.id
            ) as indicators_count
        FROM combined_analysis_items ci
        JOIN patient_analyses pa ON ci.analysis_id = pa.id
        JOIN analysis_types t ON pa.analysis_type_id = t.id
        WHERE ci.combined_analysis_id = :combined_id
        ORDER BY 
            CASE t.code 
                WHEN 'BA' THEN 1
                WHEN 'TUH' THEN 2
                WHEN 'TUP' THEN 3
                ELSE 4
            END
    ";
    
    $stmtIncluded = $pdo->prepare($sqlIncludedAnalyses);
    $stmtIncluded->execute([':combined_id' => $analysisId]);
    $includedAnalyses = $stmtIncluded->fetchAll();
}

$items = admin_loadAnalysisItems($pdo, $analysisId, $analysisType);

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

if ($analysisType === 'combined') {
    $analysisTypeName = 'Комбинированный анализ';
    $analysisTypeCode = 'combined';
    $checkNumber = $header['combined_check_number'] ?? '';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">
<style>
.analysis-group {
    background: #1e293b;
    border-left: 4px solid #3b82f6;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 4px;
}

.analysis-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 8px;
    border-bottom: 1px solid #374151;
    margin-bottom: 8px;
}

.analysis-group-title {
    font-weight: bold;
    color: #e5e7eb;
}

.analysis-group-type {
    background: #3b82f6;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.fixed-price-item {
    background-color: rgba(59, 130, 246, 0.1);
}

.fixed-price-badge {
    background-color: #10b981;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}

.included-analyses-list {
    background: #0f172a;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.analysis-card {
    background: #1e293b;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    border-left: 4px solid #10b981;
}

.analysis-card.tup { border-left-color: #f59e0b; }
.analysis-card.tuh { border-left-color: #8b5cf6; }
.analysis-card.ba { border-left-color: #10b981; }

.total-price-row {
    background-color: rgba(16, 185, 129, 0.2) !important;
    font-weight: bold;
}

.total-price-cell {
    font-weight: bold;
    color: #10b981 !important;
}
</style>

<div class="container py-4 ba-page">
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">
                    Редактирование анализа 
                    <?php if ($analysisType === 'combined'): ?>
                        <span class="badge bg-success ms-2">Комбинированный</span>
                    <?php endif; ?>
                    №<?php echo (int)$analysisId; ?>
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
                    } elseif ($analysisTypeCode === 'combined') {
                        echo 'Комбинированный анализ';
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

    <!-- Информация о включенных анализах (только для комбинированных) -->
    <?php if ($analysisType === 'combined' && !empty($includedAnalyses)): ?>
        <div class="included-analyses-list mb-4">
            <h4 class="mb-3">Включенные анализы в комбинированном:</h4>
            <div class="row">
                <?php foreach ($includedAnalyses as $inc): ?>
                    <div class="col-md-4 mb-3">
                        <div class="analysis-card <?php echo strtolower($inc['analysis_type_code']); ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($inc['analysis_type_name']); ?></strong><br>
                                    <small class="text-muted">Чек: <?php echo htmlspecialchars($inc['check_number']); ?></small><br>
                                    <small class="text-muted">ID анализа: <?php echo $inc['analysis_id']; ?></small><br>
                                    <small class="text-muted">ID в комбинированном: <?php echo $inc['combined_item_id']; ?></small>
                                </div>
                                <div class="badge bg-dark">
                                    <?php echo htmlspecialchars($inc['analysis_type_code']); ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php echo (int)$inc['indicators_count']; ?> показателей
                                    </small>
                                    <span class="h6 text-success">
                                        <?php 
                                        $price = (float)$inc['total_price'];
                                        if (in_array($inc['analysis_type_code'], ['TUP', 'TUH'])) {
                                            $price = 20.00;
                                        }
                                        echo number_format($price, 2, '.', ' '); ?> с.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="/lab-system/index.php?page=admin_analysis_edit&id=<?php echo (int)$analysisId; ?>&type=<?php echo $analysisType; ?>">
        <input type="hidden" name="analysis_id" value="<?php echo (int)$analysisId; ?>">
        <input type="hidden" name="analysis_type" value="<?php echo htmlspecialchars($analysisType); ?>">

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

            <?php if ($items): ?>
                <div class="table-responsive mb-2">
                    <table class="table table-sm table-dark table-striped align-middle ba-result-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">№</th>
                                <th>Показатель</th>
                                <?php if ($analysisType === 'combined'): ?>
                                    <th>Тип анализа</th>
                                <?php endif; ?>
                                <th style="width: 140px;">Результат</th>
                                <th>Норма</th>
                                <th style="width: 120px;">Цена</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; ?>
                            <?php 
                            $currentGroup = 0;
                            $groupedItems = [];
                            
                            // Группируем элементы по анализу (для комбинированных)
                            if ($analysisType === 'combined') {
                                foreach ($items as $item) {
                                    $group = $item['analysis_number'] ?? 0;
                                    if (!isset($groupedItems[$group])) {
                                        $groupedItems[$group] = [];
                                    }
                                    $groupedItems[$group][] = $item;
                                }
                            } else {
                                $groupedItems[1] = $items;
                            }
                            ?>
                            
                            <?php foreach ($groupedItems as $groupNum => $groupItems): ?>
                                <?php if ($analysisType === 'combined' && count($groupedItems) > 1): ?>
                                    <tr class="bg-secondary">
                                        <td colspan="<?php echo $analysisType === 'combined' ? 6 : 5; ?>" class="py-2">
                                            <div class="analysis-group-header">
                                                <div class="analysis-group-title">
                                                    Анализ <?php echo $groupNum; ?>: 
                                                    <?php 
                                                    $firstItem = reset($groupItems);
                                                    echo htmlspecialchars($firstItem['analysis_name'] ?? 'Неизвестный тип'); 
                                                    ?>
                                                </div>
                                                <div class="analysis-group-type">
                                                    <?php 
                                                    echo htmlspecialchars($firstItem['analysis_type_code'] ?? ''); 
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php foreach ($groupItems as $item): ?>
                                    <?php
                                    $isFixedPrice = in_array($item['analysis_type_code'] ?? '', ['TUP', 'TUH']);
                                    $isFakeItem = ($item['id'] == 0);
                                    $isTotalPriceRow = isset($item['is_total_price']) && $item['is_total_price'];
                                    ?>
                                    <tr class="<?php echo $isFixedPrice ? 'fixed-price-item' : ''; ?> <?php echo $isTotalPriceRow ? 'total-price-row' : ''; ?>">
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($item['indicator_name']); ?>
                                            <?php if ($isFixedPrice && !$isTotalPriceRow): ?>
                                                <span class="fixed-price-badge ms-2">Фиксированная цена</span>
                                            <?php endif; ?>
                                            <?php if ($isTotalPriceRow): ?>
                                                <span class="badge bg-success ms-2">Сумма анализа</span>
                                            <?php endif; ?>
                                            <input type="hidden" name="item_ids[]" value="<?php echo (int)$item['id']; ?>">
                                        </td>
                                        <?php if ($analysisType === 'combined'): ?>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($item['analysis_type_code'] ?? ''); ?>
                                                </small>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($isTotalPriceRow): ?>
                                                <input
                                                    type="text"
                                                    name="results[]"
                                                    value="—"
                                                    class="form-control form-control-sm"
                                                    readonly
                                                    style="background-color: #374151; color: #9ca3af;"
                                                >
                                            <?php elseif ($isFixedPrice && $isFakeItem): ?>
                                                <input
                                                    type="text"
                                                    name="results[]"
                                                    value="0"
                                                    class="form-control form-control-sm"
                                                    readonly
                                                    style="background-color: #374151; color: #9ca3af;"
                                                >
                                            <?php else: ?>
                                                <input
                                                    type="text"
                                                    name="results[]"
                                                    value="<?php echo htmlspecialchars(number_format((float)$item['result_value'], 2, '.', ' ')); ?>"
                                                    class="form-control form-control-sm"
                                                >
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isTotalPriceRow): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($item['norm_text']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?php echo $isTotalPriceRow ? 'total-price-cell' : ''; ?>">
                                            <?php if ($isFixedPrice && $isTotalPriceRow): ?>
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <strong><?php echo number_format((float)$item['item_price'], 2, '.', ' '); ?></strong>
                                                    <input type="hidden" name="prices[]" value="<?php echo number_format((float)$item['item_price'], 2, '.', ''); ?>">
                                                    <small class="text-success">(фикс.)</small>
                                                </div>
                                            <?php elseif ($isFixedPrice): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="text-muted me-2"><?php echo number_format((float)$item['item_price'], 2, '.', ' '); ?></span>
                                                    <input type="hidden" name="prices[]" value="<?php echo number_format((float)$item['item_price'], 2, '.', ''); ?>">
                                                    <small class="text-success">(фикс.)</small>
                                                </div>
                                            <?php else: ?>
                                                <input
                                                    type="text"
                                                    name="prices[]"
                                                    value="<?php echo htmlspecialchars(number_format((float)$item['item_price'], 2, '.', ' ')); ?>"
                                                    class="form-control form-control-sm"
                                                >
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-end text-muted-soft small">
                    Текущая сумма по анализу:
                    <strong><?php echo number_format($totalPrice, 2, '.', ' '); ?></strong><br>
                    После сохранения сумма будет пересчитана по отредактированным ценам.
                    <?php if ($analysisType === 'combined'): ?>
                        <br><em>Примечание: TUP и TUH имеют фиксированную цену 20 сомон (не редактируется)</em>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    Для этого анализа нет показателей. 
                    <?php if ($analysisType === 'combined'): ?>
                        Комбинированный анализ включает только общие анализы (TUP, TUH) без детальных показателей.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

       <!-- Кнопки -->
    <div class="d-flex justify-content-between align-items-center mb-4">
    
        <a href="/lab-system/index.php?page=you" class="btn btn-outline-light btn-sm">
            ← Назад к комбинированным
        </a>
    
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