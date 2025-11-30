<?php
// pages/doctor/analysis_export_pdf.php
// PDF-файл: чек + анализ без цен (для отправки пациенту в WhatsApp / Telegram)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

// Подключаем Dompdf (через Composer)
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;

// ---- ПАРАМЕТРЫ ----
$analysisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode       = $_GET['mode'] ?? 'combined'; // пока используем combined

if (!$analysisId) {
    die('Не указан ID анализа.');
}

// ---- ГРУЗИМ "ШАПКУ" АНАЛИЗА ----
$sqlHeader = "
    SELECT
        pa.*,
        p.first_name   AS patient_first_name,
        p.last_name    AS patient_last_name,
        p.sex          AS patient_sex,
        p.phones        AS patient_phone,
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
$header = $stmt->fetch();

if (!$header) {
    die('Анализ не найден.');
}

// Небольшая защита: врач видит только свои анализы (админ видит всё)
if (!is_admin() && current_user_id() !== (int)$header['doctor_id']) {
    die('У вас нет доступа к этому анализу.');
}

// ---- ПАЦИЕНТ / ВРАЧ / ОСНОВНЫЕ ПОЛЯ ----
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

$doctorName         = $header['doctor_name'] ?? '—';
$analysisTypeName   = $header['analysis_type_name'] ?? 'Анализ';
$analysisTypeCode   = $header['analysis_type_code'] ?? '';
$createdAt          = $header['created_at'] ?? null;
$createdAtFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '';
$checkNumber        = $header['check_number'] ?? '';
$totalPrice         = (float)$header['total_price'];

// ---- ГРУЗИМ СТРОКИ АНАЛИЗА ----
$sqlItems = "
    SELECT
        i.result_value,
        ai.name      AS indicator_name,
        ai.norm_text AS norm_text
    FROM patient_analysis_items i
    JOIN analysis_indicators ai
        ON i.indicator_id = ai.id
    WHERE i.patient_analysis_id = :id
    ORDER BY ai.id
";
$stmtItems = $pdo->prepare($sqlItems);
$stmtItems->execute([':id' => $analysisId]);
$items = $stmtItems->fetchAll();

// ---- СТРОИМ HTML ДЛЯ PDF: 1) ЧЕК, 2) АНАЛИЗ БЕЗ ЦЕН ----

$clinicName    = 'Городская клиническая больница №1';
$clinicAddress = 'г. Душанбе, ул. Примерная, 10';
$clinicInn     = 'ИНН 1234567890';

// Тип анализа (человекочитаемый)
$typeLabel = $analysisTypeName;
if ($analysisTypeCode === 'BA') {
    $typeLabel = 'Биохимический анализ крови';
} elseif ($analysisTypeCode === 'TUH') {
    $typeLabel = 'Общий анализ крови (ТУХ)';
} elseif ($analysisTypeCode === 'TUP') {
    $typeLabel = 'Общий анализ мочи (ТУП)';
}

ob_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анализ №<?php echo (int)$analysisId; ?></title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        .section-title {
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            margin-bottom: 4px;
        }
        .clinic-name {
            text-align: center;
            font-size: 12px;
            margin-bottom: 2px;
        }
        .clinic-extra {
            text-align: center;
            font-size: 10px;
            margin-bottom: 6px;
        }
        .info-row {
            margin-bottom: 3px;
            font-size: 11px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .table th,
        .table td {
            border: 1px solid #9ca3af;
            padding: 4px 5px;
        }
        .table th {
            background-color: #e5e7eb;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .mt-8 { margin-top: 8px; }
        .mt-4 { margin-top: 4px; }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

<!-- ===== 1. КАССОВЫЙ ЧЕК ===== -->
<div class="clinic-name"><?php echo htmlspecialchars($clinicName); ?></div>
<div class="clinic-extra">
    <?php echo htmlspecialchars($clinicInn); ?> •
    <?php echo htmlspecialchars($clinicAddress); ?>
</div>

<div class="section-title">Кассовый чек №<?php echo htmlspecialchars($checkNumber); ?></div>

<div class="info-row">
    <strong>Дата и время:</strong> <?php echo htmlspecialchars($createdAtFormatted); ?>
</div>
<div class="info-row">
    <strong>Пациент:</strong>
    <?php echo htmlspecialchars($patientName); ?>
    <?php if ($patientSexLabel): ?>
        (<?php echo htmlspecialchars($patientSexLabel); ?>)
    <?php endif; ?>
</div>
<div class="info-row">
    <strong>Врач:</strong> <?php echo htmlspecialchars($doctorName); ?>
</div>
<div class="info-row">
    <strong>Вид анализа:</strong> <?php echo htmlspecialchars($typeLabel); ?>
</div>

<div class="info-row mt-4">
    <strong>Сумма к оплате:</strong>
    <?php echo number_format($totalPrice, 2, '.', ' '); ?> сомони
</div>
<div class="info-row">
    <strong>Способ оплаты:</strong> Наличные
</div>

<div class="mt-8">
    Спасибо за обращение в нашу больницу!
</div>

<div class="page-break"></div>

<!-- ===== 2. ОТЧЁТ ПО АНАЛИЗУ (БЕЗ ЦЕН) ===== -->
<div class="section-title">Отчёт по лабораторному анализу</div>
<div class="clinic-name"><?php echo htmlspecialchars($typeLabel); ?></div>

<div class="info-row">
    <strong>Пациент:</strong>
    <?php echo htmlspecialchars($patientName); ?>
    <?php if ($patientSexLabel): ?>
        (<?php echo htmlspecialchars($patientSexLabel); ?>)
    <?php endif; ?>
</div>
<div class="info-row">
    <strong>Врач:</strong> <?php echo htmlspecialchars($doctorName); ?>
</div>
<div class="info-row">
    <strong>Дата и время:</strong> <?php echo htmlspecialchars($createdAtFormatted); ?>
</div>
<div class="info-row">
    <strong>Номер анализа:</strong> <?php echo (int)$analysisId; ?>
</div>

<table class="table mt-4">
    <thead>
    <tr>
        <th style="width:40px;">№</th>
        <th>Исследование</th>
        <th style="width:120px;">Результат</th>
        <th>Норма</th>
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
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="4" class="text-right">Показатели не найдены.</td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="mt-8" style="font-size:10px; color:#6b7280;">
    Документ сформирован системой лабораторных анализов.
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ---- ГЕНЕРИРУЕМ PDF ----
$dompdf = new Dompdf([
    'defaultFont' => 'DejaVu Sans',
]);

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Имя файла
$patientSlug = trim($patientName);
$patientSlug = preg_replace('/\s+/', '_', $patientSlug);
$patientSlug = preg_replace('/[^A-Za-zА-Яа-я0-9_\-]+/u', '', $patientSlug);
if ($patientSlug === '') {
    $patientSlug = 'no_name';
}
$filename = 'analysis_' . $analysisId . '_check_and_report_' . $patientSlug . '.pdf';

// Отдаём в браузер (скачивание)
$dompdf->stream($filename, ['Attachment' => true]);
exit;
