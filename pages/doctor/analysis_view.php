<?php
// pages/doctor/analysis_view.php
// Просмотр сохранённого анализа (чек / отчёт)
// Теперь показывает ВСЕ анализы из объединенного заказа

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Просмотр анализа';

// ID анализа из GET
$analysisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$analysisId) {
    die('Не указан ID анализа.');
}

// 1. ПРОВЕРЯЕМ, ЭТО ОБЪЕДИНЕННЫЙ АНАЛИЗ ИЛИ ОТДЕЛЬНЫЙ
// Ищем связанные анализы по номеру чека (без последней части - идентификатора)
$sqlCurrent = "
    SELECT pa.*, t.code as analysis_type_code
    FROM patient_analyses pa
    JOIN analysis_types t ON pa.analysis_type_id = t.id
    WHERE pa.id = :id
    LIMIT 1
";
$stmtCurrent = $pdo->prepare($sqlCurrent);
$stmtCurrent->execute(['id' => $analysisId]);
$currentAnalysis = $stmtCurrent->fetch();

if (!$currentAnalysis) {
    die('Анализ не найден.');
}

// Извлекаем базовую часть номера чека (без случайного суффикса)
$checkNumber = $currentAnalysis['check_number'] ?? '';
$checkBase = preg_replace('/-\d{3}$/', '', $checkNumber); // убираем последние 3 цифры

// 2. НАХОДИМ ВСЕ АНАЛИЗЫ ЭТОГО ЖЕ ЗАКАЗА
$sqlAllAnalyses = "
    SELECT 
        pa.*,
        p.first_name   AS patient_first_name,
        p.last_name    AS patient_last_name,
        p.sex          AS patient_sex,
        p.phones       AS patient_phone,
        u.full_name    AS doctor_name,
        t.name         AS analysis_type_name,
        t.code         AS analysis_type_code
    FROM patient_analyses pa
    LEFT JOIN patients p   ON pa.patient_id = p.id
    LEFT JOIN users u      ON pa.doctor_id = u.id
    LEFT JOIN analysis_types t ON pa.analysis_type_id = t.id
    WHERE pa.created_at = :created_at 
      AND pa.patient_id = :patient_id 
      AND pa.doctor_id = :doctor_id
      AND pa.check_number LIKE :check_pattern
    ORDER BY 
        CASE t.code 
            WHEN 'BA' THEN 1
            WHEN 'TUH' THEN 2
            WHEN 'TUP' THEN 3
            ELSE 4
        END
";

$stmtAll = $pdo->prepare($sqlAllAnalyses);
$stmtAll->execute([
    'created_at' => $currentAnalysis['created_at'],
    'patient_id' => $currentAnalysis['patient_id'],
    'doctor_id' => $currentAnalysis['doctor_id'],
    'check_pattern' => $checkBase . '%'
]);

$allAnalyses = $stmtAll->fetchAll();

if (empty($allAnalyses)) {
    // Если не нашли связанные анализы, используем только текущий
    $allAnalyses = [$currentAnalysis];
}

// Собираем информацию о пациенте из первого анализа
$firstAnalysis = $allAnalyses[0];
$patientName = 'Не указан';
$patientSexLabel = '';

if (!empty($firstAnalysis['patient_last_name']) || !empty($firstAnalysis['patient_first_name'])) {
    $patientName = trim($firstAnalysis['patient_last_name'] . ' ' . $firstAnalysis['patient_first_name']);
}
if (!empty($firstAnalysis['patient_sex'])) {
    if ($firstAnalysis['patient_sex'] === 'M') {
        $patientSexLabel = 'Муж';
    } elseif ($firstAnalysis['patient_sex'] === 'F') {
        $patientSexLabel = 'Жен';
    }
}

// Врач
$doctorName = $firstAnalysis['doctor_name'] ?? '—';

// Дата/время
$createdAt = $firstAnalysis['created_at'] ?? null;
$createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';

// Телефон пациента
$patientPhoneRaw = $firstAnalysis['patient_phone'] ?? '';
$patientPhoneDisplay = $patientPhoneRaw !== '' ? $patientPhoneRaw : '—';
$patientPhoneDigits = preg_replace('/\D+/', '', $patientPhoneRaw);

// 3. ЗАГРУЖАЕМ ДАННЫЕ ДЛЯ КАЖДОГО АНАЛИЗА
$analysesData = [];
$totalOverallPrice = 0;

// ФИКСИРОВАННЫЕ ЦЕНЫ
$fixed_prices = [
    'TUH' => 20.00,
    'TUP' => 20.00
];

