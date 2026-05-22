<?php
// Keep PHP warnings out of the response body — they were corrupting JSON output
// and making the frontend show a generic "Connection error" instead of the real reason.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

session_start();
header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$oracleConfig = [
    'username' => getenv('COBBLECART_DB_USER') ?: 'COBBLECART',
    'password' => getenv('COBBLECART_DB_PASSWORD') ?: 'Oracle#12345@',
    'connection' => getenv('COBBLECART_DB_DSN') ?: 'localhost/FREEPDB1'
];

$dsnCandidates = [$oracleConfig['connection']];
if (!getenv('COBBLECART_DB_DSN')) {
    $dsnCandidates = array_values(array_unique(array_merge($dsnCandidates, [
        'localhost/freepdb1',
        'localhost/XEPDB1',
    ])));
}

$conn = false;
$connectionErrors = [];
foreach ($dsnCandidates as $dsn) {
    $conn = @oci_connect($oracleConfig['username'], $oracleConfig['password'], $dsn, 'AL32UTF8');
    if ($conn) {
        $oracleConfig['connection'] = $dsn;
        break;
    }

    $error = oci_error();
    $connectionErrors[] = $dsn . ': ' . ($error['message'] ?? 'unknown error');
}

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Oracle connection failed. Tried ' . implode(', ', $dsnCandidates) . '. ' . implode(' | ', $connectionErrors)
    ]);
    exit;
}

function oci_execute_stmt(string $sql, array $params = [], bool $autoCommit = true)
{
    global $conn;
    $stmt = oci_parse($conn, $sql);
    if ($stmt === false) {
        $error = oci_error($conn);
        throw new Exception($error['message'] ?? 'Unable to parse SQL statement');
    }

    foreach ($params as $key => &$value) {
        $bindName = is_int($key) ? ':p' . ($key + 1) : (strpos($key, ':') === 0 ? $key : ':' . $key);
        if (!oci_bind_by_name($stmt, $bindName, $value, -1)) {
            $error = oci_error($stmt);
            throw new Exception($error['message'] ?? 'Unable to bind parameter ' . $bindName);
        }
    }

    $mode = $autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT;
    if (!oci_execute($stmt, $mode)) {
        $error = oci_error($stmt);
        throw new Exception($error['message'] ?? 'Unable to execute SQL statement');
    }

    return $stmt;
}

function oci_fetch_assoc_all($stmt): array
{
    $rows = [];
    while (($row = oci_fetch_assoc($stmt)) !== false) {
        $rows[] = $row;
    }
    return $rows;
}

function oci_fetch_assoc_one($stmt)
{
    return oci_fetch_assoc($stmt) ?: null;
}

function oracle_nextval(string $sequence): int
{
    $stmt = oci_parse($GLOBALS['conn'], "SELECT $sequence.NEXTVAL AS NEXTVAL FROM DUAL");
    if ($stmt === false || !oci_execute($stmt)) {
        $error = oci_error($stmt ?: $GLOBALS['conn']);
        throw new Exception($error['message'] ?? 'Unable to get sequence value');
    }
    $row = oci_fetch_assoc($stmt);
    return (int)$row['NEXTVAL'];
}

function db_commit(): bool
{
    return oci_commit($GLOBALS['conn']);
}

function db_rollback(): bool
{
    return oci_rollback($GLOBALS['conn']);
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function input_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function collection_slot_windows(): array
{
    return [
        '10:00-13:00' => 10,
        '13:00-16:00' => 13,
        '16:00-19:00' => 16,
    ];
}

function normalize_collection_time_slot(string $slot): ?string
{
    $compact = strtoupper(preg_replace('/[^0-9APM:-]/', '', $slot));
    $aliases = [
        '10:00-13:00' => '10:00-13:00',
        '10-13' => '10:00-13:00',
        '10:00AM-01:00PM' => '10:00-13:00',
        '10:00AM-1:00PM' => '10:00-13:00',
        '13:00-16:00' => '13:00-16:00',
        '13-16' => '13:00-16:00',
        '01:00PM-04:00PM' => '13:00-16:00',
        '1:00PM-4:00PM' => '13:00-16:00',
        '16:00-19:00' => '16:00-19:00',
        '16-19' => '16:00-19:00',
        '04:00PM-07:00PM' => '16:00-19:00',
        '4:00PM-7:00PM' => '16:00-19:00',
    ];
    return $aliases[$compact] ?? null;
}

function collection_slot_normalized_sql(string $slotColumn): string
{
    $compact = "REGEXP_REPLACE(UPPER($slotColumn), '[^0-9APM:-]', '')";
    return "CASE
        WHEN $compact IN ('10:00-13:00','10-13','10:00AM-01:00PM','10:00AM-1:00PM') THEN '10:00-13:00'
        WHEN $compact IN ('13:00-16:00','13-16','01:00PM-04:00PM','1:00PM-4:00PM') THEN '13:00-16:00'
        WHEN $compact IN ('16:00-19:00','16-19','04:00PM-07:00PM','4:00PM-7:00PM') THEN '16:00-19:00'
        ELSE NULL
    END";
}

function collection_slot_start_sql(string $dateColumn, string $slotColumn): string
{
    $compact = "REGEXP_REPLACE(UPPER($slotColumn), '[^0-9APM:-]', '')";
    return "CASE
        WHEN $compact IN ('10:00-13:00','10-13','10:00AM-01:00PM','10:00AM-1:00PM') THEN $dateColumn + (10 / 24)
        WHEN $compact IN ('13:00-16:00','13-16','01:00PM-04:00PM','1:00PM-4:00PM') THEN $dateColumn + (13 / 24)
        WHEN $compact IN ('16:00-19:00','16-19','04:00PM-07:00PM','4:00PM-7:00PM') THEN $dateColumn + (16 / 24)
        ELSE NULL
    END";
}

function save_uploaded_image(string $inputName, string $destinationDir, array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp']): ?string
{
    if (empty($_FILES[$inputName]['tmp_name']) || !is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
        return null;
    }
    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $originalName = basename($_FILES[$inputName]['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return null;
    }
    $imageInfo = @getimagesize($_FILES[$inputName]['tmp_name']);
    if ($imageInfo === false || strpos((string)$imageInfo['mime'], 'image/') !== 0) {
        return null;
    }
    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
        return null;
    }
    $filename = sprintf('avatar-%s-%s.%s', time(), bin2hex(random_bytes(6)), $ext);
    $target = $destinationDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) {
        return null;
    }
    return 'images/avatars/' . $filename;
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            json_response(['success' => false, 'message' => "$field is required"], 400);
        }
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user_role(): ?string
{
    return isset($_SESSION['role']) ? strtolower($_SESSION['role']) : null;
}

function current_shop_id(): ?int
{
    return isset($_SESSION['shop_id']) ? (int)$_SESSION['shop_id'] : null;
}

function require_login(): void
{
    if (!current_user_id()) {
        json_response(['success' => false, 'message' => 'Authentication required'], 401);
    }
}

function require_role($roles): void
{
    require_login();
    $accepted = is_array($roles) ? $roles : [$roles];
    $accepted = array_map('strtolower', $accepted);
    if (!in_array(current_user_role(), $accepted, true)) {
        json_response(['success' => false, 'message' => 'Access denied'], 403);
    }
}

function split_full_name(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName));
    if (!$parts) {
        return ['', ''];
    }
    $firstName = array_shift($parts);
    $lastName = count($parts) ? implode(' ', $parts) : '';
    return [$firstName, $lastName];
}
?>
