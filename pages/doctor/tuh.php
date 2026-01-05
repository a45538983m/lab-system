
<?php
// pages/doctor/tuh.php
// Общий анализ крови (ТУХ)

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Общий анализ крови (ТУХ)';

// ID и имя врача
$doctorId   = current_user_id();
$doctorName = current_user_name();

// ---------- ЗАГРУЖАЕМ ПАЦИЕНТОВ ----------
$patients = [];
$patientId       = null;
$patientName     = 'Пациент не выбран';
$patientSexLabel = '';

$stmtPat = $pdo->query("SELECT id, first_name, last_name, sex FROM patients ORDER BY last_name, first_name");
$patients = $stmtPat->fetchAll();

// ---------- ЗАГРУЖАЕМ ПОКАЗАТЕЛИ ТУХ ИЗ analysis_indicators ----------
$sql = "
    SELECT ai.id, ai.name, ai.norm_text, ai.default_price
    FROM analysis_indicators ai
    JOIN analysis_types t ON ai.analysis_type_id = t.id
    WHERE t.code = 'TUH'
    ORDER BY ai.id
";
$stmt = $pdo->query($sql);
$tuhParams = $stmt->fetchAll();

// ------- ОБЩИЕ ПЕРЕМЕННЫЕ -------

$mode          = 'initial'; // initial | preview | saved
$generatedRows = [];
$totalPrice    = 0.0;
$errorMsg      = '';
$successMsg    = '';
$selectedIds   = [];

// хелпер для диапазона случайного числа
function tuh_randomInRange(float $min, float $max): float {
    if ($max < $min) {
        $max = $min;
    }
    return mt_rand((int)round($min * 100), (int)round($max * 100)) / 100;
}

// Генерация значения по названию показателя
function generateTuhResultByName(string $name): float {
    switch ($name) {
        case 'Гемоглобин':
            return tuh_randomInRange(110.0, 160.0);
        case 'Эритроциты':
            return tuh_randomInRange(3.7, 5.0);
        case 'Тромбоциты':
            return tuh_randomInRange(180.0, 320.0);
        case 'Цветной показатель':
            return tuh_randomInRange(0.85, 1.05);
        case 'Лейкоциты':
            return tuh_randomInRange(4.0, 9.0);
        case 'Миелоциты':
            return 0.0;
        case 'Метамиелоциты':
            return 0.0;
        case 'Палочкоядерные нейтрофилы':
            return tuh_randomInRange(1.0, 6.0);
        case 'Сегментоядерные нейтрофилы':
            return tuh_randomInRange(47.0, 72.0);
        case 'Эозинофилы':
            return tuh_randomInRange(1.0, 5.0);
        case 'Базофилы':
            return tuh_randomInRange(0.0, 1.0);
        case 'Моноциты':
            return tuh_randomInRange(1.0, 4.0);
        case 'Лимфоциты':
            return tuh_randomInRange(17.0, 37.0);
        case 'Плазматические клетки':
            return tuh_randomInRange(0.0, 0.5);
        case 'СОЭ':
            return tuh_randomInRange(1.0, 15.0);
        default:
            return 0.00;
    }
}

// помогаем найти пациента по id и задать имя + пол
function tuh_fillPatientInfoFromId(?int $patientId, array $patients, string &$patientName, string &$patientSexLabel): void {
    if (!$patientId) {
        $patientName     = 'Пациент не выбран';
        $patientSexLabel = '';
        return;
    }

    foreach ($patients as $p) {
        if ((int)$p['id'] === $patientId) {
            $sex = $p['sex'] ?? '';
            $patientName = trim($p['last_name'] . ' ' . $p['first_name']);
            $patientSexLabel = ($sex === 'M') ? 'Муж' : (($sex === 'F') ? 'Жен' : '');
            return;
        }
    }

    $patientName     = 'Пациент не найден';
    $patientSexLabel = '';
}

