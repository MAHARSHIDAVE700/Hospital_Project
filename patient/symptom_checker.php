<?php
session_start();
include "../includes/config.php";

$recommendation = null;
$symptomsInput = "";

if (isset($_POST['check_symptoms'])) {
    $symptomsInput = strtolower(trim($_POST['symptoms']));
    
    $departmentRules = [
        'Cardiology' => ['chest pain', 'heart', 'palpitations', 'shortness of breath', 'bp', 'blood pressure', 'cardiac'],
        'Orthopedics' => ['bone', 'joint', 'fracture', 'knee pain', 'back pain', 'sprain', 'arthritis', 'ligament'],
        'Pediatrics' => ['child', 'infant', 'baby', 'fever in kid', 'vaccination', 'pediatric'],
        'Neurology' => ['headache', 'migraine', 'dizziness', 'seizure', 'numbness', 'paralysis', 'brain', 'nerve'],
        'Dermatology' => ['skin', 'rash', 'itching', 'acne', 'allergy', 'eczema', 'hair fall'],
        'ENT' => ['ear', 'nose', 'throat', 'sinus', 'tonsil', 'hearing', 'cough'],
        'General Medicine' => ['fever', 'cold', 'body ache', 'fatigue', 'weakness', 'stomach ache', 'vomiting']
    ];
    
    $matchedDepartment = "General Medicine";
    $highestScore = 0;
    
    foreach ($departmentRules as $dept => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            if (strpos($symptomsInput, $kw) !== false) {
                $score += 1;
            }
        }
        if ($score > $highestScore) {
            $highestScore = $score;
            $matchedDepartment = $dept;
        }
    }
    
    // Fetch available doctors in matched department
    $docQuery = $conn->query("
        SELECT d.doctor_id, d.full_name, dep.department_name 
        FROM doctors d 
        JOIN departments dep ON d.department_id = dep.department_id 
        WHERE dep.department_name LIKE '%$matchedDepartment%' 
        LIMIT 3
    ");
    
    $suggestedDoctors = [];
    if ($docQuery) {
        while ($d = $docQuery->fetch_assoc()) {
            $suggestedDoctors[] = $d;
        }
    }
    
    $recommendation = [
        'department' => $matchedDepartment,
        'doctors' => $suggestedDoctors
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Symptom Checker | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="text-center mb-4">
                    <div class="fs-1 text-primary">🤖</div>
                    <h2 class="fw-bold">AI Symptom Checker</h2>
                    <p class="text-muted">Describe your symptoms to receive instant department & specialist recommendations.</p>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-chat-pulse"></i> Describe How You Feel</label>
                        <textarea name="symptoms" class="form-control" rows="4" placeholder="E.g., I have severe chest pain and breathlessness..." required><?= htmlspecialchars($symptomsInput); ?></textarea>
                    </div>
                    <button type="submit" name="check_symptoms" class="btn btn-primary btn-lg w-100 fw-bold">
                        <i class="bi bi-cpu"></i> Analyze Symptoms
                    </button>
                </form>

                <?php if ($recommendation): ?>
                <div class="mt-4 p-4 bg-primary-subtle border border-primary rounded-3">
                    <h5 class="text-primary fw-bold"><i class="bi bi-lightbulb-fill"></i> Recommended Department: <?= htmlspecialchars($recommendation['department']); ?></h5>
                    <p class="mb-3">Based on your description, we recommend consulting our <strong><?= htmlspecialchars($recommendation['department']); ?></strong> specialists.</p>

                    <h6 class="fw-bold text-dark mb-2">Available Doctors:</h6>
                    <div class="list-group mb-3">
                        <?php if (count($recommendation['doctors']) > 0): ?>
                            <?php foreach ($recommendation['doctors'] as $doc): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Dr. <?= htmlspecialchars($doc['full_name']); ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($doc['department_name']); ?></small>
                                    </div>
                                    <a href="../appointment.php" class="btn btn-sm btn-success">Book Slot</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-muted">No specific doctor list found. You can book an appointment with any available general physician.</div>
                        <?php endif; ?>
                    </div>

                    <a href="../appointment.php" class="btn btn-success fw-bold w-100">Proceed to Book Appointment</a>
                </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
