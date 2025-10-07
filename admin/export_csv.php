<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    redirect('login.php');
}

// Get all orders
$query = "SELECT 
    order_number,
    total_pages,
    paper_size,
    color_type,
    copies,
    price_per_page,
    total_price,
    payment_status,
    order_status,
    customer_ip,
    created_at
FROM orders
ORDER BY created_at DESC";

$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=anyprint_orders_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add column headers
fputcsv($output, [
    'Order Number',
    'Total Pages',
    'Paper Size',
    'Color Type',
    'Copies',
    'Price Per Page',
    'Total Price',
    'Payment Status',
    'Order Status',
    'Customer IP',
    'Order Date'
]);

// Add data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['order_number'],
        $row['total_pages'],
        $row['paper_size'],
        $row['color_type'],
        $row['copies'],
        'Rp ' . number_format($row['price_per_page'], 0, ',', '.'),
        'Rp ' . number_format($row['total_price'], 0, ',', '.'),
        $row['payment_status'],
        $row['order_status'],
        $row['customer_ip'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
?>