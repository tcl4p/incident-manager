<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "REQUEST_METHOD = " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n\n";
echo "POST:\n";
var_dump($_POST);
echo "</pre>";
