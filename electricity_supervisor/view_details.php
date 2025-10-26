<?php
require_once '../includes/functions.php';
requireRole('electricity_supervisor');

$request_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT qr.*, u.full_name as clerk_name FROM quotation_requests qr JOIN users u ON qr.clerk_id = u.id WHERE qr.id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: approved_requests.php');
    exit();
}

$request = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Request Details #<?php echo $request['id']; ?></h2>
                <div class="nav-links">
                    <a href="approved_requests.php">Back to Requests</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <div class="dashboard-content">
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
                    <div class="card-header">Approval Status</div>

                    <div class="status-timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon <?php echo $request['chief_clerk_status']; ?>">
                                <?php echo $request['chief_clerk_status'] === 'approved' ? '✓' : ($request['chief_clerk_status'] === 'denied' ? '✗' : '?'); ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Chief Clerk</h4>
                                <p>Status: <strong><?php echo ucfirst($request['chief_clerk_status']); ?></strong></p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon <?php echo $request['electricity_supervisor_status']; ?>">
                                <?php echo $request['electricity_supervisor_status'] === 'approved' ? '✓' : ($request['electricity_supervisor_status'] === 'denied' ? '✗' : '?'); ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Electrical Superintendent</h4>
                                <p>Status: <strong><?php echo ucfirst($request['electricity_supervisor_status']); ?></strong></p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon <?php echo $request['electrical_engineer_status']; ?>">
                                <?php echo $request['electrical_engineer_status'] === 'approved' ? '✓' : ($request['electrical_engineer_status'] === 'denied' ? '✗' : '?'); ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Electrical Engineer</h4>
                                <p>Status: <strong><?php echo ucfirst($request['electrical_engineer_status']); ?></strong></p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-icon <?php echo $request['chief_engineer_status']; ?>">
                                <?php echo $request['chief_engineer_status'] === 'approved' ? '✓' : ($request['chief_engineer_status'] === 'denied' ? '✗' : '?'); ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Chief Engineer</h4>
                                <p>Status: <strong><?php echo ucfirst($request['chief_engineer_status']); ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
