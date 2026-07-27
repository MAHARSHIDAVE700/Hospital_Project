<?php
/**
 * AI-HODE: AI Model Metrics & Performance Dashboard
 * Path: ai_hode/model_metrics/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../ai_models/model_registry.php';
require_once __DIR__ . '/metrics_collector.php';

// Seed default metrics evaluation data if empty
$existingCount = $conn->query("SELECT COUNT(*) AS total FROM model_metrics")->fetch_assoc()['total'] ?? 0;
if ($existingCount == 0) {
    $models = ModelRegistry::getAllModels($conn);
    foreach ($models as $m) {
        MetricsCollector::recordMetrics($conn, $m['model_id'], 2.4500, 3.1200, 0.9450, rand(45, 120));
    }
}

// Handle AJAX recording of new evaluation metrics
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_metrics') {
    header('Content-Type: application/json');
    $mId = intval($_POST['model_id'] ?? 0);
    $mae = floatval($_POST['mae'] ?? 2.5);
    $rmse = floatval($_POST['rmse'] ?? 3.1);
    $acc = floatval($_POST['accuracy_score'] ?? 0.92);
    $lat = intval($_POST['latency_ms'] ?? 65);

    if ($mId) {
        $metricId = MetricsCollector::recordMetrics($conn, $mId, $mae, $rmse, $acc, $lat);
        echo json_encode(['success' => (bool)$metricId, 'metric_id' => $metricId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid model ID']);
    }
    exit;
}

$history = MetricsCollector::getEvaluationHistory($conn, 50);
$allModels = ModelRegistry::getAllModels($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Model Evaluation Metrics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-teal mb-1" style="color: #14b8a6;"><i class="bi bi-graph-up-arrow me-2"></i>AI-HODE: Model Evaluation Metrics</h2>
                <p class="text-secondary mb-0">Performance monitoring: MAE, RMSE, Accuracy/R² score & inference latency</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-teal text-white" style="background-color: #0d9488;" data-bs-toggle="modal" data-bs-target="#evalModal"><i class="bi bi-plus-lg me-1"></i> Record Evaluation</button>
                <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
            </div>
        </div>

        <div class="card card-custom p-3 mb-4">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>Metric ID</th>
                            <th>Model Name</th>
                            <th>Version</th>
                            <th>MAE (Mean Abs Error)</th>
                            <th>RMSE (Root Mean Sq Error)</th>
                            <th>Accuracy / R² Score</th>
                            <th>Inference Latency</th>
                            <th>Evaluated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><strong>#<?= $h['metric_id'] ?></strong></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($h['model_name']) ?></td>
                                <td><span class="badge bg-info text-dark fw-bold"><?= htmlspecialchars($h['version']) ?></span></td>
                                <td class="text-warning"><?= number_format($h['mae'], 4) ?></td>
                                <td class="text-danger"><?= number_format($h['rmse'], 4) ?></td>
                                <td>
                                    <span class="badge bg-success fs-6">
                                        <?= number_format($h['accuracy_score'] * 100, 2) ?>%
                                    </span>
                                </td>
                                <td><span class="text-info fw-bold"><?= $h['latency_ms'] ?> ms</span></td>
                                <td><small><?= date('Y-m-d H:i:s', strtotime($h['evaluated_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="evalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>Record Model Evaluation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="evalForm">
                        <input type="hidden" name="action" value="record_metrics">
                        <div class="mb-3">
                            <label class="form-label">Target Model</label>
                            <select class="form-select bg-secondary text-white border-0" name="model_id">
                                <?php foreach ($allModels as $m): ?>
                                    <option value="<?= $m['model_id'] ?>"><?= htmlspecialchars($m['model_name']) ?> (<?= $m['version'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">MAE (Mean Absolute Error)</label>
                            <input type="number" step="0.0001" class="form-control bg-secondary text-white border-0" name="mae" value="2.3500">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">RMSE (Root Mean Squared Error)</label>
                            <input type="number" step="0.0001" class="form-control bg-secondary text-white border-0" name="rmse" value="3.0500">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Accuracy / R² Score (0.0 to 1.0)</label>
                            <input type="number" step="0.0001" class="form-control bg-secondary text-white border-0" name="accuracy_score" value="0.9480">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Inference Latency (ms)</label>
                            <input type="number" class="form-control bg-secondary text-white border-0" name="latency_ms" value="52">
                        </div>
                        <button type="submit" class="btn btn-teal text-white w-100" style="background-color: #0d9488;">Save Evaluation Metrics</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('evalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error recording metrics');
            });
        });
    </script>
</body>
</html>
