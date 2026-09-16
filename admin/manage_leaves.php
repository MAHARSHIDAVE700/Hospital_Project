<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// Handle Approve / Reject Action
if (isset($_POST['action_leave'])) {
    $leaveId = intval($_POST['leave_id']);
    $newStatus = trim($_POST['status']);
    $remarks = trim($_POST['admin_remarks'] ?? '');

    if (in_array($newStatus, ['Approved', 'Rejected'])) {
        $stmt = $conn->prepare("UPDATE doctor_leaves SET status=?, admin_remarks=? WHERE leave_id=?");
        $stmt->bind_param("ssi", $newStatus, $remarks, $leaveId);
        
        if ($stmt->execute()) {
            // Get leave details for notifications and doctor status update
            $leaveInfo = $conn->query("
                SELECT l.*, d.doctor_id, d.full_name, d.email 
                FROM doctor_leaves l 
                JOIN doctors d ON l.doctor_id = d.doctor_id 
                WHERE l.leave_id = $leaveId
            ")->fetch_assoc();

            if ($leaveInfo && $newStatus === 'Approved') {
                $today = date('Y-m-d');
                if ($today >= $leaveInfo['start_date'] && $today <= $leaveInfo['end_date']) {
                    $docId = $leaveInfo['doctor_id'];
                    $conn->query("UPDATE doctors SET status='Leave' WHERE doctor_id=$docId");
                }
            }

            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Doctor Leave Review', "Set Leave Request #$leaveId to $newStatus");
            $message = "<div class='alert alert-success alert-dismissible fade show'><i class='bi bi-check-circle-fill me-2'></i>Leave request updated to <strong>$newStatus</strong>.</div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='bi bi-exclamation-triangle-fill me-2'></i>Failed to update leave request.</div>";
        }
    }
}

// Fetch all leave requests
$leaves = $conn->query("
    SELECT l.*, d.full_name AS doctor_name, d.specialization, dep.department_name
    FROM doctor_leaves l
    JOIN doctors d ON l.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    ORDER BY CASE WHEN l.status = 'Pending' THEN 1 ELSE 2 END, l.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctor Leaves | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="hms-layout">
    <!-- Sidebar -->
    <aside class="hms-sidebar" id="sidebar">
        <div class="hms-sidebar-brand">
            <span>🏥</span>
            <strong>Narayan Admin</strong>
        </div>
        <div class="hms-sidebar-menu">
            <a href="dashboard.php" class="hms-sidebar-item">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="manage_doctors.php" class="hms-sidebar-item">
                <i class="bi bi-person-badge"></i> Doctors
            </a>
            <a href="manage_leaves.php" class="hms-sidebar-item active">
                <i class="bi bi-calendar2-range"></i> Doctor Leaves
            </a>
            <a href="manage_doctor_schedule.php" class="hms-sidebar-item">
                <i class="bi bi-calendar3"></i> Doctor Schedules
            </a>
            <a href="manage_appointments.php" class="hms-sidebar-item">
                <i class="bi bi-calendar2-check"></i> Appointments
            </a>
        </div>
        <div class="hms-sidebar-footer">
            <a href="../logout.php" class="hms-sidebar-item text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <main class="hms-main" id="main-content">
        <header class="hms-topbar">
            <div class="hms-topbar-left">
                <div class="hms-breadcrumb">
                    <span>Admin</span>
                    <span><i class="bi bi-chevron-right text-muted fs-8"></i></span>
                    <span class="hms-breadcrumb-item-active">Manage Doctor Leaves</span>
                </div>
            </div>
            <div class="hms-topbar-right">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
            </div>
        </header>

        <div class="hms-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">🌴 Doctor Leave Requests</h3>
                    <p class="text-muted mb-0">Review, approve, or reject doctor leave applications.</p>
                </div>
            </div>

            <?= $message; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Doctor</th>
                                    <th>Department</th>
                                    <th>Leave Period</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($leaves && $leaves->num_rows > 0): ?>
                                    <?php while ($row = $leaves->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $row['leave_id']; ?></td>
                                            <td>
                                                <strong class="text-dark">Dr. <?= htmlspecialchars($row['doctor_name']); ?></strong>
                                                <div class="small text-muted"><?= htmlspecialchars($row['specialization']); ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($row['department_name'] ?? 'General'); ?></span></td>
                                            <td>
                                                <span class="fw-bold text-primary"><?= date('d M Y', strtotime($row['start_date'])); ?></span>
                                                <span class="text-muted"> to </span>
                                                <span class="fw-bold text-primary"><?= date('d M Y', strtotime($row['end_date'])); ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($row['reason']); ?></td>
                                            <td>
                                                <?php if ($row['status'] === 'Approved'): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Approved</span>
                                                <?php elseif ($row['status'] === 'Rejected'): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Rejected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['admin_remarks'])): ?>
                                                    <div class="small text-muted mt-1"><em>Remarks: <?= htmlspecialchars($row['admin_remarks']); ?></em></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] === 'Pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#actionModal<?= $row['leave_id']; ?>" onclick="setModalStatus(<?= $row['leave_id']; ?>, 'Approved')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#actionModal<?= $row['leave_id']; ?>" onclick="setModalStatus(<?= $row['leave_id']; ?>, 'Rejected')">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>

                                                    <!-- Action Modal -->
                                                    <div class="modal fade" id="actionModal<?= $row['leave_id']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="POST">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Review Leave Request #<?= $row['leave_id']; ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="leave_id" value="<?= $row['leave_id']; ?>">
                                                                        <input type="hidden" name="status" id="statusInput<?= $row['leave_id']; ?>" value="Approved">
                                                                        
                                                                        <p>You are about to <strong id="statusText<?= $row['leave_id']; ?>">Approve</strong> leave for <strong>Dr. <?= htmlspecialchars($row['doctor_name']); ?></strong> (<?= date('d M Y', strtotime($row['start_date'])); ?> to <?= date('d M Y', strtotime($row['end_date'])); ?>).</p>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label font-weight-bold">Admin Remarks (Optional)</label>
                                                                            <textarea name="admin_remarks" class="form-control" rows="3" placeholder="Enter any notes or remarks..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="action_leave" class="btn btn-primary">Confirm Action</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No leave requests found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setModalStatus(leaveId, status) {
    document.getElementById('statusInput' + leaveId).value = status;
    document.getElementById('statusText' + leaveId).innerText = status;
}
</script>
</body>
</html>
