<?php
/**
 * AI-HODE Dataset Dashboard Interface
 * Path: ai_hode/datasets/index.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/DatasetExtractor.php';
require_once __DIR__ . '/DatasetValidator.php';

$extractor = new DatasetExtractor();
$validator = new DatasetValidator();

// Fetch summary metrics
$arrivalRes = $conn->query("SELECT COUNT(*) as total_rows, MIN(arrival_date) as min_date, MAX(arrival_date) as max_date FROM ai_dataset_patient_arrivals");
$arrivalData = $arrivalRes ? $arrivalRes->fetch_assoc() : ['total_rows' => 0, 'min_date' => '-', 'max_date' => '-'];

$waitRes = $conn->query("SELECT COUNT(*) as total_rows, AVG(actual_wait_minutes) as avg_wait FROM ai_dataset_waiting_time");
$waitData = $waitRes ? $waitRes->fetch_assoc() : ['total_rows' => 0, 'avg_wait' => 0.0];

$bedRes = $conn->query("SELECT COUNT(*) as total_rows, AVG(occupancy_rate_pct) as avg_occ FROM ai_dataset_bed_occupancy");
$bedData = $bedRes ? $bedRes->fetch_assoc() : ['total_rows' => 0, 'avg_occ' => 0.0];

$workloadRes = $conn->query("SELECT COUNT(*) as total_rows, AVG(workload_score) as avg_score FROM ai_dataset_doctor_workload");
$workloadData = $workloadRes ? $workloadRes->fetch_assoc() : ['total_rows' => 0, 'avg_score' => 0.0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-HODE — Dataset Extraction & Feature Engineering</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-indigo: #6366f1;
            --accent-amber: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .title-group h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-indigo));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .title-group p {
            color: var(--text-muted);
            margin-top: 0.25rem;
            font-size: 0.95rem;
        }

        .badge-read-only {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--accent-emerald);
            color: var(--accent-emerald);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent-cyan);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.arrivals::before { background: var(--accent-cyan); }
        .stat-card.waiting::before { background: var(--accent-indigo); }
        .stat-card.occupancy::before { background: var(--accent-emerald); }
        .stat-card.workload::before { background: var(--accent-amber); }

        .stat-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;

        }

        .stat-card .value {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0.75rem 0 0.25rem 0;
        }

        .stat-card .subtext {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .action-toolbar {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            background: #334155;
            color: var(--text-main);
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn:hover {
            background: #475569;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-indigo));
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.95;
        }

        .btn-success {
            background: var(--accent-emerald);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 2rem;
        }

        .content-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;

            background: rgba(15, 23, 42, 0.5);
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        pre {
            background: #090d16;
            color: #38bdf8;
            padding: 1.25rem;
            border-radius: 10px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="title-group">
                <h1>AI-HODE Dataset Extraction & Feature Engineering</h1>
                <p>Isolated Operational Dataset Preparation for AI Prediction Models</p>
            </div>
            <div class="badge-read-only">
                <i class="fa-solid fa-shield-halved"></i> Source Data: Read-Only Isolated
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card arrivals">
                <div class="header">
                    <span>Patient Arrivals</span>
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="value"><?= number_format($arrivalData['total_rows']) ?></div>
                <div class="subtext">Target: Arrival Volume | Date Range: <?= htmlspecialchars($arrivalData['min_date'] ?? 'N/A') ?> to <?= htmlspecialchars($arrivalData['max_date'] ?? 'N/A') ?></div>
            </div>

            <div class="stat-card waiting">
                <div class="header">
                    <span>OPD Waiting Time</span>
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="value"><?= number_format($waitData['total_rows']) ?></div>
                <div class="subtext">Target: Wait Mins | Avg Wait: <?= number_format((float)$waitData['avg_wait'], 1) ?> mins</div>
            </div>

            <div class="stat-card occupancy">
                <div class="header">
                    <span>Bed Occupancy</span>
                    <i class="fa-solid fa-bed"></i>
                </div>
                <div class="value"><?= number_format($bedData['total_rows']) ?></div>
                <div class="subtext">Target: Occupancy % | Avg Rate: <?= number_format((float)$bedData['avg_occ'], 1) ?>%</div>
            </div>

            <div class="stat-card workload">
                <div class="header">
                    <span>Doctor Workload</span>
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <div class="value"><?= number_format($workloadData['total_rows']) ?></div>
                <div class="subtext">Target: Workload Score | Avg Score: <?= number_format((float)$workloadData['avg_score'], 1) ?></div>
            </div>
        </div>

        <!-- Action Toolbar -->
        <div class="action-toolbar">
            <div class="btn-group">
                <button class="btn" onclick="discoverSourceData()">
                    <i class="fa-solid fa-database"></i> Discover Source Data
                </button>
                <button class="btn btn-primary" onclick="extractAllDatasets()">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Extract All Datasets
                </button>
                <button class="btn btn-success" onclick="validateDatasets()">
                    <i class="fa-solid fa-check-double"></i> Validate Datasets
                </button>
            </div>
            <div class="btn-group">
                <select id="datasetSelect" class="btn" style="background: #1e293b; color: var(--text-main);">
                    <option value="patient_arrivals">Patient Arrivals Dataset</option>
                    <option value="waiting_time">Waiting Time Dataset</option>
                    <option value="bed_occupancy">Bed Occupancy Dataset</option>
                    <option value="doctor_workload">Doctor Workload Dataset</option>
                </select>
                <button class="btn" onclick="previewSelectedDataset()">
                    <i class="fa-solid fa-eye"></i> Preview Dataset
                </button>
                <button class="btn" onclick="exportSelectedCSV()">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Dynamic Output Section -->
        <div class="content-card">
            <h2 id="outputTitle"><i class="fa-solid fa-table-list"></i> Dataset Preview & Diagnostics</h2>
            <div id="outputContainer">
                <p style="color: var(--text-muted);">Click any toolbar button above to discover source schemas, trigger feature extraction, run integrity validations, preview dataset records, or export CSV files.</p>
            </div>
        </div>
    </div>

    <script>
        function discoverSourceData() {
            document.getElementById('outputTitle').innerHTML = '<i class="fa-solid fa-database"></i> Source Schema Discovery';
            document.getElementById('outputContainer').innerHTML = '<p><i class="fa-solid fa-spinner fa-spin"></i> Discovering actual PostgreSQL tables and columns...</p>';

            fetch('dataset_controller.php?action=discover_source_data')
                .then(r => r.json())
                .then(data => {
                    let html = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    document.getElementById('outputContainer').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('outputContainer').innerHTML = '<p style="color: #ef4444;">Error discovering schema: ' + err + '</p>';
                });
        }

        function extractAllDatasets() {
            document.getElementById('outputTitle').innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Feature Extraction Progress';
            document.getElementById('outputContainer').innerHTML = '<p><i class="fa-solid fa-spinner fa-spin"></i> Running feature engineering and dataset extraction...</p>';

            fetch('dataset_controller.php?action=extract_all')
                .then(r => r.json())
                .then(data => {
                    let html = '<div style="color: #10b981; font-weight: 600; margin-bottom: 1rem;"><i class="fa-solid fa-circle-check"></i> ' + data.message + '</div>';
                    html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    document.getElementById('outputContainer').innerHTML = html;
                    setTimeout(() => location.reload(), 2500);
                })
                .catch(err => {
                    document.getElementById('outputContainer').innerHTML = '<p style="color: #ef4444;">Error extracting datasets: ' + err + '</p>';
                });
        }

        function validateDatasets() {
            document.getElementById('outputTitle').innerHTML = '<i class="fa-solid fa-check-double"></i> Dataset Integrity Validation';
            document.getElementById('outputContainer').innerHTML = '<p><i class="fa-solid fa-spinner fa-spin"></i> Validating dataset ranges, non-negative constraints, and FK references...</p>';

            fetch('dataset_controller.php?action=validate_datasets')
                .then(r => r.json())
                .then(data => {
                    let html = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    document.getElementById('outputContainer').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('outputContainer').innerHTML = '<p style="color: #ef4444;">Error validating datasets: ' + err + '</p>';
                });
        }

        function previewSelectedDataset() {
            const selected = document.getElementById('datasetSelect').value;
            document.getElementById('outputTitle').innerHTML = '<i class="fa-solid fa-eye"></i> Previewing: ' + selected;
            document.getElementById('outputContainer').innerHTML = '<p><i class="fa-solid fa-spinner fa-spin"></i> Fetching records...</p>';

            fetch('dataset_controller.php?action=preview_dataset&type=' + selected)
                .then(r => r.json())
                .then(data => {
                    if (data.data.length === 0) {
                        document.getElementById('outputContainer').innerHTML = '<p style="color: var(--text-muted);">No records found in this dataset table. Click [ Extract All Datasets ] to populate.</p>';
                        return;
                    }
                    let html = '<div class="table-responsive"><table><thead><tr>';
                    let keys = Object.keys(data.data[0]);
                    keys.forEach(k => html += '<th>' + k + '</th>');
                    html += '</tr></thead><tbody>';

                    data.data.forEach(row => {
                        html += '<tr>';
                        keys.forEach(k => html += '<td>' + (row[k] !== null ? row[k] : '-') + '</td>');
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    document.getElementById('outputContainer').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('outputContainer').innerHTML = '<p style="color: #ef4444;">Error previewing dataset: ' + err + '</p>';
                });
        }

        function exportSelectedCSV() {
            const selected = document.getElementById('datasetSelect').value;
            window.location.href = 'dataset_controller.php?action=export_csv&type=' + selected;
        }
    </script>
</body>
</html>
