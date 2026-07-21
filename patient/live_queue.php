<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live OPD Queue | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-modern shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="dashboard.php">
            🏥 <strong>Narayan Hospital</strong> <span class="badge bg-success-subtle text-success fs-8">Live Queue</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-secondary d-none d-md-inline">Welcome, <strong><?= htmlspecialchars($_SESSION['patient_name']); ?></strong></span>
            <a href="dashboard.php" class="btn btn-modern btn-outline-primary py-2 px-3">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="../logout.php" class="btn btn-modern btn-outline-danger py-2 px-3">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container mt-5">

    <div class="row align-items-center mb-4">
        <div class="col">
            <p class="text-secondary mb-1">Narayan Live OPD Status</p>
            <h2>Live Doctor Queue Monitor</h2>
        </div>
        <div class="col-auto">
            <span class="badge bg-danger-subtle text-danger badge-modern"><i class="bi bi-record-fill"></i> Auto-Refreshing (5s)</span>
        </div>
    </div>

    <!-- Live Queue Table -->
    <div class="card-modern">
        <div class="card-body-modern p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>Doctor Name</th>
                            <th>Department</th>
                            <th>Doctor Status</th>
                            <th>Currently Serving</th>
                            <th>Patients Waiting</th>
                        </tr>
                    </thead>
                    <tbody id="queue-tbody">
                        <!-- Skeleton loader initially -->
                        <tr>
                            <td colspan="5" class="py-4 text-center">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2 text-secondary">Loading live queue status...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<footer class="text-center py-4 mt-5 text-secondary border-top">
    Narayan Hospital OPD Management System &copy; <?= date('Y') ?>
</footer>

<script>
function getStatusBadge(status) {
    switch (status) {
        case 'Available':
            return '<span class="badge bg-success-subtle text-success badge-modern">🟢 Available</span>';
        case 'Busy':
            return '<span class="badge bg-warning-subtle text-warning badge-modern">🟡 Busy</span>';
        case 'Break':
            return '<span class="badge bg-secondary-subtle text-secondary badge-modern">☕ On Break</span>';
        case 'Emergency':
            return '<span class="badge bg-danger-subtle text-danger badge-modern">🚨 Emergency</span>';
        case 'Offline':
            return '<span class="badge bg-dark-subtle text-dark badge-modern">⚫ Offline</span>';
        case 'Leave':
            return '<span class="badge bg-danger-subtle text-danger badge-modern">❌ On Leave</span>';
        default:
            return `<span class="badge bg-light text-dark badge-modern">${status}</span>`;
    }
}

function fetchQueues() {
    fetch('get_all_queues.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }
            
            const tbody = document.getElementById('queue-tbody');
            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-5">
                            <i class="bi bi-calendar2-x display-6 d-block mb-3 text-muted"></i>
                            No doctors registered in the system.
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            data.forEach(item => {
                html += `
                    <tr>
                        <td><strong>Dr. ${escapeHTML(item.doctor_name)}</strong></td>
                        <td>${escapeHTML(item.department)}</td>
                        <td>${getStatusBadge(item.status)}</td>
                        <td><span class="fw-bold text-primary">#${item.live_token}</span></td>
                        <td><span class="badge bg-primary-subtle text-primary py-2 px-3 fw-semibold" style="border-radius: 8px;">${item.waiting_count} waiting</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        })
        .catch(error => {
            console.error('Error fetching live queue data:', error);
        });
}

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

// Initial fetch
fetchQueues();

// Refresh queue every 5 seconds
setInterval(fetchQueues, 5000);
</script>

</body>
</html>
