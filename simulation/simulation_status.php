<?php
/**
 * Simulation Status Monitor Page
 * Path: simulation/simulation_status.php
 */

require_once __DIR__ . '/config.php';
$runs = SimulationDatabaseService::getRecentRuns($conn, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simulation Status Monitor | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-activity text-primary me-2"></i>Simulation Execution Log & Status Monitor</h2>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white font-weight-bold fw-bold">Recent Simulation Runs</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Run ID</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Records Generated</th>
                            <th>Errors</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($runs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No simulation runs executed yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($runs as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['run_id']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $r['status'] === 'COMPLETED' ? 'success' : ($r['status'] === 'FAILED' ? 'danger' : 'info') ?>">
                                            <?= htmlspecialchars($r['status']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($r['run_type']); ?></td>
                                    <td><?= intval($r['duration_days']); ?> Day(s)</td>
                                    <td><?= intval($r['records_generated']); ?></td>
                                    <td><?= intval($r['error_count']); ?></td>
                                    <td><?= htmlspecialchars($r['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
