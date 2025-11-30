<?php
// pages/doctor/analysis_export.php
// Экспорт анализа в Excel (чек или полный отчёт)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

// ---- ПАРАМЕТРЫ ----
$analysisId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode       = $_GET['mode'] ?? 'check'; // 'check' | 'full' | 'full_patient' | 'full_admin'

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
        i.price AS item_price,
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

// ---- ИМЯ ФАЙЛА (с именем пациента) ----
$baseFilename = 'analysis_' . $analysisId;
if ($mode === 'check') {
    $baseFilename .= '_check';
} elseif ($mode === 'full_admin') {
    $baseFilename .= '_full_admin';
} else {
    // full / full_patient / всё остальное без цен
    $baseFilename .= '_full';
}

// Добавим имя пациента
$patientSlug = trim($patientName);
$patientSlug = preg_replace('/\s+/', '_', $patientSlug);
$patientSlug = preg_replace('/[^A-Za-zА-Яа-я0-9_\-]+/u', '', $patientSlug);
if ($patientSlug === '') {
    $patientSlug = 'no_name';
}

$filename = $baseFilename . '_' . $patientSlug . '.xls';

// ---- ЗАГОЛОВКИ ДЛЯ EXCEL ----
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Excel понимает HTML-таблицы
echo "<html><head><meta charset=\"utf-8\"></head><body>";

// ===== РЕЖИМ ЧЕКА (КРАСИВЫЙ КАССОВЫЙ ЧЕК БЕЗ ПОКАЗАТЕЛЕЙ) =====
if ($mode === 'check') {

    // Название больницы можно потом поменять
    $clinicName    = 'Городская клиническая больница №1';
    $clinicAddress = 'г. Душанбе, ул. Примерная, 10';
    $clinicInn     = 'ИНН 1234567890';

    echo '
    <table border="0" cellspacing="0" cellpadding="3"
           style="font-family: Arial, sans-serif; font-size:10pt; width:320px;">
        <tr>
            <td colspan="2" align="center" style="font-weight:bold; font-size:11pt;">
                Кассовый чек №' . htmlspecialchars($checkNumber) . '
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">' . htmlspecialchars($clinicName) . '</td>
        </tr>
        <tr>
            <td colspan="2" align="center">' . htmlspecialchars($clinicInn) . '</td>
        </tr>
        <tr>
            <td colspan="2" align="center">' . htmlspecialchars($clinicAddress) . '</td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                Дата и время: ' . htmlspecialchars($createdAtFormatted) . '
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                ***********************************************
            </td>
        </tr>
        <tr>
            <td><strong>Пациент:</strong></td>
            <td>' . htmlspecialchars($patientName);

    if ($patientSexLabel) {
        echo ' (' . htmlspecialchars($patientSexLabel) . ')';
    }

    echo '</td>
        </tr>
        <tr>
            <td><strong>Врач:</strong></td>
            <td>' . htmlspecialchars($doctorName) . '</td>
        </tr>
        <tr>
            <td><strong>Вид анализа:</strong></td>
            <td>' . htmlspecialchars(
                ($analysisTypeCode === 'BA' ? 'Биохимический анализ крови' : $analysisTypeName)
            ) . '</td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                ***********************************************
            </td>
        </tr>
        <tr>
            <td><strong>Сумма к оплате:</strong></td>
            <td align="right"><strong>' . number_format($totalPrice, 2, '.', ' ') . '</strong></td>
        </tr>
        <tr>
            <td>Способ оплаты:</td>
            <td align="right">Наличные</td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                -----------------------------------------------
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                Спасибо за обращение в нашу больницу!
            </td>
        </tr>
    </table>';

    echo "</body></html>";
    exit;
}

