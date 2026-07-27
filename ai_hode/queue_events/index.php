<?php
/**
 * AI-HODE: Real-time Queue Events & Token Telemetry Dashboard
 * Path: ai_hode/queue_events/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../patient_flow/patient_flow_engine.php';
require_once __DIR__ . '/queue_event_tracker.php';

// Handle AJAX actions (Call token, Complete token, Issue token)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'call_token') {
        $flowId = intval($_POST['flow_id'] ?? 0);
        $token = trim($_POST['token'] ?? '');

        if ($flowId && $token) {
            // Update patient flow stage to IN_CONSULTATION
            PatientFlowEngine::updateStage($conn, $flowId, 'IN_CONSULTATION');

            // Calculate actual wait time from ISSUED event
            $issuedEvt = $conn->query("SELECT event_timestamp FROM queue_events WHERE flow_id = {$flowId} AND event_type = 'ISSUED' ORDER BY event_id ASC LIMIT 1");
            $actualWaitSec = 0;
            if ($issuedEvt && $iRow = $issuedEvt->fetch_assoc()) {
                $actualWaitSec = time() - strtotime($iRow['event_timestamp']);
            }

            // Record CALLED event
            $eventId = QueueEventTracker::recordEvent($conn, $flowId, $token, 'CALLED', null, $actualWaitSec);
            echo json_encode(['success' => (bool)$eventId, 'message' => 'Token called successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        }
        exit;
    }

    if ($action === 'complete_token') {
        $flowId = intval($_POST['flow_id'] ?? 0);
        $token = trim($_POST['token'] ?? '');

        if ($flowId && $token) {
            // Update patient flow stage to LAB_PHARMACY or DISCHARGED
            PatientFlowEngine::updateStage($conn, $flowId, 'DISCHARGED');

            // Record COMPLETED event
            $eventId = QueueEventTracker::recordEvent($conn, $flowId, $token, 'COMPLETED');
            echo json_encode(['success' => (bool)$eventId, 'message' => 'Consultation completed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        }
        exit;
    }

    if ($action === 'fetch_queue_status') {
        $deptId = intval($_POST['department_id'] ?? 1);
        $res = $conn->query("
            SELECT qe.event_id, qe.queue_token, qe.event_type, qe.event_timestamp, qe.estimated_wait_seconds, qe.actual_wait_seconds, pf.flow_id, p.full_name AS patient_name, doc.full_name AS doctor_name
            FROM queue_events qe
            JOIN patient_flow pf ON qe.flow_id = pf.flow_id
            LEFT JOIN patients p ON pf.patient_id = p.patient_id
            LEFT JOIN doctors doc ON pf.assigned_doctor_id = doc.doctor_id
            WHERE pf.department_id = {$deptId}
            ORDER BY qe.event_id DESC LIMIT 30
        ");
        $events = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $events[] = $r;
            }
        }
        echo json_encode(['success' => true, 'events' => $events]);
        exit;
    }
}

// Fetch department list for selector
$deptRes = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
$departments = [];
if ($deptRes) {
    while ($d = $deptRes->fetch_assoc()) {
        $departments[] = $d;
    }
}

// Fetch active tokens
$activeTokensRes = $conn->query("
    SELECT qe.event_id, qe.queue_token, qe.event_type, qe.event_timestamp, qe.estimated_wait_seconds, qe.actual_wait_seconds,
           pf.flow_id, pf.current_stage, p.full_name AS patient_name, d.department_name, doc.full_name AS doctor_name
    FROM queue_events qe
    JOIN patient_flow pf ON qe.flow_id = pf.flow_id
    LEFT JOIN patients p ON pf.patient_id = p.patient_id
    LEFT JOIN departments d ON pf.department_id = d.department_id
    LEFT JOIN doctors doc ON pf.assigned_doctor_id = doc.doctor_id
    ORDER BY qe.event_id DESC LIMIT 40
");
$allEvents = [];
if ($activeTokensRes) {
    while ($row = $activeTokensRes->fetch_assoc()) {
        $allEvents[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Live Queue Telemetry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .token-display { font-family: monospace; font-size: 1.2rem; font-weight: 700; color: #38bdf8; }
        .badge-evt-ISSUED { background-color: #0284c7; }
        .badge-evt-CALLED { background-color: #d97706; }
        .badge-evt-COMPLETED { background-color: #16a34a; }
        .badge-evt-DELAYED { background-color: #dc2626; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-info mb-1"><i class="bi bi-person-lines-fill me-2"></i>AI-HODE: Live Queue & Token Telemetry</h2>
                <p class="text-secondary mb-0">Real-time token movement, calling desk & delay telemetry</p>
            </div>
            <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
        </div>

        <div class="card card-custom p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 text-white"><i class="bi bi-ticket-detailed me-2"></i>Queue Events Monitor</h5>
                <button class="btn btn-sm btn-outline-info" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>Event ID</th>
                            <th>Token #</th>
                            <th>Patient</th>
                            <th>Department</th>
                            <th>Doctor</th>
                            <th>Event Type</th>
                            <th>Est. Wait</th>
                            <th>Actual Wait</th>
                            <th>Timestamp</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allEvents)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No queue events recorded yet. Book an appointment to generate tokens.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allEvents as $evt): ?>
                                <tr>
                                    <td>#<?= $evt['event_id'] ?></td>
                                    <td><span class="token-display"><?= htmlspecialchars($evt['queue_token']) ?></span></td>
                                    <td><?= htmlspecialchars($evt['patient_name'] ?? 'Patient Flow #' . $evt['flow_id']) ?></td>
                                    <td><?= htmlspecialchars($evt['department_name'] ?? 'OPD') ?></td>
                                    <td><?= htmlspecialchars($evt['doctor_name'] ?? 'Unassigned') ?></td>
                                    <td><span class="badge badge-evt-<?= $evt['event_type'] ?> p-2"><?= $evt['event_type'] ?></span></td>
                                    <td><?= $evt['estimated_wait_seconds'] ? round($evt['estimated_wait_seconds'] / 60, 1) . 'm' : '-' ?></td>
                                    <td><?= $evt['actual_wait_seconds'] ? round($evt['actual_wait_seconds'] / 60, 1) . 'm' : '-' ?></td>
                                    <td><small><?= date('H:i:s', strtotime($evt['event_timestamp'])) ?></small></td>
                                    <td>
                                        <?php if ($evt['event_type'] === 'ISSUED'): ?>
                                            <button class="btn btn-sm btn-warning text-dark fw-bold" onclick="callToken(<?= $evt['flow_id'] ?>, '<?= $evt['queue_token'] ?>')">
                                                <i class="bi bi-telephone-out"></i> Call
                                            </button>
                                        <?php elseif ($evt['event_type'] === 'CALLED'): ?>
                                            <button class="btn btn-sm btn-success fw-bold" onclick="completeToken(<?= $evt['flow_id'] ?>, '<?= $evt['queue_token'] ?>')">
                                                <i class="bi bi-check-circle"></i> Complete
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-check2-all"></i> Done</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function callToken(flowId, token) {
            let formData = new FormData();
            formData.append('action', 'call_token');
            formData.append('flow_id', flowId);
            formData.append('token', token);

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error calling token: ' + data.message);
            });
        }

        function completeToken(flowId, token) {
            let formData = new FormData();
            formData.append('action', 'complete_token');
            formData.append('flow_id', flowId);
            formData.append('token', token);

            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error completing token: ' + data.message);
            });
        }
    </script>
</body>
</html>
