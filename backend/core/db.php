<?php
// Oracle (OCI8) database helpers. Mirrors the conventions used in api/config.php
// so existing SQL stays portable across both layers.

function db_connect()
{
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        return $GLOBALS['conn'];
    }
    $cfg  = $GLOBALS['db_config'];
    $dsnCandidates = [$cfg['connection']];
    if (!getenv('COBBLECART_DB_DSN')) {
        $dsnCandidates = array_values(array_unique(array_merge($dsnCandidates, [
            'localhost/freepdb1',
            'localhost/XEPDB1',
        ])));
    }

    $conn = false;
    $connectionErrors = [];
    foreach ($dsnCandidates as $dsn) {
        $conn = @oci_connect($cfg['username'], $cfg['password'], $dsn, $cfg['charset']);
        if ($conn) {
            $GLOBALS['db_config']['connection'] = $dsn;
            break;
        }

        $error = oci_error();
        $connectionErrors[] = $dsn . ': ' . ($error['message'] ?? 'unknown error');
    }

    if (!$conn) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed. Tried ' . implode(', ', $dsnCandidates) . '. ' . implode(' | ', $connectionErrors)
        ]);
        exit;
    }
    $GLOBALS['conn'] = $conn;
    return $conn;
}

function db_execute(string $sql, array $params = [], bool $autoCommit = true)
{
    $conn = db_connect();
    $stmt = oci_parse($conn, $sql);
    if ($stmt === false) {
        $e = oci_error($conn);
        throw new RuntimeException($e['message'] ?? 'parse failed');
    }

    foreach ($params as $key => &$value) {
        $bind = is_int($key) ? ':p' . ($key + 1) : (strpos($key, ':') === 0 ? $key : ':' . $key);
        if (!oci_bind_by_name($stmt, $bind, $value, -1)) {
            $e = oci_error($stmt);
            throw new RuntimeException($e['message'] ?? 'bind failed: ' . $bind);
        }
    }

    $mode = $autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT;
    if (!oci_execute($stmt, $mode)) {
        $e = oci_error($stmt);
        throw new RuntimeException($e['message'] ?? 'execute failed');
    }
    return $stmt;
}

function db_fetch_all($stmt): array
{
    $rows = [];
    while (($row = oci_fetch_assoc($stmt)) !== false) {
        $rows[] = $row;
    }
    return $rows;
}

function db_fetch_one($stmt)
{
    return oci_fetch_assoc($stmt) ?: null;
}

function db_nextval(string $sequence): int
{
    $stmt = db_execute("SELECT $sequence.NEXTVAL AS NEXTVAL FROM DUAL");
    $row  = oci_fetch_assoc($stmt);
    return (int)$row['NEXTVAL'];
}

function db_commit(): bool   { return oci_commit($GLOBALS['conn']); }
function db_rollback(): bool { return oci_rollback($GLOBALS['conn']); }
