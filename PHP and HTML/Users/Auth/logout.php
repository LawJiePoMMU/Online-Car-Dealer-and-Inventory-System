<?php
// 启动 Session
session_start();

// 清空所有 Session 数据
$_SESSION = [];

// 销毁 Session
session_destroy();

// 跳转回登录页面
header("Location: /Online-Car-Dealer-and-Inventory-System/PHP%20AND%20HTML/Users/Auth/login.php");
exit();
?>