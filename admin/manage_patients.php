<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);

    $stmt = $conn->prepare("
        SELECT u.id AS user_id, p.patient_id, u.full_name, u.email, p.phone
        FROM users u
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.role='patient'
        AND (u.full_name LIKE ? OR u.email LIKE ? OR p.phone LIKE ?)
    ");

    $like = "%".$search."%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $patients = $stmt->get_result();

} else {

    $patients = $conn->query("
        SELECT u.id AS user_id, p.patient_id, u.full_name, u.email, p.phone
        FROM users u
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.role='patient'
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Patients</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">

<div class="container mt-5">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Patients</h2>
    <a href="add_patient.php" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Add New Patient
    </a>
</div>

<form method="GET" class="mb-4">

<div class="input-group">

<input
type="text"
name="search"
class="form-control"
placeholder="Search by Name, Email or Phone"
value="<?= htmlspecialchars($search) ?>">

<button class="btn btn-primary">
Search
</button>

</div>

</form>

<table class="table table-bordered table-striped">

<thead class="table-dark">

<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Actions</th>
</tr>

</thead>

<tbody>

<?php while($row = ($patients)->fetch_assoc()){ ?>

<tr>

<td><?= $row['user_id']; ?></td>

<td><?= htmlspecialchars($row['full_name']); ?></td>

<td><?= htmlspecialchars($row['email']); ?></td>

<td><?= htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>

<td>
    <?php if (!empty($row['patient_id'])): ?>
        <a href="book_appointment.php?patient_id=<?= $row['patient_id']; ?>" class="btn btn-sm btn-success me-1">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </a>
    <?php endif; ?>
    <a href="view_patient.php?id=<?= $row['user_id']; ?>" class="btn btn-sm btn-info text-white">
        <i class="bi bi-eye"></i> View Details
    </a>
</td>

</tr>

<?php } ?>

</tbody>

</table>

<a href="dashboard.php" class="btn btn-secondary">
← Back to Dashboard
</a>

</div>

</body>
</html>
