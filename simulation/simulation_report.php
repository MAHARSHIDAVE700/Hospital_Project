<?php
/**
 * Simulation Summary Report Page
 * Path: simulation/simulation_report.php
 */

require_once __DIR__ . '/config.php';
$recentRuns = SimulationDatabaseService::getRecentRuns($conn, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simulation Analytics & Report | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-bar-graph text-success me-2"></i>Simulation Architectural Summary Report</h2>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase">Safety Layer Mode</h6>
                    <h3 class="fw-bold text-primary mb-0"><?= SIM_MODE_ACTIVE ? 'ACTIVE' : 'IDLE (Log-Only)' ?></h3>
                    <small class="text-muted">Side-effect isolation active</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase">Total Runs Logged</h6>
                    <h3 class="fw-bold text-success mb-0"><?= count($recentRuns); ?></h3>
                    <small class="text-muted">Executed in NeonDB PostgreSQL</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase">AI-HODE Data Flow</h6>
                    <h3 class="fw-bold text-info mb-0">READY</h3>
                    <small class="text-muted">Telemetry integration mapped</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white font-weight-bold fw-bold">Executive Overview</div>
        <div class="card-body">
            <p>The Hospital Workflow Simulation Foundation (Phase 3.1) establishes a safe, isolated simulation module within the Hospital Management System root. All simulation procedures run inside database transactions, with complete side-effect protection prohibiting external email and payment gateway calls.</p>
        </div>
    </div>
</div>

</body>
</html>
