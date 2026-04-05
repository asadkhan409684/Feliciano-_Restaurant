<?php
require_once 'config/database.php';
$res = $conn->query("SELECT * FROM branches");
$data = [];
while($row = $res->fetch_assoc()) $data[] = $row;
echo json_encode($data, JSON_PRETTY_PRINT);
?>
