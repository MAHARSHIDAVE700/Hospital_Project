<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Smart Hospital Management System</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="assets/css/style.css">

<style>

body{
font-family:Arial,Helvetica,sans-serif;
}

.hero{
background:linear-gradient(rgba(0,123,255,.75),rgba(0,123,255,.75)),
url("assets/images/hospital.jpg");
background-size:cover;
background-position:center;
height:90vh;
display:flex;
align-items:center;
color:white;
}

.hero h1{
font-size:60px;
font-weight:bold;
}

.hero p{
font-size:22px;
}

.stats{
margin-top:-70px;
z-index:10;
position:relative;
}

.stat-card{
border:none;
border-radius:15px;
transition:.3s;
}

.stat-card:hover{
transform:translateY(-10px);
}

</style>

</head>

<body>

<!-- Navbar -->

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow">

<div class="container">

<a class="navbar-brand fw-bold" href="index.php">

<i class="bi bi-hospital-fill"></i>

Narayan Hospital
</a>

<button
class="navbar-toggler"
type="button"
data-bs-toggle="collapse"
data-bs-target="#menu">

<span class="navbar-toggler-icon"></span>

</button>

<div class="collapse navbar-collapse" id="menu">

<ul class="navbar-nav ms-auto">

<li class="nav-item">
<a class="nav-link active" href="index.php">
Home
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="#about">
About
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="#departments">
Departments
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="#doctors">
Doctors
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="#contact">
Contact
</a>
</li>

<li class="nav-item">
<a class="nav-link text-warning fw-bold" href="patient/symptom_checker.php">
🤖 AI Symptoms
</a>
</li>

<li class="nav-item">
<a class="nav-link text-danger fw-bold" href="patient/ambulance.php">
🚨 Ambulance
</a>
</li>

<li class="nav-item dropdown ms-3">

<a
class="btn btn-light dropdown-toggle"
data-bs-toggle="dropdown"
href="#">

Login

</a>

<ul class="dropdown-menu">

<li>

<a class="dropdown-item" href="admin/login.php">

Admin Login

</a>

</li>

<li>

<a class="dropdown-item" href="doctor/login.php">

Doctor Login

</a>

</li>

<li>

<a class="dropdown-item" href="patient/login.php">

Patient Login

</a>

</li>

</ul>

</li>

</ul>

</div>

</div>

</nav>

<!-- Hero -->

<section class="hero">

<div class="container">

<div class="row align-items-center">

<div class="col-lg-7">

<h1>

Your Health

<br>

Our Priority

</h1>

<p class="mt-4">

Book appointments with experienced doctors.

Manage prescriptions online.

Fast, Secure and Available 24×7.

</p>

<a href="patient/login.php"
class="btn btn-warning btn-lg mt-3">

Book Appointment

</a>

<a href="#about"
class="btn btn-outline-light btn-lg mt-3 ms-2">

Learn More

</a>

</div>

<div class="col-lg-5 text-center">

<i class="bi bi-heart-pulse-fill"
style="font-size:220px;"></i>

</div>

</div>

</div>

</section>

<!-- Statistics -->

<section class="container stats">

<div class="row">

<div class="col-md-3 mb-4">

<div class="card stat-card shadow text-center p-4">

<h1 class="text-primary">

<i class="bi bi-person-badge-fill"></i>

</h1>

<h2>

150+

</h2>

<h5>

Doctors

</h5>

</div>

</div>

<div class="col-md-3 mb-4">

<div class="card stat-card shadow text-center p-4">

<h1 class="text-success">

<i class="bi bi-building"></i>

</h1>

<h2>

20+

</h2>

<h5>

Departments

</h5>

</div>

</div>

<div class="col-md-3 mb-4">

<div class="card stat-card shadow text-center p-4">

<h1 class="text-danger">

<i class="bi bi-people-fill"></i>

</h1>

<h2>

10000+

</h2>

<h5>

Patients

</h5>

</div>

</div>

<div class="col-md-3 mb-4">

<div class="card stat-card shadow text-center p-4">

<h1 class="text-warning">

<i class="bi bi-ambulance"></i>

</h1>

<h2>

24×7

</h2>

<h5>

Emergency

</h5>

</div>

</div>

</div>

</section>
<!-- =======================
     OUR DEPARTMENTS
======================= -->

<section id="departments" class="container py-5">

<div class="text-center mb-5">

<h2 class="fw-bold text-primary">

Our Departments

</h2>

<p class="text-muted">

We provide world-class healthcare services through specialized departments.

</p>

</div>

<div class="row g-4">

