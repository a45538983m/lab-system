<?php
// pages/doctor/analyses_list_export.php
// Экспорт СПИСКА анализов по выбранному фильтру (для врача и админа)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

// ---- Текущий пользователь ----
$doctorId = current_user_id();
$isAdmin  = is_admin();

// ---- ФИЛЬТРЫ (такие же, как в analyses_list.php) ----
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$typeCode   = trim($_GET['type_code'] ?? '');
$patientQ   = trim($_GET['patient_q'] ?? '');

// ---- БАЗОВЫЙ SQL + ДИНАМИЧЕСКИЕ УСЛОВИЯ ----
$sql = "
    SELECT
        pa.id,
        pa.check_number,
        pa.created_at,
        pa.total_price,
        t.code         AS analysis_type_code,
        t.name         AS analysis_type_name,
        p.first_name   AS patient_first_name,
        p.last_name    AS patient_last_name,
        u.full_name    AS doctor_name
    FROM patient_analyses pa
    JOIN analysis_types t ON pa.analysis_type_id = t.id
    LEFT JOIN patients p   ON pa.patient_id = p.id
    LEFT JOIN users u      ON pa.doctor_id = u.id
    WHERE 1=1
";

$params = [];

// Если не админ — показываем только анализы текущего врача
if (!$isAdmin) {
    $sql .= " AND pa.doctor_id = :doctor_id";
    $params['doctor_id'] = $doctorId;
}

// Фильтр по дате "с"
if ($dateFrom !== '') {
    $sql .= " AND DATE(pa.created_at) >= :date_from";
    $params['date_from'] = $dateFrom;
}

// Фильтр по дате "по"
if ($dateTo !== '') {
    $sql .= " AND DATE(pa.created_at) <= :date_to";
    $params['date_to'] = $dateTo;
}

// Фильтр по типу анализа (BA, TUH, TUP, IFA и т.д.)
if ($typeCode !== '') {
    $sql .= " AND t.code = :type_code";
    $params['type_code'] = $typeCode;
}

// Поиск по пациенту (имя / фамилия)
if ($patientQ !== '') {
    $sql .= " AND (p.first_name LIKE :patient_q1 OR p.last_name LIKE :patient_q2)";
    $params['patient_q1'] = '%' . $patientQ . '%';
    $params['patient_q2'] = '%' . $patientQ . '%';
}

$sql .= " ORDER BY pa.created_at DESC, pa.id DESC";

// Выполняем запрос
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$analyses = $stmt->fetchAll();

// Считаем общую сумму
$grandTotal = 0.0;
foreach ($analyses as $row) {
    $grandTotal += (float)$row['total_price'];
}

// ---- Формируем имя файла ----
$filenameParts = ['analyses_list'];

if ($dateFrom !== '' || $dateTo !== '') {
    $filenameParts[] = $dateFrom !== '' ? $dateFrom : '...';
    $filenameParts[] = $dateTo   !== '' ? $dateTo   : '...';
}

if (!$isAdmin) {
    $filenameParts[] = 'doctor_' . (int)$doctorId;
} else {
    $filenameParts[] = 'all';
}

$filename = implode('_', $filenameParts) . '.xls';

// ---- Заголовки для Excel ----
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Excel понимает простой HTML
echo "<html><head><meta charset=\"utf-8\"></head><body>";

// Шапка отчёта
echo '
<table border="0" cellspacing="0" cellpadding="4"
       style="font-family: Arial, sans-serif; font-size:10pt; width:100%;">
    <tr>
        <td colspan="6" align="center" style="font-weight:bold; font-size:11pt;">
            Список анализов по выбранному фильтру
        </td>
    </tr>
    <tr>
        <td colspan="6" align="center">
';

if ($dateFrom !== '' || $dateTo !== '') {
    echo 'Период: ';
    echo htmlspecialchars($dateFrom !== '' ? $dateFrom : '—');
    echo ' — ';
    echo htmlspecialchars($dateTo   !== '' ? $dateTo   : '—');
} else {
    echo 'Период: все даты';
}

echo '
        </td>
    </tr>
    <tr>
        <td colspan="6" align="center">
';

// Тип анализа в шапке, если выбран
if ($typeCode !== '') {
    echo 'Тип анализа: ' . htmlspecialchars($typeCode);
} else {
    echo 'Тип анализа: все';
}

echo '
        </td>
    </tr>
    <tr><td colspan="6">&nbsp;</td></tr>
</table>
';

// Таблица данных
echo '
<table border="1" cellspacing="0" cellpadding="4"
       style="font-family: Arial, sans-serif; font-size:10pt; width:100%; border-collapse:collapse;">
    <tr style="font-weight:bold; background-color:#e5e7eb;">
        <th style="width:40px;">№</th>
        <th style="width:120px;">Дата / время</th>
        <th>Пациент</th>
        <th>Тип анализа</th>
        <th>Врач</th>
        <th style="width:110px;">Сумма</th>
    </tr>
';

if ($analyses) {
    $i = 1;
    foreach ($analyses as $row) {
        $patientFullName = 'Не указан';
        if (!empty($row['patient_last_name']) || !empty($row['patient_first_name'])) {
            $patientFullName = trim($row['patient_last_name'] . ' ' . $row['patient_first_name']);
        }

        $dt = $row['created_at']
            ? date('d.m.Y H:i', strtotime($row['created_at']))
            : '';

        // подпись типа анализа
        $typeLabel = $row['analysis_type_code'] ?? '';
        if ($typeLabel === 'BA') {
            $typeLabel = 'Биохимия (БА)';
        } elseif ($typeLabel === 'TUH') {
            $typeLabel = 'Общий анализ крови (ТУХ)';
        } elseif ($typeLabel === 'TUP') {
            $typeLabel = 'Общий анализ мочи (ТУП)';
        } else {
            $typeLabel = $row['analysis_type_name'] ?: 'Анализ';
        }

        echo '<tr>';
        echo '<td>' . $i++ . '</td>';
        echo '<td>' . htmlspecialchars($dt) . '</td>';
        echo '<td>' . htmlspecialchars($patientFullName) . '</td>';
        echo '<td>' . htmlspecialchars($typeLabel) . '</td>';
        echo '<td>' . htmlspecialchars($row['doctor_name'] ?? '—') . '</td>';
        echo '<td align="right">' . number_format((float)$row['total_price'], 2, '.', ' ') . '</td>';
        echo '</tr>';
    }

    // Итоговая строка
    echo '
    <tr style="font-weight:bold;">
        <td colspan="5" align="right">Итого по выборке:</td>
        <td align="right">' . number_format($grandTotal, 2, '.', ' ') . '</td>
    </tr>';

    // Количество анализов
    echo '
    <tr>
        <td colspan="6" align="left">
            Всего анализов: ' . count($analyses) . '
        </td>
    </tr>';

} else {
    echo '
    <tr>
        <td colspan="6" align="center">Анализы по заданным фильтрам не найдены.</td>
    </tr>';
}

echo '</table>';

echo '</body></html>';
exit;