// --- ЕСЛИ ЗАШЛИ ПО GET: ПОДСТАВЛЯЕМ ТЕКУЩЕГО ПАЦИЕНТА ИЗ СЕССИИ ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['current_patient_id'])) {
    $patientId = (int)$_SESSION['current_patient_id'];
    tuh_fillPatientInfoFromId($patientId, $patients, $patientName, $patientSexLabel);
}

// --- ОБРАБОТКА POST (preview / save) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['do_action'] ?? '';

    // Пациент выбирается в обоих случаях
    if (isset($_POST['patient_id']) && $_POST['patient_id'] !== '') {
        $patientId = (int)$_POST['patient_id'];
    } else {
        $patientId = null;
    }
    tuh_fillPatientInfoFromId($patientId, $patients, $patientName, $patientSexLabel);

    // Обновляем текущего пациента в сессии, если выбран
    if ($patientId) {
        $_SESSION['current_patient_id'] = $patientId;
    }

    if ($action === 'preview') {
        // Шаг 1: ПРЕДПРОСМОТР
        $selectedIds = array_map('intval', $_POST['parameters'] ?? []);

        if (!$selectedIds) {
            $errorMsg = 'Выберите хотя бы один показатель для предварительного просмотра.';
        } else {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $sqlSel = "
                SELECT ai.id, ai.name, ai.norm_text, ai.default_price
                FROM analysis_indicators ai
                WHERE ai.id IN ($placeholders)
                ORDER BY ai.id
            ";
            $stmtSel = $pdo->prepare($sqlSel);
            $stmtSel->execute($selectedIds);
            $selectedParams = $stmtSel->fetchAll();

            if (!$selectedParams) {
                $errorMsg = 'Не удалось загрузить выбранные показатели.';
            } else {
                foreach ($selectedParams as $row) {
                    $value = generateTuhResultByName($row['name']);
                    $price = (float)$row['default_price'];

                    $generatedRows[] = [
                        'id'        => (int)$row['id'],
                        'name'      => $row['name'],
                        'norm_text' => $row['norm_text'],
                        'result'    => $value,
                        'price'     => $price,
                    ];

                    $totalPrice += $price;
                }
                $mode = 'preview';
            }
        }

    } elseif ($action === 'save') {
        // Шаг 2: СОХРАНЕНИЕ

        if (!$doctorId) {
            $errorMsg = 'Ошибка: не найден ID текущего врача.';
        } else {
            $indicatorIds = array_map('intval', $_POST['indicator_ids'] ?? []);
            $resultsPost  = $_POST['results'] ?? [];

            if (!$indicatorIds || !$resultsPost || count($indicatorIds) !== count($resultsPost)) {
                $errorMsg = 'Некорректные данные для сохранения анализа.';
            } else {
                $placeholders = implode(',', array_fill(0, count($indicatorIds), '?'));
                $sqlSel = "
                    SELECT ai.id, ai.name, ai.norm_text, ai.default_price
                    FROM analysis_indicators ai
                    WHERE ai.id IN ($placeholders)
                ";
                $stmtSel = $pdo->prepare($sqlSel);
                $stmtSel->execute($indicatorIds);
                $rowsDb = $stmtSel->fetchAll();

                if (!$rowsDb) {
                    $errorMsg = 'Не удалось загрузить показатели для сохранения.';
                } else {
                    $map = [];
                    foreach ($rowsDb as $r) {
                        $map[(int)$r['id']] = $r;
                    }

                    foreach ($indicatorIds as $idx => $id) {
                        if (!isset($map[$id])) {
                            continue;
                        }
                        $dbRow  = $map[$id];
                        $price  = (float)$dbRow['default_price'];
                        $result = (float)str_replace(',', '.', $resultsPost[$idx]);

                        $generatedRows[] = [
                            'id'        => $id,
                            'name'      => $dbRow['name'],
                            'norm_text' => $dbRow['norm_text'],
                            'result'    => $result,
                            'price'     => $price,
                        ];

                        $totalPrice += $price;
                    }

                    if (!$generatedRows) {
                        $errorMsg = 'Нет данных для сохранения.';
                    } else {
                        try {
                            $pdo->beginTransaction();

                            // ID типа анализа TUH
                            $stmtType = $pdo->prepare("SELECT id FROM analysis_types WHERE code = 'TUH' LIMIT 1");
                            $stmtType->execute();
                            $typeRow = $stmtType->fetch();
                            if (!$typeRow) {
                                throw new RuntimeException('Не найден тип анализа TUH в таблице analysis_types.');
                            }
                            $analysisTypeId = (int)$typeRow['id'];

                            // Номер чека
                            $checkNumber = 'TUH-' . date('YmdHis') . '-' . $doctorId . '-' . mt_rand(100, 999);

                            // Вставляем шапку анализа
                            $stmtInsAnalysis = $pdo->prepare('
                                INSERT INTO patient_analyses (patient_id, doctor_id, analysis_type_id, check_number, total_price)
                                VALUES (:patient_id, :doctor_id, :analysis_type_id, :check_number, :total_price)
                            ');
                            $stmtInsAnalysis->execute([
                                'patient_id'       => $patientId ?: null,
                                'doctor_id'        => $doctorId,
                                'analysis_type_id' => $analysisTypeId,
                                'check_number'     => $checkNumber,
                                'total_price'      => $totalPrice,
                            ]);

                            $analysisId = (int)$pdo->lastInsertId();

                            // Вставляем строки анализа
                            $stmtInsItem = $pdo->prepare('
                                INSERT INTO patient_analysis_items (patient_analysis_id, indicator_id, result_value, price)
                                VALUES (:analysis_id, :indicator_id, :result_value, :price)
                            ');

                            foreach ($generatedRows as $row) {
                                $stmtInsItem->execute([
                                    'analysis_id'  => $analysisId,
                                    'indicator_id' => $row['id'],
                                    'result_value' => $row['result'],
                                    'price'        => $row['price'],
                                ]);
                            }

                            $pdo->commit();

                            $successMsg = 'Анализ сохранён. Номер чека: ' . $checkNumber;
                            header('Location: /lab-system/index.php?page=analysis_view&id=' . $analysisId);
                            exit;
                        } catch (Throwable $e) {
                            $pdo->rollBack();
                            $errorMsg = 'Ошибка при сохранении анализа: ' . $e->getMessage();
                            $generatedRows = [];
                            $totalPrice    = 0.0;
                        }
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/ba.css"><!-- пока используем тот же стиль, что и для БА -->

<div class="container py-4 ba-page">
    <!-- Шапка анализа -->
    <div class="ba-header panel mb-4 p-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">Общий анализ крови (ТУХ)</div>
                <div class="ba-header-meta">
                    Дата и время: <?php echo date('d.m.Y H:i'); ?>
                </div>
            </div>
            <div class="text-md-end small">
                <div>
                    Пациент:
                    <?php if ($patientId): ?>
                        <strong><?php echo htmlspecialchars($patientName); ?></strong>
                        <?php if ($patientSexLabel): ?>
                            (<?php echo htmlspecialchars($patientSexLabel); ?>)
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted-soft"><em>не выбран</em></span>
                    <?php endif; ?>
                </div>
                <div>
                    Врач:
                    <strong><?php echo htmlspecialchars($doctorName); ?></strong>
                </div>
            </div>
        </div>
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

    <!-- ФОРМА 1: выбор пациента и показателей (ПРЕДПРОСМОТР) -->
    <div class="panel p-3 mb-3">
        <h2 class="ba-section-title">Пациент и показатели для ТУХ</h2>
        <p class="text-muted-soft small mb-3">
            1) Выберите пациента и нужные показатели.<br>
            2) Нажмите <strong>«Показать результаты»</strong> — появится таблица, где можно изменить значения.<br>
            3) Если всё верно — нажмите <strong>«Сохранить анализ»</strong>.
        </p>

        <form method="post" action="/lab-system/index.php?page=tuh">
            <input type="hidden" name="do_action" value="preview">

            <!-- выбор пациента -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label">Пациент</label>
                    <select name="patient_id" class="form-select">
                        <option value="">— Не выбран —</option>
                        <?php foreach ($patients as $p): ?>
                            <?php
                                $pid = (int)$p['id'];
                                $sex = $p['sex'] ?? '';
                                $sexLabel = ($sex === 'M') ? 'Муж' : (($sex === 'F') ? 'Жен' : '');
                                $label = trim($p['last_name'] . ' ' . $p['first_name']);
                                if ($sexLabel) {
                                    $label .= ' (' . $sexLabel . ')';
                                }
                            ?>
                            <option value="<?php echo $pid; ?>"
                                <?php echo ($patientId === $pid) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- чекбокс "выбрать все" -->
            <div class="d-flex align-items-center mb-2">
                <input
                    type="checkbox"
                    id="select_all_tuh"
                    class="form-check-input me-2"
                >
                <label for="select_all_tuh" class="form-check-label small">
                    Выбрать все показатели / снять выделение
                </label>
            </div>

            <!-- список показателей -->
            <div class="row g-2 ba-parameters-list">
                <?php foreach ($tuhParams as $param): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="ba-param-item">
                            <input
                                type="checkbox"
                                class="form-check-input me-2 tuh-param-checkbox"
                                name="parameters[]"
                                value="<?php echo (int)$param['id']; ?>"
                                <?php echo in_array((int)$param['id'], $selectedIds, true) ? 'checked' : ''; ?>
                            >
                            <div class="ba-param-text">
                                <div class="ba-param-name">
                                    <?php echo htmlspecialchars($param['name']); ?>
                                </div>
                                <div class="ba-param-norm">
                                    Норма: <?php echo htmlspecialchars($param['norm_text']); ?>
                                </div>
                                <div class="ba-param-price">
                                    Цена: <?php echo number_format((float)$param['default_price'], 2, '.', ' '); ?>
                                </div>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="small text-muted-soft">
                    Шаг 1: выберите пациента и показатели, затем нажмите
                    &laquo;Показать результаты&raquo;.
                </div>
                <button type="submit" class="btn btn-primary">
                    Показать результаты
                </button>
            </div>
        </form>
    </div>

    <!-- ФОРМА 2: таблица результатов + кнопка "Сохранить анализ" -->
    <?php if ($generatedRows): ?>
        <div class="panel p-3">
            <h2 class="ba-section-title mb-3">
                Результаты анализа (ТУХ)
                <?php if ($mode === 'preview'): ?>
                    <span class="text-muted-soft small">(предпросмотр, можно изменить значения)</span>
                <?php elseif ($mode === 'saved'): ?>
                    <span class="text-muted-soft small">(анализ сохранён)</span>
                <?php endif; ?>
            </h2>

            <form method="post" action="/lab-system/index.php?page=tuh">
                <input type="hidden" name="do_action" value="save">
                <input type="hidden" name="patient_id" value="<?php echo $patientId ? (int)$patientId : ''; ?>">

                <!-- Панель с подсказкой и кнопкой перегенерации -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="small text-muted-soft">
                        Если сгенерированные значения вас не устраивают, нажмите
                        <strong>«Перегенерировать значения»</strong> — система заново
                        подберёт случайные результаты в пределах нормы. После этого
                        вы можете отредактировать их вручную и сохранить анализ.
                    </div>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btn-tuh-regenerate">
                        🔄 Перегенерировать значения
                    </button>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-dark table-striped align-middle ba-result-table" id="tuh-result-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">№</th>
                                <th>Исследование</th>
                                <th style="width: 160px;">Результат</th>
                                <th>Норма</th>
                                <th style="width: 120px;">Цена</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; ?>
                            <?php foreach ($generatedRows as $idx => $row): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td class="tuh-indicator-name">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                        <input type="hidden" name="indicator_ids[]" value="<?php echo (int)$row['id']; ?>">
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="results[]"
                                            value="<?php echo htmlspecialchars(number_format($row['result'], 2, '.', ' ')); ?>"
                                            class="form-control form-control-sm tuh-result-input"
                                        >
                                    </td>
                                    <td class="tuh-indicator-norm">
                                        <?php echo htmlspecialchars($row['norm_text']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($row['price'], 2, '.', ' '); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Итого:</th>
                                <th><?php //echo number_format($totalPrice, 2, '.', ' ');
                                echo 25 ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted-soft">
                        Если значения удовлетворяют, нажмите &laquo;Сохранить анализ&raquo;.
                        Цены изменяются только через админ-панель или напрямую в базе.
                    </div>
                    <button type="submit" class="btn btn-success" <?php echo ($mode === 'saved') ? 'disabled' : ''; ?>>
                        Сохранить анализ
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ==== Перегенерация значений ТУХ ====
    const btn = document.getElementById('btn-tuh-regenerate');
    if (btn) {
        btn.addEventListener('click', function () {
            const table = document.getElementById('tuh-result-table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function (tr) {
                const nameCell    = tr.querySelector('.tuh-indicator-name');
                const resultInput = tr.querySelector('.tuh-result-input');

                if (!nameCell || !resultInput) return;

                const name = nameCell.textContent.trim();
                const range = getTuhRangeByName(name);
                const min = range.min;
                const max = range.max;

                let value = 0.0;
                if (min === max) {
                    value = min;
                } else {
                    value = Math.random() * (max - min) + min;
                }

                resultInput.value = value.toFixed(2);
            });
        });
    }

    // Диапазоны такие же, как в PHP-функции generateTuhResultByName
    function getTuhRangeByName(name) {
        switch (name) {
            case 'Гемоглобин':
                return {min: 110.0, max: 160.0};
            case 'Эритроциты':
                return {min: 3.7, max: 5.0};
            case 'Тромбоциты':
                return {min: 180.0, max: 320.0};
            case 'Цветной показатель':
                return {min: 0.85, max: 1.05};
            case 'Лейкоциты':
                return {min: 4.0, max: 9.0};
            case 'Миелоциты':
                return {min: 0.0, max: 0.0};
            case 'Метамиелоциты':
                return {min: 0.0, max: 0.0};
            case 'Палочкоядерные нейтрофилы':
                return {min: 1.0, max: 6.0};
            case 'Сегментоядерные нейтрофилы':
                return {min: 47.0, max: 72.0};
            case 'Эозинофилы':
                return {min: 1.0, max: 5.0};
            case 'Базофилы':
                return {min: 0.0, max: 1.0};
            case 'Моноциты':
                return {min: 1.0, max: 4.0};
            case 'Лимфоциты':
                return {min: 17.0, max: 37.0};
            case 'Плазматические клетки':
                return {min: 0.0, max: 0.5};
            case 'СОЭ':
                return {min: 1.0, max: 15.0};
            default:
                return {min: 0.0, max: 0.0};
        }
    }

    // ==== ЧЕКБОКС "ВЫБРАТЬ ВСЕ" ДЛЯ ТУХ ====
    const selectAll = document.getElementById('select_all_tuh');
    const checkboxes = document.querySelectorAll('.tuh-param-checkbox');

    function updateSelectAllFromItems() {
        if (!selectAll || !checkboxes.length) return;

        const total = checkboxes.length;
        let checkedCount = 0;

        checkboxes.forEach(cb => {
            if (cb.checked) checkedCount++;
        });

        if (checkedCount === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checkedCount === total) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    if (selectAll) {
        // клик по "выбрать все" — отмечаем/снимаем все
        selectAll.addEventListener('change', function () {
            const checked = this.checked;
            selectAll.indeterminate = false;

            checkboxes.forEach(function (cb) {
                cb.checked = checked;
            });
        });

        // любые изменения отдельных чекбоксов -> обновляем состояние "выбрать все"
        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', updateSelectAllFromItems);
        });

        // выставляем правильное состояние при загрузке
        updateSelectAllFromItems();
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