foreach ($allAnalyses as $analysis) {
    $typeCode = $analysis['analysis_type_code'] ?? '';
    
    // Загружаем показатели для этого анализа
    $sqlItems = "
        SELECT
            i.*,
            ai.name      AS indicator_name,
            ai.norm_text AS norm_text
        FROM patient_analysis_items i
        JOIN analysis_indicators ai ON i.indicator_id = ai.id
        WHERE i.patient_analysis_id = :id
        ORDER BY ai.id
    ";
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute(['id' => $analysis['id']]);
    $items = $stmtItems->fetchAll();
    
    // Определяем цену
    $price = (float)$analysis['total_price'];
    if (isset($fixed_prices[$typeCode])) {
        $price = $fixed_prices[$typeCode];
    }
    
    $totalOverallPrice += $price;
    
    // Название анализа
    $analysisName = $analysis['analysis_type_name'] ?? '';
    if ($typeCode === 'BA') {
        $analysisName = 'Биохимический анализ крови';
    }
    
    // Номер чека
    $checkNumber = $analysis['check_number'] ?? '';
    
    $analysesData[] = [
        'id' => $analysis['id'],
        'type_code' => $typeCode,
        'type_name' => $analysisName,
        'check_number' => $checkNumber,
        'price' => $price,
        'items' => $items,
        'is_fixed_price' => isset($fixed_prices[$typeCode])
    ];
}

// 4. ПРОВЕРКА ДОСТУПА
if (!is_admin() && current_user_id() !== (int)$firstAnalysis['doctor_id']) {
    die('У вас нет доступа к этому анализу.');
}

// Собираем абсолютный URL к PDF
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host . '/lab-system';

// ---------- ТЕКСТОВЫЙ ЧЕК ДЛЯ МЕССЕНДЖЕРОВ ----------
$linesCheck = [];

$linesCheck[] = "🧾 ЛАБОРАТОРНЫЙ ЧЕК (КОМПЛЕКСНЫЙ АНАЛИЗ)";
$linesCheck[] = "Больница: Шифои Замон / Лаборатория";
$linesCheck[] = "Дата: {$createdAtFormatted}";
$linesCheck[] = str_repeat('─', 30);

$linePatient = "👤 Пациент: {$patientName}";
if ($patientSexLabel) {
    $linePatient .= " ({$patientSexLabel})";
}
$linesCheck[] = $linePatient;
$linesCheck[] = "⚕ Врач: {$doctorName}";
$linesCheck[] = "🔬 Состав заказа:";
foreach ($analysesData as $data) {
    $priceText = number_format($data['price'], 2, '.', ' ');
    $linesCheck[] = "  • {$data['type_name']} - {$priceText} с.";
}
$linesCheck[] = str_repeat('─', 30);
$linesCheck[] = "💰 Общая сумма: " . number_format($totalOverallPrice, 2, '.', ' ') . " с.";
$linesCheck[] = "";
$linesCheck[] = "Спасибо за обращение!";

$checkText = implode("\n", $linesCheck);

// ---------- ТЕКСТ РЕЗУЛЬТАТОВ АНАЛИЗА ----------
$linesAnalysis = [];

$linesAnalysis[] = "🧪 РЕЗУЛЬТАТЫ КОМПЛЕКСНОГО АНАЛИЗА";
$linesAnalysis[] = "Дата: {$createdAtFormatted}";
$linesAnalysis[] = str_repeat('═', 40);

foreach ($analysesData as $data) {
    $linesAnalysis[] = "📋 " . strtoupper($data['type_name']);
    $linesAnalysis[] = str_repeat('─', 30);
    
    if (!empty($data['items'])) {
        foreach ($data['items'] as $row) {
            $indicatorName = $row['indicator_name'] ?? '';
            $resultValue = number_format((float)$row['result_value'], 2, '.', ' ');
            $normText = trim($row['norm_text'] ?? '');
            
            $linesAnalysis[] = "• {$indicatorName}";
            $linesAnalysis[] = "  Результат: {$resultValue}";
            if ($normText !== '') {
                $linesAnalysis[] = "  Норма: {$normText}";
            }
            $linesAnalysis[] = "";
        }
    } else {
        $linesAnalysis[] = "Нет данных.";
    }
    $linesAnalysis[] = "";
}

$analysisText = implode("\n", $linesAnalysis);

// ---------- ОБЪЕДИНЁННЫЙ ТЕКСТ ----------
$separator = "\n\n\n\n\n";
$fullText = $checkText . $separator . $analysisText;

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/analysis_view.css">

