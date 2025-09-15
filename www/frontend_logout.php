<?php
session_start();
session_destroy();
header('Location: /frontend_login.php');
exit;
?>

