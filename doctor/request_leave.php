<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['doctor_id'];

// Get doctor email & doctor_id
$userQuery = $conn->query("SELECT email FROM users WHERE id='$userID'");
$user = $userQuery ? $userQuery->fetch_assoc() : null;
$email = $user ? $user['email'] : '';

$doctorQuery = $conn->query("SELECT doctor_id, full_name FROM doctors WHERE LOWER(email)=LOWER('$email')");
$doctor = $doctorQuery ? $doctorQuery->fetch_assoc() : null;

if (!$doctor) {
    die("Doctor profile not found.");
}
$doctorID = $doctor['doctor_id'];

$message = "";

// Handle Leave Submission
if (isset($_POST['submit_leave'])) {
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate   = trim($_POST['end_date'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');

    if (empty($startDate) || empty($endDate) || empty($reason)) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>All fields are required.</div>";
    } elseif ($startDate > $endDate) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>Start date cannot be after End date.</div>";
    } elseif ($startDate < date('Y-m-d')) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>Leave start date cannot be in the past.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO doctor_leaves (doctor_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("isss", $doctorID, $startDate, $endDate, $reason);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['doctor_id'], 'doctor', 'Leave Request', "Requested leave from $startDate to $endDate");
            $message = "<div class='alert alert-success'><i class='bi bi-check-circle-fill me-2'></i>Leave request submitted successfully! Pending admin approval.</div>";
        } else {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill me-2'></i>Failed to submit leave request.</div>";
        }
    }
}

// Fetch doctor's past leave requests
$leavesQuery = $conn->query("SELECT * FROM doctor_leaves WHERE doctor_id='$doctorID' ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Leave | Doctor Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4 shadow">
    <div class="container">
        <span class="navbar-brand">👨‍⚕️ Doctor Panel - Leave Management</span>
        <a href="dashboard.php" class="btn btn-light"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>
</nav>

<div class="container">
    <div class="row">
        <!-- Leave Form -->
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-plus me-2"></i>Apply for Leave</h5>
                </div>
                <div class="card-body">
                    <?= $message; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" min="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" min="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Reason for Leave</label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="Explain reason for leave..." required></textarea>
                        </div>
                        <button type="submit" name="submit_leave" class="btn btn-primary w-100 shadow-sm">
                            <i class="bi bi-send-fill me-1"></i> Submit Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>My Leave Requests</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Dates</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Admin Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($leavesQuery && $leavesQuery->num_rows > 0): ?>
                                    <?php while ($row = $leavesQuery->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <small class="fw-bold text-primary"><?= date('d M Y', strtotime($row['start_date'])); ?></small><br>
                                                <small class="text-muted">to</small> <?= date('d M Y', strtotime($row['end_date'])); ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['reason']); ?></td>
                                            <td>
                                                <?php if ($row['status'] === 'Approved'): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>
                                                <?php elseif ($row['status'] === 'Rejected'): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?= htmlspecialchars($row['admin_remarks'] ?? '-'); ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No leave requests submitted yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
