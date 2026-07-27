<?php
// ai_hode/migrate_ai_hode.php
// Script to migrate and create all AI-HODE tables in Neon PostgreSQL database

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain');
echo "===================================================\n";
echo "AI-HODE (AI Hospital Operational Decision Engine)\n";
echo "Database Table Creation in Neon PostgreSQL\n";
echo "===================================================\n\n";

$tables = [
    'patient_flow' => "
        CREATE TABLE IF NOT EXISTS patient_flow (
            flow_id BIGSERIAL PRIMARY KEY,
            patient_id INT NOT NULL,
            appointment_id INT DEFAULT NULL,
            department_id INT NOT NULL,
            current_stage VARCHAR(50) NOT NULL DEFAULT 'CHECK_IN',
            triage_priority VARCHAR(30) DEFAULT 'P3_STANDARD',
            stage_entry_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            stage_exit_time TIMESTAMP DEFAULT NULL,
            dwell_time_seconds INT DEFAULT NULL,
            is_bottleneck BOOLEAN NOT NULL DEFAULT FALSE,
            delay_reason VARCHAR(255) DEFAULT NULL,
            assigned_doctor_id INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ",

    'queue_events' => "
        CREATE TABLE IF NOT EXISTS queue_events (
            event_id BIGSERIAL PRIMARY KEY,
            flow_id BIGINT REFERENCES patient_flow(flow_id) ON DELETE CASCADE,
            queue_token VARCHAR(50) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            estimated_wait_seconds INT DEFAULT NULL,
            actual_wait_seconds INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ",

    'doctor_workload' => "
        CREATE TABLE IF NOT EXISTS doctor_workload (
            workload_id BIGSERIAL PRIMARY KEY,
            doctor_id INT NOT NULL,
            active_patients INT DEFAULT 0,
            consultations_completed_today INT DEFAULT 0,
            avg_consultation_time_seconds INT DEFAULT 0,
            workload_score DECIMAL(5,2) DEFAULT 0.00,
            burnout_risk_level VARCHAR(20) DEFAULT 'LOW',
            recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ",

    'predictions' => "
        CREATE TABLE IF NOT EXISTS predictions (
            prediction_id BIGSERIAL PRIMARY KEY,
            target_type VARCHAR(50) NOT NULL,
            target_entity_id VARCHAR(50) DEFAULT NULL,
            predicted_value DECIMAL(10,2) NOT NULL,
            confidence_lower DECIMAL(10,2) DEFAULT NULL,
            confidence_upper DECIMAL(10,2) DEFAULT NULL,
            prediction_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            model_version VARCHAR(50) DEFAULT 'v1.0'
        );
    ",

    'recommendations' => "
        CREATE TABLE IF NOT EXISTS recommendations (
            recommendation_id BIGSERIAL PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            action_details TEXT NOT NULL,
            urgency_level VARCHAR(20) DEFAULT 'MEDIUM',
            impact_score DECIMAL(5,2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'PENDING',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ",

    'decision_logs' => "
        CREATE TABLE IF NOT EXISTS decision_logs (
            log_id BIGSERIAL PRIMARY KEY,
            recommendation_id BIGINT REFERENCES recommendations(recommendation_id) ON DELETE SET NULL,
            action_taken VARCHAR(255) NOT NULL,
            executed_by INT DEFAULT NULL,
            execution_type VARCHAR(50) DEFAULT 'MANUAL',
            outcome_notes TEXT DEFAULT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ",

    'ai_models' => "
        CREATE TABLE IF NOT EXISTS ai_models (
            model_id SERIAL PRIMARY KEY,
            model_name VARCHAR(100) NOT NULL,
            algorithm VARCHAR(100) NOT NULL,
            version VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'ACTIVE',
            parameters JSONB DEFAULT '{}'::jsonb,
            last_trained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ",

    'model_metrics' => "
        CREATE TABLE IF NOT EXISTS model_metrics (
            metric_id BIGSERIAL PRIMARY KEY,
            model_id INT REFERENCES ai_models(model_id) ON DELETE CASCADE,
            mae DECIMAL(10,4) DEFAULT NULL,
            rmse DECIMAL(10,4) DEFAULT NULL,
            accuracy_score DECIMAL(5,4) DEFAULT NULL,
            latency_ms INT DEFAULT NULL,
            evaluated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    "
];

$successCount = 0;
foreach ($tables as $tableName => $sql) {
    echo "Creating table '{$tableName}'... ";
    $res = $conn->query($sql);
    if ($res === false) {
        echo "FAILED: " . $conn->error . "\n";
    } else {
        echo "SUCCESS.\n";
        $successCount++;
    }
}

echo "\nMigration finished: {$successCount}/" . count($tables) . " tables ready in Neon database.\n";
