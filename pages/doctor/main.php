<?php
// pages/doctor/main.php
// Главная страница врача

require_once __DIR__ . '/../../includes/functions.php';
require_auth();

$pageTitle   = 'Главная врача';
$doctorName  = current_user_name();
$doctorRole  = current_user_role();

// ----- ТЕКУЩИЙ ПАЦИЕНТ ИЗ СЕССИИ -----
$currentPatientLabel = '';

if (isset($_SESSION['current_patient_id'])) {
    $pid = (int)$_SESSION['current_patient_id'];

    $stmt = $pdo->prepare("
        SELECT first_name, last_name, sex
        FROM patients
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $pid]);
    $p = $stmt->fetch();

    if ($p) {
        $sex      = $p['sex'] ?? '';
        $sexLabel = ($sex === 'M') ? 'Муж' : (($sex === 'F') ? 'Жен' : '');
        $label    = trim($p['last_name'] . ' ' . $p['first_name']);
        if ($sexLabel) {
            $label .= ' (' . $sexLabel . ')';
        }
        $currentPatientLabel = $label;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/lab-system/public/css/doctor.css">

<div class="container doctor-main">
    <div class="row mb-3">
        <div class="col-12 col-lg-8">
            <h1 class="h4 mb-1">Панель врача</h1>
            <p class="doctor-subtitle mb-0">
                Вы вошли как:
                <strong><?php echo htmlspecialchars($doctorName); ?></strong>
                (<?php echo $doctorRole === 'admin' ? 'Главврач' : 'Врач'; ?>)
            </p>
            <p class="doctor-subtitle mt-1 mb-1">
                Выберите пациента и нужный анализ, чтобы сформировать отчёт и чек.
            </p>

            <?php if ($currentPatientLabel): ?>
                <p class="doctor-subtitle mt-1">
                    Текущий пациент:
                    <span class="badge bg-info text-dark">
                        <?php echo htmlspecialchars($currentPatientLabel); ?>
                    </span>
                </p>
            <?php else: ?>
                <p class="doctor-subtitle mt-1 text-muted">
                    Текущий пациент не выбран.
                </p>
            <?php endif; ?>
        </div>

        <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap gap-2 justify-content-lg-end">
            <!-- Отдельно регистрация пациента -->
            <a href="/lab-system/index.php?page=patient_register"
               class="btn btn-success btn-sm doctor-patient-btn me-2 position-relative"
               title="Регистрация нового пациента"
               aria-label="Регистрация нового пациента"
               style="background: linear-gradient(180deg,#28a745 0%,#1e7e34 100%); box-shadow: 0 6px 18px rgba(30,126,52,0.18); border: 1px solid rgba(0,0,0,0.06); color:#fff; transition: transform .12s ease, box-shadow .12s ease;"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 24px rgba(30,126,52,0.22)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 18px rgba(30,126,52,0.18)';">
                <span style="display:inline-flex;align-items:center;gap:.5rem;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M8 1v14M1 8h14" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <strong style="line-height:1">Регистрация</strong>
                    <small class="d-none d-md-inline" style="opacity:.92"> пациента</small>
                </span>
                <span style="position:absolute;top:-6px;right:-6px;background:#ffc107;color:#000;padding:.18rem .45rem;border-radius:999px;font-size:11px;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,.12);">NEW</span>
            </a>

            <!-- Отдельно вход / выбор уже зарегистрированного пациента -->
            <a href="/lab-system/index.php?page=patient_select"
               class="btn btn-primary btn-sm doctor-patient-btn me-2 position-relative"
               title="Выбор зарегистрированного пациента"
               aria-label="Выбор зарегистрированного пациента"
               style="background: linear-gradient(180deg,#007bff 0%,#0056b3 100%); box-shadow: 0 6px 18px rgba(0,86,179,0.18); border: 1px solid rgba(0,0,0,0.06); color:#fff; transition: transform .12s ease, box-shadow .12s ease;"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 24px rgba(0,86,179,0.22)';"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 18px rgba(0,86,179,0.18)';">
                <span style="display:inline-flex;align-items:center;gap:.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 20c0-3.31 3.59-6 8-6s8 2.69 8 6" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <strong style="line-height:1">Вход / выбор</strong>
                    <small class="d-none d-md-inline" style="opacity:.92"> пациента</small>
                </span>
                <span style="position:absolute;top:-6px;right:-6px;background:#17a2b8;color:#fff;padding:.18rem .45rem;border-radius:999px;font-size:11px;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,.12);">OK</span>
            </a>
        </div>
    </div>

    <!-- Блок выбора анализа -->
    <div class="row g-3 dashboard-tiles">
        <div class="col-12 col-md-6 col-lg-3">
            <a href="/lab-system/index.php?page=ba" class="dashboard-tile">
                <div class="dashboard-tile-type">БА</div>
                <div class="dashboard-tile-title">Биохимический анализ</div>
                <div class="dashboard-tile-desc">
                    Выбор показателей, авто-значения, чек и экспорт в Excel.
                </div>
            </a>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <a href="/lab-system/index.php?page=tuh" class="dashboard-tile">
                <div class="dashboard-tile-type">ТУХ</div>
                <div class="dashboard-tile-title">Общий анализ крови</div>
                <div class="dashboard-tile-desc">
                    Гемоглобин, эритроциты, лейкоциты и другие показатели крови.
                </div>
            </a>
        </div>


                <div class="col-12 col-md-6 col-lg-3">
            <a href="/lab-system/index.php?page=tup" class="dashboard-tile">
                <div class="dashboard-tile-type">ТУП</div>
                <div class="dashboard-tile-title">Общий анализ мочи</div>
                <div class="dashboard-tile-desc">
                    Основные показатели мочи с нормами и результатами.
                </div>
            </a>
        </div>


        <div class="col-12 col-md-6 col-lg-3">
            <a href="/lab-system/index.php?page=ifa" class="dashboard-tile">
                <div class="dashboard-tile-type">ИФА</div>
                <div class="dashboard-tile-title">ИФА</div>
                <div class="dashboard-tile-desc">
                    Показатели по антителам и биомаркерам для диагностики.
                </div>
            </a>
        </div>
    </div>

    <!-- Блок быстрых действий -->
    <div class="row g-3 mt-4">
        <div class="col-12 col-lg-6">
            <div class="doctor-panel">
                <h2 class="doctor-panel-title">Текущие задачи врача</h2>
                <ul class="doctor-panel-list">
                    <li>🧪 Сначала зарегистрируйте или выберите пациента.</li>
                    <li>📄 После сохранения анализа — распечатайте чек и отчёт.</li>
                    <li>📊 Главврач может просмотреть все отчёты и суммы по дням.</li>
                </ul>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="doctor-panel">
                <h2 class="doctor-panel-title">Дальше мы сделаем</h2>
                <ul class="doctor-panel-list">
                    <li>✔ Полную реализацию ТУХ, ТУП и ИФА.</li>
                    <li>✔ Фильтры по пациентам, врачам и датам в админ-панели.</li>
                    <li>✔ Дополнительные отчёты по доходам и количеству анализов.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
