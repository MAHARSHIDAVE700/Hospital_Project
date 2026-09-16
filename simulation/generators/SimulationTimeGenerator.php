<?php
/**
 * Simulation Time & Timestamp Distribution Generator
 * Path: simulation/generators/SimulationTimeGenerator.php
 * 
 * Generates realistic timestamps, queue arrival distributions, wait times,
 * consultation durations, lab processing delays, and IPD stay lengths.
 */

class SimulationTimeGenerator {

    /**
     * Generate a realistic hospital arrival timestamp for a simulation run.
     *
     * @param string $baseDate Y-m-d format date (default today)
     * @return string Y-m-d H:i:s formatted timestamp
     */
    public static function generateArrivalTimestamp($baseDate = null) {
        if (!$baseDate) {
            $baseDate = date('Y-m-d');
        }

        // Weighted time distribution: 09:00-12:00 peak (50%), 14:00-17:00 (35%), 08:00-09:00 (15%)
        $rand = rand(1, 100);
        if ($rand <= 50) {
            $hour = rand(9, 11);
        } elseif ($rand <= 85) {
            $hour = rand(14, 16);
        } else {
            $hour = 8;
        }

        $minute = rand(0, 59);
        $second = rand(0, 59);

        return sprintf("%s %02d:%02d:%02d", $baseDate, $hour, $minute, $second);
    }

    /**
     * Add minutes to a given timestamp.
     *
     * @param string $timestamp
     * @param int $minutes
     * @return string
     */
    public static function addMinutes($timestamp, $minutes) {
        $time = strtotime($timestamp);
        return date('Y-m-d H:i:s', $time + ($minutes * 60));
    }
}
