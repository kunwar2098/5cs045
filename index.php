<?php
$host = 'localhost';
$user = '2417131';
$pass = 'University2025@#$&';
$dbname = 'db2417131';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

?>
