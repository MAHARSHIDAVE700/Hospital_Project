<?php
/**
 * AI-HODE: Decision Audit Logs & Governance Dashboard
 * Path: ai_hode/decision_logs/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/decision_logger.php';

// Check if GET parameters trigger a decision action (from recommendations dashboard)
if (isset($_GET['rec_id']) && isset($_GET['action'])) {
    $recId = intval($_GET['rec_id']);
    $act = strtoupper(trim($_GET['action']));
    if ($act === 'ACCEPT') $actionTaken = 'ACCEPTED';
    elseif ($act === 'REJECT') $actionTaken = 'REJECTED';
    else $actionTaken = 'IGNORED';

    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $outcomeNotes = isset($_GET['notes']) ? trim($_GET['notes']) : "Action {$actionTaken} executed via Admin Control Panel";

    DecisionLogger::logDecision($conn, $recId, $actionTaken, $userId, $outcomeNotes, 'MANUAL');

    header("Location: index.php?status=success");
    exit;
}

// Handle AJAX POST decision logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_decision') {
    header('Content-Type: application/json');
    $recId = intval($_POST['recommendation_id'] ?? 0);
    $actionTaken = trim($_POST['action_taken'] ?? 'ACCEPTED');
    $notes = trim($_POST['outcome_notes'] ?? '');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $logId = DecisionLogger::logDecision($conn, $recId, $actionTaken, $userId, $notes, 'MANUAL');
    echo json_encode(['success' => (bool)$logId, 'log_id' => $logId]);
    exit;
}

$logs = DecisionLogger::getDecisionLogs($conn, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Decision Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .badge-act-ACCEPTED { background-color: #16a34a; }
        .badge-act-REJECTED { background-color: #dc2626; }
        .badge-act-IGNORED { background-color: #475569; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-journal-check me-2"></i>AI-HODE: Decision Governance Audit Logs</h2>
                <p class="text-secondary mb-0">Full audit trail of administrator accepted, rejected & ignored AI operational recommendations</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../recommendations/index.php" class="btn btn-warning"><i class="bi bi-lightbulb me-1"></i> Recommendations</a>
                <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
            </div>
        </div>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show bg-success text-white border-0 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Decision logged and recommendation status updated successfully!
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom p-3 mb-4">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>Log ID</th>
                            <th>Rec. ID</th>
                            <th>Recommendation Title</th>
                            <th>Category</th>
                            <th>Action Taken</th>
                            <th>Execution</th>
                            <th>Executed By</th>
                            <th>Outcome Notes</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No decision logs recorded yet. Action recommendations to populate audit trail.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td><strong>#<?= $l['log_id'] ?></strong></td>
                                    <td>#<?= $l['recommendation_id'] ?></td>
                                    <td><?= htmlspecialchars($l['rec_title'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($l['rec_category'] ?? 'SYSTEM') ?></span></td>
                                    <td><span class="badge badge-act-<?= $l['action_taken'] ?> p-2"><?= $l['action_taken'] ?></span></td>
                                    <td><small><?= $l['execution_type'] ?></small></td>
                                    <td><?= htmlspecialchars($l['user_name'] ?? 'Administrator') ?></td>
                                    <td><small class="text-slate-300"><?= htmlspecialchars($l['outcome_notes'] ?? '-') ?></small></td>
                                    <td><small><?= date('Y-m-d H:i:s', strtotime($l['timestamp'])) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
