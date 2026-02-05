<?php
// pages/doctor/combined_view.php
// Просмотр комплексного анализа (из our.php)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Комплексный анализ';

// ID комплексного анализа из GET
$combinedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$combinedId) {
    die('Не указан ID комплексного анализа.');
}

// Загружаем комплексный анализ
$sqlCombined = "
    SELECT ca.*, 
           p.first_name AS patient_first_name,
           p.last_name AS patient_last_name,
           p.sex AS patient_sex,
           p.phones AS patient_phone,
           u.full_name AS doctor_name
    FROM combined_analyses ca
    LEFT JOIN patients p ON ca.patient_id = p.id
    LEFT JOIN users u ON ca.doctor_id = u.id
    WHERE ca.id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sqlCombined);
$stmt->execute(['id' => $combinedId]);
$combined = $stmt->fetch();

if (!$combined) {
    die('Комплексный анализ не найден.');
}

// Проверка доступа
if (!is_admin() && current_user_id() !== (int)$combined['doctor_id']) {
    die('У вас нет доступа к этому анализу.');
}

// Загружаем все анализы в этом комплексном анализе
$sqlAnalyses = "
    SELECT pa.*, 
           t.name AS analysis_type_name,
           t.code AS analysis_type_code,
           p.first_name AS patient_first_name,
           p.last_name AS patient_last_name,
           p.sex AS patient_sex,
           u.full_name AS doctor_name
    FROM combined_analysis_items ci
    JOIN patient_analyses pa ON ci.analysis_id = pa.id
    JOIN analysis_types t ON pa.analysis_type_id = t.id
    LEFT JOIN patients p ON pa.patient_id = p.id
    LEFT JOIN users u ON pa.doctor_id = u.id
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
$stmtAnalyses->execute(['combined_id' => $combinedId]);
$analyses = $stmtAnalyses->fetchAll();

// Собираем данные по каждому анализу
$analysesData = [];
$fixed_prices = ['TUH' => 20.00, 'TUP' => 20.00];

foreach ($analyses as $analysis) {
    $typeCode = $analysis['analysis_type_code'] ?? '';
    
    // Загружаем показатели для этого анализа
    $sqlItems = "
        SELECT i.*, ai.name AS indicator_name, ai.norm_text
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
    
    // Название анализа
    $analysisName = $analysis['analysis_type_name'] ?? '';
    if ($typeCode === 'BA') {
        $analysisName = 'Биохимический анализ крови';
    }
    
    $analysesData[] = [
        'id' => $analysis['id'],
        'type_code' => $typeCode,
        'type_name' => $analysisName,
        'check_number' => $analysis['check_number'] ?? '',
        'price' => $price,
        'items' => $items,
        'is_fixed_price' => isset($fixed_prices[$typeCode])
    ];
}

// Информация о пациенте
$patientName = trim(($combined['patient_last_name'] ?? '') . ' ' . ($combined['patient_first_name'] ?? ''));
if (empty($patientName)) {
    $patientName = 'Не указан';
}

$patientSexLabel = '';
if (!empty($combined['patient_sex'])) {
    if ($combined['patient_sex'] === 'M') {
        $patientSexLabel = 'Муж';
    } elseif ($combined['patient_sex'] === 'F') {
        $patientSexLabel = 'Жен';
    }
}

$doctorName = $combined['doctor_name'] ?? '—';
$patientPhoneRaw = $combined['patient_phone'] ?? '';
$patientPhoneDisplay = $patientPhoneRaw !== '' ? $patientPhoneRaw : '—';
$patientPhoneDigits = preg_replace('/\D+/', '', $patientPhoneRaw);

// Дата/время
$createdAt = $combined['created_at'] ?? null;
$createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';

// Общая сумма
$totalPrice = (float)$combined['total_price'];

// Короткое название клиники
$clinicShort = 'Шифои Замон / Лаборатория';

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/analysis_view.css">

