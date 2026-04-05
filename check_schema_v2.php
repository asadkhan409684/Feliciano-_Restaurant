<?php
require_once 'config/database.php';
$tables = ['orders', 'reservations', 'users'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
?>
