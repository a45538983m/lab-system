<?php
// pages/doctor/ifa.php
// Анализ ИФА

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'ИФА-анализ';

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

// ---------- ЗАГРУЖАЕМ ПОКАЗАТЕЛИ ИФА ИЗ analysis_indicators ----------
$sql = "
    SELECT ai.id, ai.name, ai.norm_text, ai.default_price
    FROM analysis_indicators ai
    JOIN analysis_types t ON ai.analysis_type_id = t.id
    WHERE t.code = 'IFA'
    ORDER BY ai.id
";
$stmt = $pdo->query($sql);
$ifaParams = $stmt->fetchAll();

// ------- ОБЩИЕ ПЕРЕМЕННЫЕ -------

$mode          = 'initial'; // initial | preview | saved
$generatedRows = [];
$totalPrice    = 0.0;
$errorMsg      = '';
$successMsg    = '';
$selectedIds   = [];

// хелпер для диапазона случайного числа
function ifa_randomInRange(float $min, float $max): float {
    if ($max < $min) {
        $max = $min;
    }
    return mt_rand((int)round($min * 100), (int)round($max * 100)) / 100;
}

// Генерация значения по названию показателя
function generateIfaResultByName(string $name): float {
    // Здесь можно прописать отдельные диапазоны под каждый показатель ИФА
    switch ($name) {
        case 'IgG':
            return ifa_randomInRange(0.0, 10.0);
        case 'IgM':
            return ifa_randomInRange(0.0, 10.0);
        case 'IgA':
            return ifa_randomInRange(0.0, 10.0);
        default:
            return ifa_randomInRange(0.0, 100.0);
    }
}

// помогаем найти пациента по id и задать имя + пол
function ifa_fillPatientInfoFromId(?int $patientId, array $patients, string &$patientName, string &$patientSexLabel): void {
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
    ifa_fillPatientInfoFromId($patientId, $patients, $patientName, $patientSexLabel);
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
    ifa_fillPatientInfoFromId($patientId, $patients, $patientName, $patientSexLabel);

    // Обновляем текущего пациента в сессии, если выбран
    if ($patientId) {
        $_SESSION['current_patient_id'] = $patientId;
    }

    if ($action === 'preview') {
        // Шаг 1: ПРЕДПРОСМОТР
        $selectedIds = array_map('intval', $_POST['parameters'] ?? []);

        if (!$selectedIds) {
            $errorMsg = 'Выберите хотя бы один показатель для предварительного просмотра.';
        } elseif (!$patientId) {
            $errorMsg = 'Сначала выберите пациента.';
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
                    $value = generateIfaResultByName($row['name']);
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
        } elseif (!$patientId) {
            $errorMsg = 'Нельзя сохранить анализ без выбранного пациента.';
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

                            // ID типа анализа IFA
                            $stmtType = $pdo->prepare("SELECT id FROM analysis_types WHERE code = 'IFA' LIMIT 1");
                            $stmtType->execute();
                            $typeRow = $stmtType->fetch();
                            if (!$typeRow) {
                                throw new RuntimeException('Не найден тип анализа IFA в таблице analysis_types.');
                            }
                            $analysisTypeId = (int)$typeRow['id'];

                            // Номер чека
                            $checkNumber = 'IFA-' . date('YmdHis') . '-' . $doctorId . '-' . mt_rand(100, 999);

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

<link rel="stylesheet" href="/lab-system/public/css/ba.css">

<div class="container py-4 ba-page">
    <!-- Шапка анализа -->
    <div class="ba-header panel mb-4 p-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">ИФА-анализ</div>
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
        <h2 class="ba-section-title">Пациент и показатели для ИФА</h2>
        <p class="text-muted-soft small mb-3">
            1) Выберите пациента и нужные показатели.<br>
            2) Нажмите <strong>«Показать результаты»</strong> — появится таблица, где можно изменить значения.<br>
            3) Если всё верно — нажмите <strong>«Сохранить анализ»</strong>.
        </p>

        <form method="post" action="/lab-system/index.php?page=ifa">
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

            <!-- Кнопка "выбрать все" -->
            <div class="d-flex align-items-center mb-2">
                <input
                    type="checkbox"
                    id="select_all_ifa"
                    class="form-check-input me-2"
                >
                <label for="select_all_ifa" class="form-check-label small">
                    Выбрать все показатели / снять выделение
                </label>
            </div>

            <!-- список показателей -->
            <div class="row g-2 ba-parameters-list">
                <?php foreach ($ifaParams as $param): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="ba-param-item">
                            <input
                                type="checkbox"
                                class="form-check-input me-2 ifa-param-checkbox"
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
                Результаты анализа (ИФА)
                <?php if ($mode === 'preview'): ?>
                    <span class="text-muted-soft small">(предпросмотр, можно изменить значения)</span>
                <?php elseif ($mode === 'saved'): ?>
                    <span class="text-muted-soft small">(анализ сохранён)</span>
                <?php endif; ?>
            </h2>

            <form method="post" action="/lab-system/index.php?page=ifa">
                <input type="hidden" name="do_action" value="save">
                <input type="hidden" name="patient_id" value="<?php echo $patientId ? (int)$patientId : ''; ?>">

                <!-- Верхняя панель: подсказка + кнопка перегенерации -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="small text-muted-soft">
                        Если значения не подходят, нажмите
                        <strong>«Перегенерировать значения»</strong> — система заново
                        подберёт случайные результаты. После этого можете при необходимости
                        отредактировать их вручную и нажать «Сохранить анализ».
                    </div>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btn-ifa-regenerate">
                        🔄 Перегенерировать значения
                    </button>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-dark table-striped align-middle ba-result-table" id="ifa-result-table">
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
                                    <td class="ifa-indicator-name">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                        <input type="hidden" name="indicator_ids[]" value="<?php echo (int)$row['id']; ?>">
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="results[]"
                                            value="<?php echo htmlspecialchars(number_format($row['result'], 2, '.', ' ')); ?>"
                                            class="form-control form-control-sm ifa-result-input"
                                        >
                                    </td>
                                    <td class="ifa-indicator-norm">
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
                                <th><?php echo number_format($totalPrice, 2, '.', ' '); ?></th>
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
    // ===== Перегенерация значений ИФА =====
    const btn = document.getElementById('btn-ifa-regenerate');
    if (btn) {
        btn.addEventListener('click', function () {
            const table = document.getElementById('ifa-result-table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function (tr) {
                const nameCell   = tr.querySelector('.ifa-indicator-name');
                const resultInput = tr.querySelector('.ifa-result-input');

                if (!nameCell || !resultInput) return;

                const name = nameCell.textContent.trim();
                const range = getIfaRangeByName(name);
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

    // Диапазоны такие же по смыслу, как в PHP-функции generateIfaResultByName
    function getIfaRangeByName(name) {
        switch (name) {
            case 'IgG':
                return {min: 0.0, max: 10.0};
            case 'IgM':
                return {min: 0.0, max: 10.0};
            case 'IgA':
                return {min: 0.0, max: 10.0};
            default:
                return {min: 0.0, max: 100.0};
        }
    }

    // ===== ЧЕКБОКС "ВЫБРАТЬ ВСЕ" ДЛЯ ИФА =====
    const selectAll = document.getElementById('select_all_ifa');
    const checkboxes = document.querySelectorAll('.ifa-param-checkbox');

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
