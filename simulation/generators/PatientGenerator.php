<?php
/**
 * Synthetic Patient & User Generator
 * Path: simulation/generators/PatientGenerator.php
 * 
 * Generates realistic synthetic patient records linked to user accounts in NeonDB PostgreSQL.
 * All generated accounts use reserved test domains (@example.test) and synthetic test contact numbers.
 */

class PatientGenerator {

    /**
     * Generate realistic synthetic patients linked to user accounts.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param int $requestedCount
     * @return array
     */
    public static function generatePatients($conn, $runId, $requestedCount = 10) {
        $requestedCount = max(1, min(100, intval($requestedCount)));
        
        // Activate safety mode
        SimulationSafetyService::enableSafetyMode($runId);

        $createdPatientIds = [];
        $createdUserIds = [];
        $failedCount = 0;

        $firstNames = ['Aarav', 'Ananya', 'Rohan', 'Priya', 'Vikram', 'Sneha', 'Karan', 'Pooja', 'Amit', 'Neha', 'Siddharth', 'Kavya', 'Aditya', 'Riya', 'Rahul', 'Ishita', 'Manish', 'Divya', 'Sanjay', 'Meera'];
        $lastNames = ['Sharma', 'Patel', 'Verma', 'Gupta', 'Singh', 'Deshmukh', 'Joshi', 'Mehta', 'Kulkarni', 'Chawla', 'Bhatia', 'Shah', 'Rao', 'Nair', 'Iyer', 'Dutta'];
        $genders = ['Male', 'Female'];
        $streets = ['Healthcare Way', 'Hospital Road', 'Station Road', 'MG Road', 'Ring Road', 'Civil Lines', 'Park Avenue'];

        SimulationSafetyService::logEvent($conn, $runId, 'PATIENT_GENERATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Starting synthetic patient generation for count {$requestedCount}");

        for ($i = 0; $i < $requestedCount; $i++) {
            $fn = $firstNames[array_rand($firstNames)];
            $ln = $lastNames[array_rand($lastNames)];
            $fullName = $fn . ' ' . $ln;
            $gender = $genders[array_rand($genders)];

            // Age distribution (0-12 ~10%, 13-30 ~25%, 31-50 ~30%, 51-70 ~25%, 71+ ~10%)
            $randPct = rand(1, 100);
            if ($randPct <= 10) {
                $age = rand(1, 12);
            } elseif ($randPct <= 35) {
                $age = rand(13, 30);
            } elseif ($randPct <= 65) {
                $age = rand(31, 50);
            } elseif ($randPct <= 90) {
                $age = rand(51, 70);
            } else {
                $age = rand(71, 88);
            }

            // Safe synthetic email domain (@example.test)
            $uniqSeed = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $email = strtolower($fn . '.' . $ln . '.' . $uniqSeed . '@example.test');

            // Safe synthetic test phone number
            $phone = '98' . sprintf("%08d", rand(10000000, 99999999));
            $address = rand(10, 999) . ' ' . $streets[array_rand($streets)] . ', Sector ' . rand(1, 25);
            $passwordHash = password_hash('SimPass@123', PASSWORD_BCRYPT);

            try {
                $conn->begin_transaction();

                // 1. Create User Account
                $userStmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'patient')");
                $userStmt->bind_param("sss", $fullName, $email, $passwordHash);
                if (!$userStmt->execute()) {
                    throw new Exception("Failed to insert user account: " . $conn->error);
                }
                $userId = $conn->insert_id;

                // 2. Create Patient Record
                $patStmt = $conn->prepare("INSERT INTO patients (user_id, phone, age, gender, address) VALUES (?, ?, ?, ?, ?)");
                $patStmt->bind_param("isiss", $userId, $phone, $age, $gender, $address);
                if (!$patStmt->execute()) {
                    throw new Exception("Failed to insert patient record: " . $conn->error);
                }
                $patientId = $conn->insert_id;

                // 3. Track Entities in simulation_entities
                $trackUser = $conn->prepare("INSERT INTO simulation_entities (run_id, entity_type, entity_id) VALUES (?, 'user', ?)");
                $trackUser->bind_param("si", $runId, $userId);
                $trackUser->execute();

                $trackPat = $conn->prepare("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES (?, 'patient', ?, 'user', ?)");
                $trackPat->bind_param("sii", $runId, $patientId, $userId);
                $trackPat->execute();

                $conn->commit();

                $createdPatientIds[] = $patientId;
                $createdUserIds[] = $userId;

                SimulationSafetyService::logEvent($conn, $runId, 'PATIENT_CREATED', 'SUCCESS', 'patient', $patientId, "Created synthetic patient '{$fullName}' (User ID: {$userId})");

            } catch (Exception $e) {
                $conn->rollback();
                $failedCount++;
                SimulationSafetyService::logEvent($conn, $runId, 'PATIENT_CREATE_FAILED', 'FAILED', 'patient', null, null, $e->getMessage());
            }
        }

        $createdCount = count($createdPatientIds);
        SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $createdCount, $failedCount);
        SimulationSafetyService::logEvent($conn, $runId, 'PATIENT_GENERATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "Completed patient generation: Requested={$requestedCount}, Created={$createdCount}, Failed={$failedCount}");

        return [
            'success'       => true,
            'run_id'        => $runId,
            'requested'     => $requestedCount,
            'created'       => $createdCount,
            'failed'        => $failedCount,
            'patient_ids'   => $createdPatientIds,
            'user_ids'      => $createdUserIds,
            'is_simulated'  => true
        ];
    }
}