<div class="col-lg-3 col-md-6">

<div class="card shadow border-0 h-100 text-center p-4">

<div class="mb-3">

<i class="bi bi-heart-pulse-fill text-danger" style="font-size:55px;"></i>

</div>

<h4>Cardiology</h4>

<p class="text-muted">

Advanced heart care with experienced cardiologists.

</p>

</div>

</div>

<div class="col-lg-3 col-md-6">

<div class="card shadow border-0 h-100 text-center p-4">

<div class="mb-3">

<i class="bi bi-brain text-primary" style="font-size:55px;"></i>

</div>

<h4>Neurology</h4>

<p class="text-muted">

Expert diagnosis and treatment for neurological disorders.

</p>

</div>

</div>

<div class="col-lg-3 col-md-6">

<div class="card shadow border-0 h-100 text-center p-4">

<div class="mb-3">

<i class="bi bi-bandaid-fill text-success" style="font-size:55px;"></i>

</div>

<h4>Orthopedics</h4>

<p class="text-muted">

Comprehensive bone and joint care by specialists.

</p>

</div>

</div>

<div class="col-lg-3 col-md-6">

<div class="card shadow border-0 h-100 text-center p-4">

<div class="mb-3">

<i class="bi bi-emoji-smile-fill text-warning" style="font-size:55px;"></i>

</div>

<h4>Pediatrics</h4>

<p class="text-muted">

Complete healthcare services for infants and children.

</p>

</div>

</div>

</div>

</section>

<!-- =======================
        ABOUT US
======================= -->

<section id="about" class="py-5 bg-light">

<div class="container">

<div class="row align-items-center">

<div class="col-lg-6">

<img src="assets/images/hospital.jpg"
class="img-fluid rounded shadow"
alt="Hospital">

</div>

<div class="col-lg-6">

<h2 class="fw-bold text-primary mb-4">

About Narayan Hospital

</h2>

<p class="lead">

Narayan Hospital Management System is a modern healthcare platform
designed to simplify hospital operations and improve patient care.

</p>

<p>

Our hospital offers quality healthcare through experienced doctors,
advanced medical technology, online appointment booking,
digital prescriptions, and 24×7 emergency services.

</p>

<div class="row mt-4">

<div class="col-6">

<p>

<i class="bi bi-check-circle-fill text-success"></i>

24×7 Emergency

</p>

<p>

<i class="bi bi-check-circle-fill text-success"></i>

Qualified Specialists

</p>

</div>

<div class="col-6">

<p>

<i class="bi bi-check-circle-fill text-success"></i>

Digital Records

</p>

<p>

<i class="bi bi-check-circle-fill text-success"></i>

Online Appointments

</p>

</div>

</div>

<a href="patient/register.php"
class="btn btn-primary btn-lg mt-3">

Register Now

</a>

</div>

</div>

</div>

</section>

<!-- =======================
      WHY CHOOSE US
======================= -->

<section class="container py-5">

<div class="text-center mb-5">

<h2 class="fw-bold text-primary">

Why Choose Narayan Hospital?

</h2>

<p class="text-muted">

Quality healthcare with compassion and innovation.

</p>

</div>

<div class="row g-4">

<div class="col-md-3">

<div class="card shadow border-0 text-center p-4 h-100">

<i class="bi bi-hospital-fill text-primary"
style="font-size:60px;"></i>

<h4 class="mt-3">

Modern Infrastructure

</h4>

<p>

Latest equipment and smart healthcare facilities.

</p>

</div>

</div>

<div class="col-md-3">

<div class="card shadow border-0 text-center p-4 h-100">

<i class="bi bi-person-heart text-danger"
style="font-size:60px;"></i>

<h4 class="mt-3">

Expert Doctors

</h4>

<p>

Highly qualified specialists in every department.

</p>

</div>

</div>

<div class="col-md-3">

<div class="card shadow border-0 text-center p-4 h-100">

<i class="bi bi-ambulance-fill text-warning"
style="font-size:60px;"></i>

<h4 class="mt-3">

Emergency Care

</h4>

<p>

Emergency ambulance and treatment available 24×7.

</p>

</div>

</div>

<div class="col-md-3">

<div class="card shadow border-0 text-center p-4 h-100">

<i class="bi bi-shield-check text-success"
style="font-size:60px;"></i>

<h4 class="mt-3">

Trusted Service

</h4>

<p>

Thousands of satisfied patients trust our healthcare.

</p>

</div>

</div>

</div>

</section>
<!-- ==========================
        OUR DOCTORS
=========================== -->

