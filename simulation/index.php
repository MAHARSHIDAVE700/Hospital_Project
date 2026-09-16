<?php
/**
 * Hospital Workflow Simulation Dashboard
 * Path: simulation/index.php
 */

require_once __DIR__ . '/config.php';

$recentRuns = SimulationDatabaseService::getRecentRuns($conn, 5);
$latestRun = !empty($recentRuns) ? $recentRuns[0] : null;

// Metric Counters from simulation_entities
function getEntityCount($conn, $type) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM simulation_entities WHERE entity_type = '$type'");
    return ($res && $res->num_rows > 0) ? intval($res->fetch_assoc()['total']) : 0;
}

$simPatientsCount     = getEntityCount($conn, 'patient');
$simAppointmentsCount = getEntityCount($conn, 'appointment');
$simLabRequestsCount  = getEntityCount($conn, 'lab_request');
$simDispensesCount    = getEntityCount($conn, 'pharmacy_dispense');
$simIpdAdmissionsCount= getEntityCount($conn, 'ipd_admission');
$simInvoicesCount     = getEntityCount($conn, 'invoice');

// Simulated Revenue Counter
$revRes = $conn->query("SELECT SUM(total_amount) AS total FROM invoices WHERE invoice_id IN (SELECT entity_id FROM simulation_entities WHERE entity_type = 'invoice')");
$simRevenue = ($revRes && $revRes->num_rows > 0) ? floatval($revRes->fetch_assoc()['total']) : 0.00;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Master Simulator | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .sim-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border-radius: 16px; padding: 28px; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .sim-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .metric-val { font-size: 1.4rem; font-weight: 700; color: #0f172a; }
        .profile-btn { border-radius: 10px; transition: all 0.2s ease-in-out; }
        .profile-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <div class="sim-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary">Phase 3.8 Master Hospital Simulation Engine</span>
                <span class="badge bg-success"><i class="bi bi-cpu me-1"></i>AI-HODE Telemetry Ready</span>
            </div>
            <h1 class="fw-bold mb-0"><i class="bi bi-hospital-fill text-primary me-2"></i>MASTER HOSPITAL SIMULATION ENGINE</h1>
            <p class="text-slate-300 mb-0 opacity-75">Cross-Module OPD, Lab, Pharmacy, IPD, Bed Allocation & Billing Telemetry Orchestration</p>
        </div>
        <div class="text-end">
            <a href="../admin/dashboard.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i>Return to Admin Portal</a>
        </div>
    </div>

    <!-- Live Telemetry Status Summary Card -->
    <div class="sim-card border-start border-4 border-primary">
        <h6 class="text-muted text-uppercase fw-bold mb-3"><i class="bi bi-activity text-primary me-2"></i>Live Master Operational Telemetry Metrics</h6>
        <div class="row text-center g-2">
            <div class="col-4 col-md-2">
                <div class="p-2 bg-light rounded">
                    <small class="text-muted">Patients</small>
                    <div class="metric-val text-primary"><?= $simPatientsCount; ?></div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 bg-light rounded">
                    <small class="text-muted">Appointments</small>
                    <div class="metric-val text-success"><?= $simAppointmentsCount; ?></div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 bg-light rounded">
                    <small class="text-muted">Lab Requests</small>
                    <div class="metric-val text-danger"><?= $simLabRequestsCount; ?></div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 bg-light rounded">
                    <small class="text-muted">Dispenses</small>
                    <div class="metric-val text-warning"><?= $simDispensesCount; ?></div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 bg-light rounded">
                    <small class="text-muted">IPD / Beds</small>
                    <div class="metric-val text-indigo"><?= $simIpdAdmissionsCount; ?></div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 bg-light rounded">
                    <small class="text-muted">Sim Revenue</small>
                    <div class="metric-val text-success">₹<?= number_format($simRevenue, 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Console Output Area -->
    <div id="outputContainer" style="display: none;" class="mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold" id="outputTitle"><i class="bi bi-terminal me-2"></i>Console Output</span>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('outputContainer').style.display='none';"></button>
            </div>
            <div class="card-body bg-black text-success font-monospace" id="outputContent" style="max-height: 350px; overflow-y: auto;"></div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Master Simulation Profiles & Controls -->
        <div class="col-lg-7">
            <!-- PHASE 3.8 MASTER SIMULATION SECTION -->
            <div class="sim-card border-primary">
                <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                    <i class="bi bi-play-circle-fill me-2"></i>SIMULATION PROFILES & WORKFLOW ORCHESTRATION (PHASE 3.8)
                </h5>
                <p class="small text-muted mb-3">Select a pre-configured simulation profile to run automated end-to-end hospital operational journeys with realistic branching distribution.</p>

                <!-- Profile Selection Grid -->
                <div class="row g-2 mb-4">
                    <div class="col-6 col-md-4">
                        <button type="button" onclick="runMasterProfile('quick_test')" class="btn btn-outline-primary w-100 p-3 text-start profile-btn">
                            <div class="fw-bold text-primary mb-1"><i class="bi bi-lightning-charge me-1"></i>Quick Test</div>
                            <small class="text-muted d-block">10 Patients</small>
                        </button>
                    </div>
                    <div class="col-6 col-md-4">
                        <button type="button" onclick="runMasterProfile('small_day')" class="btn btn-outline-success w-100 p-3 text-start profile-btn">
                            <div class="fw-bold text-success mb-1"><i class="bi bi-building me-1"></i>Small Day</div>
                            <small class="text-muted d-block">25 Patients</small>
                        </button>
                    </div>
                    <div class="col-6 col-md-4">
                        <button type="button" onclick="runMasterProfile('normal_day')" class="btn btn-outline-info w-100 p-3 text-start profile-btn">
                            <div class="fw-bold text-info mb-1"><i class="bi bi-hospital me-1"></i>Normal Day</div>
                            <small class="text-muted d-block">50 Patients</small>
                        </button>
                    </div>
                    <div class="col-6 col-md-4">
                        <button type="button" onclick="runMasterProfile('busy_day')" class="btn btn-outline-warning text-dark w-100 p-3 text-start profile-btn">
                            <div class="fw-bold text-dark mb-1"><i class="bi bi-people-fill me-1"></i>Busy Day</div>
                            <small class="text-muted d-block">100 Patients</small>
                        </button>
                    </div>
                    <div class="col-6 col-md-4">
                        <button type="button" onclick="runMasterProfile('high_load')" class="btn btn-outline-danger w-100 p-3 text-start profile-btn">
                            <div class="fw-bold text-danger mb-1"><i class="bi bi-fire me-1"></i>High Load</div>
                            <small class="text-muted d-block">200 Patients</small>
                        </button>
                    </div>
                    <div class="col-6 col-md-4">
                        <button type="button" onclick="document.getElementById('customConfigCollapse').classList.toggle('show')" class="btn btn-outline-dark w-100 p-3 text-start profile-btn">
                            <div class="fw-bold text-dark mb-1"><i class="bi bi-sliders me-1"></i>Custom</div>
                            <small class="text-muted d-block">Configurable</small>
                        </button>
                    </div>
                </div>

                <!-- Custom Parameters Collapse -->
                <div class="collapse mb-4" id="customConfigCollapse">
                    <div class="card card-body bg-light border-0">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-sliders me-2"></i>Custom Branching Distribution Config</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Patient Count</label>
                                <input type="number" id="customPatients" class="form-control form-control-sm" value="30" min="1" max="500">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">OPD Probability (%)</label>
                                <input type="number" id="customOpd" class="form-control form-control-sm" value="85" min="0" max="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Laboratory Probability (%)</label>
                                <input type="number" id="customLab" class="form-control form-control-sm" value="40" min="0" max="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Pharmacy Probability (%)</label>
                                <input type="number" id="customPhm" class="form-control form-control-sm" value="60" min="0" max="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">IPD Admission Probability (%)</label>
                                <input type="number" id="customIpd" class="form-control form-control-sm" value="15" min="0" max="100">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" onclick="runCustomMasterSimulation()" class="btn btn-primary btn-sm w-100 fw-bold py-2">
                                    <i class="bi bi-play-fill me-1"></i>RUN CUSTOM SIMULATION
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="d-grid gap-2">
                    <button type="button" onclick="runAction('verify_master_integrity')" class="btn btn-info text-white font-weight-bold py-2">
                        <i class="bi bi-shield-check me-2"></i>VERIFY MASTER DATA & AI-HODE INTEGRITY
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: System Diagnostics & Safety Layer -->
        <div class="col-lg-5">
            <!-- Diagnostics Controls -->
            <div class="sim-card">
                <h5 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-speedometer2 me-2 text-primary"></i>System Diagnostics</h5>
                <div class="d-grid gap-2">
                    <button type="button" onclick="runAction('safety_check')" class="btn btn-info text-white font-weight-bold">
                        <i class="bi bi-shield-check me-2"></i>RUN SAFETY CHECK
                    </button>
                    <button type="button" onclick="runAction('dry_run')" class="btn btn-warning text-dark font-weight-bold">
                        <i class="bi bi-lightning-charge me-2"></i>RUN DRY TEST
                    </button>
                </div>
            </div>

            <!-- Safety Status Panel -->
            <div class="sim-card">
                <h5 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Safety Protection Layer</h5>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                        <span><i class="bi bi-envelope-slash text-danger me-2"></i>Email Dispatch Block:</span>
                        <span class="badge bg-success">ACTIVE</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                        <span><i class="bi bi-credit-card-2-front text-danger me-2"></i>Razorpay Live Call Block:</span>
                        <span class="badge bg-success">ACTIVE</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2">
                        <span><i class="bi bi-database-check text-primary me-2"></i>PostgreSQL Atomic Isolation:</span>
                        <span class="badge bg-success">ACTIVE</span>
                    </li>
                </ul>
            </div>

            <!-- Danger Zone: Reset Simulation Run -->
            <div class="sim-card border-danger">
                <h5 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="bi bi-exclamation-octagon me-2"></i>Danger Zone</h5>
                <a href="reset_simulation.php" class="btn btn-outline-danger w-100 py-2 font-weight-bold fw-bold">
                    <i class="bi bi-trash3 me-2"></i>RESET SELECTED SIMULATION RUN
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function showConsole(title, content) {
    document.getElementById('outputTitle').innerHTML = '<i class="bi bi-terminal me-2"></i>' + title;
    document.getElementById('outputContent').innerHTML = '<pre class="text-success mb-0">' + content + '</pre>';
    document.getElementById('outputContainer').style.display = 'block';
    document.getElementById('outputContainer').scrollIntoView({ behavior: 'smooth' });
}

function runMasterProfile(profileKey) {
    showConsole('Executing Master Profile (' + profileKey.toUpperCase() + ')...', 'Orchestrating cross-module simulation...');
    
    var formData = new FormData();
    formData.append('action', 'run_master_simulation');
    formData.append('profile', profileKey);

    fetch('simulation_controller.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showConsole('MASTER SIMULATION RESULT', JSON.stringify(data, null, 2));
        setTimeout(() => location.reload(), 3000);
    })
    .catch(err => {
        showConsole('MASTER SIMULATION ERROR', err.toString());
    });
}

function runCustomMasterSimulation() {
    showConsole('Executing Custom Master Simulation...', 'Sending custom parameters...');
    
    var formData = new FormData();
    formData.append('action', 'run_master_simulation');
    formData.append('profile', 'custom');
    formData.append('patient_count', document.getElementById('customPatients').value);
    formData.append('opd_probability', document.getElementById('customOpd').value);
    formData.append('lab_probability', document.getElementById('customLab').value);
    formData.append('pharmacy_probability', document.getElementById('customPhm').value);
    formData.append('ipd_probability', document.getElementById('customIpd').value);

    fetch('simulation_controller.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showConsole('CUSTOM SIMULATION RESULT', JSON.stringify(data, null, 2));
        setTimeout(() => location.reload(), 3000);
    })
    .catch(err => {
        showConsole('CUSTOM SIMULATION ERROR', err.toString());
    });
}

function runAction(action) {
    showConsole('Executing ' + action.toUpperCase() + '...', 'Sending request to simulation controller...');
    
    var formData = new FormData();
    formData.append('action', action);

    fetch('simulation_controller.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showConsole(action.toUpperCase() + ' RESULT', JSON.stringify(data, null, 2));
        setTimeout(() => location.reload(), 2500);
    })
    .catch(err => {
        showConsole(action.toUpperCase() + ' ERROR', err.toString());
    });
}
</script>

</body>
</html>
