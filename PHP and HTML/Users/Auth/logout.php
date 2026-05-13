<?php
// 启动 Session
session_start();

// 清空所有 Session 数据
$_SESSION = [];

// 销毁 Session
session_destroy();

// 4. 重定向回登录页面 (或者你想让他退出后回到主页 index.php 也可以)
header("location: ../index.php");
exit;
?>