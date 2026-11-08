<?php
$host = 'localhost';
$user = 'your_db_user';
$pass = 'your_db_password';
$dbname = 'your_db_name';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = mysqli_connect($host, $user, $pass, $dbname);
mysqli_set_charset($conn, 'utf8mb4');
