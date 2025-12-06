<?php
require_once 'config.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'employee') {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $quotation_type = trim($_POST['quotation_type']);
    $vehicle_no = trim($_POST['vehicle_no']);
    $gang = trim($_POST['gang']);
    $description = trim($_POST['description']);
    $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
    
    // Handle file upload
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $attachment = uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $attachment;
        
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
            $error_message = "Failed to upload attachment.";
            $attachment = null;
        }
    }
    
    if (empty($error_message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO quotations (quotation_type, vehicle_no, gang, description, expected_date, attachment, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_type, $vehicle_no, $gang, $description, $expected_date, $attachment, $_SESSION['user_id']]);
            
            $success_message = "Quotation request submitted successfully!";
            
            // Clear form data
            $_POST = array();
        } catch (PDOException $e) {
            $error_message = "Failed to submit quotation request. Please try again.";
        }
    }
}

// Fetch employee's quotation history
try {
    $stmt = $pdo->prepare("
        SELECT q.*, u.full_name as approved_by_name 
        FROM quotations q 
        LEFT JOIN users u ON q.approved_by = u.id 
        WHERE q.submitted_by = ? 
        ORDER BY q.submission_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quotations = array();
    $error_message = "Failed to load quotation history.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Quotation Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1>Employee Dashboard</h1>
                    <p>Submit and manage quotation requests</p>
                </div>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="logout.php" class="btn btn-secondary" style="margin-top: 10px;">Logout</a>
                </div>
            </div>
            
            <div class="dashboard-content">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Navigation Tabs -->
                <div class="nav-tabs">
                    <button class="nav-tab active" onclick="showTab('new-request')">New Request</button>
                    <button class="nav-tab" onclick="showTab('history')">My Requests History</button>
                </div>
                
                <!-- New Quotation Request Form -->
                <div id="new-request" class="tab-content">
                    <div class="quotation-form">
                        <h3>Submit New Quotation Request</h3>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="quotation_type">Quotation Type *</label>
                                    <select id="quotation_type" name="quotation_type" required>
                                        <option value="">Select quotation type</option>
                                        <option value="Vehicle Maintenance" <?php echo (isset($_POST['quotation_type']) && $_POST['quotation_type'] == 'Vehicle Maintenance') ? 'selected' : ''; ?>>Vehicle Maintenance</option>
                                        <option value="Fuel Supply" <?php echo (isset($_POST['quotation_type']) && $_POST['quotation_type'] == 'Fuel Supply') ? 'selected' : ''; ?>>Fuel Supply</option>
                                        <option value="Parts & Accessories" <?php echo (isset($_POST['quotation_type']) && $_POST['quotation_type'] == 'Parts & Accessories') ? 'selected' : ''; ?>>Parts & Accessories</option>
                                        <option value="Repair Services" <?php echo (isset($_POST['quotation_type']) && $_POST['quotation_type'] == 'Repair Services') ? 'selected' : ''; ?>>Repair Services</option>
                                        <option value="Other" <?php echo (isset($_POST['quotation_type']) && $_POST['quotation_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="vehicle_no">Vehicle Number *</label>
                                    <input type="text" id="vehicle_no" name="vehicle_no" required 
                                           placeholder="e.g., ABC-1234"
                                           value="<?php echo isset($_POST['vehicle_no']) ? htmlspecialchars($_POST['vehicle_no']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gang">Assigned Gang *</label>
                                    <select id="gang" name="gang" required>
                                        <option value="">Select gang</option>
                                        <option value="Gang A" <?php echo (isset($_POST['gang']) && $_POST['gang'] == 'Gang A') ? 'selected' : ''; ?>>Gang A</option>
                                        <option value="Gang B" <?php echo (isset($_POST['gang']) && $_POST['gang'] == 'Gang B') ? 'selected' : ''; ?>>Gang B</option>
                                        <option value="Gang C" <?php echo (isset($_POST['gang']) && $_POST['gang'] == 'Gang C') ? 'selected' : ''; ?>>Gang C</option>
                                        <option value="Maintenance Team" <?php echo (isset($_POST['gang']) && $_POST['gang'] == 'Maintenance Team') ? 'selected' : ''; ?>>Maintenance Team</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="expected_date">Expected Date (Optional)</label>
                                    <input type="date" id="expected_date" name="expected_date" 
                                           value="<?php echo isset($_POST['expected_date']) ? htmlspecialchars($_POST['expected_date']) : ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" required 
                                          placeholder="Provide detailed description of the quotation requirement..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="attachment">Attachment (Optional)</label>
                                <div class="file-upload">
                                    <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <label for="attachment" class="file-upload-label">
                                        Click to browse files or drag and drop
                                        <br><small>Supported: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn">Submit Quotation Request</button>
                        </form>
                    </div>
                </div>
                
                <!-- Quotation History -->
                <div id="history" class="tab-content" style="display: none;">
                    <h3>My Quotation Requests History</h3>
                    
                    <?php if (empty($quotations)): ?>
                        <div class="alert alert-info">
                            No quotation requests found. Submit your first request using the "New Request" tab.
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Vehicle No</th>
                                        <th>Gang</th>
                                        <th>Description</th>
                                        <th>Expected Date</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>ES/CE Approvals</th>
                                        <th>Approved By</th>
                                        <th>Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quotations as $quotation): ?>
                                        <tr>
                                            <td><?php echo $quotation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($quotation['quotation_type']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['vehicle_no']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['gang']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($quotation['description'], 0, 100)) . (strlen($quotation['description']) > 100 ? '...' : ''); ?></td>
                                            <td><?php echo $quotation['expected_date'] ? date('Y-m-d', strtotime($quotation['expected_date'])) : 'N/A'; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $quotation['status']; ?>">
                                                    <?php echo ucfirst($quotation['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($quotation['submission_date'])); ?></td>
                                            <td>
                                                <?php
                                                    $officerApprovedCount = 0;
                                                    for ($i = 1; $i <= 2; $i++) {
                                                        if (!empty($quotation['officer' . $i . '_approved']) && (int)$quotation['officer' . $i . '_approved'] === 1) {
                                                            $officerApprovedCount++;
                                                        }
                                                    }
                                                    echo $officerApprovedCount . '/2';
                                                ?>
                                            </td>
                                            <td><?php echo $quotation['approved_by_name'] ? htmlspecialchars($quotation['approved_by_name']) : 'N/A'; ?></td>
                                            <td>
                                                <?php if ($quotation['attachment']): ?>
                                                    <a href="uploads/<?php echo htmlspecialchars($quotation['attachment']); ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View</a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            var contents = document.querySelectorAll('.tab-content');
            contents.forEach(function(content) {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            var tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // File upload preview
        document.getElementById('attachment').addEventListener('change', function(e) {
            var label = document.querySelector('.file-upload-label');
            if (e.target.files.length > 0) {
                label.innerHTML = 'Selected: ' + e.target.files[0].name;
            } else {
                label.innerHTML = 'Click to browse files or drag and drop<br><small>Supported: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>';
            }
        });
    </script>
</body>
</html>