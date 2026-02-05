<?php
// pages/doctor/our.php
// Объединённый анализ: БА + ТУХ + ТУП в одном интерфейсе
// ТУХ и ТУП выбираются целиком за фиксированную сумму 20 сомон

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle = 'Объединённый анализ (БА + ТУХ + ТУП)';

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

// ---------- ЗАГРУЖАЕМ ПОКАЗАТЕЛИ ДЛЯ ВСЕХ ТИПОВ АНАЛИЗОВ ----------
$analysisTypes = ['BA', 'TUH', 'TUP'];
$allParams = [];
$groupedParams = [
    'BA' => [],
    'TUH' => [],
    'TUP' => []
];

foreach ($analysisTypes as $type) {
    $sql = "
        SELECT ai.id, ai.name, ai.norm_text, ai.default_price, t.code as analysis_type
        FROM analysis_indicators ai
        JOIN analysis_types t ON ai.analysis_type_id = t.id
        WHERE t.code = ?
        ORDER BY ai.id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$type]);
    $params = $stmt->fetchAll();
    
    foreach ($params as $param) {
        $param['analysis_type'] = $type;
        $allParams[] = $param;
        $groupedParams[$type][] = $param;
    }
}

// ------- ОБЩИЕ ПЕРЕМЕННЫЕ -------

$mode          = 'initial'; // initial | preview | saved
$generatedRows = [];        // для таблицы результатов
$totalPrice    = 0.0;
$errorMsg      = '';
$successMsg    = '';
$selectedIds   = [];        // какие показатели отмечены

// Фиксированные цены для ТУХ и ТУП
$FIXED_TUH_PRICE = 20.00;
$FIXED_TUP_PRICE = 20.00;

// Флаги выбора анализов целиком
$selectedTuh = false;
$selectedTup = false;

// хелпер для диапазона случайного числа
function our_randomInRange(float $min, float $max): float {
    if ($max < $min) {
        $max = $min;
    }
    return mt_rand((int)round($min * 100), (int)round($max * 100)) / 100;
}

// Генерация значения по названию показателя и типу анализа
function generateOurResultByName(string $name, string $type): float {
    switch ($type) {
        case 'BA':
            switch ($name) {
                case 'Общий билирубин':
                    return our_randomInRange(5.0, 21.0);
                case 'Общий белок':
                    return our_randomInRange(65.0, 85.0);
                case 'АСТ':
                    return our_randomInRange(15.0, 37.0);
                case 'АЛТ':
                    return our_randomInRange(15.0, 40.0);
                case 'Альбумин':
                    return our_randomInRange(35.0, 55.0);
                case 'Щелочная фосфатаза':
                    return our_randomInRange(70.0, 270.0);
                case 'Сахар крови':
                    return our_randomInRange(4.0, 6.1);
                case 'Холестерин':
                    return our_randomInRange(3.0, 5.2);
                case 'Креатинин крови':
                    return our_randomInRange(53.0, 115.0);
                case 'Мочевина крови':
                    return our_randomInRange(2.1, 8.32);
                case 'Амилаза крови':
                    return our_randomInRange(10.0, 220.0);
                case 'Амилаза мочи':
                    return our_randomInRange(50.0, 900.0);
                case 'Мочевая кислота':
                    return our_randomInRange(137.0, 452.0);
                case 'Кальций':
                    return our_randomInRange(2.02, 2.60);
                case 'Фосфор':
                    return our_randomInRange(0.7, 1.6);
                case 'Калий':
                    return our_randomInRange(3.6, 5.5);
                case 'Железо':
                    return our_randomInRange(7.6, 44.8);
                case 'Ревмафактор':
                    return our_randomInRange(0.0, 8.0);
                case 'С-реактивный белок':
                    return our_randomInRange(0.0, 6.0);
                case 'Антистрептолизин-О':
                    return our_randomInRange(0.0, 200.0);
                default:
                    return 0.00;
            }
            break;
            
        case 'TUH':
            switch ($name) {
                case 'Гемоглобин':
                    return our_randomInRange(110.0, 160.0);
                case 'Эритроциты':
                    return our_randomInRange(3.7, 5.0);
                case 'Тромбоциты':
                    return our_randomInRange(180.0, 320.0);
                case 'Цветной показатель':
                    return our_randomInRange(0.85, 1.05);
                case 'Лейкоциты':
                    return our_randomInRange(4.0, 9.0);
                case 'Миелоциты':
                    return 0.0;
                case 'Метамиелоциты':
                    return 0.0;
                case 'Палочкоядерные нейтрофилы':
                    return our_randomInRange(1.0, 6.0);
                case 'Сегментоядерные нейтрофилы':
                    return our_randomInRange(47.0, 72.0);
                case 'Эозинофилы':
                    return our_randomInRange(1.0, 5.0);
                case 'Базофилы':
                    return our_randomInRange(0.0, 1.0);
                case 'Моноциты':
                    return our_randomInRange(1.0, 4.0);
                case 'Лимфоциты':
                    return our_randomInRange(17.0, 37.0);
                case 'Плазматические клетки':
                    return our_randomInRange(0.0, 0.5);
                case 'СОЭ':
                    return our_randomInRange(1.0, 15.0);
                default:
                    return 0.00;
            }
            break;
            
        case 'TUP':
            switch ($name) {
                case 'Вазни хос':
                    return our_randomInRange(1003, 1020);
                case 'рН':
                    return our_randomInRange(4.5, 8.0);
                case 'Кетон':
                    return our_randomInRange(0.0, 17.0);
                case 'Лейкоситҳо':
                    return our_randomInRange(0, 6);
                case 'Эритроситҳо':
                    return our_randomInRange(0, 2);
                case 'Канд (глюкоза)':
                case 'Сафеда':
                case 'Бактерияҳо':
                case 'Эпителияи гурдагӣ':
                case 'Сангчаҳо':
                    // 0 или 1 условно (0 - Нест, 1 - Ҳаст)
                    return (float)mt_rand(0, 1);
                default:
                    return our_randomInRange(0, 3);
            }
            break;
            
        default:
            return 0.00;
    }
}

