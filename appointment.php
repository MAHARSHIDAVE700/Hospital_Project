<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: patient/login.php");
    exit();
}

include "includes/config.php";

$message = "";
$bookedAppointmentId = null;

// Get patient_id from patients table
$userID = $_SESSION['patient_id'];
$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patientRow = $getPatient->fetch_assoc();

if (!$patientRow) {
    die("Patient profile not found.");
}
$patientID = $patientRow['patient_id'];

// ─── AJAX: Get time slots for a doctor ───────────────────────────────────────
if (isset($_GET['get_slots']) && isset($_GET['doctor_id'])) {
    header('Content-Type: application/json');
    $docId = intval($_GET['doctor_id']);
    $date  = trim($_GET['date'] ?? '');
    
    $slotsResult = $conn->query("SELECT slot_id, slot_time, slot_label FROM opd_slots WHERE doctor_id='$docId' AND is_active=1 ORDER BY slot_time ASC");
    $slots = [];
    
    date_default_timezone_set('Asia/Kolkata');
    $currentDate = date('Y-m-d');
    $cutoffTime = date('H:i:s', strtotime('+1 hour'));
    
    if ($slotsResult) {
        while ($slot = $slotsResult->fetch_assoc()) {
            if ($date === $currentDate) {
                if ($slot['slot_time'] < $cutoffTime) {
                    continue;
                }
            }
            $slots[] = $slot;
        }
    }
    echo json_encode($slots);
    exit();
}

