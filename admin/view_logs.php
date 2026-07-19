<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$query = "SELECT * FROM activity_logs ORDER BY log_id DESC LIMIT 50";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Audit Logs | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>🛡️ System Audit &amp; Activity Logs</h2>
            <p class="text-muted">Real-time security and operational audit history</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $row['log_id']; ?></td>
                                <td><?= $row['user_id'] ? '#' . $row['user_id'] : 'System'; ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['role']); ?></span></td>
                                <td><strong><?= htmlspecialchars($row['action']); ?></strong></td>
                                <td><?= htmlspecialchars($row['details'] ?? '-'); ?></td>
                                <td><code><?= htmlspecialchars($row['ip_address']); ?></code></td>
                                <td><?= $row['created_at']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4">No audit logs recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