// помогаем найти пациента по id и задать имя + пол
function our_fillPatientInfoFromId(?int $patientId, array $patients, string &$patientName, string &$patientSexLabel): void {
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
    our_fillPatientInfoFromId($patientId, $patients, $patientName, $patientSexLabel);
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
    our_fillPatientInfoFromId($patientId, $patients, $patientName, $patientSexLabel);

    // Обновляем текущего пациента в сессии, если выбран
    if ($patientId) {
        $_SESSION['current_patient_id'] = $patientId;
    }

    if ($action === 'preview') {
        // Шаг 1: ПРЕДПРОСМОТР
        $selectedIds = array_map('intval', $_POST['parameters'] ?? []);
        
        // Проверяем, выбраны ли ТУХ и ТУП целиком
        $selectedTuh = isset($_POST['select_tuh']) && $_POST['select_tuh'] === '1';
        $selectedTup = isset($_POST['select_tup']) && $_POST['select_tup'] === '1';

        if (empty($selectedIds) && !$selectedTuh && !$selectedTup) {
            $errorMsg = 'Выберите хотя бы один анализ или показатель для предварительного просмотра.';
        } elseif (!$patientId) {
            $errorMsg = 'Сначала выберите пациента.';
        } else {
            // СОБИРАЕМ ВСЕ ВЫБРАННЫЕ ПОКАЗАТЕЛИ
            $allSelectedIds = $selectedIds; // начинаем с выбранных БА
            
            // Если выбран ТУХ целиком - добавляем все его показатели
            if ($selectedTuh && isset($groupedParams['TUH'])) {
                foreach ($groupedParams['TUH'] as $param) {
                    if (!in_array((int)$param['id'], $allSelectedIds)) {
                        $allSelectedIds[] = (int)$param['id'];
                    }
                }
            }
            
            // Если выбран ТУП целиком - добавляем все его показатели
            if ($selectedTup && isset($groupedParams['TUP'])) {
                foreach ($groupedParams['TUP'] as $param) {
                    if (!in_array((int)$param['id'], $allSelectedIds)) {
                        $allSelectedIds[] = (int)$param['id'];
                    }
                }
            }

            if (empty($allSelectedIds)) {
                $errorMsg = 'Не выбраны показатели для предварительного просмотра.';
            } else {
                // Загружаем выбранные показатели
                $placeholders = implode(',', array_fill(0, count($allSelectedIds), '?'));
                $sqlSel = "
                    SELECT ai.id, ai.name, ai.norm_text, ai.default_price, t.code as analysis_type
                    FROM analysis_indicators ai
                    JOIN analysis_types t ON ai.analysis_type_id = t.id
                    WHERE ai.id IN ($placeholders)
                    ORDER BY t.code, ai.id
                ";
                $stmtSel = $pdo->prepare($sqlSel);
                $stmtSel->execute($allSelectedIds);
                $selectedParams = $stmtSel->fetchAll();

                if (!$selectedParams) {
                    $errorMsg = 'Не удалось загрузить выбранные показатели.';
                } else {
                    foreach ($selectedParams as $row) {
                        $value = generateOurResultByName($row['name'], $row['analysis_type']);
                        $price = (float)$row['default_price'];
                        
                        // Для ТУХ и ТУП используем фиксированные цены, если анализ выбран целиком
                        if ($row['analysis_type'] === 'TUH' && $selectedTuh) {
                            $price = 0; // Общая сумма будет добавлена отдельно
                        } elseif ($row['analysis_type'] === 'TUP' && $selectedTup) {
                            $price = 0; // Общая сумма будет добавлена отдельно
                        }

                        $generatedRows[] = [
                            'id'        => (int)$row['id'],
                            'name'      => $row['name'],
                            'norm_text' => $row['norm_text'],
                            'result'    => $value,
                            'price'     => $price,
                            'type'      => $row['analysis_type']
                        ];

                        $totalPrice += $price;
                    }
                    
                    // Добавляем фиксированные суммы для ТУХ и ТУП
                    if ($selectedTuh) {
                        $totalPrice += $FIXED_TUH_PRICE;
                    }
                    if ($selectedTup) {
                        $totalPrice += $FIXED_TUP_PRICE;
                    }
                    
                    $mode = 'preview';
                }
            }
        }

    } elseif ($action === 'save') {
    // Шаг 2: СОХРАНЕНИЕ В КОМПЛЕКСНЫЙ АНАЛИЗ

    if (!$doctorId) {
        $errorMsg = 'Ошибка: не найден ID текущего врача.';
    } elseif (!$patientId) {
        $errorMsg = 'Нельзя сохранить анализы без выбранного пациента.';
    } else {
        $indicatorIds = array_map('intval', $_POST['indicator_ids'] ?? []);
        $resultsPost  = $_POST['results'] ?? [];
        $selectedTuh = isset($_POST['selected_tuh']) && $_POST['selected_tuh'] === '1';
        $selectedTup = isset($_POST['selected_tup']) && $_POST['selected_tup'] === '1';

        if (!$indicatorIds || !$resultsPost || count($indicatorIds) !== count($resultsPost)) {
            $errorMsg = 'Некорректные данные для сохранения анализа.';
        } else {
            // Загружаем инфу о показателях из базы
            $placeholders = implode(',', array_fill(0, count($indicatorIds), '?'));
            $sqlSel = "
                SELECT ai.id, ai.name, ai.norm_text, ai.default_price, t.code as analysis_type
                FROM analysis_indicators ai
                JOIN analysis_types t ON ai.analysis_type_id = t.id
                WHERE ai.id IN ($placeholders)
            ";
            $stmtSel = $pdo->prepare($sqlSel);
            $stmtSel->execute($indicatorIds);
            $rowsDb = $stmtSel->fetchAll();

            if (!$rowsDb) {
                $errorMsg = 'Не удалось загрузить показатели для сохранения.';
            } else {
                // Группируем показатели по типам анализа
                $indicatorsByType = [];
                foreach ($rowsDb as $r) {
                    $type = $r['analysis_type'];
                    if (!isset($indicatorsByType[$type])) {
                        $indicatorsByType[$type] = [];
                    }
                    $indicatorsByType[$type][(int)$r['id']] = $r;
                }

                // Собираем итоговые строки для отображения
                foreach ($indicatorIds as $idx => $id) {
                    // Находим к какому типу относится показатель
                    $found = false;
                    foreach ($indicatorsByType as $type => $typeIndicators) {
                        if (isset($typeIndicators[$id])) {
                            $dbRow  = $typeIndicators[$id];
                            $price  = (float)$dbRow['default_price'];
                            $result = (float)str_replace(',', '.', $resultsPost[$idx]);

                            // Для ТУХ и ТУП используем фиксированные цены, если анализ выбран целиком
                            if ($type === 'TUH' && $selectedTuh) {
                                $price = 0; // Общая сумма будет добавлена отдельно
                            } elseif ($type === 'TUP' && $selectedTup) {
                                $price = 0; // Общая сумма будет добавлена отдельно
                            }

                            $generatedRows[] = [
                                'id'        => $id,
                                'name'      => $dbRow['name'],
                                'norm_text' => $dbRow['norm_text'],
                                'result'    => $result,
                                'price'     => $price,
                                'type'      => $type
                            ];

                            $totalPrice += $price;
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        $errorMsg = 'Не найден показатель ID: ' . $id;
                        break;
                    }
                }

                // Добавляем фиксированные суммы для ТУХ и ТУП
                if ($selectedTuh) {
                    $totalPrice += $FIXED_TUH_PRICE;
                }
                if ($selectedTup) {
                    $totalPrice += $FIXED_TUP_PRICE;
                }

                if (!$generatedRows) {
                    $errorMsg = 'Нет данных для сохранения.';
                } else {
                    // Сохраняем в базу как КОМПЛЕКСНЫЙ АНАЛИЗ
                    try {
                        $pdo->beginTransaction();
                        
                        // 1. СОЗДАЕМ КОМПЛЕКСНЫЙ АНАЛИЗ
                        $combinedCheckNumber = 'COMB-' . date('YmdHis') . '-' . $doctorId . '-' . mt_rand(100, 999);
                        
                        $stmtCombined = $pdo->prepare('
                            INSERT INTO combined_analyses 
                            (combined_check_number, patient_id, doctor_id, total_price)
                            VALUES (:check_number, :patient_id, :doctor_id, :total_price)
                        ');
                        $stmtCombined->execute([
                            'check_number' => $combinedCheckNumber,
                            'patient_id'   => $patientId ?: null,
                            'doctor_id'    => $doctorId,
                            'total_price'  => $totalPrice,
                        ]);
                        
                        $combinedAnalysisId = (int)$pdo->lastInsertId();
                        
                        // Массив для хранения ID сохраненных анализов
                        $savedAnalysisIds = [];
                        
                        // 2. СОХРАНЯЕМ КАЖДЫЙ АНАЛИЗ ОТДЕЛЬНО
                        // ----- СОХРАНЕНИЕ БА -----
                        if (isset($indicatorsByType['BA']) && !empty($indicatorsByType['BA'])) {
                            $typeCode = 'BA';
                            $typeRows = [];
                            $typeTotalPrice = 0;
                            
                            // Собираем только БА показатели
                            foreach ($generatedRows as $row) {
                                if ($row['type'] === 'BA') {
                                    $typeRows[] = $row;
                                    $typeTotalPrice += $row['price'];
                                }
                            }
                            
                            if (!empty($typeRows)) {
                                // ID типа анализа БА
                                $stmtType = $pdo->prepare("SELECT id FROM analysis_types WHERE code = ? LIMIT 1");
                                $stmtType->execute([$typeCode]);
                                $typeRow = $stmtType->fetch();
                                if (!$typeRow) {
                                    throw new RuntimeException('Не найден тип анализа ' . $typeCode . ' в таблице analysis_types.');
                                }
                                $analysisTypeId = (int)$typeRow['id'];

                                // Номер чека
                                $checkNumber = $typeCode . '-' . date('YmdHis') . '-' . $doctorId . '-' . mt_rand(100, 999);

                                // Вставляем шапку анализа
                                $stmtInsAnalysis = $pdo->prepare('
                                    INSERT INTO patient_analyses 
                                    (patient_id, doctor_id, analysis_type_id, check_number, total_price)
                                    VALUES (:patient_id, :doctor_id, :analysis_type_id, :check_number, :total_price)
                                ');
                                $stmtInsAnalysis->execute([
                                    'patient_id'       => $patientId ?: null,
                                    'doctor_id'        => $doctorId,
                                    'analysis_type_id' => $analysisTypeId,
                                    'check_number'     => $checkNumber,
                                    'total_price'      => $typeTotalPrice,
                                ]);

                                $analysisId = (int)$pdo->lastInsertId();
                                $savedAnalysisIds[] = $analysisId;

                                // Вставляем строки анализа
                                $stmtInsItem = $pdo->prepare('
                                    INSERT INTO patient_analysis_items 
                                    (patient_analysis_id, indicator_id, result_value, price)
                                    VALUES (:analysis_id, :indicator_id, :result_value, :price)
                                ');

                                foreach ($typeRows as $row) {
                                    $stmtInsItem->execute([
                                        'analysis_id'  => $analysisId,
                                        'indicator_id' => $row['id'],
                                        'result_value' => $row['result'],
                                        'price'        => $row['price'],
                                    ]);
                                }
                                
                                // Связываем с комплексным анализом
                                $stmtLink = $pdo->prepare('
                                    INSERT INTO combined_analysis_items 
                                    (combined_analysis_id, analysis_id)
                                    VALUES (:combined_id, :analysis_id)
                                ');
                                $stmtLink->execute([
                                    'combined_id' => $combinedAnalysisId,
                                    'analysis_id' => $analysisId,
                                ]);
                            }
                        }

                        // ----- СОХРАНЕНИЕ ТУХ (если выбран целиком) -----
                        if ($selectedTuh && isset($groupedParams['TUH']) && !empty($groupedParams['TUH'])) {
                            $typeCode = 'TUH';
                            $typeRows = [];
                            $typeTotalPrice = $FIXED_TUH_PRICE; // Фиксированная цена
                            
                            // Добавляем ВСЕ показатели ТУХ
                            foreach ($groupedParams['TUH'] as $param) {
                                // Находим результат в $generatedRows или генерируем новый
                                $resultValue = 0;
                                foreach ($generatedRows as $row) {
                                    if ($row['id'] == $param['id']) {
                                        $resultValue = $row['result'];
                                        break;
                                    }
                                }
                                
                                // Если не нашли - генерируем
                                if ($resultValue == 0) {
                                    $resultValue = generateOurResultByName($param['name'], 'TUH');
                                }
                                
                                $typeRows[] = [
                                    'id'        => (int)$param['id'],
                                    'name'      => $param['name'],
                                    'norm_text' => $param['norm_text'],
                                    'result'    => $resultValue,
                                    'price'     => 0, // Цена 0, т.к. фиксированная цена в шапке
                                    'type'      => 'TUH'
                                ];
                            }
                            
                            // ID типа анализа ТУХ
                            $stmtType = $pdo->prepare("SELECT id FROM analysis_types WHERE code = ? LIMIT 1");
                            $stmtType->execute([$typeCode]);
                            $typeRow = $stmtType->fetch();
                            if (!$typeRow) {
                                throw new RuntimeException('Не найден тип анализа ' . $typeCode . ' в таблице analysis_types.');
                            }
                            $analysisTypeId = (int)$typeRow['id'];

                            // Номер чека
                            $checkNumber = $typeCode . '-' . date('YmdHis') . '-' . $doctorId . '-' . mt_rand(100, 999);

                            // Вставляем шапку анализа
                            $stmtInsAnalysis = $pdo->prepare('
                                INSERT INTO patient_analyses 
                                (patient_id, doctor_id, analysis_type_id, check_number, total_price)
                                VALUES (:patient_id, :doctor_id, :analysis_type_id, :check_number, :total_price)
                            ');
                            $stmtInsAnalysis->execute([
                                'patient_id'       => $patientId ?: null,
                                'doctor_id'        => $doctorId,
                                'analysis_type_id' => $analysisTypeId,
                                'check_number'     => $checkNumber,
                                'total_price'      => $typeTotalPrice,
                            ]);

                            $analysisId = (int)$pdo->lastInsertId();
                            $savedAnalysisIds[] = $analysisId;

                            // Вставляем строки анализа
                            $stmtInsItem = $pdo->prepare('
                                INSERT INTO patient_analysis_items 
                                (patient_analysis_id, indicator_id, result_value, price)
                                VALUES (:analysis_id, :indicator_id, :result_value, :price)
                            ');

                            foreach ($typeRows as $row) {
                                $stmtInsItem->execute([
                                    'analysis_id'  => $analysisId,
                                    'indicator_id' => $row['id'],
                                    'result_value' => $row['result'],
                                    'price'        => $row['price'],
                                ]);
                            }
                            
                            // Связываем с комплексным анализом
                            $stmtLink = $pdo->prepare('
                                INSERT INTO combined_analysis_items 
                                (combined_analysis_id, analysis_id)
                                VALUES (:combined_id, :analysis_id)
                            ');
                            $stmtLink->execute([
                                'combined_id' => $combinedAnalysisId,
                                'analysis_id' => $analysisId,
                            ]);
                        }

                        // ----- СОХРАНЕНИЕ ТУП (если выбран целиком) -----
                        if ($selectedTup && isset($groupedParams['TUP']) && !empty($groupedParams['TUP'])) {
                            $typeCode = 'TUP';
                            $typeRows = [];
                            $typeTotalPrice = $FIXED_TUP_PRICE; // Фиксированная цена
                            
                            // Добавляем ВСЕ показатели ТУП
                            foreach ($groupedParams['TUP'] as $param) {
                                // Находим результат в $generatedRows или генерируем новый
                                $resultValue = 0;
                                foreach ($generatedRows as $row) {
                                    if ($row['id'] == $param['id']) {
                                        $resultValue = $row['result'];
                                        break;
                                    }
                                }
                                
                                // Если не нашли - генерируем
                                if ($resultValue == 0) {
                                    $resultValue = generateOurResultByName($param['name'], 'TUP');
                                }
                                
                                $typeRows[] = [
                                    'id'        => (int)$param['id'],
                                    'name'      => $param['name'],
                                    'norm_text' => $param['norm_text'],
                                    'result'    => $resultValue,
                                    'price'     => 0, // Цена 0, т.к. фиксированная цена в шапке
                                    'type'      => 'TUP'
                                ];
                            }
                            
                            // ID типа анализа ТУП
                            $stmtType = $pdo->prepare("SELECT id FROM analysis_types WHERE code = ? LIMIT 1");
                            $stmtType->execute([$typeCode]);
                            $typeRow = $stmtType->fetch();
                            if (!$typeRow) {
                                throw new RuntimeException('Не найден тип анализа ' . $typeCode . ' в таблице analysis_types.');
                            }
                            $analysisTypeId = (int)$typeRow['id'];

                            // Номер чека
                            $checkNumber = $typeCode . '-' . date('YmdHis') . '-' . $doctorId . '-' . mt_rand(100, 999);

                            // Вставляем шапку анализа
                            $stmtInsAnalysis = $pdo->prepare('
                                INSERT INTO patient_analyses 
                                (patient_id, doctor_id, analysis_type_id, check_number, total_price)
                                VALUES (:patient_id, :doctor_id, :analysis_type_id, :check_number, :total_price)
                            ');
                            $stmtInsAnalysis->execute([
                                'patient_id'       => $patientId ?: null,
                                'doctor_id'        => $doctorId,
                                'analysis_type_id' => $analysisTypeId,
                                'check_number'     => $checkNumber,
                                'total_price'      => $typeTotalPrice,
                            ]);

                            $analysisId = (int)$pdo->lastInsertId();
                            $savedAnalysisIds[] = $analysisId;

                            // Вставляем строки анализа
                            $stmtInsItem = $pdo->prepare('
                                INSERT INTO patient_analysis_items 
                                (patient_analysis_id, indicator_id, result_value, price)
                                VALUES (:analysis_id, :indicator_id, :result_value, :price)
                            ');

                            foreach ($typeRows as $row) {
                                $stmtInsItem->execute([
                                    'analysis_id'  => $analysisId,
                                    'indicator_id' => $row['id'],
                                    'result_value' => $row['result'],
                                    'price'        => $row['price'],
                                ]);
                            }
                            
                            // Связываем с комплексным анализом
                            $stmtLink = $pdo->prepare('
                                INSERT INTO combined_analysis_items 
                                (combined_analysis_id, analysis_id)
                                VALUES (:combined_id, :analysis_id)
                            ');
                            $stmtLink->execute([
                                'combined_id' => $combinedAnalysisId,
                                'analysis_id' => $analysisId,
                            ]);
                        }

                        $pdo->commit();

                        // Перенаправляем на просмотр комплексного анализа
                        if ($combinedAnalysisId) {
                            header('Location: /lab-system/index.php?page=combined_view&id=' . $combinedAnalysisId);
                            exit;
                        } else {
                            $successMsg = 'Комплексный анализ успешно сохранен!';
                            $mode = 'saved';
                        }
                        
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
    <!-- Шапка объединенного анализа -->
    <div class="ba-header panel mb-4 p-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <div class="ba-header-title">Объединённый анализ (БА + ТУХ + ТУП)</div>
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
        <h2 class="ba-section-title">Пациент и показатели для объединенного анализа</h2>
        <p class="text-muted-soft small mb-3">
            1) Выберите пациента и нужные показатели из разных анализов.<br>
            2) ТУХ и ТУП можно выбрать целиком за фиксированную сумму 20 сомон.<br>
            3) Нажмите <strong>«Показать результаты»</strong> — появится таблица с результатами.<br>
            4) Если всё верно — нажмите <strong>«Сохранить все анализы»</strong>.
        </p>

        <form method="post" action="/lab-system/index.php?page=our">
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

            <!-- Блок для выбора анализов целиком -->
            <div class="panel p-3 mb-4 bg-light">
                <h3 class="h5 mb-3">Выберите анализы целиком (фиксированная цена):</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="select_tuh" id="select_tuh" value="1"
                                           <?php echo $selectedTuh ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="select_tuh">
                                        Общий анализ крови (ТУХ)
                                    </label>
                                </div>
                                <p class="card-text small mt-2">
                                    Полный общий анализ крови со всеми показателями:
                                    Гемоглобин, Эритроциты, Тромбоциты, Лейкоциты, СОЭ и др.
                                </p>
                                <div class="fw-bold text-success">
                                    Фиксированная цена: 20 сомон
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="select_tup" id="select_tup" value="1"
                                           <?php echo $selectedTup ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="select_tup">
                                        Общий анализ мочи (ТУП)
                                    </label>
                                </div>
                                <p class="card-text small mt-2">
                                    Полный общий анализ мочи со всеми показателями:
                                    Вазни хос, рН, Кетон, Лейкоситҳо, Эритроситҳо и др.
                                </p>
                                <div class="fw-bold text-success">
                                    Фиксированная цена: 20 сомон
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Блок для выбора отдельных показателей БА -->
            <?php if (!empty($groupedParams['BA'])): ?>
                <div class="analysis-type-section mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="h5 mb-0">Биохимический анализ (БА) - выберите показатели:</h3>
                        <div class="form-check">
                            <input type="checkbox" id="select_all_ba" 
                                   class="form-check-input analysis-type-select-all"
                                   data-type="ba">
                            <label for="select_all_ba" class="form-check-label small">
                                Выбрать все показатели БА
                            </label>
                        </div>
                    </div>
                    
                    <div class="row g-2 ba-parameters-list" id="params-ba">
                        <?php foreach ($groupedParams['BA'] as $param): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="ba-param-item">
                                    <input
                                        type="checkbox"
                                        class="form-check-input me-2 our-param-checkbox param-type-ba"
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
                </div>
            <?php endif; ?>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="small text-muted-soft">
                    Выберите пациента, анализы ТУХ/ТУП целиком и/или показатели БА, затем нажмите
                    &laquo;Показать результаты&raquo;.
                </div>
                <button type="submit" class="btn btn-primary">
                    Показать результаты
                </button>
            </div>
        </form>
    </div>

    <!-- ФОРМА 2: таблица результатов + кнопка "Сохранить все анализы" -->
    <?php if ($generatedRows): ?>
        <div class="panel p-3">
            <h2 class="ba-section-title mb-3">
                Результаты объединенного анализа
                <?php if ($mode === 'preview'): ?>
                    <span class="text-muted-soft small">(предпросмотр, можно изменить значения)</span>
                <?php elseif ($mode === 'saved'): ?>
                    <span class="text-muted-soft small">(анализы сохранены)</span>
                <?php endif; ?>
            </h2>

            <form method="post" action="/lab-system/index.php?page=our">
                <input type="hidden" name="do_action" value="save">
                <input type="hidden" name="patient_id" value="<?php echo $patientId ? (int)$patientId : ''; ?>">
                <input type="hidden" name="selected_tuh" value="<?php echo $selectedTuh ? '1' : '0'; ?>">
                <input type="hidden" name="selected_tup" value="<?php echo $selectedTup ? '1' : '0'; ?>">

                <!-- Панель с кнопками перегенерации по типам анализа -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small text-muted-soft">
                        Можно перегенерировать значения отдельно для каждого типа анализа:
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-warning" id="btn-regenerate-ba" data-type="ba">
                            Перегенерировать БА
                        </button>
                        <?php if ($selectedTuh): ?>
                            <button type="button" class="btn btn-outline-warning" id="btn-regenerate-tuh" data-type="tuh">
                                Перегенерировать ТУХ
                            </button>
                        <?php endif; ?>
                        <?php if ($selectedTup): ?>
                            <button type="button" class="btn btn-outline-warning" id="btn-regenerate-tup" data-type="tup">
                                Перегенерировать ТУП
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger" id="btn-regenerate-all">
                            Перегенерировать все
                        </button>
                    </div>
                </div>

                <!-- Группированные таблицы по типам анализа -->
                <?php 
                // Группируем строки по типам анализа
                $groupedRows = [
                    'ba' => [],
                    'tuh' => [],
                    'tup' => []
                ];
                
                foreach ($generatedRows as $row) {
                    $type = strtolower($row['type']);
                    if (isset($groupedRows[$type])) {
                        $groupedRows[$type][] = $row;
                    }
                }
                
                $typeTitles = [
                    'ba' => 'Биохимический анализ (БА)',
                    'tuh' => 'Общий анализ крови (ТУХ)',
                    'tup' => 'Общий анализ мочи (ТУП)'
                ];
                ?>
                
                <?php foreach ($groupedRows as $typeCode => $typeRows): ?>
                    <?php if (!empty($typeRows) || ($typeCode === 'tuh' && $selectedTuh) || ($typeCode === 'tup' && $selectedTup)): ?>
                        <div class="analysis-results-section mb-4">
                            <h4 class="h6 mb-2">
                                <?php echo htmlspecialchars($typeTitles[$typeCode] ?? $typeCode); ?>
                                <?php if (($typeCode === 'tuh' && $selectedTuh) || ($typeCode === 'tup' && $selectedTup)): ?>
                                    <span class="badge bg-success ms-2">Фиксированная цена: 20 сомон</span>
                                <?php endif; ?>
                            </h4>
                            
                            <?php if (!empty($typeRows)): ?>
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm table-dark table-striped align-middle ba-result-table our-result-table" 
                                           data-type="<?php echo $typeCode; ?>">
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
                                            <?php foreach ($typeRows as $idx => $row): ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td class="our-indicator-name" data-type="<?php echo $typeCode; ?>">
                                                        <?php echo htmlspecialchars($row['name']); ?>
                                                        <input type="hidden" name="indicator_ids[]" value="<?php echo (int)$row['id']; ?>">
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="text"
                                                            name="results[]"
                                                            value="<?php echo htmlspecialchars(number_format($row['result'], 2, '.', ' ')); ?>"
                                                            class="form-control form-control-sm our-result-input"
                                                            data-type="<?php echo $typeCode; ?>"
                                                        >
                                                    </td>
                                                    <td class="our-indicator-norm">
                                                        <?php echo htmlspecialchars($row['norm_text']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if (($typeCode === 'tuh' && $selectedTuh) || ($typeCode === 'tup' && $selectedTup)): ?>
                                                            <span class="text-muted-soft">(включено в общую сумму)</span>
                                                        <?php else: ?>
                                                            <?php echo number_format($row['price'], 2, '.', ' '); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Итоговая сумма -->
                <div class="total-price-section p-3 bg-dark rounded mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Общая сумма всех анализов:</h5>
                            <small class="text-muted-soft">
                                <?php if ($selectedTuh): ?>(ТУХ: 20 сомон)<?php endif; ?>
                                <?php if ($selectedTup): ?><?php echo $selectedTuh ? ' + ' : '('; ?>(ТУП: 20 сомон)<?php endif; ?>
                                <?php if (!empty($groupedRows['ba'])): ?>
                                    <?php echo ($selectedTuh || $selectedTup) ? ' + ' : '('; ?>
                                    (БА: <?php 
                                        $baTotal = 0;
                                        foreach ($generatedRows as $row) {
                                            if ($row['type'] === 'BA') {
                                                $baTotal += $row['price'];
                                            }
                                        }
                                        echo number_format($baTotal, 2, '.', ' ');
                                    ?> сомон)
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="h4 mb-0 text-success">
                            <?php echo number_format($totalPrice, 2, '.', ' '); ?> сомон
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted-soft">
                        Если значения удовлетворяют, нажмите &laquo;Сохранить все анализы&raquo;.
                        Каждый тип анализа будет сохранен отдельно с собственным номером чека.
                    </div>
                    <button type="submit" class="btn btn-success" <?php echo ($mode === 'saved') ? 'disabled' : ''; ?>>
                        Сохранить все анализы
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ===== ОБРАБОТКА "ВЫБРАТЬ ВСЕ" ДЛЯ БА =====
    const selectAllBa = document.getElementById('select_all_ba');
    const baCheckboxes = document.querySelectorAll('.param-type-ba');

    function updateSelectAllBa() {
        if (!selectAllBa || !baCheckboxes.length) return;

        const total = baCheckboxes.length;
        let checkedCount = 0;

        baCheckboxes.forEach(cb => {
            if (cb.checked) checkedCount++;
        });

        if (checkedCount === 0) {
            selectAllBa.checked = false;
            selectAllBa.indeterminate = false;
        } else if (checkedCount === total) {
            selectAllBa.checked = true;
            selectAllBa.indeterminate = false;
        } else {
            selectAllBa.checked = false;
            selectAllBa.indeterminate = true;
        }
    }

    if (selectAllBa) {
        // Клик по "выбрать все" для БА
        selectAllBa.addEventListener('change', function () {
            const checked = this.checked;
            selectAllBa.indeterminate = false;

            baCheckboxes.forEach(function (cb) {
                cb.checked = checked;
            });
        });

        // Обновление состояния при изменении отдельных чекбоксов
        baCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', updateSelectAllBa);
        });

        // Инициализация состояния
        updateSelectAllBa();
    }

    // ===== ПЕРЕГЕНЕРАЦИЯ ЗНАЧЕНИЙ ПО ТИПАМ АНАЛИЗА =====
    
    // Диапазоны для каждого типа анализа
    const baRanges = {
        'Общий билирубин': {min: 5.0, max: 21.0},
        'Общий белок': {min: 65.0, max: 85.0},
        'АСТ': {min: 15.0, max: 37.0},
        'АЛТ': {min: 15.0, max: 40.0},
        'Альбумин': {min: 35.0, max: 55.0},
        'Щелочная фосфатаза': {min: 70.0, max: 270.0},
        'Сахар крови': {min: 4.0, max: 6.1},
        'Холестерин': {min: 3.0, max: 5.2},
        'Креатинин крови': {min: 53.0, max: 115.0},
        'Мочевина крови': {min: 2.1, max: 8.32},
        'Амилаза крови': {min: 10.0, max: 220.0},
        'Амилаза мочи': {min: 50.0, max: 900.0},
        'Мочевая кислота': {min: 137.0, max: 452.0},
        'Кальций': {min: 2.02, max: 2.60},
        'Фосфор': {min: 0.7, max: 1.6},
        'Калий': {min: 3.6, max: 5.5},
        'Железо': {min: 7.6, max: 44.8},
        'Ревмафактор': {min: 0.0, max: 8.0},
        'С-реактивный белок': {min: 0.0, max: 6.0},
        'Антистрептолизин-О': {min: 0.0, max: 200.0}
    };

    const tuhRanges = {
        'Гемоглобин': {min: 110.0, max: 160.0},
        'Эритроциты': {min: 3.7, max: 5.0},
        'Тромбоциты': {min: 180.0, max: 320.0},
        'Цветной показатель': {min: 0.85, max: 1.05},
        'Лейкоциты': {min: 4.0, max: 9.0},
        'Миелоциты': {min: 0.0, max: 0.0},
        'Метамиелоциты': {min: 0.0, max: 0.0},
        'Палочкоядерные нейтрофилы': {min: 1.0, max: 6.0},
        'Сегментоядерные нейтрофилы': {min: 47.0, max: 72.0},
        'Эозинофилы': {min: 1.0, max: 5.0},
        'Базофилы': {min: 0.0, max: 1.0},
        'Моноциты': {min: 1.0, max: 4.0},
        'Лимфоциты': {min: 17.0, max: 37.0},
        'Плазматические клетки': {min: 0.0, max: 0.5},
        'СОЭ': {min: 1.0, max: 15.0}
    };

    const tupRanges = {
        'Вазни хос': {min: 1003, max: 1020, type: 'range'},
        'рН': {min: 4.5, max: 8.0, type: 'range'},
        'Кетон': {min: 0.0, max: 17.0, type: 'range'},
        'Лейкоситҳо': {min: 0, max: 6, type: 'range'},
        'Эритроситҳо': {min: 0, max: 2, type: 'range'},
        'Канд (глюкоза)': {type: 'int01'},
        'Сафеда': {type: 'int01'},
        'Бактерияҳо': {type: 'int01'},
        'Эпителияи гурдагӣ': {type: 'int01'},
        'Сангчаҳо': {type: 'int01'}
    };

    // Функция для перегенерации значений определенного типа
    function regenerateType(typeCode) {
        const tables = document.querySelectorAll('.our-result-table[data-type="' + typeCode + '"]');
        
        if (tables.length === 0) return;
        
        tables.forEach(function(table) {
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(function(tr) {
                const nameCell = tr.querySelector('.our-indicator-name');
                const resultInput = tr.querySelector('.our-result-input');

                if (!nameCell || !resultInput) return;

                const name = nameCell.textContent.trim();
                let range = {};
                
                // Выбираем правильный набор диапазонов
                switch(typeCode) {
                    case 'ba':
                        range = baRanges[name] || {min: 0.0, max: 0.0};
                        break;
                    case 'tuh':
                        range = tuhRanges[name] || {min: 0.0, max: 0.0};
                        break;
                    case 'tup':
                        range = tupRanges[name] || {type: 'range', min: 0.0, max: 0.0};
                        break;
                }

                let value = 0.0;
                
                if (typeCode === 'tup' && range.type === 'int01') {
                    // Для ТУП показателей типа "есть/нет" - 0 или 1
                    value = Math.random() < 0.5 ? 0 : 1;
                } else {
                    const min = range.min || 0;
                    const max = range.max || 0;
                    if (min === max) {
                        value = min;
                    } else {
                        value = Math.random() * (max - min) + min;
                    }
                }

                resultInput.value = value.toFixed(2);
            });
        });
    }

    // Функция для перегенерации всех типов
    function regenerateAll() {
        ['ba', 'tuh', 'tup'].forEach(function(type) {
            regenerateType(type);
        });
    }

    // Назначение обработчиков кнопок
    document.getElementById('btn-regenerate-ba')?.addEventListener('click', function() {
        regenerateType('ba');
    });
    
    document.getElementById('btn-regenerate-tuh')?.addEventListener('click', function() {
        regenerateType('tuh');
    });
    
    document.getElementById('btn-regenerate-tup')?.addEventListener('click', function() {
        regenerateType('tup');
    });
    
    document.getElementById('btn-regenerate-all')?.addEventListener('click', function() {
        regenerateAll();
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';