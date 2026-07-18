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
        SELECT id, full_name, email
        FROM users
        WHERE role='patient'
        AND (full_name LIKE ? OR email LIKE ?)
    ");

    $like = "%".$search."%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $patients = $stmt->get_result();

} else {

    $patients = $conn->query("
        SELECT id, full_name, email
        FROM users
        WHERE role='patient'
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Patients</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">

<div class="container mt-5">

<h2>Manage Patients</h2>

<form method="GET" class="mb-4">

<div class="input-group">

<input
type="text"
name="search"
class="form-control"
placeholder="Search by Name or Email"
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
</tr>

</thead>

<tbody>

<?php while($row = ($patients)->fetch_assoc()){ ?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= htmlspecialchars($row['full_name']); ?></td>

<td><?= htmlspecialchars($row['email']); ?></td>

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