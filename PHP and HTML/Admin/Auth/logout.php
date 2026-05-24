<?php
session_name("AdminSession");
session_start();
$_SESSION = [];
session_destroy();
header("location: login.php");
exit;
?>