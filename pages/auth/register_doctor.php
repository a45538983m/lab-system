<?php
// pages/auth/register_doctor.php
// Регистрация нового врача (обычного doctor, не админа)

require_once __DIR__ . '/../../includes/functions.php';

$error = '';
$success = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName       = trim($_POST['full_name'] ?? '');
    $login          = trim($_POST['login'] ?? '');
    $password       = trim($_POST['password'] ?? '');
    $passwordRepeat = trim($_POST['password_repeat'] ?? '');

    // Простая валидация
    if ($fullName === '' || $login === '' || $password === '' || $passwordRepeat === '') {
        $error = 'Заполните все поля.';
    } elseif ($password !== $passwordRepeat) {
        $error = 'Пароли не совпадают.';
    } else {
        // Проверяем, нет ли уже такого логина
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $existing = $stmt->fetch();

        if ($existing) {
            $error = 'Пользователь с таким логином уже существует.';
        } else {
            // Всё ок — создаём врача
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('
                INSERT INTO users (login, password_hash, full_name, role)
                VALUES (:login, :password_hash, :full_name, :role)
            ');

            $stmt->execute([
                'login'         => $login,
                'password_hash' => $hash,
                'full_name'     => $fullName,
                'role'          => 'doctor', // всегда обычный врач
            ]);

            // Сохраняем логин во временную сессию, чтобы подставить его в форму входа
            $_SESSION['just_registered_login'] = $login;

            // Сразу отправляем врача на страницу входа
            header('Location: /lab-system/index.php?page=login&registered=1');
            exit;
        }
    }
}

// Заголовок для <title>
$pageTitle = 'Регистрация врача';

// Подключаем общий header (Bootstrap + base.css)
require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/auth.css">

<div class="auth-container">
    <div class="auth-card card shadow-lg border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">Регистрация врача</h1>
            <p class="text-muted small text-center mb-4">
                Заполните данные, чтобы создать учётную запись врача.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/lab-system/index.php?page=register_doctor">
                <div class="mb-3">
                    <label class="form-label">ФИО врача</label>
                    <input
                        type="text"
                        name="full_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Логин</label>
                    <input
                        type="text"
                        name="login"
                        class="form-control"
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Пароль</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        autocomplete="new-password"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Повтор пароля</label>
                    <input
                        type="password"
                        name="password_repeat"
                        class="form-control"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn btn-success w-100 mb-2">
                    Зарегистрировать врача
                </button>
            </form>

            <p class="text-center small mt-3 mb-0">
                Уже есть аккаунт? <a href="/lab-system/index.php?page=login">Войти</a>
            </p>
        </div>
    </div>
</div>

<?php
// Общий footer (подключает Bootstrap JS)
require_once __DIR__ . '/../../includes/footer.php';

