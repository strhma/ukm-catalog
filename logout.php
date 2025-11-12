<?php
require_once '../config/config.php';

$auth = new Auth($db);
$auth->logout();

setFlashMessage('success', 'You have been logged out successfully');
header('Location: ../login.php');
exit();
?>