<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

if (isset($_POST['update_stock'])) {
    $bg = $_POST['blood_group'];
    $units = intval($_POST['units_available']);

    $stmt = $conn->prepare("UPDATE blood_bank SET units_available = ?, last_updated = CURRENT_TIMESTAMP WHERE blood_group = ?");
    $stmt->bind_param("is", $units, $bg);

    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Update Blood Bank', 'Updated blood group ' . $bg . ' stock to ' . $units . ' units.');
        $message = "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> Blood bank stock updated!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to update stock.</div>";
    }
}

$result = $conn->query("SELECT * FROM blood_bank ORDER BY blood_group ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Bank Stock Management | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>🩸 24x7 Blood Bank Management</h2>
            <p class="text-muted">Real-time Blood Stock Inventory</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>

    <?= $message; ?>

    <div class="row g-4">
        <?php if ($result): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 rounded-4 text-center p-3">
                    <h1 class="text-danger fw-bold display-4"><?= htmlspecialchars($row['blood_group']); ?></h1>
                    <h4 class="fw-bold mb-3"><?= $row['units_available']; ?> Units</h4>
                    
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="blood_group" value="<?= $row['blood_group']; ?>">
                        <input type="number" name="units_available" class="form-control text-center" value="<?= $row['units_available']; ?>" min="0" required>
                        <button type="submit" name="update_stock" class="btn btn-danger btn-sm"><i class="bi bi-save"></i> Save</button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
