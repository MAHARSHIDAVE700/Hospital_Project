-- ============================================================================
-- AI-HODE (AI Hospital Operational Decision Engine)
-- Table 1: patient_flow Schema Definition
-- Engine: MySQL 8.0+ / InnoDB / utf8mb4
-- ============================================================================

CREATE TABLE IF NOT EXISTS patient_flow (
    flow_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    department_id INT NOT NULL,
    current_stage ENUM(
        'CHECK_IN', 
        'TRIAGE', 
        'WAITING_FOR_CONSULTATION', 
        'IN_CONSULTATION', 
        'LAB_PHARMACY', 
        'BILLING', 
        'ADMITTED', 
        'DISCHARGED', 
        'LEFT_WITHOUT_BEING_SEEN'
    ) NOT NULL DEFAULT 'CHECK_IN',
    triage_priority ENUM('P1_CRITICAL', 'P2_URGENT', 'P3_STANDARD', 'P4_NON_URGENT') DEFAULT 'P3_STANDARD',
    stage_entry_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stage_exit_time DATETIME DEFAULT NULL,
    dwell_time_seconds INT GENERATED ALWAYS AS (
        CASE 
            WHEN stage_exit_time IS NOT NULL THEN TIMESTAMPDIFF(SECOND, stage_entry_time, stage_exit_time)
            ELSE NULL 
        END
    ) STORED,
    is_bottleneck BOOLEAN NOT NULL DEFAULT FALSE,
    delay_reason VARCHAR(255) DEFAULT NULL,
    assigned_doctor_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Key Constraints referencing existing HMS tables
    CONSTRAINT fk_pf_patient FOREIGN KEY (patient_id) 
        REFERENCES patients(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pf_appointment FOREIGN KEY (appointment_id) 
        REFERENCES appointments(appointment_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pf_department FOREIGN KEY (department_id) 
        REFERENCES departments(department_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pf_doctor FOREIGN KEY (assigned_doctor_id) 
        REFERENCES doctors(doctor_id) ON DELETE SET NULL ON UPDATE CASCADE,

    -- Indexes for Operational Telemetry and Time-Series Analytics
    INDEX idx_pf_patient_stage (patient_id, current_stage),
    INDEX idx_pf_dept_stage_entry (department_id, current_stage, stage_entry_time),
    INDEX idx_pf_doctor_active (assigned_doctor_id, current_stage),
    INDEX idx_pf_triage_entry (triage_priority, stage_entry_time),
    INDEX idx_pf_bottleneck (is_bottleneck, stage_entry_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
