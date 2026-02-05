<?php
// pages/admin/you.php
// Просмотр и редактирование комбинированных анализов

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Комбинированные анализы';

// ==== ФИЛЬТРЫ ПО ДАТЕ ====
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

if ($dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-d');
}

// ==== ОБРАБОТКА СОХРАНЕНИЯ ====
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_combined'])) {
    $combinedId = (int)($_POST['combined_id'] ?? 0);
    
    if ($combinedId) {
        try {
            $pdo->beginTransaction();
            
            // Обновляем основные данные комбинированного анализа
            $newPatientId = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
            $newDoctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
            $newCheckNumber = trim($_POST['combined_check_number'] ?? '');
            
            $updateSql = "
                UPDATE combined_analyses 
                SET patient_id = :patient_id,
                    doctor_id = :doctor_id,
                    combined_check_number = :check_number
                WHERE id = :id
            ";
            
            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute([
                ':patient_id' => $newPatientId,
                ':doctor_id' => $newDoctorId,
                ':check_number' => $newCheckNumber,
                ':id' => $combinedId
            ]);
            
            // Обновляем результаты анализов
            if (!empty($_POST['analysis_results'])) {
                foreach ($_POST['analysis_results'] as $itemId => $resultValue) {
                    $itemId = (int)$itemId;
                    $resultValue = trim($resultValue);
                    
                    // Пропускаем фиктивные записи (id = 0 или отрицательные)
                    if ($itemId <= 0) {
                        continue;
                    }
                    
                    // Для пустых значений можно установить NULL или 0
                    if ($resultValue === '') {
                        $resultValue = 0;
                    }
                    
                    // Обновляем существующие записи
                    $updateItemSql = "
                        UPDATE patient_analysis_items
                        SET result_value = :result_value
                        WHERE id = :item_id
                    ";
                    
                    $stmtItem = $pdo->prepare($updateItemSql);
                    $stmtItem->execute([
                        ':result_value' => (float)str_replace(',', '.', $resultValue),
                        ':item_id' => $itemId
                    ]);
                }
            }
            
            // Пересчитываем общую сумму
            $getAnalysesSql = "
                SELECT 
                    pa.id,
                    t.code as analysis_type_code,
                    pa.total_price
                FROM combined_analysis_items ci
                JOIN patient_analyses pa ON ci.analysis_id = pa.id
                JOIN analysis_types t ON pa.analysis_type_id = t.id
                WHERE ci.combined_analysis_id = :combined_id
            ";
            
            $stmtAnalyses = $pdo->prepare($getAnalysesSql);
            $stmtAnalyses->execute([':combined_id' => $combinedId]);
            $analyses = $stmtAnalyses->fetchAll();
            
            $newTotal = 0.0;
            
            foreach ($analyses as $analysis) {
                if (in_array($analysis['analysis_type_code'], ['TUP', 'TUH'])) {
                    // Фиксированная цена для TUP и TUH
                    $newTotal += 20.00;
                } else {
                    // Для БА и других анализов считаем сумму из показателей
                    $calcPriceSql = "
                        SELECT COALESCE(SUM(price), 0) as analysis_total
                        FROM patient_analysis_items
                        WHERE patient_analysis_id = :analysis_id
                    ";
                    
                    $stmtPrice = $pdo->prepare($calcPriceSql);
                    $stmtPrice->execute([':analysis_id' => $analysis['id']]);
                    $priceResult = $stmtPrice->fetch();
                    $newTotal += (float)$priceResult['analysis_total'];
                }
            }
            
            // Обновляем общую сумму в комбинированном анализе
            $updateTotalSql = "
                UPDATE combined_analyses 
                SET total_price = :total_price
                WHERE id = :id
            ";
            
            $stmtUpdateTotal = $pdo->prepare($updateTotalSql);
            $stmtUpdateTotal->execute([
                ':total_price' => $newTotal,
                ':id' => $combinedId
            ]);
            
            $pdo->commit();
            
            // Перенаправляем, чтобы обновить данные на странице
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=you&date_from=" . urlencode($dateFrom) . "&date_to=" . urlencode($dateTo) . "&saved=" . $combinedId);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = 'Ошибка при сохранении: ' . $e->getMessage();
            
            error_log('Ошибка сохранения комбинированного анализа: ' . $e->getMessage());
            error_log('POST данные: ' . print_r($_POST, true));
            error_log('ID анализа: ' . $combinedId);
        }
    } else {
        $errorMsg = 'Не указан ID анализа для сохранения.';
    }
}

// Проверяем успешное сохранение
if (isset($_GET['saved'])) {
    $savedId = (int)$_GET['saved'];
    $successMsg = 'Изменения для анализа #' . $savedId . ' успешно сохранены!';
}

