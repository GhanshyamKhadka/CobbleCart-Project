<?php
// Admin: create a pilot collection slot.
// Allowed windows: 10:00-13:00, 13:00-16:00, 16:00-19:00.
require 'config.php';
require_role('admin');

$data = input_json();
$date = trim($data['collection_date'] ?? '');
$slot = normalize_collection_time_slot(trim($data['time_slot'] ?? ''));
$maxOrd = 20;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(['success' => false, 'message' => 'collection_date must be YYYY-MM-DD'], 400);
}
if ($slot === null) {
    json_response(['success' => false, 'message' => 'Choose one of the three collection windows: 10:00-13:00, 13:00-16:00, or 16:00-19:00.'], 400);
}

try {
    $dateObj = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dateObj) {
        json_response(['success' => false, 'message' => 'collection_date must be YYYY-MM-DD'], 400);
    }
    if (!in_array((int)$dateObj->format('N'), [3, 4, 5], true)) {
        json_response(['success' => false, 'message' => 'Slots can only be Wednesday, Thursday, or Friday.'], 400);
    }

    $slotStart = clone $dateObj;
    $slotStart->setTime(collection_slot_windows()[$slot], 0, 0);
    if ($slotStart->getTimestamp() < time() + 86400) {
        json_response(['success' => false, 'message' => 'Collection slot must start at least 24 hours from now.'], 400);
    }

    $existing = oci_execute_stmt(
        "SELECT COUNT(*) AS TOTAL
         FROM COLLECTION_SLOT
         WHERE TRUNC(COLLECTION_DATE) = TO_DATE(:dt, 'YYYY-MM-DD')
         AND " . collection_slot_normalized_sql('TIME_SLOT') . " = :ts",
        ['dt' => $date, 'ts' => $slot]
    );
    $existingRow = oci_fetch_assoc_one($existing);
    if ((int)($existingRow['TOTAL'] ?? 0) > 0) {
        json_response(['success' => false, 'message' => 'That collection slot already exists.'], 409);
    }

    oci_execute_stmt(
        "INSERT INTO COLLECTION_SLOT (slot_id, collection_date, time_slot, max_orders)
         VALUES (seq_slot.NEXTVAL, TO_DATE(:dt, 'YYYY-MM-DD'), :ts, :maxord)",
        [
            'dt' => $date,
            'ts' => $slot,
            'maxord' => $maxOrd,
        ]
    );
    json_response(['success' => true, 'message' => 'Slot added']);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Collection only Wed/Thu/Fri') !== false) {
        $msg = 'Slots can only be Wednesday, Thursday, or Friday.';
    } elseif (stripos($msg, 'Invalid collection time slot') !== false) {
        $msg = 'Choose one of the three collection windows: 10:00-13:00, 13:00-16:00, or 16:00-19:00.';
    }
    json_response(['success' => false, 'message' => $msg], 400);
}
