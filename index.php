<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Narayan Clinic & Hospital | Smart Healthcare Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.8) 100%), url("assets/images/hospital.jpg");
            background-size: cover;
            background-position: center;
            min-height: 85vh;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--border-color);
        }
        .landing-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(0deg, #f8fafc 0%, rgba(248, 250, 252, 0) 100%);
            pointer-events: none;
        }
        .landing-hero h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.15;
            color: #ffffff;
        }
        .hero-heartbeat-glow {
            animation: heartbeat 2s infinite ease-in-out;
        }
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.08); opacity: 1; }
        }
        .stats-overlay {
            margin-top: -60px;
            position: relative;
            z-index: 10;
        }
        .landing-navbar {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: rgba(15, 23, 42, 0.8) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark landing-navbar sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <span style="font-size: 24px;">🏥</span>
            <div class="brand-text">
                <span class="d-block" style="font-size: 16px; font-weight: 800; line-height: 1.2; letter-spacing: -0.01em;">Narayan Clinic</span>
                <span class="d-block text-secondary" style="font-size: 10px; font-weight: 600;">OPD & Health Suite</span>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link active fw-semibold" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#departments">Departments</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#doctors">Doctors</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#contact">Contact</a></li>
                <li class="nav-item"><a class="nav-link text-warning fw-bold" href="patient/symptom_checker.php">🤖 AI Symptom Checker</a></li>
                <li class="nav-item"><a class="nav-link text-danger fw-bold me-3" href="patient/ambulance.php">🚨 Emergency Pickups</a></li>
                <li class="nav-item dropdown">
                    <a class="btn btn-primary px-4 py-2 fw-semibold shadow-sm dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" style="background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; border-radius: 10px;">
                        Portal Access
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow mt-2" style="border-radius: 12px;">
                        <li><a class="dropdown-item py-2 fw-semibold" href="patient/login.php"><i class="bi bi-person-fill text-teal-accent"></i> Patient Login</a></li>
                        <li><a class="dropdown-item py-2 fw-semibold" href="doctor/login.php"><i class="bi bi-person-badge text-primary"></i> Doctor Login</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 fw-semibold text-danger" href="admin/login.php"><i class="bi bi-shield-fill-check"></i> Admin Dashboard</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="landing-hero">
    <div class="container position-relative z-1">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-teal-accent text-white px-3 py-2 rounded-pill mb-3" style="background-color: var(--primary-color) !important;"><i class="bi bi-stars"></i> Smart OPD & EMR Suite</span>
                <h1>Advanced Clinical Care<br><span style="color: var(--primary-color);">At Your Fingertips</span></h1>
                <p class="mt-4 text-secondary-light lead" style="font-size: 18px; color: #94a3b8;">
                    Secure instant online token bookings, inspect live queue status, download electronic records, and access 24/7 smart medical consultation.
                </p>
                <div class="mt-5 d-flex gap-3">
                    <a href="patient/login.php" class="btn btn-primary btn-lg px-4 py-3 shadow" style="background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; border-radius: 12px; font-weight: 600;">
                        <i class="bi bi-calendar-check-fill me-2"></i> Book Appointment
                    </a>
                    <a href="#about" class="btn btn-outline-light btn-lg px-4 py-3" style="border-radius: 12px; font-weight: 600; border-color: rgba(255,255,255,0.2);">
                        Explore Facilities
                    </a>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <i class="bi bi-heart-pulse-fill text-danger hero-heartbeat-glow display-1" style="font-size: 160px; filter: drop-shadow(0 0 45px rgba(239,68,68,0.3));"></i>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Overlay -->
<section class="container stats-overlay mb-5">
    <div class="row g-4">
        <div class="col-md-3 col-sm-6">
            <div class="card-modern shadow-lg text-center p-4">
                <div class="widget-icon bg-primary-subtle text-primary mx-auto mb-3">
                    <i class="bi bi-person-fill-gear"></i>
                </div>
                <h3 class="fw-bold mb-1">15+</h3>
                <p class="text-secondary small mb-0 fw-semibold">Specialist Doctors</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card-modern shadow-lg text-center p-4">
                <div class="widget-icon bg-success-subtle text-success mx-auto mb-3">
                    <i class="bi bi-building-fill-add"></i>
                </div>
                <h3 class="fw-bold mb-1">10+</h3>
                <p class="text-secondary small mb-0 fw-semibold">Medical Departments</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card-modern shadow-lg text-center p-4">
                <div class="widget-icon bg-info-subtle text-info mx-auto mb-3">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3 class="fw-bold mb-1">5,000+</h3>
                <p class="text-secondary small mb-0 fw-semibold">Patients Served</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card-modern shadow-lg text-center p-4">
                <div class="widget-icon bg-danger-subtle text-danger mx-auto mb-3">
                    <i class="bi bi-truck-flatbed"></i>
                </div>
                <h3 class="fw-bold mb-1">24/7</h3>
                <p class="text-secondary small mb-0 fw-semibold">Ambulance Dispatch</p>
            </div>
        </div>
    </div>
</section>

<!-- Departments Section -->
<section id="departments" class="container py-5 my-3">
    <div class="text-center mb-5">
        <p class="text-uppercase small fw-bold text-primary tracking-wider mb-1">Narayan Specialty Care</p>
        <h2 class="fw-bold">Clinical Specializations</h2>
        <p class="text-secondary" style="max-width: 600px; margin: 0 auto;">We offer world-class outpatient care across advanced medical fields.</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-3 col-md-6">
            <div class="card-modern text-center p-4 h-100">
                <div class="widget-icon bg-danger-subtle text-danger mx-auto mb-3">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <h4 class="fw-bold">Cardiology</h4>
                <p class="text-secondary small mb-0">Advanced diagnostic tools, mapping, and therapy by qualified cardiologists.</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card-modern text-center p-4 h-100">
                <div class="widget-icon bg-primary-subtle text-primary mx-auto mb-3">
                    <i class="bi bi-brain-fill"></i>
                </div>
                <h4 class="fw-bold">Neurology</h4>
                <p class="text-secondary small mb-0">Comprehensive treatment for complex central nervous system and nerve disorders.</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card-modern text-center p-4 h-100">
                <div class="widget-icon bg-success-subtle text-success mx-auto mb-3">
                    <i class="bi bi-bandaid-fill"></i>
                </div>
                <h4 class="fw-bold">Orthopedics</h4>
                <p class="text-secondary small mb-0">Joint replacements, recovery treatments, and high-precision bone care.</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card-modern text-center p-4 h-100">
                <div class="widget-icon bg-warning-subtle text-warning mx-auto mb-3">
                    <i class="bi bi-emoji-smile-fill"></i>
                </div>
                <h4 class="fw-bold">Pediatrics</h4>
                <p class="text-secondary small mb-0">Specialized newborn screening and pediatric care for infants and kids.</p>
            </div>
        </div>
    </div>
</section>

<!-- About Section -->
<section id="about" class="py-5 bg-light border-top border-bottom">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="rounded-4 overflow-hidden shadow-lg" style="max-height: 400px;">
                    <img src="assets/images/hospital.jpg" class="img-fluid w-100 h-100" style="object-fit: cover;" alt="Hospital Building">
                </div>
            </div>
            <div class="col-lg-6">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-3">Institution Overview</span>
                <h2 class="fw-bold mb-4">Dedicated to Wellness &amp; Patient Convenience</h2>
                <p class="lead text-secondary">
                    Narayan Clinic combines top-tier clinical expertise with a smart outpatient digital suite.
                </p>
                <p class="text-secondary">
                    We support real-time token tracking, live doctor availability statuses, automated wait time calculations, and interactive symptom checker portals. Registered patients can log in anytime to review past prescriptions, reports, and calendar events.
                </p>
                <div class="row g-3 my-4">
                    <div class="col-sm-6">
                        <p class="fw-semibold text-dark mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> 24/7 Ambulance pickup</p>
                        <p class="fw-semibold text-dark mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> Live status indicators</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="fw-semibold text-dark mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> Digital prescription receipts</p>
                        <p class="fw-semibold text-dark mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> Standardized queue tracking</p>
                    </div>
                </div>
                <a href="patient/register.php" class="btn btn-primary btn-lg px-4 py-3" style="background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; border-radius: 12px; font-weight: 600;">
                    Create Patient Account
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Specialist Section -->
<section id="doctors" class="container py-5 my-3">
    <div class="text-center mb-5">
        <p class="text-uppercase small fw-bold text-primary tracking-wider mb-1">Meet Our Specialists</p>
        <h2 class="fw-bold">Experienced Clinicians</h2>
        <p class="text-secondary" style="max-width: 600px; margin: 0 auto;">Our OPD is staffed by highly qualified practitioners across every specialization.</p>
    </div>
    <div class="row g-4 justify-content-center">
        <div class="col-md-4 col-sm-6">
            <div class="card-modern h-100">
                <img src="assets/images/doctor1.jpg" class="card-img-top w-100" style="height: 280px; object-fit: cover;" alt="Dr. Amit Sharma">
                <div class="p-4 text-center">
                    <h5 class="fw-bold mb-1">Dr. Amit Sharma</h5>
                    <span class="badge bg-danger-subtle text-danger mb-3">Cardiologist</span>
                    <p class="text-secondary small mb-3">10+ Years clinical experience in invasive and non-invasive cardiac care.</p>
                    <a href="patient/login.php" class="btn btn-outline-primary btn-sm w-100 py-2" style="border-radius: 8px;">Book Appointment</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card-modern h-100">
                <img src="assets/images/doctor2.jpg" class="card-img-top w-100" style="height: 280px; object-fit: cover;" alt="Dr. Sarah Johnson">
                <div class="p-4 text-center">
                    <h5 class="fw-bold mb-1">Dr. Sarah Johnson</h5>
                    <span class="badge bg-primary-subtle text-primary mb-3">Neurologist</span>
                    <p class="text-secondary small mb-3">8+ Years treating complex stroke conditions and neuro-system diagnostics.</p>
                    <a href="patient/login.php" class="btn btn-outline-primary btn-sm w-100 py-2" style="border-radius: 8px;">Book Appointment</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card-modern h-100">
                <img src="assets/images/doctor3.jpg" class="card-img-top w-100" style="height: 280px; object-fit: cover;" alt="Dr. David Brown">
                <div class="p-4 text-center">
                    <h5 class="fw-bold mb-1">Dr. David Brown</h5>
                    <span class="badge bg-success-subtle text-success mb-3">Orthopedic Surgeon</span>
                    <p class="text-secondary small mb-3">12+ Years in surgical joint reconstruction, sports injuries, and trauma rehabilitation.</p>
                    <a href="patient/login.php" class="btn btn-outline-primary btn-sm w-100 py-2" style="border-radius: 8px;">Book Appointment</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="py-5 bg-light border-top">
    <div class="container py-4">
        <div class="text-center mb-5">
            <p class="text-uppercase small fw-bold text-primary tracking-wider mb-1">Get In Touch</p>
            <h2 class="fw-bold">Contact &amp; Location details</h2>
            <p class="text-secondary" style="max-width: 600px; margin: 0 auto;">Reach out to our information desk or find our location.</p>
        </div>
        <div class="row g-5">
            <div class="col-lg-5">
                <div class="card-modern p-4 h-100">
                    <h4 class="fw-bold mb-4 text-primary">Narayan Information Center</h4>
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <div class="widget-icon bg-primary-subtle text-primary mb-0"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <strong class="d-block small text-dark">Location Address</strong>
                            <span class="text-secondary small">Halvad, Gujarat, India</span>
                        </div>
                    </div>
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <div class="widget-icon bg-success-subtle text-success mb-0"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <strong class="d-block small text-dark">OPD Helpline</strong>
                            <span class="text-secondary small">+91 81401 50700</span>
                        </div>
                    </div>
                    <div class="mb-4 d-flex align-items-center gap-3">
                        <div class="widget-icon bg-info-subtle text-info mb-0"><i class="bi bi-envelope-fill"></i></div>
                        <div>
                            <strong class="d-block small text-dark">Email Support</strong>
                            <span class="text-secondary small">info@narayanhospital.com</span>
                        </div>
                    </div>
                    <!-- Map Integration -->
                    <div class="rounded-3 overflow-hidden shadow-sm" style="height: 180px;">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d14691.018617180373!2d71.17144215!3d23.0135062!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395995b0580970a9%3A0xb35a09bfd1a7cb6!2sHalvad%2C%20Gujarat!5e0!3m2!1sen!2sin!4v1700000000000!5m2!1sen!2sin" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card-modern p-4 h-100">
                    <h4 class="fw-bold mb-4">Send a Direct Inquiry</h4>
                    <form onsubmit="event.preventDefault(); alert('Message sent successfully!');">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Full Name</label>
                                <input type="text" class="form-control" placeholder="Enter Full Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Email Address</label>
                                <input type="email" class="form-control" placeholder="Enter Email" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Phone Number</label>
                                <input type="text" class="form-control" placeholder="Enter Phone Number" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Subject</label>
                                <input type="text" class="form-control" placeholder="Inquiry Subject" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Message Detail</label>
                                <textarea class="form-control" rows="5" placeholder="Write your message here..." required></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" style="background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; border-radius: 10px;">
                                    Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white pt-5 pb-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h4 class="fw-bold text-white mb-3">🏥 Narayan Clinic</h4>
                <p class="text-secondary small">
                    Narayan Outpatient Clinic & Hospital Management System is designed to offer international-grade health management, online appointment queues, and digital medical history tracking.
                </p>
            </div>
            <div class="col-lg-2 col-sm-6">
                <h5 class="fw-bold text-white mb-3">Quick Links</h5>
                <ul class="list-unstyled text-secondary small">
                    <li class="mb-2"><a href="#" class="text-secondary text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="#about" class="text-secondary text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="#departments" class="text-secondary text-decoration-none">Departments</a></li>
                    <li class="mb-2"><a href="#doctors" class="text-secondary text-decoration-none">Doctors</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-sm-6">
                <h5 class="fw-bold text-white mb-3">OPD Services</h5>
                <ul class="list-unstyled text-secondary small">
                    <li class="mb-2">✔ Quick Token Bookings</li>
                    <li class="mb-2">✔ Live Waiting Queue</li>
                    <li class="mb-2">✔ Digital prescriptions</li>
                    <li class="mb-2">✔ Medical history portal</li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h5 class="fw-bold text-white mb-3">Follow Us</h5>
                <div class="d-flex gap-3">
                    <a href="#" class="text-secondary fs-4"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-secondary fs-4"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-secondary fs-4"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="text-secondary fs-4"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <div class="text-center text-secondary small">
            <p class="mb-1">© 2026 Smart Hospital Management System. All Rights Reserved.</p>
            <p class="mb-0">Developed by <strong class="text-warning">Maharshi Dave</strong></p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>