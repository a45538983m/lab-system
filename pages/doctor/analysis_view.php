<?php
// pages/doctor/analysis_view.php
// Просмотр сохранённого анализа (чек / отчёт)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Просмотр анализа';

// ID анализа из GET
$analysisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$analysisId) {
    die('Не указан ID анализа.');
}

// Загружаем шапку анализа
$sqlHeader = "
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
    WHERE pa.id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sqlHeader);
$stmt->execute(['id' => $analysisId]);
$header = $stmt->fetch();

if (!$header) {
    die('Анализ не найден.');
}

// Небольшая защита: врач видит только свои анализы (админ видит всё)
if (!is_admin() && current_user_id() !== (int)$header['doctor_id']) {
    die('У вас нет доступа к этому анализу.');
}

// Телефон пациента
$patientPhoneRaw      = $header['patient_phone'] ?? '';
$patientPhoneDisplay  = $patientPhoneRaw !== '' ? $patientPhoneRaw : '—';
// только цифры для ссылки WhatsApp
$patientPhoneDigits   = preg_replace('/\D+/', '', $patientPhoneRaw);

// Собираем абсолютный URL к PDF (для скачивания, если нужно)
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host . '/lab-system';

$pdfCombinedUrl = $baseUrl . '/pages/doctor/analysis_export_pdf.php?id=' . $analysisId . '&mode=combined';

// Загружаем строки анализа
$sqlItems = "
    SELECT
        i.*,
        ai.name      AS indicator_name,
        ai.norm_text AS norm_text
    FROM patient_analysis_items i
    JOIN analysis_indicators ai
        ON i.indicator_id = ai.id
    WHERE i.patient_analysis_id = :id
    ORDER BY ai.id
";
$stmtItems = $pdo->prepare($sqlItems);
$stmtItems->execute(['id' => $analysisId]);
$items = $stmtItems->fetchAll();

// Пациент
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

// Врач
$doctorName = $header['doctor_name'] ?? '—';

// Тип анализа
$analysisTypeName = $header['analysis_type_name'] ?? 'Анализ';
$analysisTypeCode = $header['analysis_type_code'] ?? '';

// Дата/время
$createdAt          = $header['created_at'] ?? null;
$createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';

// Номер чека и сумма
$checkNumber = $header['check_number'] ?? '';
$totalPrice  = (float)$header['total_price'];

// Короткое название клиники для текста
$clinicShort = 'Гос. больница / Лаборатория';

$analysisLabel = ($analysisTypeCode === 'BA')
    ? 'Биохимический анализ крови'
    : $analysisTypeName;

// ---------- ТЕКСТОВЫЙ ЧЕК ДЛЯ МЕССЕНДЖЕРОВ ----------
$linesCheck = [];

$linesCheck[] = "🧾 ЛАБОРАТОРНЫЙ ЧЕК";
$linesCheck[] = "Больница: {$clinicShort}";
$linesCheck[] = "Номер чека: {$checkNumber}";
$linesCheck[] = "Дата: {$createdAtFormatted}";
$linesCheck[] = str_repeat('─', 30);

$linePatient = "👤 Пациент: {$patientName}";
if ($patientSexLabel) {
    $linePatient .= " ({$patientSexLabel})";
}
$linesCheck[] = $linePatient;
$linesCheck[] = "⚕ Врач: {$doctorName}";
$linesCheck[] = "🔬 Анализ: {$analysisLabel}";
$linesCheck[] = str_repeat('─', 30);
$linesCheck[] = "💰 Сумма к оплате: " . number_format($totalPrice, 2, '.', ' ') . " с.";
$linesCheck[] = "";
$linesCheck[] = "Спасибо за обращение!";

$checkText = implode("\n", $linesCheck);

// ---------- ТЕКСТ РЕЗУЛЬТАТОВ АНАЛИЗА (ПОКАЗАТЕЛЬ + РЕЗУЛЬТАТ + НОРМА) ----------
$linesAnalysis = [];

$linesAnalysis[] = "🧪 РЕЗУЛЬТАТЫ АНАЛИЗА";
$linesAnalysis[] = "Тип: {$analysisLabel}";
$linesAnalysis[] = str_repeat('─', 30);

