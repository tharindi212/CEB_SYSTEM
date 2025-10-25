<?php
require_once '../includes/functions.php';
requireRole('chief_engineer');

$user_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$user = $result->fetch_assoc();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $subject = "Your Registration Has Been Approved";
            $body = "<h3>Registration Approved</h3>
                     <p>Hello {$user['full_name']},</p>
                     <p>Your registration has been approved by the Chief Engineer.</p>
                     <p>You can now login with your credentials at: <a href='" . BASE_URL . "login.php'>" . BASE_URL . "login.php</a></p>
                     <p>Thank you!</p>";

            sendEmail($user['email'], $user['full_name'], $subject, $body);

            $success = 'User approved successfully!';
            header('refresh:2;url=dashboard.php');
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $subject = "Your Registration Has Been Rejected";
            $body = "<h3>Registration Rejected</h3>
                     <p>Hello {$user['full_name']},</p>
                     <p>We regret to inform you that your registration has been rejected by the Chief Engineer.</p>
                     <p>If you have any questions, please contact the administration.</p>
                     <p>Thank you!</p>";

            sendEmail($user['email'], $user['full_name'], $subject, $body);

            $success = 'User rejected successfully!';
            header('refresh:2;url=dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve User Registration</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Review User Registration</h2>
                <div class="nav-links">
                    <a href="dashboard.php">Back to Dashboard</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <div class="dashboard-content">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">User Information</div>

                    <div class="details-grid">
                        <div class="label">Full Name:</div>
                        <div class="value"><?php echo $user['full_name']; ?></div>

                        <div class="label">Email:</div>
                        <div class="value"><?php echo $user['email']; ?></div>

                        <div class="label">Role:</div>
                        <div class="value"><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></div>

                        <div class="label">Registration Date:</div>
                        <div class="value"><?php echo formatDate($user['created_at']); ?></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Approval Action</div>

                    <form method="POST" action="">
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve Registration</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject Registration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
