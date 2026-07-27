<?php
/**
 * Admin Panel - Integrated AI Intelligence Module (AI-HODE)
 * Path: admin/ai_intelligence.php
 */

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// Include AI-HODE Core Engines
require_once __DIR__ . '/../ai_hode/patient_flow/patient_flow_engine.php';
require_once __DIR__ . '/../ai_hode/queue_events/queue_event_tracker.php';
require_once __DIR__ . '/../ai_hode/doctor_workload/workload_balancer.php';
require_once __DIR__ . '/../ai_hode/predictions/predictive_analytics.php';
require_once __DIR__ . '/../ai_hode/predictions/prediction_logger.php';
require_once __DIR__ . '/../ai_hode/recommendations/recommendation_engine.php';
require_once __DIR__ . '/../ai_hode/decision_logs/decision_logger.php';
require_once __DIR__ . '/../ai_hode/ai_models/model_registry.php';
require_once __DIR__ . '/../ai_hode/model_metrics/metrics_collector.php';

// Determine active tab/sub-module
$activeTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'dashboard';

// Handle POST actions within the Admin Panel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_flow_stage') {
        $flowId = intval($_POST['flow_id'] ?? 0);
        $newStage = trim($_POST['new_stage'] ?? '');
        $reason = !empty($_POST['delay_reason']) ? trim($_POST['delay_reason']) : null;
        $isBottleneck = isset($_POST['is_bottleneck']) && $_POST['is_bottleneck'] == '1';

        if ($flowId && $newStage) {
            PatientFlowEngine::updateStage($conn, $flowId, $newStage, $reason, $isBottleneck);
        }
        header("Location: ai_intelligence.php?tab=patient_flow&msg=stage_updated");
        exit();
    }

    if ($action === 'call_queue_token') {
        $flowId = intval($_POST['flow_id'] ?? 0);
        $token = trim($_POST['token'] ?? '');
        if ($flowId && $token) {
            PatientFlowEngine::updateStage($conn, $flowId, 'IN_CONSULTATION');
            $issuedEvt = $conn->query("SELECT event_timestamp FROM queue_events WHERE flow_id = {$flowId} AND event_type = 'ISSUED' ORDER BY event_id ASC LIMIT 1");
            $actualWaitSec = 0;
            if ($issuedEvt && $iRow = $issuedEvt->fetch_assoc()) {
                $actualWaitSec = time() - strtotime($iRow['event_timestamp']);
            }
            QueueEventTracker::recordEvent($conn, $flowId, $token, 'CALLED', null, $actualWaitSec);
        }
        header("Location: ai_intelligence.php?tab=queue_events&msg=token_called");
        exit();
    }

    if ($action === 'complete_queue_token') {
        $flowId = intval($_POST['flow_id'] ?? 0);
        $token = trim($_POST['token'] ?? '');
        if ($flowId && $token) {
            PatientFlowEngine::updateStage($conn, $flowId, 'DISCHARGED');
            QueueEventTracker::recordEvent($conn, $flowId, $token, 'COMPLETED');
        }
        header("Location: ai_intelligence.php?tab=queue_events&msg=token_completed");
        exit();
    }

    if ($action === 'log_decision_action') {
        $recId = intval($_POST['recommendation_id'] ?? 0);
        $actionTaken = trim($_POST['action_taken'] ?? 'ACCEPTED');
        $notes = trim($_POST['outcome_notes'] ?? '');
        $userId = $_SESSION['admin_id'] ?? null;

        if ($recId) {
            DecisionLogger::logDecision($conn, $recId, $actionTaken, $userId, $notes, 'MANUAL');
        }
        header("Location: ai_intelligence.php?tab=decision_logs&msg=decision_logged");
        exit();
    }

    if ($action === 'trigger_rule_engine') {
        RecommendationEngine::evaluateRulesAndGenerate($conn);
        header("Location: ai_intelligence.php?tab=recommendations&msg=rules_executed");
        exit();
    }
}

