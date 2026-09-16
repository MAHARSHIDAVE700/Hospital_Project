<?php
/**
 * Simulation Data Reset Handler
 * Path: simulation/reset_simulation.php
 */

require_once __DIR__ . '/config.php';

$message = "";
$previewData = null;
$selectedRunId = $_GET['run_id'] ?? $_POST['run_id'] ?? '';

if (!empty($selectedRunId)) {
    $previewData = SimulationDataCleanupService::previewCleanup($conn, $selectedRunId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    $runId = trim($_POST['run_id'] ?? '');
    if (!empty($runId)) {
        $res = SimulationDataCleanupService::executeCleanup($conn, $runId);
        if ($res['success']) {
            $message = "<div class='alert alert-success'><strong>Success:</strong> " . htmlspecialchars($res['message']) . "</div>";
            $previewData = null;
        } else {
            $message = "<div class='alert alert-danger'><strong>Error:</strong> " . htmlspecialchars($res['message']) . "</div>";
        }
    }
}

$allRuns = SimulationDatabaseService::getRecentRuns($conn, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Simulation Run | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-trash3 text-danger me-2"></i>Reset Simulation Data</h2>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <?= $message; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white fw-bold">Target Simulation Run Isolation</div>
        <div class="card-body">
            <form method="GET" class="mb-3">
                <label class="form-label font-weight-bold fw-bold">Select Simulation Run to Preview & Reset:</label>
                <div class="input-group">
                    <select name="run_id" class="form-select" required>
                        <option value="">-- Select Simulation Run --</option>
                        <?php foreach ($allRuns as $r): ?>
                            <option value="<?= htmlspecialchars($r['run_id']); ?>" <?= $selectedRunId === $r['run_id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($r['run_id']); ?> (<?= htmlspecialchars($r['run_type']); ?> - <?= htmlspecialchars($r['status']); ?> - <?= htmlspecialchars($r['created_at']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Preview Data</button>
                </div>
            </form>

            <?php if ($previewData && !isset($previewData['error'])): ?>
                <div class="alert alert-warning border-warning">
                    <h5><i class="bi bi-exclamation-triangle me-2"></i>Cleanup Preview Confirmation</h5>
                    <p class="mb-1"><strong>Run ID:</strong> <?= htmlspecialchars($previewData['run_id']); ?></p>
                    <p class="mb-1"><strong>Created At:</strong> <?= htmlspecialchars($previewData['created_at']); ?></p>
                    <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($previewData['status']); ?></p>
                    <p class="mb-3"><strong>Total Recorded Simulation Events:</strong> <?= intval($previewData['event_count']); ?></p>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete simulation run <?= htmlspecialchars($previewData['run_id']); ?>? Real hospital records will NOT be touched.');">
                        <input type="hidden" name="run_id" value="<?= htmlspecialchars($previewData['run_id']); ?>">
                        <button type="submit" name="confirm_reset" class="btn btn-danger btn-lg w-100 py-2">
                            <i class="bi bi-trash me-2"></i>Confirm Reset & Delete Run Data
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
