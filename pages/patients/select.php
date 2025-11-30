<?php
// pages/patients/select.php
// Поиск и выбор пациента (устанавливаем current_patient_id в сессию)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Выбор пациента';

$errorMsg = '';

// Если пришёл параметр set_id — устанавливаем пациента и отправляем врача на главное меню
if (isset($_GET['set_id'])) {
    $setId = (int)$_GET['set_id'];

    // Проверим, что пациент существует
    $stmtCheck = $pdo->prepare("SELECT id FROM patients WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$setId]);
    $exists = $stmtCheck->fetch();

    if ($exists) {
        $_SESSION['current_patient_id'] = $setId;
        header('Location: /lab-system/index.php?page=doctor_main');
        exit;
    } else {
        $errorMsg = 'Пациент не найден.';
    }
}

// Поисковый запрос
$q = trim($_GET['q'] ?? '');

// Загружаем список пациентов
if ($q !== '') {
    // ИСПОЛЬЗУЕМ ПОЗИЦИОННЫЕ ПАРАМЕТРЫ ? ? ЧТОБЫ НЕ БЫЛО HY093
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, sex
        FROM patients
        WHERE first_name LIKE ? OR last_name LIKE ?
        ORDER BY last_name, first_name
        LIMIT 50
    ");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, sex
        FROM patients
        ORDER BY id DESC
        LIMIT 50
    ");
}

$patients = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/auth.css">

<div class="container py-4 auth-container">
    <div class="auth-card card shadow-lg border-0">
        <div class="card-body p-4">
            <h1 class="h5 mb-3 text-center">Выбор / вход пациента</h1>

            <p class="text-muted small text-center mb-3">
                Найдите пациента по имени или фамилии и нажмите кнопку <strong>«Выбрать»</strong>,
                чтобы использовать его во всех анализах.
            </p>

            <form class="row g-2 mb-3" method="get" action="/lab-system/index.php">
                <input type="hidden" name="page" value="patient_select">
                <div class="col-12 col-md-9">
                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        placeholder="Введите имя или фамилию пациента"
                        value="<?php echo htmlspecialchars($q); ?>"
                    >
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary">
                        🔍 Поиск пациента
                    </button>
                </div>
            </form>

            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger py-2">
                    <?php echo htmlspecialchars($errorMsg); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-sm table-dark align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Пол</th>
                            <th style="width: 120px;">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($patients): ?>
                            <?php foreach ($patients as $p): ?>
                                <?php
                                    $sexLabel = ($p['sex'] === 'M') ? 'Муж' : (($p['sex'] === 'F') ? 'Жен' : '');
                                ?>
                                <tr>
                                    <td><?php echo (int)$p['id']; ?></td>
                                    <td><?php echo htmlspecialchars(trim($p['last_name'] . ' ' . $p['first_name'])); ?></td>
                                    <td><?php echo htmlspecialchars($sexLabel); ?></td>
                                    <td>
                                        <a
                                            href="/lab-system/index.php?page=patient_select&set_id=<?php echo (int)$p['id']; ?>"
                                            class="btn btn-success btn-sm"
                                        >
                                            Выбрать
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    Пациенты не найдены.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="text-center small text-muted mt-3 mb-0">
                Если пациента нет в списке — зарегистрируйте его на странице
                <a href="/lab-system/index.php?page=patient_register">«Регистрация пациента»</a>.
            </p>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