// ===== ПОЛНЫЙ АНАЛИЗ ДЛЯ ПАЦИЕНТА (БЕЗ ЦЕН) =====
if ($mode === 'full_patient' || $mode === 'full') {

    echo '
    <table border="0" cellspacing="0" cellpadding="4"
           style="font-family: Arial, sans-serif; font-size:10pt; width:100%;">
        <tr>
            <td colspan="2" align="center" style="font-weight:bold; font-size:11pt;">
                Отчёт по лабораторному анализу
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                ' . htmlspecialchars(
                    ($analysisTypeCode === 'BA' ? 'Биохимический анализ крови' : $analysisTypeName)
                ) . '
            </td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        <tr>
            <td><strong>Пациент:</strong> ' . htmlspecialchars($patientName);

    if ($patientSexLabel) {
        echo ' (' . htmlspecialchars($patientSexLabel) . ')';
    }

    echo '</td>
            <td align="right"><strong>Врач:</strong> ' . htmlspecialchars($doctorName) . '</td>
        </tr>
        <tr>
            <td><strong>Дата и время:</strong> ' . htmlspecialchars($createdAtFormatted) . '</td>
            <td align="right"><strong>Номер анализа:</strong> ' . htmlspecialchars($analysisId) . '</td>
        </tr>
    </table>
    <br>';

    echo '
    <table border="1" cellspacing="0" cellpadding="4"
           style="font-family: Arial, sans-serif; font-size:10pt; width:100%; border-collapse:collapse;">
        <tr style="font-weight:bold; background-color:#e5e7eb;">
            <th style="width:40px;">№</th>
            <th>Исследование</th>
            <th style="width:140px;">Результат</th>
            <th>Норма</th>
        </tr>';

    if ($items) {
        $i = 1;
        foreach ($items as $row) {
            echo '<tr>';
            echo '<td>' . $i++ . '</td>';
            echo '<td>' . htmlspecialchars($row['indicator_name']) . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float)$row['result_value'], 2, '.', ' ')) . '</td>';
            echo '<td>' . htmlspecialchars($row['norm_text']) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '
        <tr>
            <td colspan="4" align="center">Нет показателей для этого анализа.</td>
        </tr>';
    }

    echo '</table>';

    echo "</body></html>";
    exit;
}

// ===== ПОЛНЫЙ АНАЛИЗ ДЛЯ ГЛАВВРАЧА (С ЦЕНАМИ) =====
if ($mode === 'full_admin') {

    // только для админа
    if (!is_admin()) {
        die('Только главврач может выгружать расширенный отчёт с ценами.');
    }

    echo '
    <table border="0" cellspacing="0" cellpadding="4"
           style="font-family: Arial, sans-serif; font-size:10pt; width:100%;">
        <tr>
            <td colspan="2" align="center" style="font-weight:bold; font-size:11pt;">
                Расширенный отчёт по лабораторному анализу (с ценами)
            </td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                ' . htmlspecialchars(
                    ($analysisTypeCode === 'BA' ? 'Биохимический анализ крови' : $analysisTypeName)
                ) . '
            </td>
        </tr>
        <tr><td colspan="2">&nbsp;</td></tr>
        <tr>
            <td><strong>Пациент:</strong> ' . htmlspecialchars($patientName);

    if ($patientSexLabel) {
        echo ' (' . htmlspecialchars($patientSexLabel) . ')';
    }

    echo '</td>
            <td align="right"><strong>Врач:</strong> ' . htmlspecialchars($doctorName) . '</td>
        </tr>
        <tr>
            <td><strong>Дата и время:</strong> ' . htmlspecialchars($createdAtFormatted) . '</td>
            <td align="right"><strong>Номер анализа:</strong> ' . htmlspecialchars($analysisId) . '</td>
        </tr>
    </table>
    <br>';

    echo '
    <table border="1" cellspacing="0" cellpadding="4"
           style="font-family: Arial, sans-serif; font-size:10pt; width:100%; border-collapse:collapse;">
        <tr style="font-weight:bold; background-color:#e5e7eb;">
            <th style="width:40px;">№</th>
            <th>Исследование</th>
            <th style="width:140px;">Результат</th>
            <th>Норма</th>
            <th style="width:120px;">Цена</th>
        </tr>';

    if ($items) {
        $i = 1;
        foreach ($items as $row) {
            $itemPrice = (float)$row['item_price'];

            echo '<tr>';
            echo '<td>' . $i++ . '</td>';
            echo '<td>' . htmlspecialchars($row['indicator_name']) . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float)$row['result_value'], 2, '.', ' ')) . '</td>';
            echo '<td>' . htmlspecialchars($row['norm_text']) . '</td>';
            echo '<td>' . number_format($itemPrice, 2, '.', ' ') . '</td>';
            echo '</tr>';
        }

        echo '
        <tr>
            <td colspan="4" align="right"><strong>Итого:</strong></td>
            <td><strong>' . number_format($totalPrice, 2, '.', ' ') . '</strong></td>
        </tr>';
    } else {
        echo '
        <tr>
            <td colspan="5" align="center">Нет показателей для этого анализа.</td>
        </tr>';
    }

    echo '</table>';

    echo "</body></html>";
    exit;
}

// На всякий случай, если mode какой-то странный
echo "<p>Неизвестный режим экспорта.</p>";
echo "</body></html>";
