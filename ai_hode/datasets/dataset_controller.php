<?php
/**
 * AI-HODE Dataset Controller API
 * Path: ai_hode/datasets/dataset_controller.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/DatasetExtractor.php';
require_once __DIR__ . '/DatasetValidator.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

$extractor = new DatasetExtractor();
$validator = new DatasetValidator();

switch ($action) {

    case 'discover_source_data':
        $tables = [
            'simulation_runs', 'simulation_events', 'simulation_entities',
            'patient_flow', 'queue_events', 'doctor_workload', 'predictions',
            'appointments', 'patients', 'doctors', 'lab_requests',
            'medicine_dispenses', 'medicines', 'beds', 'bed_allocations', 'ipd_admissions', 'invoices'
        ];

        $discoveryMap = [];
        foreach ($tables as $t) {
            $sql = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '{$t}' ORDER BY ordinal_position";
            $res = $conn->query($sql);
            $cols = [];
            if ($res && $res->num_rows > 0) {
                while ($r = $res->fetch_assoc()) {
                    $cols[] = $r['column_name'] . ' (' . $r['data_type'] . ')';
                }
            }
            $cRes = $conn->query("SELECT COUNT(*) as cnt FROM {$t}");
            $cnt = ($cRes && ($cRow = $cRes->fetch_assoc())) ? (int)$cRow['cnt'] : 0;
            $discoveryMap[$t] = [
                'row_count' => $cnt,
                'columns' => $cols
            ];
        }

        echo json_encode([
            'status' => 'SUCCESS',
            'tables_count' => count($discoveryMap),
            'discovery' => $discoveryMap
        ]);
        break;

    case 'extract_all':
        $res = $extractor->extractAll();
        $validation = $validator->validateAll();
        echo json_encode([
            'status' => 'SUCCESS',
            'message' => 'Dataset extraction and feature engineering completed successfully.',
            'extraction' => $res,
            'validation' => $validation
        ]);
        break;

    case 'validate_datasets':
        $validation = $validator->validateAll();
        echo json_encode([
            'status' => 'SUCCESS',
            'validation' => $validation
        ]);
        break;

    case 'preview_dataset':
        $type = isset($_GET['type']) ? $_GET['type'] : 'patient_arrivals';
        $tableMap = [
            'patient_arrivals' => 'ai_dataset_patient_arrivals',
            'waiting_time'     => 'ai_dataset_waiting_time',
            'bed_occupancy'    => 'ai_dataset_bed_occupancy',
            'doctor_workload'  => 'ai_dataset_doctor_workload'
        ];

        $tableName = isset($tableMap[$type]) ? $tableMap[$type] : 'ai_dataset_patient_arrivals';
        $res = $conn->query("SELECT * FROM {$tableName} ORDER BY dataset_id DESC LIMIT 10");

        $rows = [];
        if ($res && $res->num_rows > 0) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }

        echo json_encode([
            'status' => 'SUCCESS',
            'dataset_type' => $type,
            'table_name' => $tableName,
            'count' => count($rows),
            'data' => $rows
        ]);
        break;

    case 'export_csv':
        $type = isset($_GET['type']) ? $_GET['type'] : 'patient_arrivals';
        $tableMap = [
            'patient_arrivals' => 'ai_dataset_patient_arrivals',
            'waiting_time'     => 'ai_dataset_waiting_time',
            'bed_occupancy'    => 'ai_dataset_bed_occupancy',
            'doctor_workload'  => 'ai_dataset_doctor_workload'
        ];

        $tableName = isset($tableMap[$type]) ? $tableMap[$type] : 'ai_dataset_patient_arrivals';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tableName . '_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        $res = $conn->query("SELECT * FROM {$tableName} ORDER BY dataset_id ASC");

        if ($res && $res->num_rows > 0) {
            $first = true;
            while ($r = $res->fetch_assoc()) {
                if ($first) {
                    fputcsv($output, array_keys($r));
                    $first = false;
                }
                fputcsv($output, array_values($r));
            }
        }
        fclose($output);
        exit;

    default:
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'Invalid or missing action parameter.'
        ]);
        break;
}