<section id="doctors" class="py-5 bg-light">

<div class="container">

<div class="text-center mb-5">

<h2 class="fw-bold text-primary">

Our Specialist Doctors

</h2>

<p class="text-muted">

Meet our experienced and highly qualified medical specialists.

</p>

</div>

<div class="row g-4">

<div class="col-lg-4">

<div class="card shadow border-0">

<img src="assets/images/doctor1.jpg"
class="card-img-top"
style="height:350px;object-fit:cover;">

<div class="card-body text-center">

<h4>Dr. Amit Sharma</h4>

<h6 class="text-primary">

Cardiologist

</h6>

<p>

10+ Years Experience

</p>

<div>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

</div>

<a href="patient/login.php"
class="btn btn-primary mt-3">

Book Appointment

</a>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow border-0">

<img src="assets/images/doctor2.jpg"
class="card-img-top"
style="height:350px;object-fit:cover;">

<div class="card-body text-center">

<h4>Dr. Sarah Johnson</h4>

<h6 class="text-success">

Neurologist

</h6>

<p>

8+ Years Experience

</p>

<div>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-half text-warning"></i>

</div>

<a href="patient/login.php"
class="btn btn-success mt-3">

Book Appointment

</a>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow border-0">

<img src="assets/images/doctor3.jpg"
class="card-img-top"
style="height:350px;object-fit:cover;">

<div class="card-body text-center">

<h4>Dr. David Brown</h4>

<h6 class="text-danger">

Orthopedic Surgeon

</h6>

<p>

12+ Years Experience

</p>

<div>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

<i class="bi bi-star-fill text-warning"></i>

</div>

<a href="patient/login.php"
class="btn btn-danger mt-3">

Book Appointment

</a>

</div>

</div>

</div>

</div>

</div>

</section>

<!-- ==========================
      HOSPITAL GALLERY
=========================== -->

<section class="container py-5">

<div class="text-center mb-5">

<h2 class="fw-bold text-primary">

Hospital Gallery

</h2>

<p class="text-muted">

Take a look at our world-class facilities.

</p>

</div>

<div class="row g-4">

<div class="col-md-4">

<img src="assets/images/gallery1.jpg"
class="img-fluid rounded shadow gallery-img">

</div>

<div class="col-md-4">

<img src="assets/images/gallery2.jpg"
class="img-fluid rounded shadow gallery-img">

</div>

<div class="col-md-4">

<img src="assets/images/gallery3.jpg"
class="img-fluid rounded shadow gallery-img">

</div>

<div class="col-md-4">

<img src="assets/images/gallery4.jpg"
class="img-fluid rounded shadow gallery-img">

</div>

<div class="col-md-4">

<img src="assets/images/gallery5.jpg"
class="img-fluid rounded shadow gallery-img">

</div>

<div class="col-md-4">

<img src="assets/images/gallery6.jpg"
class="img-fluid rounded shadow gallery-img">

</div>

</div>

</section>

<!-- ==========================
        CONTACT
=========================== -->

<section id="contact" class="py-5 bg-light">

<div class="container">

<div class="text-center mb-5">

<h2 class="fw-bold text-primary">

Contact Us

</h2>

<p>

We are always ready to help you.

</p>

</div>

<div class="row">

<div class="col-lg-5">

<div class="card shadow border-0 h-100">

<div class="card-body">

<h3 class="text-primary">

Hospital Information

</h3>

<hr>

<p>

<i class="bi bi-geo-alt-fill text-danger"></i>

Halvad, Gujarat, India

</p>

<p>

<i class="bi bi-telephone-fill text-success"></i>

+91 9876543210

</p>

<p>

<i class="bi bi-envelope-fill text-primary"></i>

narayanhospital@gmail.com

</p>

<p>

<i class="bi bi-clock-fill text-warning"></i>

Monday - Saturday

8:00 AM - 8:00 PM

</p>

<p>

<i class="bi bi-ambulance text-danger"></i>

Emergency Service Available 24×7

</p>

</div>

</div>

</div>

<div class="col-lg-7">

<div class="card shadow border-0">

<div class="card-body">

<form>

<div class="row">

<div class="col-md-6">

<input
type="text"
class="form-control mb-3"
placeholder="Full Name">

</div>

<div class="col-md-6">

<input
type="email"
class="form-control mb-3"
placeholder="Email">

</div>

</div>

<input
type="text"
class="form-control mb-3"
placeholder="Phone Number">

<input
type="text"
class="form-control mb-3"
placeholder="Subject">

<textarea
class="form-control mb-3"
rows="6"
placeholder="Write your message..."></textarea>

