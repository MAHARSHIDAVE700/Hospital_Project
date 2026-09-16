# AI-HODE Dataset Extraction & Feature Engineering Module

## Overview
This module provides an isolated, read-only dataset extraction, feature engineering, validation, and CSV export pipeline for the Hospital Management System's AI-HODE module. It derives operational hospital datasets from real database tables and simulation runs (`SIM-20260817-001`, `SIM-20260817-002`, `SIM-20260817-003`, etc.) to support future machine learning forecasting models.

---

## Directory & File Structure
```
ai_hode/datasets/
├── FeatureEngineeringService.php  # Pure feature derivation functions (date/hour, wait time, occupancy %, workload scores)
├── DatasetExtractor.php           # Idempotent database query & dataset table population engine
├── DatasetValidator.php           # Data integrity, non-negativity, and foreign key reference validator
├── dataset_controller.php         # API endpoints for AJAX dashboard interactions and CSV streaming
├── index.php                      # Modern interactive web dashboard UI
└── README.md                      # Technical reference documentation
```

---

## Prepared Datasets & Target Predictions

### 1. Patient Arrival Prediction Dataset (`ai_dataset_patient_arrivals`)
- **Target**: `actual_arrival_count`
- **Features**: `run_id`, `arrival_date`, `arrival_hour`, `day_of_week`, `department_id`, `appointment_count`, `actual_arrival_count`, `historical_avg_arrivals`.

### 2. OPD Queue / Waiting Time Dataset (`ai_dataset_waiting_time`)
- **Target**: `actual_wait_minutes`
- **Features**: `run_id`, `flow_id`, `patient_id`, `appointment_id`, `doctor_id`, `department_id`, `queue_position`, `token_number`, `arrival_timestamp`, `arrival_hour`, `day_of_week`, `doctor_active_patients`, `estimated_wait_minutes`, `actual_wait_minutes`, `is_bottleneck`.

### 3. Bed Occupancy Dataset (`ai_dataset_bed_occupancy`)
- **Target**: `occupancy_rate_pct`
- **Features**: `run_id`, `snapshot_date`, `bed_type`, `total_beds`, `occupied_beds`, `available_beds`, `admissions_count`, `discharges_count`, `avg_stay_duration_days`, `occupancy_rate_pct`.

### 4. Doctor Workload Dataset (`ai_dataset_doctor_workload`)
- **Target**: `workload_score` / `burnout_risk_level`
- **Features**: `run_id`, `doctor_id`, `snapshot_date`, `department_id`, `opd_patient_count`, `ipd_patient_count`, `total_patient_count`, `avg_consult_duration_minutes`, `queue_length`, `workload_score`, `burnout_risk_level`.

---

## Safety & Idempotency Rules
1. **Source Data Isolation**: All queries on source hospital tables and simulation tables use `SELECT` (`READ ONLY`). Zero source records are altered or deleted.
2. **Idempotent Storage**: Unique constraints (`ON CONFLICT ... DO UPDATE`) ensure repeated extractions update existing records without creating duplicate rows.
3. **No External Dependencies**: Zero calls to Resend email API or Razorpay payment gateway.