// ─── FORM SUBMISSION: Book Appointment ───────────────────────────────────────
if (isset($_POST['book'])) {
    $doctor        = intval($_POST['doctor']);
    $date          = trim($_POST['appointment_date']);
    $time          = trim($_POST['appointment_time']);
    $payment_mode  = trim($_POST['payment_mode'] ?? 'cash'); // 'online' or 'cash'

    $fee_paid   = 200.00;
    $fee_status = ($payment_mode === 'online') ? 'Paid Online' : 'Pay at Counter';

    if (strtotime($date) < strtotime(date("Y-m-d"))) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Appointment date cannot be in the past.</div>";
    } elseif (empty($time)) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Please select a time slot.</div>";
    } else {
        $status = "Pending";

        $stmt = $conn->prepare("
            INSERT INTO appointments
            (patient_id, doctor_id, appointment_date, appointment_time, status, opd_fee_paid, fee_status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssds", $patientID, $doctor, $date, $time, $status, $fee_paid, $fee_status);

        if ($stmt->execute()) {
            $newApptQuery = $conn->query("
                SELECT appointment_id FROM appointments
                WHERE patient_id='$patientID' AND doctor_id='$doctor'
                AND appointment_date='$date' AND appointment_time='$time'
                ORDER BY appointment_id DESC LIMIT 1
            ");
            $newApptRow = $newApptQuery ? $newApptQuery->fetch_assoc() : null;
            $bookedAppointmentId = $newApptRow ? $newApptRow['appointment_id'] : null;

            $modeLabel = ($payment_mode === 'online') ? '✅ Paid Online (₹200)' : '💵 Pay ₹200 at OPD Counter';
            $message   = "
                <div class='alert alert-success'>
                    <i class='bi bi-check-circle-fill'></i>
                    <strong>Appointment Booked Successfully!</strong><br>
                    Payment Mode: <strong>{$modeLabel}</strong>
                    <div class='mt-2'>
                        <a href='patient/token_card.php?id={$bookedAppointmentId}' class='btn btn-success btn-sm me-2' target='_blank'>
                            <i class='bi bi-qr-code'></i> View Token &amp; QR Card
                        </a>
                        <a href='patient/my_appointments.php' class='btn btn-outline-success btn-sm'>
                            <i class='bi bi-list'></i> My Appointments
                        </a>
                    </div>
                </div>
            ";

            // SMS & Email notification
            try {
                $patQuery = $conn->query("SELECT p.phone, u.full_name, u.email FROM patients p JOIN users u ON p.user_id=u.id WHERE p.patient_id='$patientID'");
                $pat = $patQuery ? $patQuery->fetch_assoc() : null;
                $docQuery = $conn->query("SELECT full_name FROM doctors WHERE doctor_id='$doctor'");
                $docName = ($docQuery && $drow = $docQuery->fetch_assoc()) ? $drow['full_name'] : '';
                if ($pat) {
                    include_once "includes/sms_helper.php";
                    SMSHelper::sendBookingSMS($pat['phone'], $pat['full_name'], $docName, $date, $time, $bookedAppointmentId ?? '-');

                    if (!empty($pat['email'])) {
                        include_once "includes/email_helper.php";
                        EmailHelper::sendBookingConfirmation($pat['email'], $pat['full_name'], $docName, $date, $time, $bookedAppointmentId ?? '-');
                    }
                }
            } catch (Exception $e) { /* silently ignore */ }
        } else {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Failed to Book Appointment. Please try again.</div>";
        }
    }
}

// Departments + Doctors
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name");
$doctors = $conn->query("
    SELECT d.*, dep.department_name
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    ORDER BY d.full_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Slot Buttons ── */
        .slot-btn {
            padding: 8px 16px; border-radius: 10px;
            border: 2px solid #dee2e6; background: white;
            cursor: pointer; font-size: 14px; font-weight: 500;
            transition: all 0.2s ease;
        }
        .slot-btn:hover  { border-color: #0d6efd; color: #0d6efd; background: #f0f4ff; }
        .slot-btn.active { border-color: #0d6efd; background: #0d6efd; color: white; }
        .slots-container { display: flex; flex-wrap: wrap; gap: 10px; }

        /* ── Step Badge ── */
        .step-badge {
            width: 28px; height: 28px; border-radius: 50%;
            background: #0d6efd; color: white;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; margin-right: 8px; flex-shrink: 0;
        }

        /* ── Payment Mode Cards ── */
        .pay-option {
            border: 2px solid #e5e7eb; border-radius: 16px; padding: 20px 22px;
            cursor: pointer; transition: all 0.22s ease;
            background: white; display: flex; align-items: center; gap: 16px;
            position: relative; overflow: hidden; user-select: none;
        }
        .pay-option:hover { border-color: #0d6efd; box-shadow: 0 4px 16px rgba(13,110,253,.1); }
        .pay-option.selected-online { border-color: #0d6efd; background: #eff6ff; box-shadow: 0 6px 20px rgba(13,110,253,.15); }
        .pay-option.selected-cash   { border-color: #16a34a; background: #f0fdf4; box-shadow: 0 6px 20px rgba(22,163,74,.15); }
        .pay-icon { font-size: 2rem; flex-shrink: 0; }
        .pay-check {
            position: absolute; top: 10px; right: 12px;
            width: 22px; height: 22px; border-radius: 50%;
            background: #0d6efd; color: white;
            display: none; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
        }
        .pay-option.selected-online .pay-check,
        .pay-option.selected-cash   .pay-check { display: flex; }
        .pay-option.selected-cash   .pay-check { background: #16a34a; }

        /* ── UPI QR Modal Overlay ── */
        #qrOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 9999;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        #qrOverlay.show { display: flex; }
        .qr-modal {
            background: white; border-radius: 24px; padding: 36px 32px;
            max-width: 400px; width: 92%; text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
            animation: popIn .25s cubic-bezier(.34,1.56,.64,1) forwards;
        }
        @keyframes popIn { from { transform: scale(.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .qr-amount {
            font-size: 2.2rem; font-weight: 800; color: #16a34a;
            margin: 6px 0 2px;
        }
        .qr-img { border-radius: 16px; border: 3px solid #e5e7eb; margin: 18px auto; display: block; }
        .upi-id-chip {
            background: #f3f4f6; border-radius: 10px; padding: 8px 16px;
            font-size: 13px; font-weight: 700; color: #374151; margin: 0 auto;
            display: inline-block; letter-spacing: 0.5px;
        }
        .timer-bar {
            height: 4px; border-radius: 4px; background: #e5e7eb;
            margin: 14px 0 4px; overflow: hidden;
        }
        .timer-fill { height: 100%; background: #16a34a; transition: width 1s linear; width: 100%; }
        .btn-paid {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white; border: none; border-radius: 14px;
            padding: 14px 32px; font-size: 1rem; font-weight: 700;
            width: 100%; margin-top: 18px; cursor: pointer;
            transition: all 0.2s; letter-spacing: 0.3px;
        }
        .btn-paid:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(22,163,74,.35); }
        .btn-cancel-qr {
            background: none; border: none; color: #9ca3af;
            font-size: 13px; margin-top: 8px; cursor: pointer; width: 100%;
        }
        .btn-cancel-qr:hover { color: #374151; }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-modern shadow-sm sticky-top">
    <div class="container">
        <span class="navbar-brand d-flex align-items-center gap-2">🏥 <strong>Smart Hospital</strong></span>
        <a href="patient/dashboard.php" class="btn btn-outline-primary btn-modern py-2 px-3">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </div>
</nav>

<!-- ── UPI QR Modal Overlay ─────────────────────────────────────────────── -->
<div id="qrOverlay">
    <div class="qr-modal">
        <div style="font-size:2rem;">📲</div>
        <h4 class="fw-bold mt-1 mb-0">Scan &amp; Pay</h4>
        <div class="text-secondary small mb-1">OPD Registration Fee</div>
        <div class="qr-amount">₹200</div>

        <!--
            Replace the UPI ID below with your actual hospital UPI ID.
            The QR encodes: upi://pay?pa=YOUR_UPI@upi&pn=NarayanHospital&am=200&cu=INR&tn=OPDFee
            You can generate a permanent UPI QR from your bank/payment app and embed it as an image.
        -->
        <img
            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=hospital%40ybl%26pn%3DNarayanHospital%26am%3D200%26cu%3DINR%26tn%3DOPD%2520Registration%2520Fee"
            alt="UPI QR Code for ₹200"
            width="200" height="200"
            class="qr-img">

        <div class="upi-id-chip"><i class="bi bi-phone"></i> hospital@ybl</div>
        <div class="text-muted small mt-2">Narayan Hospital · ₹200 Fixed · OPD Fee</div>

        <div class="timer-bar"><div class="timer-fill" id="timerFill"></div></div>
        <div class="text-secondary small" id="timerText">QR expires in <strong id="timerCount">5:00</strong></div>

        <button class="btn-paid" onclick="confirmOnlinePayment()">
            <i class="bi bi-check-circle-fill"></i> I Have Paid — Confirm Booking
        </button>
        <button class="btn-cancel-qr" onclick="closeQRModal()">✕ Cancel &amp; go back</button>
    </div>
</div>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <!-- Page Header -->
            <div class="mb-4">
                <p class="text-secondary mb-1">OPD Portal</p>
                <h2><i class="bi bi-calendar-plus-fill text-primary"></i> Book Appointment</h2>
            </div>

            <?= $message ?>

            <!-- Booking Form Card -->
            <div class="card-modern">
                <div class="card-header-modern">
                    <h5 class="mb-0"><i class="bi bi-calendar-event text-primary"></i> Appointment Details</h5>
                </div>
                <div class="card-body-modern">

                    <form method="POST" id="bookingForm">
                        <input type="hidden" name="payment_mode" id="paymentModeInput" value="">

                        <!-- STEP 1: Doctor -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span class="step-badge">1</span> Select Doctor
                            </label>
                            <select name="doctor" id="doctorSelect" class="form-select" required onchange="loadTimeSlots(this.value)">
                                <option value="">— Choose Your Doctor —</option>
                                <?php while ($doc = $doctors->fetch_assoc()) { ?>
                                <option value="<?= $doc['doctor_id'] ?>">
                                    Dr. <?= htmlspecialchars($doc['full_name']) ?> (<?= htmlspecialchars($doc['department_name']) ?>)
                                </option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- STEP 2: Date -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span class="step-badge">2</span> Appointment Date
                            </label>
                            <input type="date" name="appointment_date" id="dateInput"
                                   min="<?= date('Y-m-d') ?>" class="form-control" required
                                   onchange="loadTimeSlots(document.getElementById('doctorSelect').value)">
                        </div>

                        <!-- STEP 3: Time Slot -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span class="step-badge">3</span> Choose OPD Time Slot
                            </label>
                            <div id="slotsWrapper">
                                <p class="text-secondary small"><i class="bi bi-arrow-up-circle"></i> Select a doctor first to see available time slots.</p>
                            </div>
                            <input type="hidden" name="appointment_time" id="selectedTime">
                        </div>

                        <!-- STEP 4: Payment Method -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span class="step-badge">4</span> OPD Registration Fee — <strong class="ms-1 text-primary">₹200</strong>
                            </label>
                            <p class="text-secondary small mb-3">Choose how you'd like to pay the mandatory ₹200 OPD registration fee.</p>

                            <div class="row g-3" id="paymentOptions">

                                <!-- Online Payment -->
                                <div class="col-md-6">
                                    <div class="pay-option" id="optOnline" onclick="selectPayment('online')">
                                        <div class="pay-check"><i class="bi bi-check2"></i></div>
                                        <div class="pay-icon">📲</div>
                                        <div>
                                            <div class="fw-bold">Pay Online</div>
                                            <div class="text-secondary small">UPI / QR Code · Instant</div>
                                            <div class="mt-1">
                                                <span class="badge" style="background:#e0f2fe; color:#0369a1; border-radius:6px; font-size:11px;">PhonePe · GPay · Paytm</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cash Payment -->
                                <div class="col-md-6">
                                    <div class="pay-option" id="optCash" onclick="selectPayment('cash')">
                                        <div class="pay-check"><i class="bi bi-check2"></i></div>
                                        <div class="pay-icon">💵</div>
                                        <div>
                                            <div class="fw-bold">Pay at Counter</div>
                                            <div class="text-secondary small">Cash · Pay on arrival</div>
                                            <div class="mt-1">
                                                <span class="badge" style="background:#fef9c3; color:#854d0e; border-radius:6px; font-size:11px;">OPD Reception · Day of visit</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div id="payError" class="text-danger small mt-2" style="display:none;">
                                <i class="bi bi-exclamation-circle"></i> Please select a payment method.
                            </div>
                        </div>

                        <!-- Book Button - shown only after payment method is selected -->
                        <div id="bookBtnWrap" style="display:none;">
                            <button type="button" id="proceedBtn" onclick="handleProceed()"
                                    class="btn btn-primary btn-modern w-100 py-3"
                                    style="font-size: 1.1rem; border-radius: 14px;">
                                <i class="bi bi-calendar-check-fill"></i>
                                <span id="proceedBtnLabel">Confirm Booking</span>
                            </button>
                            <a href="patient/dashboard.php" class="btn btn-outline-secondary btn-modern w-100 mt-2 py-2">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                        </div>

                        <!-- Hidden submit trigger -->
                        <button type="submit" name="book" id="realSubmitBtn" style="display:none;"></button>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
/* ── State ── */
let selectedMode = '';
let timerInterval = null;
let timerSeconds  = 300; // 5 min countdown

/* ── Payment option selection ── */
function selectPayment(mode) {
    selectedMode = mode;

    document.getElementById('optOnline').classList.remove('selected-online', 'selected-cash');
    document.getElementById('optCash').classList.remove('selected-online', 'selected-cash');
    document.getElementById('payError').style.display = 'none';

    if (mode === 'online') {
        document.getElementById('optOnline').classList.add('selected-online');
        document.getElementById('proceedBtnLabel').textContent = 'Pay ₹200 Online & Book';
    } else {
        document.getElementById('optCash').classList.add('selected-cash');
        document.getElementById('proceedBtnLabel').textContent = 'Book & Pay ₹200 at Counter';
    }
    document.getElementById('bookBtnWrap').style.display = 'block';
}

/* ── Time slot loading ── */
function loadTimeSlots(doctorId) {
    const date = document.getElementById('dateInput').value;
    const wrapper = document.getElementById('slotsWrapper');
    if (!doctorId) {
        wrapper.innerHTML = '<p class="text-secondary small"><i class="bi bi-arrow-up-circle"></i> Select a doctor first.</p>';
        return;
    }
    wrapper.innerHTML = '<div class="d-flex align-items-center gap-2 text-secondary"><div class="spinner-border spinner-border-sm"></div> Loading slots...</div>';
    fetch(`appointment.php?get_slots=1&doctor_id=${doctorId}&date=${date}`)
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

/* ── Proceed button clicked ── */
function handleProceed() {
    const time = document.getElementById('selectedTime').value;
    const doctor = document.getElementById('doctorSelect').value;
    const date = document.getElementById('dateInput').value;

    if (!doctor) { alert('Please select a doctor.'); return; }
    if (!date)   { alert('Please select a date.'); return; }
    if (!time)   { alert('Please select a time slot.'); return; }
    if (!selectedMode) {
        document.getElementById('payError').style.display = 'block';
        return;
    }

    if (selectedMode === 'online') {
        openQRModal();
    } else {
        // Cash — submit directly
        document.getElementById('paymentModeInput').value = 'cash';
        document.getElementById('realSubmitBtn').click();
    }
}

/* ── QR Modal ── */
function openQRModal() {
    document.getElementById('qrOverlay').classList.add('show');
    startTimer();
}

function closeQRModal() {
    document.getElementById('qrOverlay').classList.remove('show');
    clearInterval(timerInterval);
    timerSeconds = 300;
    document.getElementById('timerFill').style.width = '100%';
    document.getElementById('timerCount').textContent = '5:00';
}

function startTimer() {
    timerSeconds = 300;
    document.getElementById('timerFill').style.width = '100%';
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timerSeconds--;
        const m = Math.floor(timerSeconds / 60);
        const s = timerSeconds % 60;
        document.getElementById('timerCount').textContent = `${m}:${s.toString().padStart(2,'0')}`;
        document.getElementById('timerFill').style.width = ((timerSeconds / 300) * 100) + '%';
        if (timerSeconds <= 0) {
            clearInterval(timerInterval);
            document.getElementById('timerCount').textContent = 'Expired';
            document.getElementById('timerFill').style.width = '0%';
        }
    }, 1000);
}

function confirmOnlinePayment() {
    // Patient confirms they paid via UPI → submit form as online
    clearInterval(timerInterval);
    document.getElementById('qrOverlay').classList.remove('show');
    document.getElementById('paymentModeInput').value = 'online';
    document.getElementById('realSubmitBtn').click();
}

// Close QR modal on overlay background click
document.getElementById('qrOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeQRModal();
});
</script>

</body>
</html>