<?php
require_once '../includes/functions.php';
requireRole('clerk');

$pending_count = $conn->query("SELECT COUNT(*) as count FROM quotation_requests WHERE clerk_id = {$_SESSION['user_id']}")->fetch_assoc()['count'];

$requests = $conn->query("SELECT * FROM quotation_requests WHERE clerk_id = {$_SESSION['user_id']} ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clerk Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Clerk Dashboard</h2>
                <div class="nav-links">
                    <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    <a href="submit_quotation.php">New Quotation Request</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Total Requests</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">My Quotation Requests</div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Submitted Date</th>
                                <th>Chief Clerk</th>
                                <th>Supervisor</th>
                                <th>Engineer</th>
                                <th>Chief Engineer</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $requests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $request['id']; ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></td>
                                <td><?php echo formatDate($request['created_at']); ?></td>
                                <td><span class="badge badge-<?php echo $request['chief_clerk_status']; ?>"><?php echo ucfirst($request['chief_clerk_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $request['electricity_supervisor_status']; ?>"><?php echo ucfirst($request['electricity_supervisor_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $request['electrical_engineer_status']; ?>"><?php echo ucfirst($request['electrical_engineer_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $request['chief_engineer_status']; ?>"><?php echo ucfirst($request['chief_engineer_status']); ?></span></td>
                                <td><a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn btn-info">View</a></td>
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
