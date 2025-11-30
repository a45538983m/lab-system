<?php
// pages/admin/admin_user_edit.php
// Редактирование данных пользователя (для главврача)

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Редактирование пользователя';

// ID пользователя из GET
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$userId) {
    die('Не указан ID пользователя.');
}

$errorMsg   = '';
$successMsg = '';

// Загружаем пользователя
$stmt = $pdo->prepare("
    SELECT id, full_name, login, role
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    die('Пользователь не найден.');
}

// Текущие значения (по умолчанию из базы)
$fullName = $user['full_name'];
$login    = $user['login'];
$role     = $user['role'];

// Обработка POST (сохранение изменений)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName    = trim($_POST['full_name'] ?? '');
    $login       = trim($_POST['login'] ?? '');
    $role        = $_POST['role'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    // Валидация
    if ($fullName === '' || $login === '') {
        $errorMsg = 'Имя и логин не могут быть пустыми.';
    } elseif (!in_array($role, ['doctor', 'admin'], true)) {
        $errorMsg = 'Некорректная роль пользователя.';
    } else {
        // Проверим, что логин уникален (кроме текущего пользователя)
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM users
            WHERE login = :login AND id <> :id
        ");
        $stmtCheck->execute([
            ':login' => $login,
            ':id'    => $userId,
        ]);
        $rowCheck = $stmtCheck->fetch();
        if (!empty($rowCheck['cnt']) && (int)$rowCheck['cnt'] > 0) {
            $errorMsg = 'Пользователь с таким логином уже существует.';
        } else {
            // Готовим SQL для обновления
            if ($newPassword !== '') {
                // Меняем пароль (хэшируем)
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $sqlUpdate = "
                    UPDATE users
                    SET full_name = :full_name,
                        login     = :login,
                        role      = :role,
                        password_hash = :password_hash
                    WHERE id = :id
                    LIMIT 1
                ";
                $paramsUpdate = [
                    ':full_name'     => $fullName,
                    ':login'         => $login,
                    ':role'          => $role,
                    ':password_hash' => $passwordHash,
                    ':id'            => $userId,
                ];
            } else {
                // Без смены пароля
                $sqlUpdate = "
                    UPDATE users
                    SET full_name = :full_name,
                        login     = :login,
                        role      = :role
                    WHERE id = :id
                    LIMIT 1
                ";
                $paramsUpdate = [
                    ':full_name' => $fullName,
                    ':login'     => $login,
                    ':role'      => $role,
                    ':id'        => $userId,
                ];
            }

            try {
                $stmtUpd = $pdo->prepare($sqlUpdate);
                $stmtUpd->execute($paramsUpdate);

                // После успешного обновления — редирект обратно в список
                header('Location: /lab-system/index.php?page=admin_users&updated_user=' . $userId);
                exit;
            } catch (Throwable $e) {
                $errorMsg = 'Ошибка при сохранении изменений: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/admin.css">

<div class="container py-4">
    <div class="row mb-3">
        <div class="col-12 col-md-8">
            <h1 class="h4 mb-1">Редактирование пользователя</h1>
            <p class="text-muted-soft small mb-0">
                ID: <?php echo (int)$userId; ?>.
                Здесь можно изменить ФИО, логин, роль и при необходимости пароль.
            </p>
        </div>
        <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
            <a href="/lab-system/index.php?page=admin_users" class="btn btn-sm btn-outline-light">
                ← Вернуться к списку
            </a>
        </div>
    </div>

    <div class="panel p-3">
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger py-2">
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/lab-system/index.php?page=admin_user_edit&id=<?php echo (int)$userId; ?>">
            <div class="mb-3">
                <label class="form-label">ФИО врача / администратора</label>
                <input
                    type="text"
                    name="full_name"
                    class="form-control"
                    value="<?php echo htmlspecialchars($fullName); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Логин</label>
                <input
                    type="text"
                    name="login"
                    class="form-control"
                    value="<?php echo htmlspecialchars($login); ?>"
                    required
                >
                <div class="form-text text-muted-soft">
                    Используется для входа в систему.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Роль</label>
                <select name="role" class="form-select" required>
                    <option value="doctor" <?php echo ($role === 'doctor') ? 'selected' : ''; ?>>Врач</option>
                    <option value="admin"  <?php echo ($role === 'admin')  ? 'selected' : ''; ?>>Главврач</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Новый пароль (если нужно изменить)</label>
                <input
                    type="password"
                    name="new_password"
                    class="form-control"
                    placeholder="Оставьте пустым, если пароль менять не нужно"
                >
                <div class="form-text text-muted-soft">
                    Если это поле пустое, текущий пароль останется без изменений.
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted-soft small">
                    Проверьте данные перед сохранением. Изменения вступят в силу сразу.
                </div>
                <button type="submit" class="btn btn-success">
                    Сохранить изменения
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
