<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $newStatus = ($_GET['action'] === 'dispatch') ? 'Dispatched' : 'Completed';
    
    $stmt = $conn->prepare("UPDATE ambulance_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $newStatus, $id);
    $stmt->execute();
    header("Location: ambulance_requests.php");
    exit();
}

$result = $conn->query("SELECT * FROM ambulance_requests ORDER BY request_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ambulance Dispatch Control | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>🚨 Emergency Ambulance Dispatch Monitor</h2>
            <p class="text-muted">Manage real-time ambulance dispatch requests</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Req ID</th>
                            <th>Patient Name</th>
                            <th>Contact Phone</th>
                            <th>Pickup Address</th>
                            <th>Status</th>
                            <th>Time Requested</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $row['request_id']; ?></td>
                                <td><strong><?= htmlspecialchars($row['patient_name']); ?></strong></td>
                                <td><a href="tel:<?= $row['phone']; ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-telephone"></i> <?= htmlspecialchars($row['phone']); ?></a></td>
                                <td><?= htmlspecialchars($row['pickup_address']); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Requested'): ?>
                                        <span class="badge bg-danger">🚨 Requested</span>
                                    <?php elseif ($row['status'] === 'Dispatched'): ?>
                                        <span class="badge bg-warning text-dark">🚑 Dispatched</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">✅ Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['created_at']; ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Requested'): ?>
                                        <a href="ambulance_requests.php?action=dispatch&id=<?= $row['request_id']; ?>" class="btn btn-warning btn-sm">Dispatch Ambulance</a>
                                    <?php elseif ($row['status'] === 'Dispatched'): ?>
                                        <a href="ambulance_requests.php?action=complete&id=<?= $row['request_id']; ?>" class="btn btn-success btn-sm">Mark Completed</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Finished</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4">No active ambulance requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
