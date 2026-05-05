<?php
// 1. 必须先启动 session 才能找到当前的 session
session_start();

// 2. 将所有的 Session 变量设置为空数组（清空数据）
$_SESSION = array();

// 3. 彻底销毁当前的 Session
session_destroy();

// 4. 重定向回登录页面 (或者你想让他退出后回到主页 index.php 也可以)
header("location: login.php");
exit;
?>