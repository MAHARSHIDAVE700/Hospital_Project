<?php
/**
 * AI-HODE: Patient Flow Management & Monitoring Dashboard
 * Path: ai_hode/patient_flow/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/patient_flow_engine.php';

// Handle AJAX stage update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stage') {
    header('Content-Type: application/json');
    $flowId = intval($_POST['flow_id'] ?? 0);
    $newStage = trim($_POST['new_stage'] ?? '');
    $delayReason = !empty($_POST['delay_reason']) ? trim($_POST['delay_reason']) : null;
    $isBottleneck = isset($_POST['is_bottleneck']) && $_POST['is_bottleneck'] == '1';

    if (!$flowId || empty($newStage)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    $updated = PatientFlowEngine::updateStage($conn, $flowId, $newStage, $delayReason, $isBottleneck);
    if ($updated) {
        echo json_encode(['success' => true, 'message' => 'Stage updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update stage']);
    }
    exit;
}

// Fetch active patient flows with joins to HMS tables
$sql = "SELECT 
            pf.flow_id,
            pf.patient_id,
            pf.appointment_id,
            pf.department_id,
            pf.current_stage,
            pf.triage_priority,
            pf.stage_entry_time,
            pf.stage_exit_time,
            pf.dwell_time_seconds,
            pf.is_bottleneck,
            pf.delay_reason,
            p.full_name AS patient_name,
            d.department_name,
            doc.full_name AS doctor_name
        FROM patient_flow pf
        LEFT JOIN patients p ON pf.patient_id = p.patient_id
        LEFT JOIN departments d ON pf.department_id = d.department_id
        LEFT JOIN doctors doc ON pf.assigned_doctor_id = doc.doctor_id
        ORDER BY pf.flow_id DESC LIMIT 50";

$res = $conn->query($sql);
$flows = $res ? $res->fetch_assoc() : [];
$allFlows = [];
if ($res) {
    $res->data_seek(0);
    while ($row = $res->fetch_assoc()) {
        $allFlows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE - Patient Flow Telemetry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .badge-stage { padding: 6px 12px; font-weight: 600; border-radius: 6px; }
        .stage-CHECK_IN { background-color: #0284c7; color: white; }
        .stage-TRIAGE { background-color: #d97706; color: white; }
        .stage-WAITING_FOR_CONSULTATION { background-color: #ca8a04; color: white; }
        .stage-IN_CONSULTATION { background-color: #16a34a; color: white; }
        .stage-LAB_PHARMACY { background-color: #9333ea; color: white; }
        .stage-BILLING { background-color: #2563eb; color: white; }
        .stage-DISCHARGED { background-color: #475569; color: white; }
    </style>
</head>
<body class="p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-diagram-3-fill me-2"></i>AI-HODE: Patient Flow Telemetry</h2>
                <p class="text-secondary mb-0">Real-time operational stage tracking & bottleneck identification</p>
            </div>
            <a href="../index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Control Hub</a>
        </div>

        <div class="card card-custom p-3 mb-4">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>Flow ID</th>
                            <th>Patient Name</th>
                            <th>Department</th>
                            <th>Doctor</th>
                            <th>Current Stage</th>
                            <th>Triage</th>
                            <th>Entry Time</th>
                            <th>Dwell Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allFlows)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No active patient flow records found. Book an appointment to trigger telemetry.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allFlows as $f): ?>
                                <tr>
                                    <td><strong>#<?= $f['flow_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($f['patient_name'] ?? 'Patient #' . $f['patient_id']) ?></td>
                                    <td><?= htmlspecialchars($f['department_name'] ?? 'General OPD') ?></td>
                                    <td><?= htmlspecialchars($f['doctor_name'] ?? 'Unassigned') ?></td>
                                    <td><span class="badge-stage stage-<?= $f['current_stage'] ?>"><?= str_replace('_', ' ', $f['current_stage']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= $f['triage_priority'] ?></span></td>
                                    <td><small><?= date('H:i:s', strtotime($f['stage_entry_time'])) ?></small></td>
                                    <td>
                                        <?php if ($f['dwell_time_seconds']): ?>
                                            <?= round($f['dwell_time_seconds'] / 60, 1) ?> min
                                        <?php else: ?>
                                            <span class="text-warning"><i class="bi bi-clock-history"></i> In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($f['is_bottleneck']): ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-octagon"></i> Bottleneck</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="updateStageModal(<?= $f['flow_id'] ?>, '<?= $f['current_stage'] ?>')">
                                            <i class="bi bi-pencil-square"></i> Next Stage
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Stage Modal -->
    <div class="modal fade" id="stageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2"></i>Update Operational Stage</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="stageForm">
                        <input type="hidden" id="modalFlowId" name="flow_id">
                        <input type="hidden" name="action" value="update_stage">
                        <div class="mb-3">
                            <label class="form-label">Next Operational Stage</label>
                            <select class="form-select bg-secondary text-white border-0" id="modalNextStage" name="new_stage">
                                <option value="TRIAGE">TRIAGE</option>
                                <option value="WAITING_FOR_CONSULTATION">WAITING FOR CONSULTATION</option>
                                <option value="IN_CONSULTATION">IN CONSULTATION</option>
                                <option value="LAB_PHARMACY">LAB / PHARMACY</option>
                                <option value="BILLING">BILLING</option>
                                <option value="DISCHARGED">DISCHARGED</option>
                                <option value="LEFT_WITHOUT_BEING_SEEN">LEFT WITHOUT BEING SEEN</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delay / Bottleneck Reason (Optional)</label>
                            <input type="text" class="form-control bg-secondary text-white border-0" name="delay_reason" placeholder="e.g. Doctor in emergency">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_bottleneck" value="1" id="btnCheck">
                            <label class="form-check-label text-warning" for="btnCheck">Flag as Operational Bottleneck</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Update Stage</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let modal = new bootstrap.Modal(document.getElementById('stageModal'));
        function updateStageModal(flowId, currentStage) {
            document.getElementById('modalFlowId').value = flowId;
            modal.show();
        }

        document.getElementById('stageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
