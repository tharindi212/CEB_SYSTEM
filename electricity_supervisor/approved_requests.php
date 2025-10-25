<?php
require_once '../includes/functions.php';
requireRole('electricity_supervisor');

$approved_requests = $conn->query("SELECT qr.*, u.full_name as clerk_name FROM quotation_requests qr JOIN users u ON qr.clerk_id = u.id WHERE qr.electricity_supervisor_status = 'approved' ORDER BY qr.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Requests</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Approved Requests</h2>
                <div class="nav-links">
                    <a href="dashboard.php">Back to Dashboard</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="card">
                    <div class="card-header">Approved Quotation Requests</div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Clerk</th>
                                <th>Type</th>
                                <th>Expected Date</th>
                                <th>Engineer Status</th>
                                <th>Chief Engineer Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $approved_requests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $request['id']; ?></td>
                                <td><?php echo $request['clerk_name']; ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($request['expecting_date'])); ?></td>
                                <td><span class="badge badge-<?php echo $request['electrical_engineer_status']; ?>"><?php echo ucfirst($request['electrical_engineer_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $request['chief_engineer_status']; ?>"><?php echo ucfirst($request['chief_engineer_status']); ?></span></td>
                                <td>
                                    <?php if ($request['chief_engineer_status'] === 'approved'): ?>
                                        <a href="create_po.php?id=<?php echo $request['id']; ?>" class="btn btn-success">Create PO/Awarding</a>
                                    <?php else: ?>
                                        <a href="view_details.php?id=<?php echo $request['id']; ?>" class="btn btn-info">View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
