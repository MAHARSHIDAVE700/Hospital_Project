<?php
/**
 * Simulation Database Service
 * Path: simulation/services/SimulationDatabaseService.php
 * 
 * Manages simulation_runs metadata and DB helper queries.
 */

class SimulationDatabaseService {

    /**
     * Generate unique simulation run ID.
     * Format: SIM-YYYYMMDD-XXX
     *
     * @param NeonDB $conn
     * @return string
     */
    public static function generateRunId($conn) {
        $prefix = "SIM-" . date('Ymd') . "-";
        $res = $conn->query("SELECT run_id FROM simulation_runs WHERE run_id LIKE '{$prefix}%' ORDER BY created_at DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $lastId = $res->fetch_assoc()['run_id'];
            $seq = intval(substr($lastId, -3)) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . sprintf("%03d", $seq);
    }

    /**
     * Initialize a new simulation run record.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param string $type
     * @param int $durationDays
     * @param string $patientVolume
     * @param string $createdBy
     * @return bool
     */
    public static function createRun($conn, $runId, $type = 'Dry Run', $durationDays = 1, $patientVolume = 'Low', $createdBy = 'Admin') {
        $stmt = $conn->prepare("INSERT INTO simulation_runs (run_id, status, run_type, duration_days, patient_volume, created_by, start_time) VALUES (?, 'PREPARING', ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->bind_param("ssiss", $runId, $type, $durationDays, $patientVolume, $createdBy);
        return $stmt->execute();
    }

    /**
     * Update simulation run status.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param string $status
     * @param int $recordsGenerated
     * @param int $errorCount
     * @return bool
     */
    public static function updateRunStatus($conn, $runId, $status, $recordsGenerated = 0, $errorCount = 0) {
        $sql = "UPDATE simulation_runs SET status = ?, records_generated = records_generated + ?, error_count = error_count + ?";
        if (in_array($status, ['COMPLETED', 'FAILED', 'STOPPED'])) {
            $sql .= ", end_time = CURRENT_TIMESTAMP";
        }
        $sql .= " WHERE run_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siis", $status, $recordsGenerated, $errorCount, $runId);
        return $stmt->execute();
    }

    /**
     * Get simulation run metadata.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @return array|null
     */
    public static function getRun($conn, $runId) {
        $res = $conn->query("SELECT * FROM simulation_runs WHERE run_id = '$runId'");
        return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
    }

    /**
     * Get recent simulation runs.
     *
     * @param NeonDB $conn
     * @param int $limit
     * @return array
     */
    public static function getRecentRuns($conn, $limit = 10) {
        $res = $conn->query("SELECT * FROM simulation_runs ORDER BY created_at DESC LIMIT $limit");
        $runs = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $runs[] = $row;
            }
        }
        return $runs;
    }
}
