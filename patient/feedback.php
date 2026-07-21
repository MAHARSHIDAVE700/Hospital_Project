<?php
session_start();
if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";
$userID = $_SESSION['patient_id'];
$patientRes = $conn->query("SELECT patient_id FROM patients WHERE user_id = '$userID'");
$patient = $patientRes->fetch_assoc();
$patientID = $patient['patient_id'];

if (isset($_POST['submit_feedback'])) {
    $doctorId = intval($_POST['doctor_id']);
    $rating = intval($_POST['rating']);
    $comments = trim($_POST['comments']);

    $stmt = $conn->prepare("INSERT INTO feedback (patient_id, doctor_id, rating, comments) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $patientID, $doctorId, $rating, $comments);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> Thank you for your feedback! Your rating has been recorded.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to record feedback.</div>";
    }
}

$doctors = $conn->query("SELECT doctor_id, full_name FROM doctors ORDER BY full_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Feedback &amp; Ratings | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h3 class="fw-bold text-primary text-center mb-3">⭐ Patient Feedback &amp; Rating</h3>
                <p class="text-muted text-center mb-4">Rate your doctor and share your experience to help us improve hospital services.</p>

                <?= $message; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Doctor</label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">Choose a Doctor</option>
                            <?php while ($d = $doctors->fetch_assoc()): ?>
                                <option value="<?= $d['doctor_id']; ?>">Dr. <?= htmlspecialchars($d['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Rating (1 to 5 Stars)</label>
                        <select name="rating" class="form-select" required>
                            <option value="5">⭐⭐⭐⭐⭐ 5 - Excellent</option>
                            <option value="4">⭐⭐⭐⭐ 4 - Very Good</option>
                            <option value="3">⭐⭐⭐ 3 - Average</option>
                            <option value="2">⭐⭐ 2 - Poor</option>
                            <option value="1">⭐ 1 - Very Bad</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Comments / Suggestions</label>
                        <textarea name="comments" class="form-control" rows="4" placeholder="Write your experience..." required></textarea>
                    </div>

                    <button type="submit" name="submit_feedback" class="btn btn-primary btn-lg w-100 fw-bold">Submit Review</button>
                </form>

                <div class="text-center mt-3">
                    <a href="dashboard.php" class="btn btn-outline-secondary">← Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
