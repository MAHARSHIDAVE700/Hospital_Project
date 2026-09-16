<?php
/**
 * Pharmacy Dispense Payload Generator
 * Path: simulation/generators/PharmacyGenerator.php
 * 
 * Generates realistic pharmacy dispense items and maps prescribed text to active medicine inventory.
 */

class PharmacyGenerator {

    /**
     * Map prescription text or select available stock medicines to dispense.
     *
     * @param array $availableMedicines Array of medicines with stock_quantity > 0
     * @param string $prescribedText Text from prescriptions.medicines column
     * @return array Selected items with quantities and prices
     */
    public static function buildDispensePayload($availableMedicines, $prescribedText = '') {
        if (empty($availableMedicines)) {
            return [];
        }

        $selectedItems = [];
        $lowerText = strtolower($prescribedText);

        // Match prescribed text against inventory names
        foreach ($availableMedicines as $med) {
            $medNameLower = strtolower($med['medicine_name']);
            $genericLower = strtolower($med['generic_name']);

            $isMatch = (
                strpos($lowerText, $medNameLower) !== false || 
                strpos($lowerText, $genericLower) !== false ||
                (strpos($lowerText, 'paracetamol') !== false && strpos($medNameLower, 'paracetamol') !== false) ||
                (strpos($lowerText, 'amoxicillin') !== false && strpos($medNameLower, 'amoxicillin') !== false) ||
                (strpos($lowerText, 'cetirizine') !== false && strpos($medNameLower, 'cetirizine') !== false)
            );

            if ($isMatch && intval($med['stock_quantity']) >= 5) {
                $qty = rand(5, min(10, intval($med['stock_quantity'])));
                $pricePerUnit = floatval($med['unit_price']);
                $selectedItems[] = [
                    'medicine_id'    => intval($med['medicine_id']),
                    'medicine_name'  => $med['medicine_name'],
                    'quantity'       => $qty,
                    'price_per_unit' => $pricePerUnit,
                    'total_price'    => round($qty * $pricePerUnit, 2)
                ];
            }
        }

        // Fallback: If no direct text match, pick 1 or 2 available medicines with healthy stock
        if (empty($selectedItems)) {
            $healthyMed = array_filter($availableMedicines, function($m) {
                return intval($m['stock_quantity']) >= 5;
            });
            $healthyMed = array_values($healthyMed);

            if (!empty($healthyMed)) {
                $count = min(rand(1, 2), count($healthyMed));
                shuffle($healthyMed);

                for ($i = 0; $i < $count; $i++) {
                    $med = $healthyMed[$i];
                    $qty = rand(5, min(10, intval($med['stock_quantity'])));
                    $pricePerUnit = floatval($med['unit_price']);
                    $selectedItems[] = [
                        'medicine_id'    => intval($med['medicine_id']),
                        'medicine_name'  => $med['medicine_name'],
                        'quantity'       => $qty,
                        'price_per_unit' => $pricePerUnit,
                        'total_price'    => round($qty * $pricePerUnit, 2)
                    ];
                }
            }
        }

        return $selectedItems;
    }
}
