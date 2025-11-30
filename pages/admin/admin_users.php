<?php
// pages/admin/admin_users.php
// Управление пользователями (врачи + админы) для главврача

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Пользователи — админ-панель';

// Фильтры поиска
$q         = trim($_GET['q'] ?? '');
$role      = $_GET['role'] ?? '';
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');

// Сообщение об успешном обновлении
$updatedUserId = isset($_GET['updated_user']) ? (int)$_GET['updated_user'] : 0;

// Базовый массив параметров для запроса
$params = [];

// Условия по датам для подзапроса patient_analyses
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

// Базовый SQL
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
            COUNT(*)              AS cnt,
            SUM(pa.total_price)   AS total_sum
        FROM patient_analyses pa
        $analysisWhereSql
        GROUP BY pa.doctor_id
    ) a ON a.doctor_id = u.id
";

$where = [];

// Поиск по имени или логину
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

// Собираем WHERE, если есть условия
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// Сортировка и лимит
$sql .= ' ORDER BY u.id ASC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Собираем ссылку для Excel-экспорта с текущими фильтрами
$exportQuery = http_build_query([
    'page'      => 'admin_users_export',
    'q'         => $q,
    'role'      => $role,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
]);

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/admin.css">

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Пользователи системы</h1>
            <p class="text-muted-soft small mb-0">
                Врачи и администраторы. Здесь можно просматривать и редактировать данные учётных записей.
                Колонка &laquo;Анализы&raquo; показывает количество принятых анализов за выбранный период,
                колонка &laquo;Сумма&raquo; — общую стоимость этих анализов.
            </p>
        </div>
    </div>

    <?php if ($updatedUserId): ?>
        <div class="alert alert-success py-2">
            Данные пользователя (ID: <?php echo (int)$updatedUserId; ?>) успешно обновлены.
        </div>
    <?php endif; ?>

    <!-- Форма поиска -->
    <div class="panel p-3 mb-3">
        <form class="row g-2 align-items-end" method="get" action="/lab-system/index.php">
            <input type="hidden" name="page" value="admin_users">

            <div class="col-12 col-md-6 col-lg-4">
                <label class="form-label">Имя или логин</label>
                <input
                    type="text"
                    name="q"
                    class="form-control form-control-sm"
                    placeholder="Например: Олимов, muldon"
                    value="<?php echo htmlspecialchars($q); ?>"
                >
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label">Роль</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">Любая</option>
                    <option value="doctor" <?php echo ($role === 'doctor') ? 'selected' : ''; ?>>Врач</option>
                    <option value="admin"  <?php echo ($role === 'admin')  ? 'selected' : ''; ?>>Главврач</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label">Дата с</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateFrom); ?>"
                >
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label">Дата по</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($dateTo); ?>"
                >
            </div>

            <div class="col-6 col-md-3 col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary btn-sm">
                    Поиск
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица пользователей -->
    <div class="panel p-3">
        <div class="d-flex justify-content-end mb-2">
            <a
                href="/lab-system/index.php?<?php echo htmlspecialchars($exportQuery); ?>"
                class="btn btn-sm btn-outline-success"
            >
                ⬇ Excel (анализы по врачам)
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-dark align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>ФИО</th>
                        <th style="width:140px;">Логин</th>
                        <th style="width:110px;">Роль</th>
                        <th style="width:150px;">Создан</th>
                        <th style="width:110px;" class="text-center">Анализы</th>
                        <th style="width:130px;" class="text-end">Сумма</th>
                        <th style="width:160px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo (int)$u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['login']); ?></td>
                                <td>
                                    <?php echo $u['role'] === 'admin' ? 'Главврач' : 'Врач'; ?>
                                </td>
                                <td>
                                    <?php echo !empty($u['created_at']) ? htmlspecialchars($u['created_at']) : '—'; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        if ($u['role'] === 'doctor') {
                                            echo (int)$u['analyses_count'];
                                        } else {
                                            echo '—';
                                        }
                                    ?>
                                </td>
                                <td class="text-end">
                                    <?php
                                        if ($u['role'] === 'doctor') {
                                            echo number_format((float)$u['analyses_total_sum'], 2, '.', ' ');
                                        } else {
                                            echo '—';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <a
                                        href="/lab-system/index.php?page=admin_user_edit&id=<?php echo (int)$u['id']; ?>"
                                        class="btn btn-outline-light btn-sm"
                                    >
                                        Редактировать
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                Пользователи не найдены.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