// Fetch live counter summary data for dashboard top pills
$patientFlowCount = $conn->query("SELECT COUNT(*) AS total FROM patient_flow")->fetch_assoc()['total'] ?? 0;
$queueEventsCount = $conn->query("SELECT COUNT(*) AS total FROM queue_events")->fetch_assoc()['total'] ?? 0;
$predictionsCount = $conn->query("SELECT COUNT(*) AS total FROM predictions")->fetch_assoc()['total'] ?? 0;
$pendingRecsCount = $conn->query("SELECT COUNT(*) AS total FROM recommendations WHERE status = 'PENDING'")->fetch_assoc()['total'] ?? 0;
$decisionLogsCount = $conn->query("SELECT COUNT(*) AS total FROM decision_logs")->fetch_assoc()['total'] ?? 0;
$activeModelsCount = $conn->query("SELECT COUNT(*) AS total FROM ai_models WHERE status = 'ACTIVE'")->fetch_assoc()['total'] ?? 0;

// Tab Label Mapper for Breadcrumb
$tabTitles = [
    'dashboard' => 'AI Intelligence Dashboard',
    'patient_flow' => 'Patient Flow Telemetry',
    'queue_events' => 'Live Queue Analytics',
    'doctor_workload' => 'Doctor Workload & Burnout Risk',
    'predictions' => 'AI Predictions & Inference',
    'recommendations' => 'Operational Recommendations',
    'decision_logs' => 'Decision Audit Logs',
    'ai_models' => 'AI Model Registry',
    'model_metrics' => 'Model Performance Metrics'
];
$currentTitle = $tabTitles[$activeTab] ?? 'AI Intelligence';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentTitle ?> | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .ai-card { transition: all 0.25s ease; border: 1px solid rgba(0,0,0,0.08); border-radius: 12px; }
        .ai-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); border-color: var(--primary-color, #0d6efd); }
        .ai-icon-wrapper { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .stage-badge { font-size: 0.8rem; font-weight: 600; padding: 4px 10px; border-radius: 6px; }
        .nav-pills-custom .nav-link { color: #475569; font-weight: 500; border-radius: 8px; padding: 8px 16px; margin-right: 4px; }
        .nav-pills-custom .nav-link.active { background-color: var(--primary-color, #0d6efd); color: white; }
    </style>
</head>
<body class="bg-light">

<div class="hms-layout">
    <!-- Sidebar -->
    <aside class="hms-sidebar" id="sidebar">
        <div class="hms-sidebar-brand">
            <span>🏥</span>
            <strong>Narayan Clinic</strong>
        </div>
        <div class="hms-sidebar-menu">
            <div class="hms-sidebar-group-title">Main Dashboard</div>
            <a href="dashboard.php" class="hms-sidebar-item">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            
            <div class="hms-sidebar-group-title">Operations</div>
            <a href="manage_doctors.php" class="hms-sidebar-item">
                <i class="bi bi-person-badge"></i> Doctors
            </a>
            <a href="manage_patients.php" class="hms-sidebar-item">
                <i class="bi bi-people"></i> Patients
            </a>
            <a href="manage_departments.php" class="hms-sidebar-item">
                <i class="bi bi-hospital"></i> Departments
            </a>
            <a href="manage_appointments.php" class="hms-sidebar-item">
                <i class="bi bi-calendar2-check"></i> Appointments
            </a>
            <a href="manage_beds.php" class="hms-sidebar-item">
                <i class="bi bi-bed"></i> Bed Management
            </a>
            <a href="manage_ipd.php" class="hms-sidebar-item">
                <i class="bi bi-person-workspace"></i> IPD Admissions
            </a>
            <a href="manage_laboratory.php" class="hms-sidebar-item">
                <i class="bi bi-virus2"></i> Laboratory
            </a>
            <a href="manage_pharmacy.php" class="hms-sidebar-item">
                <i class="bi bi-prescription"></i> Pharmacy
            </a>
            <a href="manage_billing.php" class="hms-sidebar-item">
                <i class="bi bi-wallet2"></i> Billing Center
            </a>

            <div class="hms-sidebar-group-title">Analytics &amp; AI</div>
            <a href="analytics.php" class="hms-sidebar-item">
                <i class="bi bi-bar-chart-line"></i> Analytics
            </a>
            <a href="ai_intelligence.php" class="hms-sidebar-item active">
                <i class="bi bi-cpu-fill text-info"></i> 🤖 AI Intelligence
            </a>
            <a href="view_logs.php" class="hms-sidebar-item">
                <i class="bi bi-shield-check"></i> Audit Logs
            </a>
        </div>
        <div class="hms-sidebar-footer">
            <a href="../logout.php" class="hms-sidebar-item text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="hms-main" id="main-content">
        <!-- Topbar Header -->
        <header class="hms-topbar">
            <div class="hms-topbar-left">
                <button class="hms-sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <div class="hms-breadcrumb">
                    <span>Narayan Administration</span>
                    <span><i class="bi bi-chevron-right text-muted fs-8"></i></span>
                    <span>AI Intelligence</span>
                    <span><i class="bi bi-chevron-right text-muted fs-8"></i></span>
                    <span class="hms-breadcrumb-item-active"><?= htmlspecialchars($currentTitle) ?></span>
                </div>
            </div>
            <div class="hms-topbar-right">
                <div class="live-clock-widget d-none d-lg-flex me-3">
                    <i class="bi bi-clock"></i>
                    <span><?= date('D, M d, Y · h:i A') ?></span>
                </div>
                <div class="hms-topbar-profile">
                    <div class="avatar-circle">
                        <?php 
                            $nameParts = explode(' ', $_SESSION['admin_name'] ?? 'Admin');
                            $initials = '';
                            foreach($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div class="d-none d-md-block">
                        <strong class="d-block text-dark small"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></strong>
                        <span class="text-secondary small" style="font-size: 11px;">OPD Controller</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Body Content Area -->
        <div class="hms-content">

            <!-- Sub-Navigation Bar for AI Modules -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between">
                    <ul class="nav nav-pills nav-pills-custom">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="ai_intelligence.php?tab=dashboard">
                                <i class="bi bi-grid-fill me-1"></i> AI Hub
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'patient_flow' ? 'active' : '' ?>" href="ai_intelligence.php?tab=patient_flow">
                                <i class="bi bi-diagram-3 me-1"></i> Patient Flow
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'queue_events' ? 'active' : '' ?>" href="ai_intelligence.php?tab=queue_events">
                                <i class="bi bi-person-lines-fill me-1"></i> Queue Telemetry
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'doctor_workload' ? 'active' : '' ?>" href="ai_intelligence.php?tab=doctor_workload">
                                <i class="bi bi-person-badge me-1"></i> Doctor Workload
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'predictions' ? 'active' : '' ?>" href="ai_intelligence.php?tab=predictions">
                                <i class="bi bi-cpu me-1"></i> Predictions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'recommendations' ? 'active' : '' ?>" href="ai_intelligence.php?tab=recommendations">
                                <i class="bi bi-lightbulb me-1"></i> Recommendations <?= $pendingRecsCount > 0 ? "<span class='badge bg-danger rounded-pill'>{$pendingRecsCount}</span>" : '' ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'decision_logs' ? 'active' : '' ?>" href="ai_intelligence.php?tab=decision_logs">
                                <i class="bi bi-journal-check me-1"></i> Decision Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'ai_models' ? 'active' : '' ?>" href="ai_intelligence.php?tab=ai_models">
                                <i class="bi bi-box-seam me-1"></i> Model Registry
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'model_metrics' ? 'active' : '' ?>" href="ai_intelligence.php?tab=model_metrics">
                                <i class="bi bi-graph-up-arrow me-1"></i> Metrics
                            </a>
                        </li>
                    </ul>
                    <div class="d-none d-xl-block">
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                            <i class="bi bi-lightning-charge-fill me-1"></i> AI Engine Active
                        </span>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> Operation executed successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- TAB 1: AI DASHBOARD (HOME) -->
            <?php if ($activeTab === 'dashboard'): ?>
                <div class="row g-4 mb-4">
                    <!-- Card 1: Patient Flow -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=patient_flow" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-primary-subtle text-primary"><i class="bi bi-diagram-3-fill"></i></div>
                                    <span class="badge bg-primary fs-6"><?= $patientFlowCount ?> Flows</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">1. Patient Flow</h5>
                                <p class="text-muted small mb-0">Track check-in, triage, dwell times, and bottleneck SLA breaches.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 2: Queue Analytics -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=queue_events" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-info-subtle text-info"><i class="bi bi-person-lines-fill"></i></div>
                                    <span class="badge bg-info text-dark fs-6"><?= $queueEventsCount ?> Events</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">2. Queue Analytics</h5>
                                <p class="text-muted small mb-0">Live token movement (OPD-xxx), calling desk, and delay tracking.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 3: Doctor Workload -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=doctor_workload" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-warning-subtle text-warning"><i class="bi bi-person-badge-fill"></i></div>
                                    <span class="badge bg-warning text-dark fs-6">Capacity Score</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">3. Doctor Workload</h5>
                                <p class="text-muted small mb-0">Monitor doctor active patient load, consultation speeds & burnout risk.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 4: AI Predictions -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=predictions" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-success-subtle text-success"><i class="bi bi-cpu-fill"></i></div>
                                    <span class="badge bg-success fs-6"><?= $predictionsCount ?> Inferences</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">4. AI Predictions</h5>
                                <p class="text-muted small mb-0">FastAPI XGBoost predictions for wait time, arrival surge & queue length.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 5: AI Recommendations -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=recommendations" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-danger-subtle text-danger"><i class="bi bi-lightbulb-fill"></i></div>
                                    <span class="badge bg-danger fs-6"><?= $pendingRecsCount ?> Pending</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">5. Recommendations</h5>
                                <p class="text-muted small mb-0">Automated decision engine: Staff dispatch, queue rerouting & triage.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 6: Decision Logs -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=decision_logs" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-secondary-subtle text-secondary"><i class="bi bi-journal-check"></i></div>
                                    <span class="badge bg-secondary fs-6"><?= $decisionLogsCount ?> Audits</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">6. Decision Logs</h5>
                                <p class="text-muted small mb-0">Governance audit trail of administrator accepted & rejected AI actions.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 7: AI Model Registry -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=ai_models" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-purple-subtle text-purple" style="color: #6f42c1;"><i class="bi bi-box-seam-fill"></i></div>
                                    <span class="badge text-white fs-6" style="background-color: #6f42c1;"><?= $activeModelsCount ?> Active</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">7. AI Model Registry</h5>
                                <p class="text-muted small mb-0">Model versioning, deployment status, and hyperparameter configuration.</p>
                            </div>
                        </a>
                    </div>

                    <!-- Card 8: Model Metrics -->
                    <div class="col-md-6 col-xl-3">
                        <a href="ai_intelligence.php?tab=model_metrics" class="text-decoration-none">
                            <div class="card ai-card h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="ai-icon-wrapper bg-teal-subtle text-teal" style="color: #20c997;"><i class="bi bi-graph-up-arrow"></i></div>
                                    <span class="badge text-white fs-6" style="background-color: #20c997;">Telemetry</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">8. Model Metrics</h5>
                                <p class="text-muted small mb-0">Model performance tracking: MAE, RMSE, R² accuracy score & latency.</p>
                            </div>
                        </a>
                    </div>
                </div>

            <!-- TAB 2: PATIENT FLOW -->
            <?php elseif ($activeTab === 'patient_flow'): ?>
                <?php
                    $flows = $conn->query("
                        SELECT pf.*, p.full_name AS patient_name, d.department_name, doc.full_name AS doctor_name
                        FROM patient_flow pf
                        LEFT JOIN patients p ON pf.patient_id = p.patient_id
                        LEFT JOIN departments d ON pf.department_id = d.department_id
                        LEFT JOIN doctors doc ON pf.assigned_doctor_id = doc.doctor_id
                        ORDER BY pf.flow_id DESC LIMIT 40
                    ");
                ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Patient Flow Telemetry</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Flow ID</th>
                                    <th>Patient</th>
                                    <th>Department</th>
                                    <th>Doctor</th>
                                    <th>Stage</th>
                                    <th>Triage</th>
                                    <th>Entry Time</th>
                                    <th>Dwell Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($flows && $flows->num_rows > 0): ?>
                                    <?php while ($f = $flows->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong>#<?= $f['flow_id'] ?></strong></td>
                                            <td><?= htmlspecialchars($f['patient_name'] ?? 'Patient #' . $f['patient_id']) ?></td>
                                            <td><?= htmlspecialchars($f['department_name'] ?? 'General OPD') ?></td>
                                            <td><?= htmlspecialchars($f['doctor_name'] ?? 'Unassigned') ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= str_replace('_', ' ', $f['current_stage']) ?></span></td>
                                            <td><span class="badge bg-secondary"><?= $f['triage_priority'] ?></span></td>
                                            <td><small class="text-muted"><?= date('H:i:s', strtotime($f['stage_entry_time'])) ?></small></td>
                                            <td><?= $f['dwell_time_seconds'] ? round($f['dwell_time_seconds'] / 60, 1) . 'm' : '<span class="text-warning">In Progress</span>' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="openStageModal(<?= $f['flow_id'] ?>)">Update Stage</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">No active patient flow telemetry recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB 3: QUEUE EVENTS -->
            <?php elseif ($activeTab === 'queue_events'): ?>
                <?php
                    $events = $conn->query("
                        SELECT qe.*, pf.current_stage, p.full_name AS patient_name, d.department_name, doc.full_name AS doctor_name
                        FROM queue_events qe
                        JOIN patient_flow pf ON qe.flow_id = pf.flow_id
                        LEFT JOIN patients p ON pf.patient_id = p.patient_id
                        LEFT JOIN departments d ON pf.department_id = d.department_id
                        LEFT JOIN doctors doc ON pf.assigned_doctor_id = doc.doctor_id
                        ORDER BY qe.event_id DESC LIMIT 40
                    ");
                ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-lines-fill me-2 text-info"></i>Live Queue Analytics &amp; Calling Desk</h5>
                        <button class="btn btn-sm btn-outline-info" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh Queue</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Event ID</th>
                                    <th>Token #</th>
                                    <th>Patient</th>
                                    <th>Department</th>
                                    <th>Doctor</th>
                                    <th>Status Event</th>
                                    <th>Est. Wait</th>
                                    <th>Actual Wait</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($events && $events->num_rows > 0): ?>
                                    <?php while ($e = $events->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $e['event_id'] ?></td>
                                            <td><strong class="font-monospace text-primary fs-5"><?= htmlspecialchars($e['queue_token']) ?></strong></td>
                                            <td><?= htmlspecialchars($e['patient_name'] ?? 'Patient Flow #' . $e['flow_id']) ?></td>
                                            <td><?= htmlspecialchars($e['department_name'] ?? 'OPD') ?></td>
                                            <td><?= htmlspecialchars($e['doctor_name'] ?? 'Unassigned') ?></td>
                                            <td><span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1"><?= $e['event_type'] ?></span></td>
                                            <td><?= $e['estimated_wait_seconds'] ? round($e['estimated_wait_seconds'] / 60, 1) . 'm' : '-' ?></td>
                                            <td><?= $e['actual_wait_seconds'] ? round($e['actual_wait_seconds'] / 60, 1) . 'm' : '-' ?></td>
                                            <td>
                                                <?php if ($e['event_type'] === 'ISSUED'): ?>
                                                    <form method="POST" action="ai_intelligence.php?tab=queue_events" class="d-inline">
                                                        <input type="hidden" name="action" value="call_queue_token">
                                                        <input type="hidden" name="flow_id" value="<?= $e['flow_id'] ?>">
                                                        <input type="hidden" name="token" value="<?= $e['queue_token'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning font-weight-bold"><i class="bi bi-telephone-out"></i> Call Token</button>
                                                    </form>
                                                <?php elseif ($e['event_type'] === 'CALLED'): ?>
                                                    <form method="POST" action="ai_intelligence.php?tab=queue_events" class="d-inline">
                                                        <input type="hidden" name="action" value="complete_queue_token">
                                                        <input type="hidden" name="flow_id" value="<?= $e['flow_id'] ?>">
                                                        <input type="hidden" name="token" value="<?= $e['queue_token'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success fw-bold"><i class="bi bi-check-circle"></i> Complete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-check2-all"></i> Done</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">No active queue events. Book an appointment to generate tokens.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB 4: DOCTOR WORKLOAD -->
            <?php elseif ($activeTab === 'doctor_workload'): ?>
                <?php
                    // Recalculate doctor workloads dynamically
                    WorkloadBalancer::batchRecalculateAllWorkloads($conn);
                    $workloadData = WorkloadBalancer::getLatestWorkloadSummary($conn);
                ?>
                <div class="row g-4">
                    <?php foreach ($workloadData as $w): ?>
                        <?php $score = floatval($w['workload_score']); ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card border-0 shadow-sm h-100 p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($w['doctor_name']) ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($w['department_name'] ?? 'General Medicine') ?></small>
                                    </div>
                                    <span class="badge bg-<?= $w['burnout_risk_level'] === 'CRITICAL' ? 'danger' : ($w['burnout_risk_level'] === 'HIGH' ? 'warning' : 'success') ?> px-3 py-2">
                                        Risk: <?= $w['burnout_risk_level'] ?>
                                    </span>
                                </div>
                                <div class="my-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted">Capacity Load Score</span>
                                        <strong class="text-dark"><?= number_format($score, 1) ?>%</strong>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $score >= 80 ? 'danger' : ($score >= 50 ? 'warning' : 'success') ?>" role="progressbar" style="width: <?= $score ?>%"></div>
                                    </div>
                                </div>
                                <div class="row text-center pt-2 border-top">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary fs-5"><?= $w['active_patients'] ?></div>
                                        <small class="text-muted" style="font-size: 11px;">Active</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success fs-5"><?= $w['consultations_completed_today'] ?></div>
                                        <small class="text-muted" style="font-size: 11px;">Done Today</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-warning fs-5"><?= round($w['avg_consultation_time_seconds'] / 60, 1) ?>m</div>
                                        <small class="text-muted" style="font-size: 11px;">Avg Speed</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <!-- TAB 5: PREDICTIONS -->
            <?php elseif ($activeTab === 'predictions'): ?>
                <?php $predictions = PredictionLogger::getLatestPredictions($conn, 40); ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-cpu me-2 text-success"></i>AI Model Predictions Telemetry</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pred ID</th>
                                    <th>Target Category</th>
                                    <th>Entity</th>
                                    <th>Predicted Output</th>
                                    <th>Confidence Bounds</th>
                                    <th>Model Version</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictions as $p): ?>
                                    <tr>
                                        <td>#<?= $p['prediction_id'] ?></td>
                                        <td><span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1"><?= $p['target_type'] ?></span></td>
                                        <td><?= htmlspecialchars($p['target_entity_id'] ?? 'System') ?></td>
                                        <td><strong class="text-dark fs-5"><?= number_format($p['predicted_value'], 2) ?></strong></td>
                                        <td><small class="text-muted">[<?= number_format($p['confidence_lower'] ?? 0, 2) ?> - <?= number_format($p['confidence_upper'] ?? 0, 2) ?>]</small></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($p['model_version']) ?></span></td>
                                        <td><small class="text-muted"><?= date('Y-m-d H:i:s', strtotime($p['prediction_time'])) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB 6: RECOMMENDATIONS -->
            <?php elseif ($activeTab === 'recommendations'): ?>
                <?php
                    $pendingRecs = RecommendationEngine::getRecommendations($conn, 'PENDING');
                ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-lightbulb me-2 text-danger"></i>Operational AI Recommendations</h5>
                    <form method="POST" action="ai_intelligence.php?tab=recommendations">
                        <input type="hidden" name="action" value="trigger_rule_engine">
                        <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-gear-wide-connected me-1"></i> Run Rule Engine</button>
                    </form>
                </div>
                <div class="row g-4">
                    <?php if (empty($pendingRecs)): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm p-4 text-center text-muted">
                                <i class="bi bi-check-circle fs-1 mb-2 text-success"></i>
                                No active pending recommendations. All operational parameters operating normally.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingRecs as $r): ?>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 p-4 border-start border-4 border-warning">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge bg-secondary mb-1"><?= $r['category'] ?></span>
                                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($r['title']) ?></h5>
                                        </div>
                                        <span class="badge bg-danger px-3 py-2"><?= $r['urgency_level'] ?></span>
                                    </div>
                                    <p class="text-secondary small my-3"><?= htmlspecialchars($r['action_details']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <small class="text-muted">Impact Score: <strong><?= number_format($r['impact_score'], 1) ?>%</strong></small>
                                        <form method="POST" action="ai_intelligence.php?tab=decision_logs">
                                            <input type="hidden" name="action" value="log_decision_action">
                                            <input type="hidden" name="recommendation_id" value="<?= $r['recommendation_id'] ?>">
                                            <button type="submit" name="action_taken" value="ACCEPTED" class="btn btn-sm btn-success me-1"><i class="bi bi-check-lg"></i> Accept</button>
                                            <button type="submit" name="action_taken" value="REJECTED" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <!-- TAB 7: DECISION LOGS -->
            <?php elseif ($activeTab === 'decision_logs'): ?>
                <?php $logs = DecisionLogger::getDecisionLogs($conn, 40); ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Decision Audit Logs</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Log ID</th>
                                    <th>Rec ID</th>
                                    <th>Recommendation Title</th>
                                    <th>Action Taken</th>
                                    <th>Execution Type</th>
                                    <th>Executed By</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td>#<?= $l['log_id'] ?></td>
                                        <td>#<?= $l['recommendation_id'] ?></td>
                                        <td><?= htmlspecialchars($l['rec_title'] ?? 'Operational Recommendation') ?></td>
                                        <td><span class="badge bg-<?= $l['action_taken'] === 'ACCEPTED' ? 'success' : 'danger' ?> px-3 py-1"><?= $l['action_taken'] ?></span></td>
                                        <td><small class="text-muted"><?= $l['execution_type'] ?></small></td>
                                        <td><?= htmlspecialchars($l['user_name'] ?? 'System Admin') ?></td>
                                        <td><small class="text-muted"><?= date('Y-m-d H:i:s', strtotime($l['timestamp'])) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB 8: AI MODEL REGISTRY -->
            <?php elseif ($activeTab === 'ai_models'): ?>
                <?php $models = ModelRegistry::getAllModels($conn); ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam me-2 text-purple" style="color: #6f42c1;"></i>AI Model Registry</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Model ID</th>
                                    <th>Model Name</th>
                                    <th>Algorithm</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                    <th>Last Trained</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($models as $m): ?>
                                    <tr>
                                        <td>#<?= $m['model_id'] ?></td>
                                        <td><strong class="text-dark"><?= htmlspecialchars($m['model_name']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($m['algorithm']) ?></span></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($m['version']) ?></span></td>
                                        <td><span class="badge bg-<?= $m['status'] === 'ACTIVE' ? 'success' : 'secondary' ?>"><?= $m['status'] ?></span></td>
                                        <td><small class="text-muted"><?= date('Y-m-d H:i:s', strtotime($m['last_trained_at'])) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB 9: MODEL METRICS -->
            <?php elseif ($activeTab === 'model_metrics'): ?>
                <?php $history = MetricsCollector::getEvaluationHistory($conn, 40); ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-graph-up-arrow me-2 text-teal" style="color: #20c997;"></i>Model Performance Metrics</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Metric ID</th>
                                    <th>Model Name</th>
                                    <th>MAE (Error)</th>
                                    <th>RMSE</th>
                                    <th>Accuracy Score</th>
                                    <th>Latency</th>
                                    <th>Evaluated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>#<?= $h['metric_id'] ?></td>
                                        <td><strong class="text-dark"><?= htmlspecialchars($h['model_name']) ?></strong></td>
                                        <td class="text-warning fw-bold"><?= number_format($h['mae'], 4) ?></td>
                                        <td class="text-danger fw-bold"><?= number_format($h['rmse'], 4) ?></td>
                                        <td><span class="badge bg-success"><?= number_format($h['accuracy_score'] * 100, 2) ?>%</span></td>
                                        <td><strong class="text-info"><?= $h['latency_ms'] ?> ms</strong></td>
                                        <td><small class="text-muted"><?= date('Y-m-d H:i:s', strtotime($h['evaluated_at'])) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Modal for Stage Update -->
<div class="modal fade" id="stageUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2"></i>Update Operational Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ai_intelligence.php?tab=patient_flow">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_flow_stage">
                    <input type="hidden" id="modal_flow_id" name="flow_id">
                    <div class="mb-3">
                        <label class="form-label">Next Operational Stage</label>
                        <select class="form-select" name="new_stage">
                            <option value="TRIAGE">TRIAGE</option>
                            <option value="WAITING_FOR_CONSULTATION">WAITING FOR CONSULTATION</option>
                            <option value="IN_CONSULTATION">IN CONSULTATION</option>
                            <option value="LAB_PHARMACY">LAB / PHARMACY</option>
                            <option value="BILLING">BILLING</option>
                            <option value="DISCHARGED">DISCHARGED</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delay / Bottleneck Reason (Optional)</label>
                        <input type="text" class="form-control" name="delay_reason" placeholder="e.g. Extended consultation">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_bottleneck" value="1" id="chkBottleneck">
                        <label class="form-check-label text-danger" for="chkBottleneck">Flag as Operational Bottleneck</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('main-content').classList.toggle('expanded');
    }

    function openStageModal(flowId) {
        document.getElementById('modal_flow_id').value = flowId;
        new bootstrap.Modal(document.getElementById('stageUpdateModal')).show();
    }
</script>
</body>
</html>
