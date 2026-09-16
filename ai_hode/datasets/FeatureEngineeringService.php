<?php
/**
 * AI-HODE Feature Engineering Service
 * Path: ai_hode/datasets/FeatureEngineeringService.php
 */

class FeatureEngineeringService {

    /**
     * Extract date string (YYYY-MM-DD) from timestamp or date string
     */
    public static function extractDate($timestamp) {
        if (empty($timestamp)) return date('Y-m-d');
        $time = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
        return $time ? date('Y-m-d', $time) : date('Y-m-d');
    }

    /**
     * Extract hour integer (0-23) from timestamp
     */
    public static function extractHour($timestamp) {
        if (empty($timestamp)) return (int)date('H');
        $time = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
        return $time ? (int)date('H', $time) : 0;
    }

    /**
     * Extract day of week integer (1=Mon, 7=Sun)
     */
    public static function extractDayOfWeek($timestamp) {
        if (empty($timestamp)) return (int)date('N');
        $time = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
        return $time ? (int)date('N', $time) : 1;
    }

    /**
     * Calculate waiting time in minutes from timestamps or dwell seconds
     */
    public static function calculateWaitingTimeMinutes($entryTime, $exitTime = null, $dwellSeconds = null) {
        if ($dwellSeconds !== null && $dwellSeconds >= 0) {
            return round($dwellSeconds / 60.0, 2);
        }
        
        if ($entryTime && $exitTime) {
            $tEntry = is_numeric($entryTime) ? (int)$entryTime : strtotime($entryTime);
            $tExit = is_numeric($exitTime) ? (int)$exitTime : strtotime($exitTime);
            if ($tEntry && $tExit && $tExit >= $tEntry) {
                return round(($tExit - $tEntry) / 60.0, 2);
            }
        }
        
        return 0.0;
    }

    /**
     * Calculate occupancy rate percentage (0.0% to 100.0%)
     */
    public static function calculateOccupancyRate($occupiedBeds, $totalBeds) {
        $total = (int)$totalBeds;
        $occupied = (int)$occupiedBeds;

        if ($total <= 0) return 0.0;
        if ($occupied <= 0) return 0.0;
        if ($occupied >= $total) return 100.0;

        return round(($occupied / (float)$total) * 100.0, 2);
    }

    /**
     * Calculate Doctor Workload Score
     * Formula: (OPD_Count * 1.0) + (IPD_Count * 2.5) + (Queue_Length * 0.8)
     */
    public static function calculateDoctorWorkloadScore($opdCount, $ipdCount = 0, $queueLength = 0) {
        $opd = max(0, (int)$opdCount);
        $ipd = max(0, (int)$ipdCount);
        $queue = max(0, (int)$queueLength);

        $score = ($opd * 1.0) + ($ipd * 2.5) + ($queue * 0.8);
        return round($score, 2);
    }

    /**
     * Determine Doctor Burnout Risk Level based on workload score
     */
    public static function determineBurnoutRiskLevel($workloadScore) {
        $score = (float)$workloadScore;
        if ($score < 10.0) {
            return 'LOW';
        } elseif ($score < 25.0) {
            return 'MEDIUM';
        } elseif ($score < 45.0) {
            return 'HIGH';
        } else {
            return 'CRITICAL';
        }
    }
}
