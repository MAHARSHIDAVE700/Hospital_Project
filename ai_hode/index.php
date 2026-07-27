<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Path: ai_hode/index.php
 * 
 * Master AI Operational Control Hub Dashboard
 */

require_once __DIR__ . '/../includes/config.php';

// Quick metrics telemetry summary queries
$patientFlowCount = $conn->query("SELECT COUNT(*) AS total FROM patient_flow")->fetch_assoc()['total'] ?? 0;
$queueEventsCount = $conn->query("SELECT COUNT(*) AS total FROM queue_events")->fetch_assoc()['total'] ?? 0;
$predictionsCount = $conn->query("SELECT COUNT(*) AS total FROM predictions")->fetch_assoc()['total'] ?? 0;
$pendingRecsCount = $conn->query("SELECT COUNT(*) AS total FROM recommendations WHERE status = 'PENDING'")->fetch_assoc()['total'] ?? 0;
$decisionLogsCount = $conn->query("SELECT COUNT(*) AS total FROM decision_logs")->fetch_assoc()['total'] ?? 0;
$activeModelsCount = $conn->query("SELECT COUNT(*) AS total FROM ai_models WHERE status = 'ACTIVE'")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Hospital Operational Decision Engine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0b132b; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-module { background-color: #1c2541; border: 1px solid #3a506b; border-radius: 16px; transition: transform 0.2s, box-shadow 0.2s; }
        .card-module:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.4); border-color: #5bc0be; }
        .icon-box { width: 54px; height: 54px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom border-secondary">
            <div>
                <h1 class="fw-bold text-gradient mb-1" style="color: #6fffe9;"><i class="bi bi-cpu-fill me-2"></i>AI-HODE Control Hub</h1>
                <p class="text-secondary mb-0 fs-5">AI Hospital Operational Decision Engine - Operational Intelligence Layer</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../admin/dashboard.php" class="btn btn-outline-info"><i class="bi bi-speedometer2 me-1"></i> HMS Admin Panel</a>
            </div>
        </div>

        <!-- 8 AI-HODE Modules Navigation Grid -->
        <div class="row g-4 mb-5">

            <!-- Module 1: Patient Flow -->
            <div class="col-md-6 col-lg-3">
                <a href="patient_flow/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-primary bg-opacity-25 text-primary"><i class="bi bi-diagram-3-fill"></i></div>
                            <span class="badge bg-primary fs-6"><?= $patientFlowCount ?> Flows</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">1. Patient Flow</h4>
                        <p class="text-secondary small mb-0">Operational telemetry, dwell times & bottleneck SLA tracking</p>
                    </div>
                </a>
            </div>

            <!-- Module 2: Queue Events -->
            <div class="col-md-6 col-lg-3">
                <a href="queue_events/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-info bg-opacity-25 text-info"><i class="bi bi-person-lines-fill"></i></div>
                            <span class="badge bg-info text-dark fs-6"><?= $queueEventsCount ?> Events</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">2. Queue Events</h4>
                        <p class="text-secondary small mb-0">Token movement, calling desk telemetry & wait time delay logs</p>
                    </div>
                </a>
            </div>

            <!-- Module 3: Doctor Workload -->
            <div class="col-md-6 col-lg-3">
                <a href="doctor_workload/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-warning bg-opacity-25 text-warning"><i class="bi bi-person-badge-fill"></i></div>
                            <span class="badge bg-warning text-dark fs-6">Capacity Load</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">3. Doctor Workload</h4>
                        <p class="text-secondary small mb-0">Capacity load balancing, consultation speed & burnout index</p>
                    </div>
                </a>
            </div>

            <!-- Module 4: Predictions -->
            <div class="col-md-6 col-lg-3">
                <a href="predictions/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-success bg-opacity-25 text-success"><i class="bi bi-magic"></i></div>
                            <span class="badge bg-success fs-6"><?= $predictionsCount ?> Inferences</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">4. Predictions API</h4>
                        <p class="text-secondary small mb-0">FastAPI ML regressions: Wait times, arrival surge & queue forecast</p>
                    </div>
                </a>
            </div>

            <!-- Module 5: Recommendations -->
            <div class="col-md-6 col-lg-3">
                <a href="recommendations/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-danger bg-opacity-25 text-danger"><i class="bi bi-lightbulb-fill"></i></div>
                            <span class="badge bg-danger fs-6"><?= $pendingRecsCount ?> Pending</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">5. Recommendations</h4>
                        <p class="text-secondary small mb-0">Automated decision engine: Staff dispatch, queue rerouting & triage</p>
                    </div>
                </a>
            </div>

            <!-- Module 6: Decision Logs -->
            <div class="col-md-6 col-lg-3">
                <a href="decision_logs/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-primary bg-opacity-25 text-info"><i class="bi bi-journal-check"></i></div>
                            <span class="badge bg-secondary fs-6"><?= $decisionLogsCount ?> Audit Logs</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">6. Decision Logs</h4>
                        <p class="text-secondary small mb-0">Governance audit trail of administrator accepted & rejected AI decisions</p>
                    </div>
                </a>
            </div>

            <!-- Module 7: AI Models -->
            <div class="col-md-6 col-lg-3">
                <a href="ai_models/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-purple bg-opacity-25 text-purple" style="color: #a855f7;"><i class="bi bi-box-seam-fill"></i></div>
                            <span class="badge bg-purple text-white fs-6" style="background-color: #9333ea;"><?= $activeModelsCount ?> Active</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">7. AI Model Registry</h4>
                        <p class="text-secondary small mb-0">Model versioning, hyperparameters & active deployment status</p>
                    </div>
                </a>
            </div>

            <!-- Module 8: Model Metrics -->
            <div class="col-md-6 col-lg-3">
                <a href="model_metrics/index.php" class="text-decoration-none">
                    <div class="card card-module p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="icon-box bg-teal bg-opacity-25 text-teal" style="color: #14b8a6;"><i class="bi bi-graph-up-arrow"></i></div>
                            <span class="badge bg-teal text-white fs-6" style="background-color: #0d9488;">Telemetry</span>
                        </div>
                        <h4 class="fw-bold text-white mb-2">8. Model Metrics</h4>
                        <p class="text-secondary small mb-0">Evaluation metrics: MAE, RMSE, R² accuracy & inference latency</p>
                    </div>
                </a>
            </div>

        </div>
    </div>
</body>
</html>
