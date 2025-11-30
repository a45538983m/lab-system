<?php
// pages/admin/admin_indicators.php
// Редактирование показателей анализов: названия, нормы, цены

require_once __DIR__ . '/../../includes/functions.php';
require_admin();

$pageTitle = 'Админ-панель — показатели анализов';
require_once __DIR__ . '/../../includes/header.php';

$successMsg = '';
$errorMsg   = '';

// Загружаем типы анализов
$stmtTypes = $pdo->query("SELECT id, code, name FROM analysis_types ORDER BY id ASC");
$types = $stmtTypes->fetchAll();

// Выбранный тип
$selectedCode = $_GET['type'] ?? '';
if ($selectedCode === '' && $types) {
    $selectedCode = $types[0]['code'];
}

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_indicators') {
    $indicatorIds = array_map('intval', $_POST['indicator_id'] ?? []);
    $names        = $_POST['name'] ?? [];
    $norms        = $_POST['norm_text'] ?? [];
    $prices       = $_POST['default_price'] ?? [];

    if (!$indicatorIds) {
        $errorMsg = 'Нет данных для сохранения.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE analysis_indicators
                SET name = :name,
                    norm_text = :norm_text,
                    default_price = :default_price
                WHERE id = :id
                LIMIT 1
            ");

            foreach ($indicatorIds as $idx => $id) {
                if ($id <= 0) continue;

                $name = trim($names[$idx] ?? '');
                $norm = trim($norms[$idx] ?? '');
                $price = (float)str_replace(',', '.', $prices[$idx] ?? '0');

                $stmtUpdate->execute([
                    ':name'         => $name,
                    ':norm_text'    => $norm,
                    ':default_price'=> $price,
                    ':id'           => $id,
                ]);
            }

            $successMsg = 'Показатели обновлены.';
        } catch (Throwable $e) {
            $errorMsg = 'Ошибка при сохранении показателей: ' . $e->getMessage();
        }
    }
}

// Загружаем показатели выбранного типа
$indicators = [];
if ($selectedCode !== '') {
    $sql = "
        SELECT ai.id, ai.name, ai.norm_text, ai.default_price
        FROM analysis_indicators ai
        JOIN analysis_types t ON ai.analysis_type_id = t.id
        WHERE t.code = :code
        ORDER BY ai.id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':code' => $selectedCode]);
    $indicators = $stmt->fetchAll();
}
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">
    <div class="panel p-3 mb-3">
        <h1 class="h5 mb-0">Показатели анализов</h1>
        <p class="text-muted-soft small mb-0">
            Главврач может изменять названия показателей, референсные значения (нормы) и цены.
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

    <!-- Выбор типа анализа -->
    <div class="panel p-3 mb-3">
        <form method="get" action="/lab-system/index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="admin_indicators">

            <div class="col-12 col-md-6 col-lg-4">
                <label class="form-label">Тип анализа</label>
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($types as $t): ?>
                        <option
                            value="<?php echo htmlspecialchars($t['code']); ?>"
                            <?php echo ($t['code'] === $selectedCode) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($t['code'] . ' — ' . $t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3 d-none d-md-block">
                <button type="submit" class="btn btn-primary btn-sm mt-4">
                    Показать
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица показателей -->
    <?php if ($indicators): ?>
        <div class="panel p-3">
            <h2 class="ba-section-title mb-3">
                Показатели для типа:
                <span class="text-muted-soft">
                    <?php echo htmlspecialchars($selectedCode); ?>
                </span>
            </h2>

            <form method="post" action="/lab-system/index.php?page=admin_indicators&type=<?php echo htmlspecialchars($selectedCode); ?>">
                <input type="hidden" name="action" value="save_indicators">

                <div class="table-responsive">
                    <table class="table table-sm table-dark table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Название показателя</th>
                                <th>Норма (референс)</th>
                                <th style="width: 120px;">Цена</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indicators as $idx => $ind): ?>
                                <tr>
                                    <td>
                                        <?php echo (int)$ind['id']; ?>
                                        <input type="hidden" name="indicator_id[]" value="<?php echo (int)$ind['id']; ?>">
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="name[]"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($ind['name']); ?>"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="norm_text[]"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars($ind['norm_text']); ?>"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="default_price[]"
                                            class="form-control form-control-sm text-end"
                                            value="<?php echo htmlspecialchars(number_format((float)$ind['default_price'], 2, '.', ' ')); ?>"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-success btn-sm">
                        Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="panel p-3">
            <p class="text-muted mb-0">
                Для выбранного типа анализов показатели не найдены.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
