<?php
/**
 * AI-HODE: Predictions Telemetry Dashboard
 * Path: ai_hode/predictions/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/predictive_analytics.php';
require_once __DIR__ . '/prediction_logger.php';

// Handle test trigger for manual prediction testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_test_prediction') {
    header('Content-Type: application/json');
    $targetType = trim($_POST['target_type'] ?? 'WAIT_TIME');
    $dept = trim($_POST['department'] ?? 'General OPD');
    $queueLen = intval($_POST['queue_length'] ?? 5);

    if ($targetType === 'WAIT_TIME') {
        $res = PredictiveAnalyticsEngine::predictAndLogWaitTime($conn, $dept, date('H:i'), '11:00', date('l'), $queueLen, 'TEST-DEPT-1');
        echo json_encode(['success' => true, 'result' => $res]);
    } else {
        // Log custom prediction type (ARRIVAL, QUEUE, WORKLOAD)
        $val = floatval($_POST['predicted_value'] ?? rand(10, 50));
        $predId = PredictionLogger::logPrediction($conn, $targetType, 'DEPT-1', $val, $val * 0.85, $val * 1.15, 'v1.2-xgboost-ensemble');
        echo json_encode(['success' => (bool)$predId, 'prediction_id' => $predId]);
    }
    exit;
}

$predictions = PredictionLogger::getLatestPredictions($conn, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Predictions Telemetry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .badge-type-WAIT_TIME { background-color: #0284c7; }
        .badge-type-ARRIVAL { background-color: #16a34a; }
        .badge-type-QUEUE { background-color: #d97706; }
        .badge-type-WORKLOAD { background-color: #9333ea; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-success mb-1"><i class="bi bi-cpu-fill me-2"></i>AI-HODE: Predictions Telemetry</h2>
                <p class="text-secondary mb-0">Persisted model inference outputs across WAIT_TIME, ARRIVAL, QUEUE & WORKLOAD targets</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#testModal"><i class="bi bi-play-circle me-1"></i> Test Prediction</button>
                <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
            </div>
        </div>

        <div class="card card-custom p-3 mb-4">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>Prediction ID</th>
                            <th>Target Category</th>
                            <th>Entity ID</th>
                            <th>Predicted Output</th>
                            <th>Confidence Interval</th>
                            <th>Model Version</th>
                            <th>Prediction Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($predictions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No prediction logs recorded. Click "Test Prediction" to run inference.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($predictions as $p): ?>
                                <tr>
                                    <td><strong>#<?= $p['prediction_id'] ?></strong></td>
                                    <td><span class="badge badge-type-<?= $p['target_type'] ?> p-2"><?= $p['target_type'] ?></span></td>
                                    <td><?= htmlspecialchars($p['target_entity_id'] ?? 'System') ?></td>
                                    <td class="fs-5 fw-bold text-info"><?= number_format($p['predicted_value'], 2) ?></td>
                                    <td>
                                        <?php if ($p['confidence_lower'] !== null && $p['confidence_upper'] !== null): ?>
                                            <small class="text-secondary">[<?= number_format($p['confidence_lower'], 2) ?> - <?= number_format($p['confidence_upper'], 2) ?>]</small>
                                        <?php else: ?>
                                            <small class="text-muted">N/A</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['model_version']) ?></span></td>
                                    <td><small><?= date('Y-m-d H:i:s', strtotime($p['prediction_time'])) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Test Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-magic me-2"></i>Trigger AI Prediction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="testForm">
                        <input type="hidden" name="action" value="run_test_prediction">
                        <div class="mb-3">
                            <label class="form-label">Prediction Target Type</label>
                            <select class="form-select bg-secondary text-white border-0" name="target_type">
                                <option value="WAIT_TIME">WAIT_TIME (Wait-time regression)</option>
                                <option value="ARRIVAL">ARRIVAL (Patient arrival rate)</option>
                                <option value="QUEUE">QUEUE (Queue length estimation)</option>
                                <option value="WORKLOAD">WORKLOAD (Doctor capacity load)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" name="department" value="General OPD">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Queue Length</label>
                            <input type="number" class="form-control bg-secondary text-white border-0" name="queue_length" value="6">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Run Inference &amp; Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Prediction error');
            });
        });
    </script>
</body>
</html>