<div class="container py-4 analysis-view-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Комплексный анализ (отчёт)</h1>

        <div class="d-flex flex-wrap gap-2">
         
            <!-- Печать HTML-версии -->
            <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()">
                🖨 Печать
            </button>

            <!-- Старые Excel-выгрузки -->
            <a href="/lab-system/index.php?page=analysis_export&id=<?php echo $analysisId; ?>&mode=check"
               class="btn btn-outline-success btn-sm">
                ⬇ Чек (Excel)
            </a>

            <a href="/lab-system/index.php?page=analysis_export&id=<?php echo $analysisId; ?>&mode=full"
               class="btn btn-outline-success btn-sm">
                ⬇ Полный анализ (Excel)
            </a>

            <!-- Отправка в WhatsApp -->
            <?php if (!empty($patientPhoneDigits)): ?>
                <?php $waLink = 'https://wa.me/' . $patientPhoneDigits . '?text=' . urlencode($fullText); ?>
                <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" class="btn btn-outline-success btn-sm">
                    📲 Отправить в WhatsApp
                </a>
            <?php endif; ?>

            <!-- Отправка в Telegram -->
            <?php $tgLink = 'https://t.me/share/url?url=' . urlencode($baseUrl) . '&text=' . urlencode($fullText); ?>
            <a href="<?php echo htmlspecialchars($tgLink); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                📨 Отправить в Telegram
            </a>
        </div>
    </div>

    <div class="analysis-paper panel p-4">
        <!-- Шапка бланка -->
        <div class="analysis-header mb-3">
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="analysis-clinic-name">
                        Шифои Замон / Лаборатория
                    </div>
                    <div class="analysis-title">
                        Комплексный лабораторный анализ
                    </div>
                </div>
                <div class="col-12 col-md-6 text-md-end mt-2 mt-md-0">
                    <div>Дата и время: <strong><?php echo htmlspecialchars($createdAtFormatted); ?></strong></div>
                    <div>Номера чеков: 
                        <?php 
                            $checkNumbers = array_column($analysesData, 'check_number');
                            echo htmlspecialchars(implode(', ', $checkNumbers));
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Инфо о пациенте и враче -->
        <div class="analysis-info mb-3">
            <div class="row">
                <div class="col-12 col-md-7">
                    <div>
                        <span class="info-label">Пациент:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($patientName); ?>
                            <?php if ($patientSexLabel): ?>
                                (<?php echo htmlspecialchars($patientSexLabel); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="mt-1">
                        <span class="info-label">Телефон:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($patientPhoneDisplay); ?>
                        </span>
                    </div>
                </div>
                <div class="col-12 col-md-5 text-md-end mt-2 mt-md-0">
                    <div>
                        <span class="info-label">Врач:</span>
                        <span class="info-value"><?php echo htmlspecialchars($doctorName); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Сводная информация по анализам -->
        <div class="summary-section mb-4 p-3 bg-light rounded">
            <h5 class="mb-2">Состав заказа:</h5>
            <div class="row">
                <?php foreach ($analysesData as $data): ?>
                    <div class="col-md-4 mb-2">
                        <div class="card border-0 bg-white">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($data['type_name']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo count($data['items']); ?> показателей
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success">
                                            <?php echo number_format($data['price'], 2, '.', ' '); ?> с.
                                        </div>
                                        <?php if ($data['is_fixed_price']): ?>
                                            <div class="small text-muted">(фиксированная цена)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Общая сумма -->
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="h5 mb-0">Общая сумма заказа:</div>
                    <div class="h4 mb-0 text-success">
                        <?php echo number_format($totalOverallPrice, 2, '.', ' '); ?> сомон
                    </div>
                </div>
            </div>
        </div>

        <!-- Результаты по каждому анализу -->
        <?php foreach ($analysesData as $data): ?>
            <div class="analysis-section mb-4">
                <h5 class="mb-3 border-bottom pb-2">
                    <?php echo htmlspecialchars($data['type_name']); ?>
                    <span class="badge bg-primary ms-2">Чек: <?php echo htmlspecialchars($data['check_number']); ?></span>
                </h5>
                
                <?php if (!empty($data['items'])): ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle analysis-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">№</th>
                                    <th>Исследование</th>
                                    <th style="width: 140px;">Результат</th>
                                    <th>Норма</th>
                                    <th style="width: 100px;">Цена</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; ?>
                                <?php foreach ($data['items'] as $row): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['indicator_name']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format((float)$row['result_value'], 2, '.', ' ')); ?></td>
                                        <td><?php echo htmlspecialchars($row['norm_text']); ?></td>
                                        <td>
                                            <?php if ($data['is_fixed_price']): ?>
                                                <span class="text-muted">(включено)</span>
                                            <?php else: ?>
                                                <?php echo number_format((float)$row['price'], 2, '.', ' '); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Сумма по анализу:</th>
                                    <th class="text-success">
                                        <?php 
                                            echo number_format($data['price'], 2, '.', ' '); 
                                            if ($data['is_fixed_price']) {
                                                echo ' (фикс.)';
                                            }
                                        ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning py-2">
                        Нет показателей для этого анализа.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Итоговая сумма -->
        <div class="total-section p-3 bg-dark text-white rounded">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1">ИТОГОВАЯ СУММА К ОПЛАТЕ</h5>
                    <div class="small">
                        Включает все выбранные анализы
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="h2 mb-0 text-success">
                        <?php echo number_format($totalOverallPrice, 2, '.', ' '); ?> с.
                    </div>
                </div>
            </div>
        </div>

        <div class="analysis-footer text-muted-soft small mt-3">
            <div class="row">
                <div class="col-md-6">
                    <strong>Примечания:</strong><br>
                    • ТУХ (общий анализ крови) - фиксированная цена 20 сомон<br>
                    • ТУП (общий анализ мочи) - фиксированная цена 20 сомон<br>
                    • БА (биохимический анализ) - сумма выбранных показателей
                </div>
                <div class="col-md-6 text-md-end">
                    Отчёт сгенерирован системой лабораторных анализов.<br>
                    Каждый анализ сохранен отдельно с собственным номером чека.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';