if ($items) {
    foreach ($items as $row) {
        $indicatorName = $row['indicator_name'] ?? '';
        $resultValue   = number_format((float)$row['result_value'], 2, '.', ' ');
        $normText      = trim($row['norm_text'] ?? '');

        $linesAnalysis[] = "• {$indicatorName}";
        $linesAnalysis[] = "  Результат: {$resultValue}";
        if ($normText !== '') {
            $linesAnalysis[] = "  Норма: {$normText}";
        }
        $linesAnalysis[] = ""; // пустая строка между показателями
    }
} else {
    $linesAnalysis[] = "Нет показателей для этого анализа.";
}

$analysisText = implode("\n", $linesAnalysis);

// ---------- ОБЪЕДИНЁННЫЙ ТЕКСТ: ЧЕК + 5 ОТСТУПОВ + АНАЛИЗ ----------
$separator   = "\n\n\n\n\n"; // 5 пустых строк
$fullText    = $checkText . $separator . $analysisText;

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/analysis_view.css">

<div class="container py-4 analysis-view-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Отчёт по анализу</h1>

        <div class="d-flex flex-wrap gap-2">
            <!-- Печать HTML-версии -->
            <button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()">
                🖨 Печать
            </button>

            <!-- Старые Excel-выгрузки (как были) -->
            <a
                href="/lab-system/index.php?page=analysis_export&id=<?php echo $analysisId; ?>&mode=check"
                class="btn btn-outline-success btn-sm"
            >
                ⬇ Чек (Excel)
            </a>

            <a
                href="/lab-system/index.php?page=analysis_export&id=<?php echo $analysisId; ?>&mode=full"
                class="btn btn-outline-success btn-sm"
            >
                ⬇ Полный анализ (Excel)
            </a>

            <!-- PDF: чек + анализ без цен (для печати/архива) -->
            <a
                href="/lab-system/pages/doctor/analysis_export_pdf.php?id=<?php echo $analysisId; ?>&mode=combined"
                class="btn btn-success btn-sm"
            >
                ⬇ PDF: чек + анализ
            </a>

            <!-- Отправка в WhatsApp (если есть телефон пациента) -->
            <?php if (!empty($patientPhoneDigits)): ?>
                <?php
                    // В WhatsApp отправляем чек + анализ (fullText)
                    $waLink = 'https://wa.me/' . $patientPhoneDigits . '?text=' . urlencode($fullText);
                ?>
                <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" class="btn btn-outline-success btn-sm">
                    📲 Отправить чек и анализ в WhatsApp
                </a>
            <?php endif; ?>

            <!-- Отправка в Telegram Web (как текст) -->
            <?php
                // В Telegram тоже отправляем весь текст (чек + анализ)
                $tgLink = 'https://t.me/share/url?url=' . urlencode($baseUrl) . '&text=' . urlencode($fullText);
            ?>
            <a href="<?php echo htmlspecialchars($tgLink); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                📨 Отправить чек и анализ в Telegram
            </a>
        </div>
    </div>

    <div class="analysis-paper panel p-4">
        <!-- Шапка бланка -->
        <div class="analysis-header mb-3">
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="analysis-clinic-name">
                        Государственная больница / Лаборатория
                    </div>
                    <div class="analysis-title">
                        <?php
                            if ($analysisTypeCode === 'BA') {
                                echo 'Биохимический анализ крови';
                            } else {
                                echo htmlspecialchars($analysisTypeName);
                            }
                        ?>
                    </div>
                </div>
                <div class="col-12 col-md-6 text-md-end mt-2 mt-md-0">
                    <div>Номер чека: <strong><?php echo htmlspecialchars($checkNumber); ?></strong></div>
                    <div>Дата и время: <strong><?php echo htmlspecialchars($createdAtFormatted); ?></strong></div>
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

        <!-- Таблица показателей (для печати/экрана, с нормой и ценой) -->
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered align-middle analysis-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">№</th>
                        <th>Исследование</th>
                        <th style="width: 140px;">Результат</th>
                        <th>Норма</th>
                        <th style="width: 120px;">Цена</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items): ?>
                        <?php $i = 1; ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($row['indicator_name']); ?></td>
                                <td><?php echo htmlspecialchars(number_format((float)$row['result_value'], 2, '.', ' ')); ?></td>
                                <td><?php echo htmlspecialchars($row['norm_text']); ?></td>
                                <td><?php echo number_format((float)$row['price'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Нет показателей для этого анализа.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Итого:</th>
                        <th><?php echo number_format($totalPrice, 2, '.', ' '); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="analysis-footer text-muted-soft small mt-3">
            Отчёт сгенерирован системой лабораторных анализов.
            Пациенту можно отправить текст чека и результатов анализа (с нормами) через WhatsApp или Telegram с помощью кнопок выше.
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
