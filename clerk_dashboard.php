<?php
require_once 'config.php';

// Check if user is logged in and is a clerk
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'clerk') {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle ES/CE approvals (officer1 = ES, officer2 = CE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $quotation_id = (int)$_POST['quotation_id'];
    $action = $_POST['action'];
    $clerk_notes = trim($_POST['clerk_notes']);

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

                // officer1_* = ES, officer2_* = CE; once approved they cannot be changed back
                for ($i = 1; $i <= 2; $i++) {
                    $flagKey = 'officer' . $i . '_approved';
                    $timeKey = 'officer' . $i . '_approved_at';

                    $alreadyApproved = (int)$current[$flagKey] === 1;
                    $requestedApproved = isset($_POST[$flagKey]);

                    if (!$alreadyApproved && $requestedApproved) {
                        $updates[] = "$flagKey = 1";
                        $updates[] = "$timeKey = NOW()";
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
                    $updates[] = "status = 'approved'";
                    $updates[] = "approval_date = NOW()";
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
                                        <th>ES/CE Approvals</th>
                                        <th>Approval Date</th>
                                        <th>Notes</th>
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
                                                    $officerApprovedCount = 0;
                                                    for ($i = 1; $i <= 2; $i++) {
                                                        if (!empty($quotation['officer' . $i . '_approved']) && (int)$quotation['officer' . $i . '_approved'] === 1) {
                                                            $officerApprovedCount++;
                                                        }
                                                    }
                                                    echo $officerApprovedCount . '/2';
                                                ?>
                                            </td>
                                            <td><?php echo $quotation['approval_date'] ? date('Y-m-d H:i', strtotime($quotation['approval_date'])) : 'N/A'; ?></td>
                                            <td><?php echo $quotation['clerk_notes'] ? htmlspecialchars(substr($quotation['clerk_notes'], 0, 50)) . (strlen($quotation['clerk_notes']) > 50 ? '...' : '') : 'N/A'; ?></td>
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
                                <tr>
                                    <td style="padding: 4px 6px;">ES</td>
                                    <td style="padding: 4px 6px;"><small id="officer1_timestamp" style="color:#666; font-size:11px;"></small></td>
                                    <td style="padding: 4px 6px; text-align: center;"><input type="checkbox" name="officer1_approved" id="officer1_approved"></td>
                                </tr>
                                <tr>
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

            // Populate ES (officer1) and CE (officer2) checkboxes and timestamps
            for (var i = 1; i <= 2; i++) {
                var flag = officerData['officer' + i + '_approved'] === '1';
                var ts = officerData['officer' + i + '_approved_at'];
                var cb = document.getElementById('officer' + i + '_approved');
                var label = document.getElementById('officer' + i + '_timestamp');

                cb.checked = flag;
                cb.disabled = flag; // once approved, cannot be changed

                if (ts && ts !== '') {
                    label.textContent = 'Approved at ' + ts;
                } else {
                    label.textContent = 'Not approved yet';
                }
            }

            document.getElementById('approvalModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
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