<button class="btn btn-primary btn-lg w-100">

Send Message

</button>

</form>

</div>

</div>

</div>

</div>

</div>

</section>

<!-- ==========================
      EMERGENCY BANNER
=========================== -->

<section class="bg-danger text-white py-5">

<div class="container text-center">

<h1>

🚑 Emergency Ambulance Service

</h1>

<h3 class="mt-3">

Available 24 × 7

</h3>

<h2 class="mt-3">

Call Now

+91 9876543210

</h2>

<a href="tel:+919876543210"
class="btn btn-light btn-lg mt-3">

Call Emergency

</a>

</div>

</section>
<!-- ==========================
        FOOTER
=========================== -->

<footer class="bg-dark text-white pt-5 pb-3">

<div class="container">

<div class="row">

<div class="col-lg-4 mb-4">

<h3>

🏥 Narayan Hospital

</h3>

<p>

Narayan Hospital Management System is designed to provide
quality healthcare with modern technology, experienced doctors,
and 24×7 emergency services.

</p>

</div>

<div class="col-lg-2 mb-4">

<h5>

Quick Links

</h5>

<ul class="list-unstyled">

<li><a href="#" class="text-white text-decoration-none">Home</a></li>

<li><a href="#about" class="text-white text-decoration-none">About</a></li>

<li><a href="#departments" class="text-white text-decoration-none">Departments</a></li>

<li><a href="#doctors" class="text-white text-decoration-none">Doctors</a></li>

<li><a href="#contact" class="text-white text-decoration-none">Contact</a></li>

</ul>

</div>

<div class="col-lg-3 mb-4">

<h5>

Hospital Services

</h5>

<ul class="list-unstyled">

<li>✔ Online Appointment</li>

<li>✔ Digital Prescription</li>

<li>✔ Specialist Doctors</li>

<li>✔ Emergency Care</li>

<li>✔ Laboratory</li>

</ul>

</div>

<div class="col-lg-3 mb-4">

<h5>

Contact Info

</h5>

<p>

📍 Halvad, Gujarat

</p>

<p>

📞 +91 8140150700

</p>

<p>

📧 narayanhospital@gmail.com

</p>

<!-- Google Maps Integration -->
<div class="mt-3 rounded overflow-hidden shadow-sm" style="height: 140px;">
    <iframe 
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d14691.018617180373!2d71.17144215!3d23.0135062!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395995b0580970a9%3A0xb35a09bfd1a7cb6!2sHalvad%2C%20Gujarat!5e0!3m2!1sen!2sin!4v1700000000000!5m2!1sen!2sin" 
        width="100%" 
        height="100%" 
        style="border:0;" 
        allowfullscreen="" 
        loading="lazy" 
        referrerpolicy="no-referrer-when-downgrade">
    </iframe>
</div>

<div class="mt-3">

<a href="#" class="text-white fs-4 me-3">

<i class="bi bi-facebook"></i>

</a>

<a href="#" class="text-white fs-4 me-3">

<i class="bi bi-instagram"></i>

</a>

<a href="#" class="text-white fs-4 me-3">

<i class="bi bi-twitter-x"></i>

</a>

<a href="#" class="text-white fs-4">

<i class="bi bi-linkedin"></i>

</a>

</div>

</div>

</div>

<hr class="border-light">

<div class="text-center">

<p class="mb-1">

© 2026 Smart Hospital Management System.
All Rights Reserved.

</p>

<p>

Developed by

<strong class="text-warning">

Maharshi Dave

</strong>

</p>

</div>

</div>

</footer>

<!-- Back To Top Button -->

<button
onclick="topFunction()"
id="topBtn"
title="Go to top"
class="btn btn-primary rounded-circle shadow">

↑

</button>

<style>

html{
scroll-behavior:smooth;
}

#topBtn{

display:none;

position:fixed;

bottom:30px;

right:30px;

width:55px;

height:55px;

font-size:22px;

z-index:999;

}

.card{

transition:.3s;

}

.card:hover{

transform:translateY(-8px);

}

img{

transition:.4s;

}

img:hover{

transform:scale(1.03);

}

footer a:hover{

color:#ffc107 !important;

}

</style>

<script>

let mybutton=document.getElementById("topBtn");

window.onscroll=function(){

if(document.body.scrollTop>300 || document.documentElement.scrollTop>300){

mybutton.style.display="block";

}else{

mybutton.style.display="none";

}

}

function topFunction(){

document.body.scrollTop=0;

document.documentElement.scrollTop=0;

}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>