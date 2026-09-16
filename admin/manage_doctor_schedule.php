<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// Selected Doctor ID
$selectedDocId = intval($_GET['doctor_id'] ?? 0);

// Handle Schedule Update
if (isset($_POST['save_schedule'])) {
    $docId = intval($_POST['doctor_id']);
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    foreach ($days as $day) {
        $isAvailable = isset($_POST['available'][$day]) ? 1 : 0;
        $startTime   = trim($_POST['start_time'][$day] ?? '09:00:00');
        $endTime     = trim($_POST['end_time'][$day] ?? '17:00:00');

        // Check if row exists for doctor and day
        $checkStmt = $conn->prepare("SELECT schedule_id FROM doctor_schedules WHERE doctor_id=? AND day_of_week=?");
        $checkStmt->bind_param("is", $docId, $day);
        $checkStmt->execute();
        $res = $checkStmt->get_result();

        if ($res && $res->num_rows > 0) {
            $updateStmt = $conn->prepare("UPDATE doctor_schedules SET start_time=?, end_time=?, is_available=? WHERE doctor_id=? AND day_of_week=?");
            $updateStmt->bind_param("ssiis", $startTime, $endTime, $isAvailable, $docId, $day);
            $updateStmt->execute();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("isssi", $docId, $day, $startTime, $endTime, $isAvailable);
            $insertStmt->execute();
        }
    }

    ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Doctor Schedule Update', "Updated weekly schedule for Doctor ID #$docId");
    $message = "<div class='alert alert-success alert-dismissible fade show'><i class='bi bi-check-circle-fill me-2'></i>Weekly schedule saved successfully for the doctor!</div>";
    $selectedDocId = $docId;
}

// Fetch all doctors for selection
$doctorsList = $conn->query("
    SELECT d.doctor_id, d.full_name, dep.department_name 
    FROM doctors d 
    LEFT JOIN departments dep ON d.department_id = dep.department_id 
    ORDER BY d.full_name ASC
");

// Fetch current schedule if doctor selected
$currentSchedule = [];
if ($selectedDocId > 0) {
    $schedRes = $conn->query("SELECT * FROM doctor_schedules WHERE doctor_id='$selectedDocId'");
    if ($schedRes) {
        while ($sRow = $schedRes->fetch_assoc()) {
            $currentSchedule[$sRow['day_of_week']] = $sRow;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Schedules | Admin Panel</title>
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
            <a href="manage_leaves.php" class="hms-sidebar-item">
                <i class="bi bi-calendar2-range"></i> Doctor Leaves
            </a>
            <a href="manage_doctor_schedule.php" class="hms-sidebar-item active">
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
                    <span class="hms-breadcrumb-item-active">Configure Doctor Schedules</span>
                </div>
            </div>
            <div class="hms-topbar-right">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
            </div>
        </header>

        <div class="hms-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">📅 Doctor Schedule Configuration</h3>
                    <p class="text-muted mb-0">Set working days and operating hours for doctors.</p>
                </div>
            </div>

            <?= $message; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form method="GET" class="row align-items-center g-3">
                        <div class="col-md-8">
                            <label class="form-label font-weight-bold">Select Doctor to Manage Schedule:</label>
                            <select name="doctor_id" class="form-select form-select-lg" onchange="this.form.submit()">
                                <option value="0">-- Choose Doctor --</option>
                                <?php if ($doctorsList): ?>
                                    <?php while ($doc = $doctorsList->fetch_assoc()): ?>
                                        <option value="<?= $doc['doctor_id']; ?>" <?= $selectedDocId === (int)$doc['doctor_id'] ? 'selected' : ''; ?>>
                                            Dr. <?= htmlspecialchars($doc['full_name']); ?> (<?= htmlspecialchars($doc['department_name'] ?? 'General'); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selectedDocId > 0): ?>
                <?php 
                    $docDetail = $conn->query("SELECT d.full_name, dep.department_name FROM doctors d LEFT JOIN departments dep ON d.department_id=dep.department_id WHERE d.doctor_id=$selectedDocId")->fetch_assoc();
                ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Weekly Availability & Hours: <strong>Dr. <?= htmlspecialchars($docDetail['full_name']); ?></strong></h5>
                        <span class="badge bg-light text-primary"><?= htmlspecialchars($docDetail['department_name'] ?? 'General'); ?></span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="doctor_id" value="<?= $selectedDocId; ?>">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Day of Week</th>
                                            <th>Working Status</th>
                                            <th>Start Time</th>
                                            <th>End Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                            foreach ($weekdays as $day):
                                                $dayData = $currentSchedule[$day] ?? null;
                                                $isAvail = $dayData ? (int)$dayData['is_available'] : 1; // Default available
                                                $sTime   = $dayData ? $dayData['start_time'] : '09:00:00';
                                                $eTime   = $dayData ? $dayData['end_time'] : '17:00:00';
                                        ?>
                                            <tr>
                                                <td class="fw-bold"><?= $day; ?></td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="available[<?= $day; ?>]" value="1" id="switch_<?= $day; ?>" <?= $isAvail ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="switch_<?= $day; ?>">
                                                            <?= $isAvail ? '<span class="text-success font-weight-bold">Available</span>' : '<span class="text-danger font-weight-bold">Off / Closed</span>'; ?>
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="time" name="start_time[<?= $day; ?>]" class="form-control" value="<?= substr($sTime, 0, 5); ?>">
                                                </td>
                                                <td>
                                                    <input type="time" name="end_time[<?= $day; ?>]" class="form-control" value="<?= substr($eTime, 0, 5); ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" name="save_schedule" class="btn btn-primary btn-lg shadow-sm">
                                    <i class="bi bi-save me-1"></i> Save Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center py-5 shadow-sm">
                    <i class="bi bi-person-lines-fill fs-1 text-info d-block mb-3"></i>
                    <h4>Please select a doctor from the dropdown above to view or configure their weekly working schedule.</h4>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
