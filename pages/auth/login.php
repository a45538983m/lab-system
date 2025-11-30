<?php
// pages/auth/login.php
// Страница входа врача/админа

require_once __DIR__ . '/../../includes/functions.php';

// ВАЖНО: БОЛЬШЕ НЕ РЕДИРЕКТИМ УЖЕ АВТОРИЗОВАННОГО ПОЛЬЗОВАТЕЛЯ
// Можно зайти на форму входа даже если кто-то уже залогинен.
// Потом, при успешном входе, мы перезапишем сессию новым пользователем.

$error = '';
$registeredFlag = isset($_GET['registered']) && $_GET['registered'] == '1';

// Логин для подстановки в форму
$prefillLogin = '';
if (!empty($_POST['login'])) {
    $prefillLogin = trim($_POST['login']);
} elseif (!empty($_SESSION['just_registered_login'])) {
    $prefillLogin = $_SESSION['just_registered_login'];
    // Можно очистить, чтобы не висело бесконечно
    unset($_SESSION['just_registered_login']);
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Авторизуем нового пользователя (перезаписываем сессию)
            login_user($user);

            if ($user['role'] === 'admin') {
                header('Location: /lab-system/index.php?page=admin_dashboard');
            } else {
                header('Location: /lab-system/index.php?page=doctor_main');
            }
            exit;
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}

// Заголовок
$pageTitle = 'Вход в систему лаборатории';
require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/auth.css">

<div class="auth-container">
    <div class="auth-card card shadow-lg border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">Вход в систему</h1>
            <p class="text-muted small text-center mb-3">
                Введите логин и пароль врача или главврача.
            </p>

            <?php if ($registeredFlag): ?>
                <div class="alert alert-success py-2">
                    Учётная запись врача создана. Теперь введите логин и пароль для входа.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/lab-system/index.php?page=login">
                <div class="mb-3">
                    <label class="form-label">Логин</label>
                    <input
                        type="text"
                        name="login"
                        class="form-control"
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($prefillLogin); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Пароль</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-2">
                    Войти
                </button>
            </form>

            <p class="text-center small mt-3 mb-0">
                Нет аккаунта? <a href="/lab-system/index.php?page=register_doctor">Зарегистрировать врача</a>
            </p>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