<div class="container py-4 analysis-view-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Комплексный лабораторный анализ</h1>

        <div class="d-flex flex-wrap gap-2">
            <!-- Печать -->
            <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()">
                🖨 Печать
            </button>

            <!-- Отправка в WhatsApp -->
            <?php if (!empty($patientPhoneDigits)): ?>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="sendToWhatsApp()">
                    📲 Отправить в WhatsApp
                </button>
            <?php endif; ?>

            <!-- Отправка в Telegram -->
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="sendToTelegram()">
                📨 Отправить в Telegram
            </button>
        </div>
    </div>

    <div class="analysis-paper panel p-4">
        <!-- Шапка бланка -->
        <div class="analysis-header mb-3">
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="analysis-clinic-name">
                        <?php echo htmlspecialchars($clinicShort); ?>
                    </div>
                    <div class="analysis-title">
                        Комплексный лабораторный анализ
                    </div>
                    <div class="small text-muted">
                        Номер комплексного заказа: <strong><?php echo htmlspecialchars($combined['combined_check_number']); ?></strong>
                    </div>
                </div>
                <div class="col-12 col-md-6 text-md-end mt-2 mt-md-0">
                    <div>Дата и время: <strong><?php echo htmlspecialchars($createdAtFormatted); ?></strong></div>
                    <div>Врач: <strong><?php echo htmlspecialchars($doctorName); ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Инфо о пациенте -->
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
                        <span class="info-label">Общая сумма:</span>
                        <span class="info-value h5 text-success">
                            <?php echo number_format($totalPrice, 2, '.', ' '); ?> с.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Сводная информация -->
        <div class="summary-section mb-4 p-3 bg-light rounded">
            <h5 class="mb-3">Состав комплексного анализа:</h5>
            <div class="row">
                <?php foreach ($analysesData as $data): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <?php echo htmlspecialchars($data['type_name']); ?>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="small text-muted">
                                        <?php echo count($data['items']); ?> показателей
                                        <br>
                                        Чек: <?php echo htmlspecialchars($data['check_number']); ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="h5 mb-0 text-success">
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
        </div>

        <!-- Результаты по каждому анализу -->
        <?php foreach ($analysesData as $data): ?>
            <div class="analysis-section mb-4">
                <h5 class="mb-3 border-bottom pb-2">
                    <?php echo htmlspecialchars($data['type_name']); ?>
                    <?php if ($data['is_fixed_price']): ?>
                        <span class="badge bg-success ms-2">Фиксированная цена</span>
                    <?php endif; ?>
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
                                        <?php echo number_format($data['price'], 2, '.', ' '); ?>
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
        <div class="total-section p-3 bg-dark text-white rounded mt-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">ИТОГОВАЯ СУММА К ОПЛАТЕ</h4>
                    <div class="small">
                        Комплексный анализ включает <?php echo count($analysesData); ?> исследования
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="h1 mb-0 text-success">
                        <?php echo number_format($totalPrice, 2, '.', ' '); ?> с.
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
                    Комплексный анализ сохранен в системе.<br>
                    Дата генерации: <?php echo htmlspecialchars($createdAtFormatted); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Функция для отправки в WhatsApp
function sendToWhatsApp() {
    // Собираем текст для отправки
    let text = "🧾 КОМПЛЕКСНЫЙ ЛАБОРАТОРНЫЙ АНАЛИЗ\n";
    text += "Больница: <?php echo $clinicShort; ?>\n";
    text += "Дата: <?php echo $createdAtFormatted; ?>\n";
    text += "Номер заказа: <?php echo $combined['combined_check_number']; ?>\n";
    text += "────────────────\n";
    text += "Пациент: <?php echo htmlspecialchars($patientName); ?>";
    <?php if ($patientSexLabel): ?>
    text += " (<?php echo $patientSexLabel; ?>)";
    <?php endif; ?>
    text += "\n";
    text += "Врач: <?php echo htmlspecialchars($doctorName); ?>\n";
    text += "────────────────\n";
    text += "СОСТАВ АНАЛИЗА:\n";
    
    <?php foreach ($analysesData as $data): ?>
    text += "• <?php echo $data['type_name']; ?> - <?php echo number_format($data['price'], 2, '.', ' '); ?> с.\n";
    <?php endforeach; ?>
    
    text += "────────────────\n";
    text += "ОБЩАЯ СУММА: <?php echo number_format($totalPrice, 2, '.', ' '); ?> с.\n\n";
    text += "Спасибо за обращение!";
    
    // Кодируем текст для URL
    const encodedText = encodeURIComponent(text);
    const phone = "<?php echo $patientPhoneDigits; ?>";
    
    // Открываем WhatsApp
    window.open(`https://wa.me/${phone}?text=${encodedText}`, '_blank');
}

// Функция для отправки в Telegram
function sendToTelegram() {
    // Собираем текст (такой же как для WhatsApp)
    let text = "🧾 КОМПЛЕКСНЫЙ ЛАБОРАТОРНЫЙ АНАЛИЗ\n";
    text += "Больница: <?php echo $clinicShort; ?>\n";
    text += "Дата: <?php echo $createdAtFormatted; ?>\n";
    text += "Номер заказа: <?php echo $combined['combined_check_number']; ?>\n";
    text += "────────────────\n";
    text += "Пациент: <?php echo htmlspecialchars($patientName); ?>";
    <?php if ($patientSexLabel): ?>
    text += " (<?php echo $patientSexLabel; ?>)";
    <?php endif; ?>
    text += "\n";
    text += "Врач: <?php echo htmlspecialchars($doctorName); ?>\n";
    text += "────────────────\n";
    text += "СОСТАВ АНАЛИЗА:\n";
    
    <?php foreach ($analysesData as $data): ?>
    text += "• <?php echo $data['type_name']; ?> - <?php echo number_format($data['price'], 2, '.', ' '); ?> с.\n";
    <?php endforeach; ?>
    
    text += "────────────────\n";
    text += "ОБЩАЯ СУММА: <?php echo number_format($totalPrice, 2, '.', ' '); ?> с.\n\n";
    text += "Спасибо за обращение!";
    
    // Кодируем текст для URL
    const encodedText = encodeURIComponent(text);
    const url = "<?php echo $baseUrl; ?>";
    
    // Открываем Telegram
    window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodedText}`, '_blank');
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';