// ==== ЗАГРУЖАЕМ КОМБИНИРОВАННЫЕ АНАЛИЗЫ ====
$where  = [];
$params = [];

if ($dateFrom !== '') {
    $where[]              = 'ca.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[]            = 'ca.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        ca.*,
        p.first_name   AS patient_first_name,
        p.last_name    AS patient_last_name,
        p.sex          AS patient_sex,
        p.phones       AS patient_phone,
        u.full_name    AS doctor_name,
        (
            SELECT COUNT(*) 
            FROM combined_analysis_items ci 
            WHERE ci.combined_analysis_id = ca.id
        ) AS items_count
    FROM combined_analyses ca
    LEFT JOIN patients p   ON ca.patient_id = p.id
    LEFT JOIN users u      ON ca.doctor_id = u.id
    $whereSql
    ORDER BY ca.created_at DESC, ca.id DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$combinedAnalyses = $stmt->fetchAll();

// ==== ЗАГРУЖАЕМ ПОДРОБНЫЕ ДАННЫЕ КАЖДОГО КОМБИНИРОВАННОГО АНАЛИЗА ====
foreach ($combinedAnalyses as &$analysis) {
    // Загружаем анализы в этом комбинированном анализе
    $sqlAnalyses = "
        SELECT 
            ci.id as combined_item_id,
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
    
    $stmtAnalyses = $pdo->prepare($sqlAnalyses);
    $stmtAnalyses->execute([':combined_id' => $analysis['id']]);
    $includedAnalyses = $stmtAnalyses->fetchAll();
    
    // Для каждого анализа загружаем показатели
    foreach ($includedAnalyses as &$incAnalysis) {
        $sqlItems = "
            SELECT 
                i.id,
                i.indicator_id,
                i.result_value,
                ai.name AS indicator_name,
                ai.norm_text
            FROM patient_analysis_items i
            JOIN analysis_indicators ai ON i.indicator_id = ai.id
            WHERE i.patient_analysis_id = :analysis_id
            ORDER BY ai.id
        ";
        
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute([':analysis_id' => $incAnalysis['id']]);
        $incAnalysis['items'] = $stmtItems->fetchAll();
    }
    
    $analysis['included_analyses'] = $includedAnalyses;
}
unset($analysis);

// Загружаем списки пациентов и врачей для редактирования
$stmtPatients = $pdo->query("SELECT id, first_name, last_name, sex FROM patients ORDER BY last_name, first_name");
$allPatients = $stmtPatients->fetchAll();

$stmtDoctors = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name");
$allDoctors = $stmtDoctors->fetchAll();

// общая сумма
$grandTotal   = 0.0;
$patientsSet  = [];

foreach ($combinedAnalyses as $row) {
    $grandTotal += (float)$row['total_price'];

    if (!empty($row['patient_id'])) {
        $patientsSet[(int)$row['patient_id']] = true;
    }
}
$patientsCount  = count($patientsSet);
$analysesCount  = count($combinedAnalyses);

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">
<style>
.analysis-details {
    background: #1e293b;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    border-left: 4px solid #3b82f6;
    transition: all 0.3s ease;
}

.collapsed .analysis-content {
    display: none;
}

.expanded .analysis-content {
    display: block;
}

.analysis-summary {
    cursor: pointer;
    padding: 10px;
    background: #0f172a;
    border-radius: 6px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.analysis-summary:hover {
    background: #1e293b;
}

.expand-icon {
    font-size: 18px;
    transition: transform 0.3s ease;
    margin-right: 10px;
    min-width: 20px;
}

.expanded .expand-icon {
    transform: rotate(180deg);
}

.analysis-type-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    margin-right: 5px;
}

.badge-ba { background: #10b981; color: white; }
.badge-tuh { background: #8b5cf6; color: white; }
.badge-tup { background: #f59e0b; color: white; }
.badge-other { background: #6b7280; color: white; }

.indicator-row {
    border-bottom: 1px solid #374151;
    padding: 8px 0;
}

.indicator-row:last-child {
    border-bottom: none;
}

.price-tag {
    font-weight: bold;
    color: #10b981;
}

.patient-info {
    background: #0f172a;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.info-label {
    color: #9ca3af;
}

.info-value {
    color: #e5e7eb;
    font-weight: 500;
}

.result-badge {
    background: #374151;
    color: #e5e7eb;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 14px;
}

.edit-analysis-btn {
    background: #8b5cf6;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    margin-top: 10px;
    text-decoration: none;
    display: inline-block;
}

.edit-analysis-btn:hover {
    background: #7c3aed;
    color: white;
    text-decoration: none;
}

.edit-analysis-btn.ba { background: #10b981; }
.edit-analysis-btn.ba:hover { background: #0da271; }

.edit-analysis-btn.tup { background: #f59e0b; }
.edit-analysis-btn.tup:hover { background: #e6900a; }

.edit-analysis-btn.tuh { background: #8b5cf6; }
.edit-analysis-btn.tuh:hover { background: #7c3aed; }

.analysis-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.back-btn {
    background: #6b7280;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.back-btn:hover {
    background: #4b5563;
    color: white;
    text-decoration: none;
}
</style>

<div class="container py-4 ba-page">
    <!-- Шапка -->
    <div class="panel p-3 mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">Комбинированные анализы</div>
                <div class="ba-header-meta">
                    Просмотр комбинированных анализов
                </div>
            </div>
            <div class="text-md-end text-muted-soft small">
                <a href="/lab-system/index.php?page=admin_dashboard" class="btn btn-outline-light btn-sm">
                    ⬅ Назад в админ-панель
                </a>
            </div>
        </div>
    </div>

    <!-- Сообщения -->
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

    <!-- Фильтр по дате -->
    <div class="panel p-3 mb-3">
        <h2 class="ba-section-title mb-3">Фильтр по дате</h2>

        <form method="get" action="/lab-system/index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="you">

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

                <a href="/lab-system/index.php?page=you" class="btn btn-outline-light btn-sm align-self-end">
                    Сбросить
                </a>
            </div>
        </form>
    </div>

    <!-- Кнопки управления -->
    <div class="mb-3">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-light" onclick="expandAll()">
                📖 Развернуть все
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" onclick="collapseAll()">
                📕 Свернуть все
            </button>
        </div>
    </div>

    <!-- Итоги -->
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
                <div class="text-muted-soft small">Кол-во комбинированных анализов</div>
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
                <div class="text-muted-soft small">Общая сумма</div>
                <div class="fs-4 mt-1">
                    <?php echo number_format($grandTotal, 2, '.', ' '); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Список комбинированных анализов -->
    <div class="panel p-3">
        <h2 class="ba-section-title mb-3">Список комбинированных анализов</h2>

        <?php if ($combinedAnalyses): ?>
            <?php foreach ($combinedAnalyses as $row): ?>
                <?php
                    $patientName = 'Не указан';
                    if (!empty($row['patient_last_name']) || !empty($row['patient_first_name'])) {
                        $patientName = trim($row['patient_last_name'] . ' ' . $row['patient_first_name']);
                    }

                    $createdAt          = $row['created_at'] ?? null;
                    $createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';
                    $createdAtShort     = $createdAt ? date('d.m.Y', strtotime($createdAt)) : '';
                    
                    // Пол пациента
                    $patientSex = '';
                    if ($row['patient_sex'] === 'M') {
                        $patientSex = 'Муж';
                    } elseif ($row['patient_sex'] === 'F') {
                        $patientSex = 'Жен';
                    }
                    
                    // Определяем, развернут ли анализ
                    $isExpanded = isset($_GET['expand']) && $_GET['expand'] == $row['id'] || isset($_GET['saved']) && $_GET['saved'] == $row['id'];
                ?>
                
                <div class="analysis-details mb-3 <?php echo $isExpanded ? 'expanded' : 'collapsed'; ?>" id="analysis-<?php echo $row['id']; ?>">
                    <!-- Свернутый вид (сумма) -->
                    <div class="analysis-summary" onclick="toggleAnalysis(<?php echo $row['id']; ?>)">
                        <div class="analysis-summary-left">
                            <span class="expand-icon">▼</span>
                            <span class="patient-name">
                                <?php echo htmlspecialchars($patientName); ?>
                                <?php if ($patientSex): ?>
                                    <small class="text-muted">(<?php echo htmlspecialchars($patientSex); ?>)</small>
                                <?php endif; ?>
                            </span>
                            <span class="analysis-date">
                                <?php echo htmlspecialchars($createdAtShort); ?>
                            </span>
                            <span class="analysis-doctor">
                                Врач: <?php echo htmlspecialchars($row['doctor_name'] ?? '—'); ?>
                            </span>
                            <span class="order-number">
                                Заказ: <?php echo htmlspecialchars($row['combined_check_number'] ?? '—'); ?>
                            </span>
                            <span class="analysis-id">
                                ID: <?php echo $row['id']; ?>
                            </span>
                        </div>
                        <div class="analysis-summary-right">
                            <span class="price-tag me-2">
                                <?php echo number_format((float)$row['total_price'], 2, '.', ' '); ?> с.
                            </span>
                        </div>
                    </div>
                    
                    <!-- Развернутый вид (детали) -->
                    <div class="analysis-content">
                        <!-- Основная информация -->
                        <div class="patient-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Пациент:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($patientName); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Пол:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($patientSex); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Телефон:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($row['patient_phone'] ?? '—'); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Врач:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($row['doctor_name'] ?? '—'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Общая сумма:</span>
                                        <span class="info-value price-tag">
                                            <?php echo number_format((float)$row['total_price'], 2, '.', ' '); ?> с.
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Включено анализов:</span>
                                        <span class="info-value"><?php echo (int)$row['items_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Состав анализа -->
                        <?php if (!empty($row['included_analyses'])): ?>
                            <h6 class="mt-3 mb-2">Состав комбинированного анализа:</h6>
                            
                            <?php foreach ($row['included_analyses'] as $incAnalysis): ?>
                                <div class="mb-3 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="analysis-type-badge badge-<?php echo strtolower($incAnalysis['analysis_type_code'] ?? 'other'); ?>">
                                                <?php echo htmlspecialchars($incAnalysis['analysis_type_code']); ?>
                                            </span>
                                            <strong><?php echo htmlspecialchars($incAnalysis['analysis_type_name']); ?></strong>
                                            <small class="text-muted ms-2">
                                                ID анализа: <?php echo $incAnalysis['id']; ?>
                                                | Чек: <?php echo htmlspecialchars($incAnalysis['check_number'] ?? '—'); ?>
                                            </small>
                                        </div>
                                        <div class="price-tag">
                                            <?php 
                                            $analysisPrice = (float)$incAnalysis['total_price'];
                                            if (in_array($incAnalysis['analysis_type_code'], ['TUP', 'TUH'])) {
                                                $analysisPrice = 20.00;
                                            }
                                            echo number_format($analysisPrice, 2, '.', ' '); ?> с.
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($incAnalysis['items'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-borderless">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 30px;">№</th>
                                                        <th>Показатель</th>
                                                        <th style="width: 120px;">Результат</th>
                                                        <th>Норма</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $itemIndex = 1; ?>
                                                    <?php foreach ($incAnalysis['items'] as $item): ?>
                                                        <tr class="indicator-row">
                                                            <td><?php echo $itemIndex++; ?></td>
                                                            <td><?php echo htmlspecialchars($item['indicator_name']); ?></td>
                                                            <td>
                                                                <span class="result-badge">
                                                                    <?php echo number_format((float)$item['result_value'], 2, '.', ' '); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-muted-soft">
                                                                <?php echo htmlspecialchars($item['norm_text']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small">
                                            <?php if (in_array($incAnalysis['analysis_type_code'], ['TUP', 'TUH'])): ?>
                                                Фиксированный анализ без детальных показателей
                                            <?php else: ?>
                                                Нет показателей
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Кнопка для редактирования отдельных анализов -->
                                    <div class="analysis-buttons">
                                        <a href="/lab-system/index.php?page=admin_analysis_edit&id=<?php echo $incAnalysis['id']; ?>&type=regular"
                                           class="edit-analysis-btn <?php echo strtolower($incAnalysis['analysis_type_code']); ?>">
                                            ✏️ Редактировать <?php echo htmlspecialchars($incAnalysis['analysis_type_code']); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                За выбранный период комбинированные анализы не найдены.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Функция для переключения свертывания/развертывания
function toggleAnalysis(analysisId) {
    const element = document.getElementById('analysis-' + analysisId);
    if (element.classList.contains('collapsed')) {
        element.classList.remove('collapsed');
        element.classList.add('expanded');
        updateUrlParam('expand', analysisId);
    } else {
        element.classList.remove('expanded');
        element.classList.add('collapsed');
        updateUrlParam('expand', null);
    }
}

// Функция для обновления параметра URL
function updateUrlParam(param, value) {
    const url = new URL(window.location.href);
    if (value) {
        url.searchParams.set(param, value);
    } else {
        url.searchParams.delete(param);
    }
    window.history.replaceState({}, '', url.toString());
}

// Если есть параметр expand или saved, автоматически разворачиваем анализ
<?php if (isset($_GET['expand']) || isset($_GET['saved'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['saved'])): ?>
        const savedId = <?php echo (int)$_GET['saved']; ?>;
        const savedElement = document.getElementById('analysis-' + savedId);
        if (savedElement) {
            savedElement.classList.remove('collapsed');
            savedElement.classList.add('expanded');
            savedElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    <?php elseif (isset($_GET['expand'])): ?>
        const expandId = <?php echo (int)$_GET['expand']; ?>;
        const expandElement = document.getElementById('analysis-' + expandId);
        if (expandElement) {
            expandElement.classList.remove('collapsed');
            expandElement.classList.add('expanded');
            expandElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    <?php endif; ?>
});
<?php endif; ?>

// Функция для разворачивания всех анализов
function expandAll() {
    document.querySelectorAll('.analysis-details').forEach(element => {
        element.classList.remove('collapsed');
        element.classList.add('expanded');
    });
}

// Функция для сворачивания всех анализов
function collapseAll() {
    document.querySelectorAll('.analysis-details').forEach(element => {
        element.classList.remove('expanded');
        element.classList.add('collapsed');
    });
}
</script>