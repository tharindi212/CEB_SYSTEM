<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Redirect if user doesn't have required role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit();
    }
}

// Get user info by ID
function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get users by role
function getUsersByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = ? AND status = 'active'");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Count pending items for dashboard
function countPendingForRole($conn, $role) {
    $count = 0;

    switch ($role) {
        case 'chief_clerk':
            $result = $conn->query("SELECT COUNT(*) as count FROM quotation_requests WHERE chief_clerk_status = 'pending'");
            $count = $result->fetch_assoc()['count'];
            break;

        case 'electricity_supervisor':
            $result = $conn->query("SELECT COUNT(*) as count FROM quotation_requests WHERE chief_clerk_status = 'approved' AND electricity_supervisor_status = 'pending'");
            $count += $result->fetch_assoc()['count'];
            $result = $conn->query("SELECT COUNT(*) as count FROM po_awarding_letters WHERE electricity_supervisor_approval = 'pending'");
            $count += $result->fetch_assoc()['count'];
            break;

        case 'electrical_engineer':
            $result = $conn->query("SELECT COUNT(*) as count FROM quotation_requests WHERE electricity_supervisor_status = 'approved' AND electrical_engineer_status = 'pending'");
            $count += $result->fetch_assoc()['count'];
            $result = $conn->query("SELECT COUNT(*) as count FROM po_awarding_letters WHERE electrical_engineer_approval = 'pending'");
            $count += $result->fetch_assoc()['count'];
            break;

        case 'chief_engineer':
            $result = $conn->query("SELECT COUNT(*) as count FROM quotation_requests WHERE electrical_engineer_status = 'approved' AND chief_engineer_status = 'pending'");
            $count += $result->fetch_assoc()['count'];
            $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
            $count += $result->fetch_assoc()['count'];
            $result = $conn->query("SELECT COUNT(*) as count FROM po_awarding_letters WHERE chief_engineer_approval = 'pending'");
            $count += $result->fetch_assoc()['count'];
            break;
    }

    return $count;
}

// Send email using PHPMailer
function sendEmail($to, $to_name, $subject, $body) {
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Format date for display
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('d M Y, h:i A', strtotime($date));
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
