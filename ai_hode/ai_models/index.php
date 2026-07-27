<?php
/**
 * AI-HODE: AI Model Registry Dashboard
 * Path: ai_hode/ai_models/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/model_registry.php';

// Populate seed default models if table is empty
$existingCount = $conn->query("SELECT COUNT(*) AS total FROM ai_models")->fetch_assoc()['total'] ?? 0;
if ($existingCount == 0) {
    ModelRegistry::registerModel($conn, 'Wait Time Predictor', 'XGBoost Regressor', 'v1.2.0', 'ACTIVE', [
        'n_estimators' => 150,
        'max_depth' => 6,
        'learning_rate' => 0.05,
        'features' => ['department', 'queue_length', 'arrival_time', 'day']
    ]);
    ModelRegistry::registerModel($conn, 'Patient Arrival Forecaster', 'Prophet / LSTM Ensemble', 'v1.0.0', 'ACTIVE', [
        'seasonality' => 'weekly',
        'horizon' => '24h',
        'framework' => 'PyTorch'
    ]);
    ModelRegistry::registerModel($conn, 'Doctor Workload Balancer', 'Heuristic Rules Engine', 'v1.1.0', 'ACTIVE', [
        'threshold_high' => 70,
        'threshold_critical' => 90
    ]);
}

// Handle AJAX model registration / status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'register_model') {
        $name = trim($_POST['model_name'] ?? '');
        $algo = trim($_POST['algorithm'] ?? '');
        $ver = trim($_POST['version'] ?? '');
        $status = trim($_POST['status'] ?? 'ACTIVE');

        if ($name && $algo && $ver) {
            $mId = ModelRegistry::registerModel($conn, $name, $algo, $ver, $status, [
                'registered_by' => 'Admin Panel',
                'training_framework' => 'Scikit-Learn / XGBoost'
            ]);
            echo json_encode(['success' => (bool)$mId, 'model_id' => $mId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
        }
        exit;
    }

    if ($action === 'toggle_status') {
        $mId = intval($_POST['model_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'ACTIVE');
        $updated = ModelRegistry::updateStatus($conn, $mId, $newStatus);
        echo json_encode(['success' => $updated]);
        exit;
    }
}

$models = ModelRegistry::getAllModels($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - AI Model Registry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .badge-status-ACTIVE { background-color: #16a34a; }
        .badge-status-INACTIVE { background-color: #475569; }
        .badge-status-TRAINING { background-color: #d97706; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-purple mb-1" style="color: #a855f7;"><i class="bi bi-box-seam-fill me-2"></i>AI-HODE: AI Model Registry</h2>
                <p class="text-secondary mb-0">Model versioning, hyperparameter telemetry & active algorithm management</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-purple text-white" style="background-color: #9333ea;" data-bs-toggle="modal" data-bs-target="#registerModal"><i class="bi bi-plus-lg me-1"></i> Register New Model</button>
                <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
            </div>
        </div>

        <div class="card card-custom p-3 mb-4">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>Model ID</th>
                            <th>Model Name</th>
                            <th>Algorithm</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Hyperparameters</th>
                            <th>Last Trained</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($models as $m): ?>
                            <tr>
                                <td><strong>#<?= $m['model_id'] ?></strong></td>
                                <td class="fw-bold text-white"><?= htmlspecialchars($m['model_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($m['algorithm']) ?></span></td>
                                <td><span class="badge bg-info text-dark fw-bold"><?= htmlspecialchars($m['version']) ?></span></td>
                                <td><span class="badge badge-status-<?= $m['status'] ?> p-2"><?= $m['status'] ?></span></td>
                                <td>
                                    <small class="text-slate-300 font-monospace">
                                        <?= htmlspecialchars(json_encode($m['parameters_parsed'])) ?>
                                    </small>
                                </td>
                                <td><small><?= date('Y-m-d H:i:s', strtotime($m['last_trained_at'])) ?></small></td>
                                <td>
                                    <?php if ($m['status'] === 'ACTIVE'): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleStatus(<?= $m['model_id'] ?>, 'INACTIVE')">Deactivate</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" onclick="toggleStatus(<?= $m['model_id'] ?>, 'ACTIVE')">Activate</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-box me-2"></i>Register AI Model</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="regForm">
                        <input type="hidden" name="action" value="register_model">
                        <div class="mb-3">
                            <label class="form-label">Model Name</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" name="model_name" placeholder="e.g. Wait Time Regressor" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Algorithm</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" name="algorithm" placeholder="e.g. XGBoost / Random Forest" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Version</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" name="version" value="v1.3.0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select bg-secondary text-white border-0" name="status">
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                                <option value="TRAINING">TRAINING</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-purple text-white w-100" style="background-color: #9333ea;">Register Model</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('regForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error registering model');
            });
        });

        function toggleStatus(modelId, status) {
            let formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('model_id', modelId);
            formData.append('status', status);

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
            });
        }
    </script>
</body>
</html>
