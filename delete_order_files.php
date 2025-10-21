<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    // Get all files for this order
    $stmt = $conn->prepare("SELECT file_path FROM order_files WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $deleted_count = 0;
    
    // Delete physical files
    while ($file = $result->fetch_assoc()) {
        if (file_exists($file['file_path'])) {
            if (unlink($file['file_path'])) {
                $deleted_count++;
            }
        }
    }
    
    // Delete order folder
    $upload_dir = 'uploads/' . $order_id . '/';
    if (file_exists($upload_dir)) {
        @rmdir($upload_dir);
    }
    
    // Delete file records from database
    $stmt = $conn->prepare("DELETE FROM order_files WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
    
    // Mark order as completed
    $stmt = $conn->prepare("UPDATE orders SET order_status = 'completed' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
    
    // Clear session
    unset($_SESSION['order_id']);
    unset($_SESSION['order_number']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Files deleted successfully',
        'deleted_count' => $deleted_count
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>