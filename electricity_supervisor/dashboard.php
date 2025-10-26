<?php
require_once '../includes/functions.php';
requireRole('electricity_supervisor');

$pending_count = $conn->query("SELECT COUNT(*) as count FROM quotation_requests WHERE chief_clerk_status = 'approved' AND electricity_supervisor_status = 'pending'")->fetch_assoc()['count'];

$po_pending_count = $conn->query("SELECT COUNT(*) as count FROM po_awarding_letters WHERE electricity_supervisor_approval = 'pending'")->fetch_assoc()['count'];

$requests = $conn->query("SELECT qr.*, u.full_name as clerk_name FROM quotation_requests qr JOIN users u ON qr.clerk_id = u.id WHERE qr.chief_clerk_status = 'approved' AND qr.electricity_supervisor_status = 'pending' ORDER BY qr.created_at DESC");

$po_requests = $conn->query("SELECT po.*, qr.id as quotation_id FROM po_awarding_letters po JOIN quotation_requests qr ON po.quotation_id = qr.id WHERE po.electricity_supervisor_approval = 'pending' ORDER BY po.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electrical Superintendent Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Electrical Superintendent Dashboard</h2>
                <div class="nav-links">
                    <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    <a href="approved_requests.php">Approved Requests</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Pending Quotation Approvals</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $po_pending_count; ?></h3>
                        <p>Pending PO/Awarding Approvals</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Pending Quotation Requests</div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Clerk</th>
                                <th>Type</th>
                                <th>Expected Date</th>
                                <th>Submitted Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $requests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $request['id']; ?></td>
                                <td><?php echo $request['clerk_name']; ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($request['expecting_date'])); ?></td>
                                <td><?php echo formatDate($request['created_at']); ?></td>
                                <td><a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn btn-info">Review</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($po_pending_count > 0): ?>
                <div class="card">
                    <div class="card-header">Pending PO/Awarding Letter Approvals</div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Quotation ID</th>
                                <th>Created Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($po = $po_requests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $po['id']; ?></td>
                                <td>#<?php echo $po['quotation_id']; ?></td>
                                <td><?php echo formatDate($po['created_at']); ?></td>
                                <td><a href="view_po.php?id=<?php echo $po['id']; ?>" class="btn btn-info">Review</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
