<?php
require_once '../includes/functions.php';
requireRole('chief_engineer');

$request_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT qr.*, u.full_name as clerk_name FROM quotation_requests qr JOIN users u ON qr.clerk_id = u.id WHERE qr.id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$request = $result->fetch_assoc();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $reason = isset($_POST['reason']) ? sanitize($_POST['reason']) : null;

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE quotation_requests SET chief_engineer_status = 'approved', chief_engineer_date = NOW(), final_status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $request_id);

        if ($stmt->execute()) {
            $supervisors = getUsersByRole($conn, 'electricity_supervisor');

            $subject = "Quotation Request Approved - Send PO/Awarding Letter";
            $body = "<h3>Quotation Request Fully Approved</h3>
                     <p>Request #{$request_id} has been fully approved by Chief Engineer.</p>
                     <p>Please create and send the PO/Awarding letter.</p>
                     <p><a href='" . BASE_URL . "dashboard.php'>Login to create PO/Awarding letter</a></p>";

            foreach ($supervisors as $supervisor) {
                sendEmail($supervisor['email'], $supervisor['full_name'], $subject, $body);
            }

            $success = 'Request approved successfully! Electrical Superintendent will be notified to send PO/Awarding letter.';
            header('refresh:2;url=dashboard.php');
        }
    } elseif ($action === 'deny') {
        $stmt = $conn->prepare("UPDATE quotation_requests SET chief_engineer_status = 'denied', chief_engineer_reason = ?, chief_engineer_date = NOW(), final_status = 'denied' WHERE id = ?");
        $stmt->bind_param("si", $reason, $request_id);

        if ($stmt->execute()) {
            $success = 'Request denied successfully!';
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
    <title>Review Request</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Review Request #<?php echo $request['id']; ?></h2>
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
                    <div class="card-header">Request Information</div>

                    <div class="details-grid">
                        <div class="label">Clerk:</div>
                        <div class="value"><?php echo $request['clerk_name']; ?></div>

                        <div class="label">Request Type:</div>
                        <div class="value"><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></div>

                        <?php if ($request['request_type'] === 'vehicle_repair'): ?>
                            <div class="label">Selected Gang:</div>
                            <div class="value"><?php echo $request['selected_gang']; ?></div>

                            <div class="label">Vehicle Number:</div>
                            <div class="value"><?php echo $request['vehicle_number']; ?></div>

                            <div class="label">Repair Details:</div>
                            <div class="value"><?php echo $request['repair_details']; ?></div>
                        <?php else: ?>
                            <div class="label">Resource Type:</div>
                            <div class="value"><?php echo $request['resource_type']; ?></div>

                            <div class="label">Description:</div>
                            <div class="value"><?php echo $request['description']; ?></div>
                        <?php endif; ?>

                        <div class="label">Expected Date:</div>
                        <div class="value"><?php echo date('d M Y', strtotime($request['expecting_date'])); ?></div>

                        <?php if ($request['attachment']): ?>
                            <div class="label">Attachment:</div>
                            <div class="value"><a href="../uploads/<?php echo $request['attachment']; ?>" target="_blank">View Attachment</a></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Approval History</div>

                    <div class="status-timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon approved">✓</div>
                            <div class="timeline-content">
                                <h4>Chief Clerk</h4>
                                <p>Status: <strong>Approved</strong></p>
                                <p>Date: <?php echo formatDate($request['chief_clerk_date']); ?></p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon approved">✓</div>
                            <div class="timeline-content">
                                <h4>Electrical Superintendent</h4>
                                <p>Status: <strong>Approved</strong></p>
                                <p>Date: <?php echo formatDate($request['electricity_supervisor_date']); ?></p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon approved">✓</div>
                            <div class="timeline-content">
                                <h4>Electrical Engineer</h4>
                                <p>Status: <strong>Approved</strong></p>
                                <p>Date: <?php echo formatDate($request['electrical_engineer_date']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($request['chief_engineer_status'] === 'pending'): ?>
                <div class="card">
                    <div class="card-header">Final Approval Action</div>

                    <form method="POST" action="">
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn btn-success" formnovalidate>Approve and Send to Electrical Superintendent</button>
                            <button type="button" class="btn btn-danger" onclick="showDenyForm()">Deny</button>
                        </div>

                        <div id="deny-form" style="display: none; margin-top: 20px;">
                            <div class="form-group">
                                <label>Reason for Denial</label>
                                <textarea id="deny-reason" name="reason" class="form-control" disabled></textarea>
                            </div>
                            <button id="confirm-deny" type="submit" name="action" value="deny" class="btn btn-danger" disabled>Confirm Denial</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showDenyForm() {
            document.getElementById('deny-form').style.display = 'block';
            var reason = document.getElementById('deny-reason');
            var confirmBtn = document.getElementById('confirm-deny');
            reason.disabled = false;
            reason.focus();
            confirmBtn.disabled = false;
        }
    </script>
</body>
</html>
