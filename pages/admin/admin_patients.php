<?php
// pages/admin/admin_patients.php
// Управление пациентами (редактирование ФИО, пола, даты рождения, телефона)

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Админ-панель — пациенты';
require_once __DIR__ . '/../../includes/header.php';

$successMsg = '';
$errorMsg   = '';

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_patient') {
    $id        = (int)($_POST['id'] ?? 0);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $sex       = $_POST['sex'] ?? '';
    $birthDate = $_POST['birth_date'] ?? null;
    $phones    = trim($_POST['phones'] ?? '');

    if (!$id || $firstName === '' || $lastName === '') {
        $errorMsg = 'Заполните фамилию и имя пациента.';
    } else {
        if (!in_array($sex, ['M', 'F', ''], true)) {
            $sex = '';
        }

        if ($birthDate === '') {
            $birthDate = null;
        }
        if ($phones === '') {
            $phones = null;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE patients
                SET first_name = :first_name,
                    last_name  = :last_name,
                    sex        = :sex,
                    birth_date = :birth_date,
                    phones     = :phones
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':sex'        => $sex,
                ':birth_date' => $birthDate,
                ':phones'     => $phones,
                ':id'         => $id,
            ]);

            $successMsg = 'Данные пациента обновлены.';
        } catch (Throwable $e) {
            $errorMsg = 'Ошибка при обновлении пациента: ' . $e->getMessage();
        }
    }
}

// Пациент для формы
$editId   = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editPat  = null;

if ($editId) {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, sex, birth_date, phones
        FROM patients
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $editId]);
    $editPat = $stmt->fetch();
}

// Поиск / список
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, sex, birth_date, phones
        FROM patients
        WHERE first_name LIKE :q OR last_name LIKE :q
        ORDER BY last_name, first_name
        LIMIT 100
    ");
    $stmt->execute([':q' => '%' . $q . '%']);
} else {
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, sex, birth_date, phones
        FROM patients
        ORDER BY id DESC
        LIMIT 100
    ");
}
$patients = $stmt->fetchAll();
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">
    <div class="panel p-3 mb-3">
        <h1 class="h5 mb-0">Управление пациентами</h1>
        <p class="text-muted-soft small mb-0">
            Главврач может изменить данные пациентов: ФИО, пол, дату рождения и телефон.
        </p>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger py-2">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
        <div class="alert alert-success py-2">
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Форма поиска -->
    <div class="panel p-3 mb-3">
        <form method="get" action="/lab-system/index.php" class="row g-2">
            <input type="hidden" name="page" value="admin_patients">

            <div class="col-12 col-md-9">
                <input
                    type="text"
                    name="q"
                    class="form-control form-control-sm"
                    placeholder="Введите имя или фамилию пациента"
                    value="<?php echo htmlspecialchars($q); ?>"
                >
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary btn-sm">
                    Поиск
                </button>
            </div>
        </form>
    </div>

    <!-- Форма редактирования пациента -->
    <?php if ($editPat): ?>
        <div class="panel p-3 mb-3">
            <h2 class="ba-section-title mb-3">Редактирование пациента #<?php echo (int)$editPat['id']; ?></h2>

            <form method="post"
                  action="/lab-system/index.php?page=admin_patients&edit_id=<?php echo (int)$editPat['id']; ?>"
                  class="row g-3">
                <input type="hidden" name="action" value="save_patient">
                <input type="hidden" name="id" value="<?php echo (int)$editPat['id']; ?>">

                <div class="col-12 col-md-4">
                    <label class="form-label">Фамилия</label>
                    <input
                        type="text"
                        name="last_name"
                        class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($editPat['last_name']); ?>"
                        required
                    >
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Имя</label>
                    <input
                        type="text"
                        name="first_name"
                        class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($editPat['first_name']); ?>"
                        required
                    >
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label">Пол</label>
                    <select name="sex" class="form-select form-select-sm">
                        <option value="">Не указан</option>
                        <option value="M" <?php echo ($editPat['sex'] === 'M') ? 'selected' : ''; ?>>Муж</option>
                        <option value="F" <?php echo ($editPat['sex'] === 'F') ? 'selected' : ''; ?>>Жен</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label">Дата рождения</label>
                    <input
                        type="date"
                        name="birth_date"
                        class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($editPat['birth_date'] ?? ''); ?>"
                    >
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Телефон</label>
                    <input
                        type="text"
                        name="phones"
                        class="form-control form-control-sm"
                        placeholder="+992 90 123-45-67"
                        value="<?php echo htmlspecialchars($editPat['phones'] ?? ''); ?>"
                    >
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                    <a href="/lab-system/index.php?page=admin_patients" class="btn btn-outline-light btn-sm">
                        Отмена
                    </a>
                    <button type="submit" class="btn btn-success btn-sm">
                        Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Таблица пациентов -->
    <div class="panel p-3">
        <h2 class="ba-section-title mb-3">Список пациентов</h2>

        <div class="table-responsive">
            <table class="table table-sm table-dark table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>ФИО</th>
                        <th style="width: 80px;">Пол</th>
                        <th style="width: 130px;">Дата рождения</th>
                        <th style="width: 160px;">Телефон</th>
                        <th style="width: 140px;">Действия</th>
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
                                    <?php echo $p['birth_date'] ? htmlspecialchars($p['birth_date']) : '—'; ?>
                                </td>
                                <td>
                                    <?php echo $p['phones'] ? htmlspecialchars($p['phones']) : '—'; ?>
                                </td>
                                <td>
                                    <a
                                        href="/lab-system/index.php?page=admin_patients&edit_id=<?php echo (int)$p['id']; ?>"
                                        class="btn btn-sm btn-primary"
                                    >
                                        Редактировать
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                Пациенты не найдены.
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
