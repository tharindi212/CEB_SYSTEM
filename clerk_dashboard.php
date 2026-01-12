<?php
require_once 'config.php';

// Check if user is logged in and is a clerk
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'clerk') {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle ES/CE approvals (officer1 = ES, officer2 = CE) and billing uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $quotation_id = (int)$_POST['quotation_id'];
    $action = $_POST['action'];
    $clerk_notes = isset($_POST['clerk_notes']) ? trim($_POST['clerk_notes']) : '';

    if ($action === 'save_approvals') {
        try {
            // Load current ES/CE approvals
            $stmt = $pdo->prepare("SELECT status, officer1_approved, officer1_approved_at, officer2_approved, officer2_approved_at FROM quotations WHERE id = ?");
            $stmt->execute([$quotation_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $error_message = "Quotation not found.";
            } else {
                $updates = [];
                $params = [];
                $currentTimestamp = date('Y-m-d H:i:s');

                // officer1_* = ES, officer2_* = CE; once approved they cannot be changed back
                for ($i = 1; $i <= 2; $i++) {
                    $flagKey = 'officer' . $i . '_approved';
                    $timeKey = 'officer' . $i . '_approved_at';

                    $alreadyApproved = (int)$current[$flagKey] === 1;
                    $requestedApproved = isset($_POST[$flagKey]);

                    if (!$alreadyApproved && $requestedApproved) {
                        $updates[] = "$flagKey = ?";
                        $params[] = 1;
                        $updates[] = "$timeKey = ?";
                        $params[] = $currentTimestamp;
                    }
                }

                // Always allow updating clerk notes
                $updates[] = "clerk_notes = ?";
                $params[] = $clerk_notes;

                // If both ES and CE are now approved, mark overall status as approved (if not already)
                $allApprovedNow = true;
                for ($i = 1; $i <= 2; $i++) {
                    $flagKey = 'officer' . $i . '_approved';
                    $alreadyApproved = (int)$current[$flagKey] === 1;
                    $requestedApproved = isset($_POST[$flagKey]);
                    if (!($alreadyApproved || $requestedApproved)) {
                        $allApprovedNow = false;
                        break;
                    }
                }

                if ($allApprovedNow && $current['status'] !== 'approved') {
                    $updates[] = "status = ?";
                    $params[] = 'approved';
                    $updates[] = "approval_date = ?";
                    $params[] = $currentTimestamp;
                }

                if (!empty($updates)) {
                    $sql = "UPDATE quotations SET " . implode(', ', $updates) . " WHERE id = ?";
                    $params[] = $quotation_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }

                $success_message = "ES/CE approvals have been updated successfully.";
            }
        } catch (PDOException $e) {
            $error_message = "Failed to update quotation. Please try again.";
        }
    } elseif ($action === 'save_billing') {
        try {
            $stmt = $pdo->prepare("SELECT officer1_approved, officer2_approved, bill_store1, bill_store2, bill_store3 FROM quotations WHERE id = ?");
            $stmt->execute([$quotation_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $error_message = "Quotation not found.";
            } elseif ((int)$current['officer1_approved'] !== 1 || (int)$current['officer2_approved'] !== 1) {
                $error_message = "Billing uploads are allowed only after both approvals are completed.";
            } else {
                $allowedExtensions = ['jpg', 'jpeg', 'png'];
                $fileColumns = [
                    1 => 'bill_store1',
                    2 => 'bill_store2',
                    3 => 'bill_store3',
                ];

                $billingUpdates = [];
                $billingParams = [];
                $uploadBaseDir = 'uploads';
                $billingDir = $uploadBaseDir . '/billing';

                if (!is_dir($uploadBaseDir)) {
                    mkdir($uploadBaseDir, 0777, true);
                }
                if (!is_dir($billingDir)) {
                    mkdir($billingDir, 0777, true);
                }

                foreach ($fileColumns as $index => $column) {
                    $inputName = 'billing_image_' . $index;

                    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                        $originalName = $_FILES[$inputName]['name'];
                        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                        if (!in_array($extension, $allowedExtensions, true)) {
                            $error_message = "Only JPG and PNG images are allowed for billing uploads.";
                            break;
                        }

                        $newFileName = uniqid('bill_' . $quotation_id . '_' . $index . '_') . '.' . $extension;
                        $relativePath = 'billing/' . $newFileName;
                        $targetPath = $billingDir . '/' . $newFileName;

                        if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetPath)) {
                            $error_message = "Failed to upload billing image for store {$index}.";
                            break;
                        }

                        // Remove previously uploaded file for the same slot
                        if (!empty($current[$column])) {
                            $oldPath = $uploadBaseDir . '/' . $current[$column];
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }

                        $billingUpdates[] = "{$column} = ?";
                        $billingParams[] = $relativePath;
                    }
                }

                if (empty($error_message) && !empty($billingUpdates)) {
                    $billingUpdates[] = "billing_updated_at = NOW()";
                    $sql = "UPDATE quotations SET " . implode(', ', $billingUpdates) . " WHERE id = ?";
                    $billingParams[] = $quotation_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($billingParams);
                    $success_message = "Billing images uploaded successfully.";
                } elseif (empty($error_message)) {
                    $success_message = "No new billing images were uploaded.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Failed to update billing information. Please try again.";
        }
    }
}

// Fetch all quotations with employee details
try {
    $stmt = $pdo->prepare("
        SELECT q.*, 
               u1.full_name as submitted_by_name,
               u2.full_name as approved_by_name 
        FROM quotations q 
        LEFT JOIN users u1 ON q.submitted_by = u1.id 
        LEFT JOIN users u2 ON q.approved_by = u2.id 
        ORDER BY 
            CASE WHEN q.status = 'pending' THEN 1 ELSE 2 END,
            q.submission_date DESC
    ");
    $stmt->execute();
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quotations = array();
    $error_message = "Failed to load quotations.";
}

// Separate pending and processed quotations
$pending_quotations = array_filter($quotations, function($q) { return $q['status'] == 'pending'; });
$processed_quotations = array_filter($quotations, function($q) { return $q['status'] != 'pending'; });
$billing_requests = array_filter($quotations, function($q) {
    return (int)$q['officer1_approved'] === 1 && (int)$q['officer2_approved'] === 1;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clerk Dashboard - Quotation Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <div>
                    <h1>Clerk Dashboard</h1>
                    <p>Review and approve quotation requests</p>
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
                    <button class="nav-tab active" onclick="showTab('pending')">
                        Pending Requests 
                        <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;">
                            <?php echo count($pending_quotations); ?>
                        </span>
                    </button>
                    <button class="nav-tab" onclick="showTab('billing')">
                        Billing Requests
                        <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;">
                            <?php echo count($billing_requests); ?>
                        </span>
                    </button>
                    <button class="nav-tab" onclick="showTab('history')">All Requests History</button>
                </div>
                
                <!-- Pending Quotations -->
                <div id="pending" class="tab-content">
                    <h3>Pending Quotation Requests</h3>
                    
                    <?php if (empty($pending_quotations)): ?>
                        <div class="alert alert-info">
                            No pending quotation requests at this time.
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Submitted By</th>
                                        <th>Type</th>
                                        <th>Vehicle No</th>
                                        <th>Gang</th>
                                        <th>Description</th>
                                        <th>Expected Date</th>
                                        <th>Submitted Date</th>
                                        <th>Attachment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_quotations as $quotation): ?>
                                        <tr>
                                            <td><?php echo $quotation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($quotation['submitted_by_name']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['quotation_type']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['vehicle_no']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['gang']); ?></td>
                                            <td title="<?php echo htmlspecialchars($quotation['description']); ?>">
                                                <?php echo htmlspecialchars(substr($quotation['description'], 0, 100)) . (strlen($quotation['description']) > 100 ? '...' : ''); ?>
                                            </td>
                                            <td><?php echo $quotation['expected_date'] ? date('Y-m-d', strtotime($quotation['expected_date'])) : 'N/A'; ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($quotation['submission_date'])); ?></td>
                                            <td>
                                                <?php if ($quotation['attachment']): ?>
                                                    <a href="uploads/<?php echo htmlspecialchars($quotation['attachment']); ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View</a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $officerData = array(
                                                        'officer1_approved' => (string)$quotation['officer1_approved'],
                                                        'officer1_approved_at' => $quotation['officer1_approved_at'],
                                                        'officer2_approved' => (string)$quotation['officer2_approved'],
                                                        'officer2_approved_at' => $quotation['officer2_approved_at'],
                                                    );
                                                ?>
                                                <button onclick='openApprovalModal(<?php echo $quotation['id']; ?>, "<?php echo htmlspecialchars($quotation['quotation_type'], ENT_QUOTES, "UTF-8"); ?>", "<?php echo htmlspecialchars($quotation['vehicle_no'], ENT_QUOTES, "UTF-8"); ?>", <?php echo json_encode($officerData); ?>)'
                                                        class="btn" style="padding: 5px 10px; font-size: 12px; margin-right: 5px;">Review</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Billing Requests -->
                <div id="billing" class="tab-content" style="display: none;">
                    <h3>Billing Requests</h3>

                    <?php if (empty($billing_requests)): ?>
                        <div class="alert alert-info">
                            No quotations have completed both approvals yet.
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Request Details</th>
                                        <th>Approvals</th>
                                        <th>Existing Bills</th>
                                        <th>Upload / Update Bills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($billing_requests as $quotation): ?>
                                        <tr>
                                            <td><?php echo $quotation['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($quotation['quotation_type']); ?></strong><br>
                                                Vehicle: <?php echo htmlspecialchars($quotation['vehicle_no']); ?><br>
                                                Gang: <?php echo htmlspecialchars($quotation['gang']); ?><br>
                                                Submitted: <?php echo date('Y-m-d H:i', strtotime($quotation['submission_date'])); ?>
                                            </td>
                                            <td>
                                                ES: <span class="status-badge status-approved">Approved</span><br>
                                                CE: <span class="status-badge status-approved">Approved</span>
                                            </td>
                                            <td>
                                                <?php
                                                    $billColumns = ['bill_store1', 'bill_store2', 'bill_store3'];
                                                    $hasBill = false;
                                                    foreach ($billColumns as $index => $column):
                                                        if (!empty($quotation[$column])):
                                                            $hasBill = true;
                                                ?>
                                                    <div style="margin-bottom: 6px;">
                                                        Store <?php echo $index + 1; ?>:
                                                        <a href="uploads/<?php echo htmlspecialchars($quotation[$column]); ?>" target="_blank" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View</a>
                                                    </div>
                                                <?php
                                                        endif;
                                                    endforeach;
                                                    if (!$hasBill) {
                                                        echo 'No bills uploaded yet.';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="" enctype="multipart/form-data">
                                                    <input type="hidden" name="quotation_id" value="<?php echo $quotation['id']; ?>">
                                                    <div class="form-group" style="margin-bottom: 10px;">
                                                        <label>Store 1 Bill</label>
                                                        <input type="file" name="billing_image_1" accept=".jpg,.jpeg,.png">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 10px;">
                                                        <label>Store 2 Bill</label>
                                                        <input type="file" name="billing_image_2" accept=".jpg,.jpeg,.png">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 10px;">
                                                        <label>Store 3 Bill</label>
                                                        <input type="file" name="billing_image_3" accept=".jpg,.jpeg,.png">
                                                    </div>
                                                    <div style="display: flex; gap: 10px; align-items: center;">
                                                        <button type="submit" name="action" value="save_billing" class="btn">Upload Bills</button>
                                                        <small style="color: #666;">Only JPG/PNG files allowed.</small>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- All Quotations History -->
                <div id="history" class="tab-content" style="display: none;">
                    <h3>All Quotation Requests History</h3>
                    
                    <?php if (empty($quotations)): ?>
                        <div class="alert alert-info">
                            No quotation requests found in the system.
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Submitted By</th>
                                        <th>Type</th>
                                        <th>Vehicle No</th>
                                        <th>Gang</th>
                                        <th>Status</th>
                                        <th>Submitted Date</th>
                                        <th>Approvals</th>
                                        <th>Approval Date</th>
                                        <th>Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quotations as $quotation): ?>
                                        <tr>
                                            <td><?php echo $quotation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($quotation['submitted_by_name']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['quotation_type']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['vehicle_no']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['gang']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $quotation['status']; ?>">
                                                    <?php echo ucfirst($quotation['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($quotation['submission_date'])); ?></td>
                                            <td>
                                                <?php
                                                    $officer1_ts = $quotation['officer1_approved_at'] ? date('Y-m-d H:i:s', strtotime($quotation['officer1_approved_at'])) : 'Not approved';
                                                    $officer2_ts = $quotation['officer2_approved_at'] ? date('Y-m-d H:i:s', strtotime($quotation['officer2_approved_at'])) : 'Not approved';
                                                    $clerk_notes_display = htmlspecialchars($quotation['clerk_notes'], ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <button onclick="openApprovalsModal(<?php echo $quotation['id']; ?>, '<?php echo $officer1_ts; ?>', '<?php echo $officer2_ts; ?>', '<?php echo $clerk_notes_display; ?>')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View</button>
                                            </td>
                                            <td><?php echo $quotation['approval_date'] ? date('Y-m-d H:i', strtotime($quotation['approval_date'])) : 'N/A'; ?></td>
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
    
    <!-- Approval Modal -->
    <div id="approvalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%;">
            <h3 id="modalTitle">Review Quotation Request</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="quotation_id" id="modalQuotationId">
                
                <div class="form-group">
                    <label>Officer Approvals (ES / CE)</label>
                    <div class="table-container" style="margin-top: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 4px 6px; width: 80px;">Officer</th>
                                    <th style="text-align: left; padding: 4px 6px;">Status / Timestamp</th>
                                    <th style="text-align: center; padding: 4px 6px; width: 80px;">Approve</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr id="officer1_row">
                                    <td style="padding: 4px 6px;">ES</td>
                                    <td style="padding: 4px 6px;"><small id="officer1_timestamp" style="color:#666; font-size:11px;"></small></td>
                                    <td style="padding: 4px 6px; text-align: center;"><input type="checkbox" name="officer1_approved" id="officer1_approved"></td>
                                </tr>
                                <tr id="officer2_row">
                                    <td style="padding: 4px 6px;">CE</td>
                                    <td style="padding: 4px 6px;"><small id="officer2_timestamp" style="color:#666; font-size:11px;"></small></td>
                                    <td style="padding: 4px 6px; text-align: center;"><input type="checkbox" name="officer2_approved" id="officer2_approved"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-group">
                    <label for="clerk_notes">Clerk Notes</label>
                    <textarea name="clerk_notes" id="clerk_notes" placeholder="Add your notes about this quotation request..." style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="action" value="save_approvals" class="btn">Save Approvals</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Approvals Modal -->
    <div id="approvalsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%;">
            <h3>Approval Details</h3>
            
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">Officer Approvals</h4>
                <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="margin-bottom: 10px;">
                        <strong>ES Approval:</strong> <span id="approvalES" style="color: #666;"></span>
                    </div>
                    <div>
                        <strong>CE Approval:</strong> <span id="approvalCE" style="color: #666;"></span>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">Clerk Notes</h4>
                <div id="clerkNotesContent" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; min-height: 80px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeApprovalsModal()" class="btn btn-secondary">Close</button>
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
        
        function openApprovalModal(quotationId, quotationType, vehicleNo, officerData) {
            document.getElementById('modalQuotationId').value = quotationId;
            document.getElementById('modalTitle').textContent = 'Review: ' + quotationType + ' for ' + vehicleNo;
            document.getElementById('clerk_notes').value = '';

            // Check approval status
            var officer1Approved = officerData['officer1_approved'] === '1';
            var officer2Approved = officerData['officer2_approved'] === '1';
            
            // Hide both rows initially
            document.getElementById('officer1_row').style.display = 'none';
            document.getElementById('officer2_row').style.display = 'none';
            
            // Show only the next approval in line
            if (!officer1Approved) {
                // ES hasn't approved yet - show ES checkbox
                document.getElementById('officer1_row').style.display = 'table-row';
                var ts = officerData['officer1_approved_at'];
                var label = document.getElementById('officer1_timestamp');
                var cb = document.getElementById('officer1_approved');
                cb.checked = false;
                cb.disabled = false;
                label.textContent = 'Not approved yet';
            } else if (!officer2Approved) {
                // ES approved but CE hasn't - show CE checkbox
                document.getElementById('officer2_row').style.display = 'table-row';
                var ts = officerData['officer2_approved_at'];
                var label = document.getElementById('officer2_timestamp');
                var cb = document.getElementById('officer2_approved');
                cb.checked = false;
                cb.disabled = false;
                label.textContent = 'Not approved yet';
                
                // Show ES as approved in a disabled state
                document.getElementById('officer1_row').style.display = 'table-row';
                document.getElementById('officer1_timestamp').textContent = 'Approved at ' + officerData['officer1_approved_at'];
                document.getElementById('officer1_approved').checked = true;
                document.getElementById('officer1_approved').disabled = true;
            } else {
                // Both approved - show both as completed
                for (var i = 1; i <= 2; i++) {
                    document.getElementById('officer' + i + '_row').style.display = 'table-row';
                    var flag = officerData['officer' + i + '_approved'] === '1';
                    var ts = officerData['officer' + i + '_approved_at'];
                    var cb = document.getElementById('officer' + i + '_approved');
                    var label = document.getElementById('officer' + i + '_timestamp');
                    
                    cb.checked = flag;
                    cb.disabled = true;
                    label.textContent = 'Approved at ' + ts;
                }
            }

            document.getElementById('approvalModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }
        
        function openApprovalsModal(quotationId, officer1_ts, officer2_ts, clerkNotes) {
            document.getElementById('approvalES').textContent = officer1_ts;
            document.getElementById('approvalCE').textContent = officer2_ts;
            document.getElementById('clerkNotesContent').textContent = clerkNotes || 'No notes available';
            document.getElementById('approvalsModal').style.display = 'block';
        }
        
        function closeApprovalsModal() {
            document.getElementById('approvalsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('approvalsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalsModal();
            }
        });
        

        // Confirmation before checking officer approvals
        function attachOfficerConfirm(id, label) {
            var cb = document.getElementById(id);
            cb.addEventListener('change', function(e) {
                if (cb.disabled) return;
                if (cb.checked) {
                    var ok = confirm('Confirm ' + label + ' approval? This cannot be undone.');
                    if (!ok) {
                        cb.checked = false;
                    }
                }
            });
        }

        attachOfficerConfirm('officer1_approved', 'ES');
        attachOfficerConfirm('officer2_approved', 'CE');
        
        // Auto-refresh page every 30 seconds to check for new requests
        setInterval(function() {
            if (document.querySelector('.nav-tab.active').textContent.includes('Pending')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>