<?php
require_once 'includes/functions.php';
requireLogin();

switch ($_SESSION['role']) {
    case 'clerk':
        header('Location: clerk/dashboard.php');
        break;
    case 'chief_clerk':
        header('Location: chief_clerk/dashboard.php');
        break;
    case 'electricity_supervisor':
        header('Location: electricity_supervisor/dashboard.php');
        break;
    case 'electrical_engineer':
        header('Location: electrical_engineer/dashboard.php');
        break;
    case 'chief_engineer':
        header('Location: chief_engineer/dashboard.php');
        break;
    default:
        header('Location: logout.php');
}
exit();
?>
