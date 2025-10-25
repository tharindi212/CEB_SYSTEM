<?php
require_once '../includes/functions.php';
requireRole('electricity_supervisor');

$po_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT po.*, qr.id as quotation_id FROM po_awarding_letters po JOIN quotation_requests qr ON po.quotation_id = qr.id WHERE po.id = ?");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$po = $result->fetch_assoc();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE po_awarding_letters SET electricity_supervisor_approval = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $po_id);
        $stmt->execute();
        $success = 'PO/Awarding letter approved successfully!';
        header('refresh:2;url=dashboard.php');
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE po_awarding_letters SET electricity_supervisor_approval = 'rejected', final_status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $po_id);
        $stmt->execute();
        $success = 'PO/Awarding letter rejected successfully!';
        header('refresh:2;url=dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review PO/Awarding Letter</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Review PO/Awarding Letter #<?php echo $po['id']; ?></h2>
                <div class="nav-links">
                    <a href="dashboard.php">Back to Dashboard</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <div class="dashboard-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">PO/Awarding Letter Details</div>

                    <div class="details-grid">
                        <div class="label">Quotation ID:</div>
                        <div class="value">#<?php echo $po['quotation_id']; ?></div>

                        <div class="label">Letter Details:</div>
                        <div class="value"><?php echo nl2br($po['letter_details']); ?></div>

                        <div class="label">Created Date:</div>
                        <div class="value"><?php echo formatDate($po['created_at']); ?></div>
                    </div>
                </div>

                <?php if ($po['electricity_supervisor_approval'] === 'pending'): ?>
                <div class="card">
                    <div class="card-header">Approval Action</div>

                    <form method="POST" action="">
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
