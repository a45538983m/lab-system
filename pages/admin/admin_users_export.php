<?php
// pages/admin/admin_users_export.php
// Экспорт списка пользователей, количества анализов и сумм в красивый Excel

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

// Фильтры такие же, как в admin_users.php
$q         = trim($_GET['q'] ?? '');
$role      = $_GET['role'] ?? '';
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');

$params        = [];
$analysisWhere = [];

if ($dateFrom !== '') {
    $analysisWhere[]      = 'pa.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $analysisWhere[]      = 'pa.created_at <= :date_to';
    $params[':date_to']   = $dateTo . ' 23:59:59';
}

$analysisWhereSql = '';
if ($analysisWhere) {
    $analysisWhereSql = 'WHERE ' . implode(' AND ', $analysisWhere);
}

$sql = "
    SELECT
        u.id,
        u.full_name,
        u.login,
        u.role,
        u.created_at,
        COALESCE(a.cnt, 0)       AS analyses_count,
        COALESCE(a.total_sum, 0) AS analyses_total_sum
    FROM users u
    LEFT JOIN (
        SELECT
            pa.doctor_id,
            COUNT(*)            AS cnt,
            SUM(pa.total_price) AS total_sum
        FROM patient_analyses pa
        $analysisWhereSql
        GROUP BY pa.doctor_id
    ) a ON a.doctor_id = u.id
";

$where = [];

// Поиск по имени/логину
if ($q !== '') {
    $where[]       = '(u.full_name LIKE :q1 OR u.login LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

// Фильтр по роли
if ($role === 'doctor' || $role === 'admin') {
    $where[]         = 'u.role = :role';
    $params[':role'] = $role;
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY u.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Посчитаем общий итог по анализам и суммам только для врачей
$totalAnalysesDoctors = 0;
$totalMoneyDoctors    = 0.0;

if ($users) {
    foreach ($users as $u) {
        if ($u['role'] === 'doctor') {
            $totalAnalysesDoctors += (int)$u['analyses_count'];
            $totalMoneyDoctors    += (float)$u['analyses_total_sum'];
        }
    }
}

// Готовим заголовки для Excel
$filename = 'users_analyses_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // BOM для UTF-8, чтобы русские буквы не ломались
?>

<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse; font-family: Arial, sans-serif; font-size: 11pt;">
    <!-- Шапка отчёта -->
    <tr>
        <th colspan="7"
            style="
                background-color:#004b8d;
                color:#ffffff;
                font-size:14pt;
                font-weight:bold;
                text-align:center;
                padding:8px;
            "
        >
            Врачи, количество принятых анализов и суммы
            <?php if ($dateFrom || $dateTo): ?>
                (период:
                <?php echo htmlspecialchars($dateFrom ?: '…'); ?>
                —
                <?php echo htmlspecialchars($dateTo ?: '…'); ?>)
            <?php else: ?>
                (все даты)
            <?php endif; ?>
        </th>
    </tr>

    <!-- Доп. инфо -->
    <tr>
        <td colspan="7"
            style="
                background-color:#e8f0fe;
                color:#333333;
                font-size:10pt;
            "
        >
            Отчёт сформирован: <strong><?php echo date('d.m.Y H:i'); ?></strong><br>
            Общее количество анализов по врачам: <strong><?php echo (int)$totalAnalysesDoctors; ?></strong><br>
            Общая сумма по врачам: <strong><?php echo number_format($totalMoneyDoctors, 2, '.', ' '); ?></strong>
        </td>
    </tr>

    <!-- Заголовок колонок -->
    <tr>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:center; width:60px;">ID</th>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:left;  width:260px;">ФИО</th>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:left;  width:140px;">Логин</th>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:center; width:100px;">Роль</th>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:center; width:150px;">Создан</th>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:center; width:130px;">Кол-во анализов</th>
        <th style="background-color:#f2f2f2; font-weight:bold; text-align:right; width:130px;">Сумма</th>
    </tr>

    <?php if ($users): ?>
        <?php
            $rowIndex = 0;
            foreach ($users as $u):
                $rowIndex++;
                // Зебра: чётная строка чуть сероватая
                $bgColor = ($rowIndex % 2 === 0) ? '#fafafa' : '#ffffff';
        ?>
            <tr>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:center;">
                    <?php echo (int)$u['id']; ?>
                </td>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:left;">
                    <?php echo htmlspecialchars($u['full_name']); ?>
                </td>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:left;">
                    <?php echo htmlspecialchars($u['login']); ?>
                </td>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:center;">
                    <?php echo $u['role'] === 'admin' ? 'Главврач' : 'Врач'; ?>
                </td>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:center;">
                    <?php echo !empty($u['created_at']) ? htmlspecialchars($u['created_at']) : '—'; ?>
                </td>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:center; font-weight:bold;">
                    <?php
                        if ($u['role'] === 'doctor') {
                            echo (int)$u['analyses_count'];
                        } else {
                            echo 0;
                        }
                    ?>
                </td>
                <td style="background-color:<?php echo $bgColor; ?>; text-align:right; font-weight:bold;">
                    <?php
                        if ($u['role'] === 'doctor') {
                            echo number_format((float)$u['analyses_total_sum'], 2, '.', ' ');
                        } else {
                            echo '0.00';
                        }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <!-- Итоговая строка -->
        <tr>
            <td colspan="5"
                style="
                    text-align:right;
                    font-weight:bold;
                    background-color:#ddebf7;
                "
            >
                Итого по всем врачам:
            </td>
            <td
                style="
                    text-align:center;
                    font-weight:bold;
                    background-color:#ddebf7;
                "
            >
                <?php echo (int)$totalAnalysesDoctors; ?>
            </td>
            <td
                style="
                    text-align:right;
                    font-weight:bold;
                    background-color:#ddebf7;
                "
            >
                <?php echo number_format($totalMoneyDoctors, 2, '.', ' '); ?>
            </td>
        </tr>
    <?php else: ?>
        <tr>
            <td colspan="7" style="text-align:center; background-color:#ffffff;">
                Нет данных для выбранных фильтров.
            </td>
        </tr>
    <?php endif; ?>
</table>
