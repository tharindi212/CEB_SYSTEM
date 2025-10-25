<?php
require_once '../includes/functions.php';
requireRole('electricity_supervisor');

$request_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT qr.*, u.full_name as clerk_name FROM quotation_requests qr JOIN users u ON qr.clerk_id = u.id WHERE qr.id = ? AND qr.chief_engineer_status = 'approved'");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: approved_requests.php');
    exit();
}

$request = $result->fetch_assoc();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $letter_details = sanitize($_POST['letter_details']);

    $stmt = $conn->prepare("INSERT INTO po_awarding_letters (quotation_id, created_by, letter_details) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $request_id, $_SESSION['user_id'], $letter_details);

    if ($stmt->execute()) {
        $chief_clerks = getUsersByRole($conn, 'chief_clerk');
        $engineers = getUsersByRole($conn, 'electrical_engineer');
        $chief_engineers = getUsersByRole($conn, 'chief_engineer');

        $subject = "New PO/Awarding Letter Created";
        $body = "<h3>PO/Awarding Letter Created</h3>
                 <p>A new PO/Awarding letter has been created for Request #{$request_id}.</p>
                 <p><a href='" . BASE_URL . "dashboard.php'>Login to review</a></p>";

        foreach ($chief_clerks as $cc) {
            sendEmail($cc['email'], $cc['full_name'], $subject, $body);
        }

        foreach ($engineers as $engineer) {
            sendEmail($engineer['email'], $engineer['full_name'], $subject, $body);
        }

        foreach ($chief_engineers as $ce) {
            sendEmail($ce['email'], $ce['full_name'], $subject, $body);
        }

        $success = 'PO/Awarding letter created successfully!';
        header('refresh:2;url=approved_requests.php');
    } else {
        $error = 'Failed to create PO/Awarding letter.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create PO/Awarding Letter</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Create PO/Awarding Letter</h2>
                <div class="nav-links">
                    <a href="approved_requests.php">Back to Requests</a>
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
                    <div class="card-header">Request Summary</div>

                    <div class="details-grid">
                        <div class="label">Request ID:</div>
                        <div class="value">#<?php echo $request['id']; ?></div>

                        <div class="label">Clerk:</div>
                        <div class="value"><?php echo $request['clerk_name']; ?></div>

                        <div class="label">Request Type:</div>
                        <div class="value"><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></div>

                        <div class="label">Expected Date:</div>
                        <div class="value"><?php echo date('d M Y', strtotime($request['expecting_date'])); ?></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Create PO/Awarding Letter</div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Letter Details (You can create a simple table format here)</label>
                            <textarea name="letter_details" class="form-control" rows="10" required placeholder="Enter PO/Awarding details here...

Example:
Item | Description | Quantity | Unit Price | Total
------|------------|----------|------------|------
Item 1 | Description 1 | 10 | $50 | $500
Item 2 | Description 2 | 5 | $100 | $500

Total Amount: $1000"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success">Create and Publish</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
