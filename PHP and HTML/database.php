<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "online_car_dealer_and_inventory_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Connected successfully";

?>