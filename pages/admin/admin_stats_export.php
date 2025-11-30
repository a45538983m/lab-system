<?php
// pages/admin/admin_stats_export.php
// Экспорт статистики анализов и показателей в Excel

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$where  = [];
$params = [];

if ($dateFrom !== '') {
    $where[]              = 'pa.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[]            = 'pa.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- Статистика по количеству анализов по типам ---
$sqlAnalyses = "
    SELECT
        t.id,
        t.code,
        t.name,
        COUNT(pa.id) AS analyses_count
    FROM analysis_types t
    LEFT JOIN patient_analyses pa
        ON pa.analysis_type_id = t.id
        " . ($whereSql ? $whereSql : '') . "
    GROUP BY t.id, t.code, t.name
    HAVING analyses_count > 0
    ORDER BY analyses_count DESC
";
$stmtAnalyses = $pdo->prepare($sqlAnalyses);
$stmtAnalyses->execute($params);
$analysesStats = $stmtAnalyses->fetchAll();

$totalAnalyses = 0;
foreach ($analysesStats as $row) {
    $totalAnalyses += (int)$row['analyses_count'];
}

// --- Статистика по количеству показателей (каждый показатель отдельно) ---
$sqlIndicators = "
    SELECT
        ai.id,
        ai.name       AS indicator_name,
        ai.norm_text  AS norm_text,
        t.code        AS type_code,
        t.name        AS type_name,
        COUNT(i.id)   AS indicators_count
    FROM analysis_indicators ai
    JOIN patient_analysis_items i
        ON i.indicator_id = ai.id
    JOIN patient_analyses pa
        ON pa.id = i.patient_analysis_id
    LEFT JOIN analysis_types t
        ON ai.analysis_type_id = t.id
        " . ($whereSql ? $whereSql : '') . "
    GROUP BY ai.id, ai.name, ai.norm_text, t.code, t.name
    HAVING indicators_count > 0
    ORDER BY indicators_count DESC
";
$stmtIndicators = $pdo->prepare($sqlIndicators);
$stmtIndicators->execute($params);
$indicatorsStats = $stmtIndicators->fetchAll();

$totalIndicators = 0;
foreach ($indicatorsStats as $row) {
    $totalIndicators += (int)$row['indicators_count'];
}

// --- Имя файла ---
$filename = 'stats_analyses_';
if ($dateFrom !== '' || $dateTo !== '') {
    $filename .= ($dateFrom !== '' ? $dateFrom : '...') . '_' . ($dateTo !== '' ? $dateTo : '...');
} else {
    $filename .= 'all_dates';
}
$filename .= '.xls';

// --- Заголовки для Excel ---
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Excel понимает HTML
echo "<html><head><meta charset=\"utf-8\"></head><body>";

// Шапка
echo '
<table border="0" cellspacing="0" cellpadding="4"
       style="font-family: Arial, sans-serif; font-size:10pt; width:100%;">
    <tr>
        <td colspan="7" align="center" style="font-weight:bold; font-size:11pt;">
            Статистика анализов и показателей
        </td>
    </tr>
    <tr>
        <td colspan="7" align="center">
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
    <tr><td colspan="7">&nbsp;</td></tr>
</table>
';

// --- Таблица 1: количество анализов по типам ---
echo '
<table border="1" cellspacing="0" cellpadding="4"
       style="font-family: Arial, sans-serif; font-size:10pt; width:100%; border-collapse:collapse;">
    <tr style="font-weight:bold; background-color:#e5e7eb;">
        <th style="width:40px;">№</th>
        <th style="width:80px;">Код</th>
        <th>Тип анализа</th>
        <th style="width:160px;">Кол-во анализов</th>
        <th style="width:160px;">Доля от всех, %</th>
    </tr>
';

if ($analysesStats) {
    $i = 1;
    foreach ($analysesStats as $row) {
        $count   = (int)$row['analyses_count'];
        $percent = $totalAnalyses > 0 ? round($count * 100 / $totalAnalyses, 1) : 0;

        echo '<tr>';
        echo '<td>' . $i++ . '</td>';
        echo '<td>' . htmlspecialchars($row['code']) . '</td>';
        echo '<td>' . htmlspecialchars($row['name']) . '</td>';
        echo '<td>' . $count . '</td>';
        echo '<td>' . $percent . '%</td>';
        echo '</tr>';
    }

    echo '
    <tr style="font-weight:bold;">
        <td colspan="3" align="right">Итого анализов:</td>
        <td>' . (int)$totalAnalyses . '</td>
        <td></td>
    </tr>';
} else {
    echo '
    <tr>
        <td colspan="5" align="center">За выбранный период анализы не найдены.</td>
    </tr>';
}

echo '</table>';

echo '<br><br>';

// --- Таблица 2: количество показателей (каждый показатель отдельно) ---
echo '
<table border="1" cellspacing="0" cellpadding="4"
       style="font-family: Arial, sans-serif; font-size:10pt; width:100%; border-collapse:collapse;">
    <tr style="font-weight:bold; background-color:#e5e7eb;">
        <th style="width:40px;">№</th>
        <th style="width:80px;">Код типа</th>
        <th>Тип анализа</th>
        <th>Показатель</th>
        <th style="width:260px;">Норма</th>
        <th style="width:160px;">Кол-во раз</th>
        <th style="width:160px;">Доля от всех, %</th>
    </tr>
';

if ($indicatorsStats) {
    $i = 1;
    foreach ($indicatorsStats as $row) {
        $count   = (int)$row['indicators_count'];
        $percent = $totalIndicators > 0 ? round($count * 100 / $totalIndicators, 1) : 0;

        echo '<tr>';
        echo '<td>' . $i++ . '</td>';
        echo '<td>' . htmlspecialchars($row['type_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['type_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['indicator_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['norm_text']) . '</td>';
        echo '<td>' . $count . '</td>';
        echo '<td>' . $percent . '%</td>';
        echo '</tr>';
    }

    echo '
    <tr style="font-weight:bold;">
        <td colspan="5" align="right">Итого показателей (строк):</td>
        <td>' . (int)$totalIndicators . '</td>
        <td></td>
    </tr>';
} else {
    echo '
    <tr>
        <td colspan="7" align="center">За выбранный период показатели не найдены.</td>
    </tr>';
}

echo '</table>';

echo '</body></html>';
exit;
