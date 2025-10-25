<?php
require_once '../includes/functions.php';
requireRole('clerk');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_type = sanitize($_POST['request_type']);
    $expecting_date = sanitize($_POST['expecting_date']);

    $selected_gang = null;
    $vehicle_number = null;
    $repair_details = null;
    $resource_type = null;
    $description = null;
    $attachment = null;

    if ($request_type === 'vehicle_repair') {
        $selected_gang = sanitize($_POST['selected_gang']);
        $vehicle_number = sanitize($_POST['vehicle_number']);
        $repair_details = sanitize($_POST['repair_details']);
    } else {
        $resource_type = sanitize($_POST['resource_type']);
        $description = sanitize($_POST['description']);
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
            $attachment = $file_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO quotation_requests (clerk_id, request_type, selected_gang, vehicle_number, repair_details, resource_type, description, expecting_date, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $_SESSION['user_id'], $request_type, $selected_gang, $vehicle_number, $repair_details, $resource_type, $description, $expecting_date, $attachment);

    if ($stmt->execute()) {
        $chief_clerks = getUsersByRole($conn, 'chief_clerk');
        $chief_engineers = getUsersByRole($conn, 'chief_engineer');

        $subject = "New Quotation Request Submitted";
        $body = "<h3>New Quotation Request</h3>
                 <p>A new quotation request has been submitted by {$_SESSION['full_name']}.</p>
                 <p><strong>Type:</strong> " . ucwords(str_replace('_', ' ', $request_type)) . "</p>
                 <p><strong>Expected Date:</strong> {$expecting_date}</p>
                 <p><a href='" . BASE_URL . "dashboard.php'>Login to view details</a></p>";

        foreach ($chief_clerks as $cc) {
            sendEmail($cc['email'], $cc['full_name'], $subject, $body);
        }

        foreach ($chief_engineers as $ce) {
            sendEmail($ce['email'], $ce['full_name'], $subject, $body);
        }

        $success = 'Quotation request submitted successfully!';
    } else {
        $error = 'Failed to submit request. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Quotation Request</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard-wrapper">
            <div class="dashboard-header">
                <h2>Submit Quotation Request</h2>
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
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Request Type</label>
                            <select name="request_type" id="request_type" class="form-control" required onchange="toggleFields()">
                                <option value="">Select Type</option>
                                <option value="vehicle_repair">Vehicle Repair</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div id="vehicle_fields" style="display: none;">
                            <div class="form-group">
                                <label>Selected Gang</label>
                                <input type="text" name="selected_gang" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Vehicle Number</label>
                                <input type="text" name="vehicle_number" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Repair Details</label>
                                <textarea name="repair_details" class="form-control"></textarea>
                            </div>
                        </div>

                        <div id="other_fields" style="display: none;">
                            <div class="form-group">
                                <label>Resource Type</label>
                                <input type="text" name="resource_type" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control"></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Expected Date</label>
                            <input type="date" name="expecting_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Attachment (Optional)</label>
                            <input type="file" name="attachment" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleFields() {
            var requestType = document.getElementById('request_type').value;
            var vehicleFields = document.getElementById('vehicle_fields');
            var otherFields = document.getElementById('other_fields');

            if (requestType === 'vehicle_repair') {
                vehicleFields.style.display = 'block';
                otherFields.style.display = 'none';
            } else if (requestType === 'other') {
                vehicleFields.style.display = 'none';
                otherFields.style.display = 'block';
            } else {
                vehicleFields.style.display = 'none';
                otherFields.style.display = 'none';
            }
        }
    </script>
</body>
</html>
