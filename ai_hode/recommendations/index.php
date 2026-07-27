<?php
/**
 * AI-HODE: AI Operational Recommendations Control Center
 * Path: ai_hode/recommendations/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/recommendation_engine.php';

// Handle AJAX actions (Generate rules, Update status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'generate_recommendations') {
        $generated = RecommendationEngine::evaluateRulesAndGenerate($conn);
        echo json_encode(['success' => true, 'count' => count($generated), 'generated' => $generated]);
        exit;
    }

    if ($action === 'create_manual_recommendation') {
        $category = trim($_POST['category'] ?? 'STAFF_DISPATCH');
        $title = trim($_POST['title'] ?? '');
        $details = trim($_POST['action_details'] ?? '');
        $urgency = trim($_POST['urgency_level'] ?? 'MEDIUM');

        if ($title && $details) {
            $recId = RecommendationEngine::saveRecommendation($conn, $category, $title, $details, $urgency, 85.00);
            echo json_encode(['success' => (bool)$recId, 'recommendation_id' => $recId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing title or details']);
        }
        exit;
    }
}

// Automatically trigger rule engine evaluation on page view
RecommendationEngine::evaluateRulesAndGenerate($conn);

// Fetch pending and historical recommendations
$pendingRecs = RecommendationEngine::getRecommendations($conn, 'PENDING');
$processedRecs = array_merge(
    RecommendationEngine::getRecommendations($conn, 'ACCEPTED'),
    RecommendationEngine::getRecommendations($conn, 'REJECTED')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Operational Recommendation Engine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .badge-urgency-CRITICAL { background-color: #ef4444; color: white; }
        .badge-urgency-HIGH { background-color: #f97316; color: white; }
        .badge-urgency-MEDIUM { background-color: #eab308; color: black; }
        .badge-urgency-LOW { background-color: #10b981; color: white; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-danger mb-1"><i class="bi bi-lightbulb-fill me-2"></i>AI-HODE: Operational Recommendation Engine</h2>
                <p class="text-secondary mb-0">Automated decision engine evaluating staff dispatch, queue re-routing & triage priorities</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-warning" onclick="triggerRuleEngine()"><i class="bi bi-gear-wide-connected me-1"></i> Run Rule Engine</button>
                <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
            </div>
        </div>

        <h4 class="fw-bold text-white mb-3"><i class="bi bi-hourglass-split me-2 text-warning"></i>Active Pending AI Recommendations</h4>

        <div class="row g-4 mb-5">
            <?php if (empty($pendingRecs)): ?>
                <div class="col-12">
                    <div class="card card-custom p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 mb-2 text-success"></i>
                        No pending operational recommendations. All hospital systems operating within normal parameters.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pendingRecs as $r): ?>
                    <div class="col-md-6">
                        <div class="card card-custom p-4 border-start border-4 border-warning">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-secondary mb-1"><?= $r['category'] ?></span>
                                    <h5 class="fw-bold text-white mb-0"><?= htmlspecialchars($r['title']) ?></h5>
                                </div>
                                <span class="badge badge-urgency-<?= $r['urgency_level'] ?> px-3 py-2">
                                    <?= $r['urgency_level'] ?> URGENCY
                                </span>
                            </div>

                            <p class="text-slate-300 mt-2 mb-3" style="color: #cbd5e1;"><?= htmlspecialchars($r['action_details']) ?></p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary">
                                <small class="text-secondary"><i class="bi bi-clock me-1"></i> <?= date('Y-m-d H:i', strtotime($r['created_at'])) ?> | Impact Score: <strong><?= number_format($r['impact_score'], 1) ?>%</strong></small>
                                <div>
                                    <a href="../decision_logs/index.php?rec_id=<?= $r['recommendation_id'] ?>&action=ACCEPT" class="btn btn-sm btn-success me-1"><i class="bi bi-check-lg"></i> Accept</a>
                                    <a href="../decision_logs/index.php?rec_id=<?= $r['recommendation_id'] ?>&action=REJECT" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function triggerRuleEngine() {
            let formData = new FormData();
            formData.append('action', 'generate_recommendations');

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                alert('Rule Engine Executed! Generated ' + data.count + ' new recommendations.');
                location.reload();
            });
        }
    </script>
</body>
</html>
