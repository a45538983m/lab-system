<?php
// pages/admin/admin_stats_export.php
// Экспорт статистики анализов и показателей в Excel
// Логика фильтров совпадает с admin_stats.php (date_from, date_to, q)
// В таблице показателей TUP/TUH не учитываются.

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$q        = trim($_GET['q'] ?? '');

// =============================
// 1) Статистика по анализам
// =============================

// WHERE для анализов
$whereAnalysesParts = [];

if ($dateFrom !== '') {
    $fromFull = $pdo->quote($dateFrom . ' 00:00:00');
    $whereAnalysesParts[] = "pa.created_at >= $fromFull";
}
if ($dateTo !== '') {
    $toFull = $pdo->quote($dateTo . ' 23:59:59');
    $whereAnalysesParts[] = "pa.created_at <= $toFull";
}

if ($q !== '') {
    $qLike = $pdo->quote('%' . $q . '%');
    $whereAnalysesParts[] = "(t.name LIKE $qLike OR t.code LIKE $qLike)";
}

$whereAnalysesSql = $whereAnalysesParts
    ? 'WHERE ' . implode(' AND ', $whereAnalysesParts)
    : '';

$sqlAnalyses = "
    SELECT
        t.id,
        t.code,
        t.name,
        COUNT(pa.id) AS analyses_count
    FROM analysis_types t
    LEFT JOIN patient_analyses pa
        ON pa.analysis_type_id = t.id
    $whereAnalysesSql
    GROUP BY t.id, t.code, t.name
    HAVING analyses_count > 0
    ORDER BY analyses_count DESC
";

$stmtAnalyses  = $pdo->query($sqlAnalyses);
$analysesStats = $stmtAnalyses ? $stmtAnalyses->fetchAll() : [];

$totalAnalyses = 0;
foreach ($analysesStats as $row) {
    $totalAnalyses += (int)$row['analyses_count'];
}

// =============================
// 2) Статистика по показателям (без TUP/TUH)
// =============================

$whereIndicatorsParts = [];

if ($dateFrom !== '') {
    $fromFull = $pdo->quote($dateFrom . ' 00:00:00');
    $whereIndicatorsParts[] = "pa.created_at >= $fromFull";
}
if ($dateTo !== '') {
    $toFull = $pdo->quote($dateTo . ' 23:59:59');
    $whereIndicatorsParts[] = "pa.created_at <= $toFull";
}

// исключаем показатели, относящиеся к TUP/TUH
$whereIndicatorsParts[] = "(t.code IS NULL OR t.code NOT IN ('TUP', 'TUH'))";

if ($q !== '') {
    $qLike = $pdo->quote('%' . $q . '%');
    $whereIndicatorsParts[] = "(ai.name LIKE $qLike OR t.name LIKE $qLike OR t.code LIKE $qLike)";
}

$whereIndicatorsSql = $whereIndicatorsParts
    ? 'WHERE ' . implode(' AND ', $whereIndicatorsParts)
    : '';

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
    $whereIndicatorsSql
    GROUP BY ai.id, ai.name, ai.norm_text, t.code, t.name
    HAVING indicators_count > 0
    ORDER BY indicators_count DESC
";

$stmtIndicators  = $pdo->query($sqlIndicators);
$indicatorsStats = $stmtIndicators ? $stmtIndicators->fetchAll() : [];

$totalIndicators = 0;
foreach ($indicatorsStats as $row) {
    $totalIndicators += (int)$row['indicators_count'];
}

// =============================
// Имя файла
// =============================
$filename = 'stats_analyses_';
if ($dateFrom !== '' || $dateTo !== '') {
    $filename .= ($dateFrom !== '' ? $dateFrom : '...') . '_' . ($dateTo !== '' ? $dateTo : '...');
} else {
    $filename .= 'all_dates';
}
if ($q !== '') {
    $filename .= '_search';
}
$filename .= '.xls';

// =============================
// Вывод Excel (HTML-таблицы)
// =============================
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// BOM для UTF-8 (чтобы кириллица не ломалась)
echo "\xEF\xBB\xBF";

echo "<html><head><meta charset=\"utf-8\"></head><body>";

// ШАПКА
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

if ($q !== '') {
    echo '<br>Поиск: <strong>' . htmlspecialchars($q) . '</strong>';
}

echo '
        </td>
    </tr>
    <tr><td colspan="7">&nbsp;</td></tr>
</table>
';

// ---------- ТАБЛИЦА 1: АНАЛИЗЫ ПО ТИПАМ ----------
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

// ---------- ТАБЛИЦА 2: ПОКАЗАТЕЛИ (без TUP/TUH) ----------
echo '
<table border="1" cellspacing="0" cellpadding="4"
       style="font-family: Arial, sans-serif; font-size:10pt; width:100%; border-collapse:collapse;">
    <tr style="font-weight:bold; background-color:#e5e7eb;">
        <th style="width:40px;">№</th>
        <th>Имя показателя</th>
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
        echo '<td>' . htmlspecialchars($row['indicator_name']) . '</td>';
        echo '<td>' . $count . '</td>';
        echo '<td>' . $percent . '%</td>';
        echo '</tr>';
    }

    echo '
    <tr style="font-weight:bold;">
        <td colspan="2" align="right">Итого строк статистики:</td>
        <td>' . (int)$totalIndicators . '</td>
        <td></td>
    </tr>';
} else {
    echo '
    <tr>
        <td colspan="4" align="center">За выбранный период показатели не найдены.</td>
    </tr>';
}

echo '</table>';

echo '</body></html>';
exit;
