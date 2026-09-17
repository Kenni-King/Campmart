<?php
session_start();
if (isset($_SESSION['userAppId'])) {
    header('Location: dashboard.php');
} else {
    header('Location: ../login.php');
}
exit;
