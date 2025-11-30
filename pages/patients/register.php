<?php
// pages/patients/register.php
// Регистрация пациента: Имя, Фамилия, Пол (Муж/Жен), Возраст, Номер карты, Телефон, Дата рождения

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$error = '';
$success = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName  = trim($_POST['first_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $sex        = $_POST['sex'] ?? '';
    $age        = trim($_POST['age'] ?? '');
    $cardNumber = trim($_POST['card_number'] ?? '');
    $phones     = trim($_POST['phones'] ?? '');
    $birthDate  = trim($_POST['birth_date'] ?? ''); // 🔹 новое поле

    if ($firstName === '' || $lastName === '' || $sex === '') {
        $error = 'Имя, фамилия и пол обязательны.';
    } elseif (!in_array($sex, ['M', 'F'], true)) {
        $error = 'Некорректное значение пола.';
    } else {
        // Проверим уникальность номера карты (если указан)
        if ($cardNumber !== '') {
            $stmt = $pdo->prepare('SELECT id FROM patients WHERE card_number = :card LIMIT 1');
            $stmt->execute(['card' => $cardNumber]);
            if ($stmt->fetch()) {
                $error = 'Пациент с таким номером карты уже существует.';
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare('
                INSERT INTO patients (first_name, last_name, sex, age, card_number, phones, birth_date)
                VALUES (:first_name, :last_name, :sex, :age, :card_number, :phones, :birth_date)
            ');

            $stmt->execute([
                'first_name'  => $firstName,
                'last_name'   => $lastName,
                'sex'         => $sex,
                'age'         => $age !== '' ? (int)$age : null,
                'card_number' => $cardNumber !== '' ? $cardNumber : null,
                'phones'      => $phones !== '' ? $phones : null,
                'birth_date'  => $birthDate !== '' ? $birthDate : null, // 🔹 сохраняем дату рождения
            ]);

            $success = 'Пациент успешно зарегистрирован.';
            $_POST = [];
        }
    }
}

$pageTitle = 'Регистрация пациента';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="panel p-4">
                <h1 class="h5 mb-2">Регистрация пациента</h1>
                <p class="text-muted-soft small mb-4">
                    Укажите данные пациента. Пол (Муж / Жен) будет использоваться для норм в анализах.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/lab-system/index.php?page=patient_register">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Имя</label>
                            <input
                                type="text"
                                name="first_name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                            >
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Фамилия</label>
                            <input
                                type="text"
                                name="last_name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label d-block">Пол</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="sex"
                                        id="sexM"
                                        value="M"
                                        <?php echo (($_POST['sex'] ?? '') === 'M') ? 'checked' : ''; ?>
                                    >
                                    <label class="form-check-label" for="sexM">
                                        Муж
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="sex"
                                        id="sexF"
                                        value="F"
                                        <?php echo (($_POST['sex'] ?? '') === 'F') ? 'checked' : ''; ?>
                                    >
                                    <label class="form-check-label" for="sexF">
                                        Жен
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">Возраст (лет)</label>
                            <input
                                type="number"
                                name="age"
                                min="0"
                                max="120"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>"
                            >
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">№ карты пациента</label>
                            <input
                                type="text"
                                name="card_number"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['card_number'] ?? ''); ?>"
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Телефон</label>
                            <input
                                type="text"
                                name="phones"
                                class="form-control"
                                placeholder="+992 90 123-45-67"
                                value="<?php echo htmlspecialchars($_POST['phones'] ?? ''); ?>"
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Дата рождения</label>
                            <input
                                type="date"
                                name="birth_date"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>"
                            >
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="/lab-system/index.php?page=doctor_main" class="btn btn-outline-light btn-sm">
                            ← Назад к панели врача
                        </a>
                        <button type="submit" class="btn btn-success">
                            Сохранить пациента
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
