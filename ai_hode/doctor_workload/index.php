<?php
/**
 * AI-HODE: Doctor Workload Telemetry & Burnout Monitoring Dashboard
 * Path: ai_hode/doctor_workload/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/workload_balancer.php';

// Handle recalculation trigger for a doctor via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recalculate') {
    header('Content-Type: application/json');
    $doctorId = intval($_POST['doctor_id'] ?? 0);
    if ($doctorId) {
        $result = WorkloadBalancer::recalculateDoctorWorkload($conn, $doctorId);
        echo json_encode(['success' => (bool)$result, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
    }
    exit;
}

// Fetch all doctors from HMS doctors table and their latest workload summary
$docQuery = $conn->query("
    SELECT doc.doctor_id, doc.full_name AS doctor_name, d.department_name
    FROM doctors doc
    LEFT JOIN departments d ON doc.department_id = d.department_id
    ORDER BY doc.full_name ASC
");
$allDocs = [];
if ($docQuery) {
    while ($doc = $docQuery->fetch_assoc()) {
        $allDocs[] = $doc;
    }
}

// Recalculate workloads for all doctors dynamically to ensure fresh data
foreach ($allDocs as $doc) {
    WorkloadBalancer::recalculateDoctorWorkload($conn, $doc['doctor_id']);
}

// Fetch latest metrics snapshot per doctor
$workloadData = WorkloadBalancer::getLatestWorkloadSummary($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Doctor Workload & Burnout Telemetry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .progress-bar-low { background-color: #10b981; }
        .progress-bar-medium { background-color: #f59e0b; }
        .progress-bar-high { background-color: #f97316; }
        .progress-bar-critical { background-color: #ef4444; }
        .badge-risk-LOW { background-color: #065f46; color: #a7f3d0; }
        .badge-risk-MEDIUM { background-color: #78350f; color: #fde68a; }
        .badge-risk-HIGH { background-color: #7c2d12; color: #ffedd5; }
        .badge-risk-CRITICAL { background-color: #7f1d1d; color: #fecaca; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-warning mb-1"><i class="bi bi-person-badge-fill me-2"></i>AI-HODE: Doctor Workload Telemetry</h2>
                <p class="text-secondary mb-0">Active patient load balancing, consultation duration & burnout risk monitoring</p>
            </div>
            <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
        </div>

        <div class="row g-4 mb-4">
            <?php if (empty($workloadData)): ?>
                <div class="col-12">
                    <div class="card card-custom p-4 text-center text-muted">
                        <i class="bi bi-person-x fs-1 mb-2"></i>
                        No doctor workload records available.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($workloadData as $w): ?>
                    <?php 
                        $score = floatval($w['workload_score']);
                        $riskClass = strtolower($w['burnout_risk_level']);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-custom p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-bold text-white mb-0"><?= htmlspecialchars($w['doctor_name']) ?></h5>
                                    <small class="text-secondary"><?= htmlspecialchars($w['department_name'] ?? 'General Medicine') ?></small>
                                </div>
                                <span class="badge badge-risk-<?= $w['burnout_risk_level'] ?> px-3 py-2">
                                    Risk: <?= $w['burnout_risk_level'] ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between text-sm mb-1">
                                    <span class="text-secondary">Workload Capacity Score</span>
                                    <span class="fw-bold text-white"><?= number_format($score, 1) ?>%</span>
                                </div>
                                <div class="progress bg-secondary" style="height: 10px;">
                                    <div class="progress-bar progress-bar-<?= $riskClass ?>" role="progressbar" style="width: <?= $score ?>%"></div>
                                </div>
                            </div>

                            <div class="row text-center border-top border-secondary pt-3 mt-2">
                                <div class="col-4">
                                    <div class="fs-4 fw-bold text-info"><?= $w['active_patients'] ?></div>
                                    <small class="text-secondary" style="font-size: 0.75rem;">Active Patients</small>
                                </div>
                                <div class="col-4">
                                    <div class="fs-4 fw-bold text-success"><?= $w['consultations_completed_today'] ?></div>
                                    <small class="text-secondary" style="font-size: 0.75rem;">Done Today</small>
                                </div>
                                <div class="col-4">
                                    <div class="fs-4 fw-bold text-warning"><?= round($w['avg_consultation_time_seconds'] / 60, 1) ?>m</div>
                                    <small class="text-secondary" style="font-size: 0.75rem;">Avg Time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
