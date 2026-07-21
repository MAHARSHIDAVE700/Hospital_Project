<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// ─── AJAX: Get time slots for a doctor ───────────────────────────────────────
if (isset($_GET['get_slots']) && isset($_GET['doctor_id'])) {
    header('Content-Type: application/json');
    $docId = intval($_GET['doctor_id']);
    $date  = trim($_GET['date'] ?? '');
    
    date_default_timezone_set('Asia/Kolkata');
    $currentDate = date('Y-m-d');
    $cutoffTime = date('H:i:s', strtotime('+1 hour'));
    
    // Check doctor live status
    $docStatusQuery = $conn->query("SELECT status FROM doctors WHERE doctor_id = '$docId'");
    $docStatusRow = $docStatusQuery ? $docStatusQuery->fetch_assoc() : null;
    $docStatus = $docStatusRow ? $docStatusRow['status'] : 'Active';
    
    // If today, hide slots if doctor is Break, Emergency, Offline, or Leave
    if ($date === $currentDate) {
        if (in_array($docStatus, ['Break', 'Emergency', 'Offline', 'Leave'])) {
            echo json_encode([]);
            exit();
        }
    }
    
    // Check if slots exist for this doctor
    $checkSlots = $conn->query("SELECT COUNT(*) AS total FROM opd_slots WHERE doctor_id = '$docId'");
    $totalSlots = $checkSlots ? $checkSlots->fetch_assoc()['total'] : 0;
    if ($totalSlots == 0) {
        // Automatically insert 10 default slots
        $defaultSlots = [
            ['09:00:00', '9:00 AM'],
            ['09:30:00', '9:30 AM'],
            ['10:00:00', '10:00 AM'],
            ['10:30:00', '10:30 AM'],
            ['11:00:00', '11:00 AM'],
            ['11:30:00', '11:30 AM'],
            ['14:00:00', '2:00 PM'],
            ['14:30:00', '2:30 PM'],
            ['15:00:00', '3:00 PM'],
            ['15:30:00', '3:30 PM']
        ];
        foreach ($defaultSlots as $s) {
            $conn->query("INSERT INTO opd_slots (doctor_id, slot_time, slot_label, max_patients, is_active) VALUES ('$docId', '{$s[0]}', '{$s[1]}', 5, 1)");
        }
    }
    
    $slotsResult = $conn->query("SELECT slot_id, slot_time, slot_label FROM opd_slots WHERE doctor_id='$docId' AND is_active=1 ORDER BY slot_time ASC");
    $slots = [];
    
    if ($slotsResult) {
        while ($slot = $slotsResult->fetch_assoc()) {
            if ($date === $currentDate) {
                if ($slot['slot_time'] < $cutoffTime) {
                    continue;
                }
            }
            
            // Check booking count for this slot on this date (skip if >= 10)
            $slotTime = $slot['slot_time'];
            $countQuery = $conn->query("
                SELECT COUNT(*) AS total 
                FROM appointments 
                WHERE doctor_id = '$docId' 
                AND appointment_date = '$date' 
                AND appointment_time = '$slotTime' 
                AND status != 'Cancelled'
            ");
            $bookedCount = $countQuery ? $countQuery->fetch_assoc()['total'] : 0;
            
            if ($bookedCount >= 10) {
                continue;
            }
            
            $slots[] = $slot;
        }
    }
    echo json_encode($slots);
    exit();
}

$message = "";
$preselected_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if (isset($_POST['book'])) {
    $patient_type = trim($_POST['patient_type'] ?? 'existing');
    $patient_id   = 0;

    if ($patient_type === 'new') {
        $full_name = trim($_POST['new_full_name']);
        $email     = trim($_POST['new_email']);
        $password  = trim($_POST['new_password'] ?? 'Patient@123');
        $phone     = trim($_POST['new_phone']);
        $gender    = $_POST['new_gender'] ?? 'Male';
        $age       = intval($_POST['new_age'] ?? 0);
        $address   = trim($_POST['new_address'] ?? '');

        if (empty($full_name) || empty($email) || empty($phone) || empty($gender) || empty($age)) {
            $message = "Please complete all fields for the new patient.";
        } else {
            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $res = $check->get_result();

            if ($res && $res->num_rows > 0) {
                $message = "Email is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, is_email_verified) VALUES (?, ?, ?, 'patient', 1) RETURNING id");
                $stmt->bind_param("sss", $full_name, $email, $hashedPassword);

                if ($stmt->execute()) {
                    $user_row = $stmt->get_result()->fetch_assoc();
                    $user_id = $user_row['id'];

                    // Insert into patients
                    $stmt2 = $conn->prepare("INSERT INTO patients (user_id, phone, gender, age, address) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->bind_param("issis", $user_id, $phone, $gender, $age, $address);

                    if ($stmt2->execute()) {
                        // Get patient_id
                        $pat_id_query = $conn->query("SELECT patient_id FROM patients WHERE user_id='$user_id'");
                        $pat_id_row = $pat_id_query->fetch_assoc();
                        $patient_id = $pat_id_row['patient_id'];
                    } else {
                        $message = "Failed to create patient profile in patients table.";
                    }
                } else {
                    $message = "Failed to create user account.";
                }
            }
        }
    } else {
        $patient_id = intval($_POST['patient_id']);
    }

    if (empty($message)) {
        $doctor_id     = intval($_POST['doctor_id']);
        $date          = trim($_POST['appointment_date']);
        $time          = trim($_POST['appointment_time']);
        $payment_mode  = trim($_POST['payment_mode'] ?? 'cash');
        $appt_status   = trim($_POST['status'] ?? 'Confirmed');

        $fee_paid   = 200.00;
        $fee_status = ($payment_mode === 'online') ? 'Paid Online' : 'Pay at Counter';

        if (empty($patient_id) || empty($doctor_id) || empty($date) || empty($time)) {
            $message = "Please complete all fields and select a time slot.";
        } else {
            // Check slot capacity limit (max 10)
            $countQuery = $conn->query("
                SELECT COUNT(*) AS total 
                FROM appointments 
                WHERE doctor_id = '$doctor_id' 
                AND appointment_date = '$date' 
                AND appointment_time = '$time' 
                AND status != 'Cancelled'
            ");
            $bookedCount = $countQuery ? $countQuery->fetch_assoc()['total'] : 0;
            
            if ($bookedCount >= 10) {
                $message = "This time slot is fully booked. Please select another time slot.";
            } else {
                $status = ($appt_status === 'Confirmed') ? 'Confirmed' : 'Pending';

                $stmt = $conn->prepare("
                    INSERT INTO appointments
                    (patient_id, doctor_id, appointment_date, appointment_time, status, opd_fee_paid, fee_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    RETURNING appointment_id
                ");
                $stmt->bind_param("iisssds", $patient_id, $doctor_id, $date, $time, $status, $fee_paid, $fee_status);

            if ($stmt->execute()) {
                $row = $stmt->get_result()->fetch_assoc();
                $newApptId = $row['appointment_id'];

                if ($status === 'Confirmed') {
                    // Generate daily token
                    $countQuery = $conn->query("
                        SELECT COUNT(*) AS count 
                        FROM appointments 
                        WHERE doctor_id = '$doctor_id' 
                        AND appointment_date = '$date' 
                        AND token_number IS NOT NULL
                    ");
                    $count = $countQuery ? $countQuery->fetch_assoc()['count'] : 0;
                    $nextSequence = $count + 1;
                    $tokenNumber = 'A' . sprintf('%03d', $nextSequence);

                    $conn->query("
                        UPDATE appointments 
                        SET token_number = '$tokenNumber', 
                            queue_position = '$nextSequence', 
                            queue_status = 'Waiting',
                            est_consultation_time = '$time',
                            check_in_status = 1
                        WHERE appointment_id = $newApptId
                    ");
                }
                
                header("Location: manage_appointments.php?updated=1&appt_id=" . $newApptId);
                exit();
            } else {
                $message = "Failed to create appointment.";
            }
        }
    }
}
}

// Fetch patients & doctors
$patients = $conn->query("
    SELECT p.patient_id, u.full_name, u.email, p.phone
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE u.role = 'patient'
    ORDER BY u.full_name ASC
");

$doctors = $conn->query("
    SELECT d.doctor_id, d.full_name, dep.department_name
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    ORDER BY d.full_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Smart Hospital Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .slot-btn {
            padding: 8px 16px; border-radius: 10px;
            border: 2px solid #dee2e6; background: white;
            cursor: pointer; font-size: 14px; font-weight: 500;
            transition: all 0.2s ease;
        }
        .slot-btn:hover  { border-color: #0d6efd; color: #0d6efd; background: #f0f4ff; }
        .slot-btn.active { border-color: #0d6efd; background: #0d6efd; color: white; }
        .slots-container { display: flex; flex-wrap: wrap; gap: 10px; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <div class="card shadow border-0" style="border-radius: 16px;">
        <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
            <h3 class="mb-0"><i class="bi bi-calendar-plus-fill"></i> Create Appointment</h3>
        </div>
        <div class="card-body p-4">

            <?php if ($message != "") { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <form method="POST" id="bookingForm">
                <div class="row">
                    <!-- Toggle Patient Type -->
                    <div class="col-12 mb-3">
                        <label class="form-label fw-semibold d-block">Patient Selection Type</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="patient_type" id="typeExisting" value="existing" checked onchange="togglePatientType()">
                            <label class="form-check-label" for="typeExisting">Select Existing Patient</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="patient_type" id="typeNew" value="new" onchange="togglePatientType()">
                            <label class="form-check-label" for="typeNew">Register & Book New Patient</label>
                        </div>
                    </div>

                    <!-- Select Existing Patient -->
                    <div class="col-12 mb-3" id="existingPatientSection">
                        <label class="form-label fw-semibold">Select Patient</label>
                        <select name="patient_id" id="patientSelect" class="form-select">
                            <option value="">— Choose Patient —</option>
                            <?php if ($patients) { ?>
                                <?php while ($p = $patients->fetch_assoc()) { ?>
                                    <option value="<?= $p['patient_id'] ?>" <?= ($p['patient_id'] == $preselected_patient_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['full_name']) ?> (Email: <?= htmlspecialchars($p['email']) ?>, Phone: <?= htmlspecialchars($p['phone'] ?? 'N/A') ?>)
                                    </option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Register New Patient Section -->
                    <div class="col-12 border rounded p-3 mb-3 bg-white" id="newPatientSection" style="display: none; border-color: var(--border-color) !important;">
                        <h5 class="mb-3 text-primary"><i class="bi bi-person-plus-fill"></i> New Patient Profile Details</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="new_full_name" id="new_full_name" class="form-control" placeholder="Enter patient's full name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="new_email" id="new_email" class="form-control" placeholder="patient@example.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone/Mobile Number</label>
                                <input type="text" name="new_phone" id="new_phone" class="form-control" placeholder="e.g. +919876543210">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password (For Patient Account)</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" value="Patient@123">
                                <small class="text-muted">Default login password is <code>Patient@123</code></small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="new_gender" id="new_gender" class="form-select">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Age</label>
                                <input type="number" name="new_age" id="new_age" class="form-control" placeholder="Age in years">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="new_address" id="new_address" class="form-control" placeholder="City/Town">
                            </div>
                        </div>
                    </div>

                    <!-- Select Doctor -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Select Doctor</label>
                        <select name="doctor_id" id="doctorSelect" class="form-select" required onchange="loadTimeSlots(this.value)">
                            <option value="">— Choose Doctor —</option>
                            <?php if ($doctors) { ?>
                                <?php while ($d = $doctors->fetch_assoc()) { ?>
                                    <option value="<?= $d['doctor_id'] ?>">
                                        Dr. <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['department_name']) ?>)
                                    </option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Appointment Date -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Appointment Date</label>
                        <input type="date" name="appointment_date" id="dateInput" min="<?= date('Y-m-d') ?>" class="form-control" required onchange="loadTimeSlots(document.getElementById('doctorSelect').value)">
                    </div>

                    <!-- Time Slots -->
                    <div class="col-12 mb-3">
                        <label class="form-label fw-semibold">Choose OPD Time Slot</label>
                        <div id="slotsWrapper">
                            <p class="text-secondary small"><i class="bi bi-arrow-up-circle"></i> Select a doctor and date first to see available time slots.</p>
                        </div>
                        <input type="hidden" name="appointment_time" id="selectedTime" required>
                    </div>

                    <!-- Payment Mode -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Payment Mode</label>
                        <select name="payment_mode" class="form-select" required>
                            <option value="cash">Pay at Counter (Cash)</option>
                            <option value="online">Paid Online</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-semibold">Booking Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Confirmed">Confirmed (Generates Token & Waiting status)</option>
                            <option value="Pending">Pending (Requires Approval)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="book" class="btn btn-success px-4 py-2 fw-semibold">
                        <i class="bi bi-check-circle"></i> Book Appointment
                    </button>
                    <a href="manage_appointments.php" class="btn btn-secondary px-4 py-2">
                        Cancel
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
function togglePatientType() {
    const isExisting = document.getElementById('typeExisting').checked;
    const existingSection = document.getElementById('existingPatientSection');
    const newSection = document.getElementById('newPatientSection');
    const patientSelect = document.getElementById('patientSelect');
    
    // Required fields mapping
    const newFields = ['new_full_name', 'new_email', 'new_phone', 'new_age'];
    
    if (isExisting) {
        existingSection.style.display = 'block';
        newSection.style.display = 'none';
        patientSelect.setAttribute('required', 'required');
        newFields.forEach(f => document.getElementById(f).removeAttribute('required'));
    } else {
        existingSection.style.display = 'none';
        newSection.style.display = 'block';
        patientSelect.removeAttribute('required');
        newFields.forEach(f => document.getElementById(f).setAttribute('required', 'required'));
    }
}
window.addEventListener('DOMContentLoaded', togglePatientType);

function loadTimeSlots(doctorId) {
    const date = document.getElementById('dateInput').value;
    const wrapper = document.getElementById('slotsWrapper');
    if (!doctorId || !date) {
        wrapper.innerHTML = '<p class="text-secondary small"><i class="bi bi-arrow-up-circle"></i> Select both a doctor and a date first.</p>';
        return;
    }
    wrapper.innerHTML = '<div class="d-flex align-items-center gap-2 text-secondary"><div class="spinner-border spinner-border-sm"></div> Loading slots...</div>';
    fetch(`book_appointment.php?get_slots=1&doctor_id=${doctorId}&date=${date}`)
        .then(r => r.json())
        .then(slots => {
            if (!slots.length) {
                wrapper.innerHTML = '<p class="text-warning"><i class="bi bi-exclamation-triangle"></i> No time slots available for this doctor. Contact the hospital.</p>';
                return;
            }
            let html = '<div class="slots-container">';
            slots.forEach(s => {
                html += `<button type="button" class="slot-btn" onclick="selectSlot('${s.slot_time}', this)">${s.slot_label}</button>`;
            });
            html += '</div><p class="text-secondary small mt-2 mb-0"><i class="bi bi-info-circle"></i> Tap to select your preferred time slot.</p>';
            wrapper.innerHTML = html;
            document.getElementById('selectedTime').value = '';
        })
        .catch(() => {
            wrapper.innerHTML = '<p class="text-danger"><i class="bi bi-exclamation-circle"></i> Failed to load slots. Please refresh.</p>';
        });
}

function selectSlot(time, btn) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('selectedTime').value = time;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
