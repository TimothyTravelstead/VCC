<?php
header('Content-Type: application/json');

echo json_encode(['test' => 'working', 'timestamp' => time()]);
?>