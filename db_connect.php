<?php
// db_connect.php
require_once 'config.php';

function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8");
    }
    
    return $conn;
}

// Helper function for prepared statements
function executeQuery($sql, $params = [], $types = "") {
    $conn = getDBConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return ["error" => $conn->error];
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat("s", count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        return ["error" => $stmt->error];
    }
    
    if ($stmt->affected_rows > -1) {
        return [
            "affected_rows" => $stmt->affected_rows,
            "insert_id" => $stmt->insert_id
        ];
    } else {
        $result_set = $stmt->get_result();
        return $result_set->fetch_all(MYSQLI_ASSOC);
    }
}
?>