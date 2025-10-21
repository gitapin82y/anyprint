<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    redirect('login.php');
}

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE total_price > 0");
$total_orders = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE payment_status = 'success' AND total_price > 0");
$success_payments = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE payment_status = 'success' AND total_price > 0");
$total_revenue = $stmt->fetch_assoc()['total'] ?: 0;

$stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'pending' AND total_price > 0");
$pending_orders = $stmt->fetch_assoc()['total'];


// Get orders with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filter
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filter_payment = isset($_GET['payment']) ? sanitize($_GET['payment']) : '';

$where = ['total_price > 0'];
$params = [];
$types = '';

if ($filter_status) {
    $where[] = "order_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_payment) {
    $where[] = "payment_status = ?";
    $params[] = $filter_payment;
    $types .= 's';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders $where_clause");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);
$stmt->close();

// Get orders
$stmt = $conn->prepare("SELECT * FROM orders $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Anyprint</title>
  <!-- Favicon -->
<link rel="icon" type="image/jpeg" href="../assets/logo-anyprint.jpeg" />
<link rel="apple-touch-icon" href="../assets/logo-anyprint.jpeg" />
<link rel="shortcut icon" type="image/x-icon" href="../assets/logo-anyprint.jpeg" />
<meta name="theme-color" content="#1A2E55" />

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
  <!-- Header -->
  <header class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center gap-3">
          <div class="bg-gradient-to-r from-[#1D4A80] to-[#828275] text-white rounded-full flex items-center justify-center font-bold">    <img src="../assets/logo-anyprint.jpeg" width="150px" alt=""></div>
          <div>
            <h1 class="text-xl font-bold text-gray-800">Admin</h1>
            <p class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
          </div>
        </div>
        <a href="logout.php" class="text-red-600 hover:text-red-700 font-medium text-sm">
          <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
        </a>
      </div>
    </div>
  </header>

  <small class="text-[#828275] mt-8 ms-8">Created By Group 50.</small>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 grid-cols-2 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Total Orders</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $total_orders; ?></p>
          </div>
          <div class="bg-blue-100 text-blue-600 rounded-full w-12 h-12 flex items-center justify-center">
            <i class="fa-solid fa-file-lines text-xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Success Payments</p>
            <p class="text-2xl font-bold text-green-600"><?php echo $success_payments; ?></p>
          </div>
          <div class="bg-green-100 text-green-600 rounded-full w-12 h-12 flex items-center justify-center">
            <i class="fa-solid fa-circle-check text-xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Total Revenue</p>
            <p class="text-2xl font-bold text-purple-600"><?php echo formatPrice($total_revenue); ?></p>
          </div>
          <div class="bg-purple-100 text-purple-600 rounded-full w-12 h-12 flex items-center justify-center">
            <i class="fa-solid fa-dollar-sign text-xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Pending Orders</p>
            <p class="text-2xl font-bold text-orange-600"><?php echo $pending_orders; ?></p>
          </div>
          <div class="bg-orange-100 text-orange-600 rounded-full w-12 h-12 flex items-center justify-center">
            <i class="fa-solid fa-clock text-xl"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-xl shadow">
      <div class="p-6 border-b">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold text-gray-800">Orders List</h2>
          <a href="export_csv.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
            <i class="fa-solid fa-download mr-1"></i> Export CSV
          </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex gap-3">
          <select name="payment" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">All Payments</option>
            <option value="pending" <?php echo $filter_payment === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="success" <?php echo $filter_payment === 'success' ? 'selected' : ''; ?>>Success</option>
            <option value="failed" <?php echo $filter_payment === 'failed' ? 'selected' : ''; ?>>Failed</option>
          </select>

          <select name="status" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="processing" <?php echo $filter_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>

          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
            <i class="fa-solid fa-filter mr-1"></i> Filter
          </button>

          <?php if ($filter_payment || $filter_status): ?>
          <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition text-sm">
            Clear
          </a>
          <?php endif; ?>
        </form>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($order = $orders->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <span class="font-medium text-gray-800"><?php echo $order['order_number']; ?></span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?php echo $order['total_pages']; ?> pages<br>
                <?php echo $order['color_type']; ?> • <?php echo $order['copies']; ?> cop<?php echo $order['copies'] > 1 ? 'ies' : 'y'; ?>
              </td>
              <td class="px-6 py-4">
                <span class="font-semibold text-gray-800"><?php echo formatPrice($order['total_price']); ?></span>
              </td>
              <td class="px-6 py-4">
                <?php
                $payment_class = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'success' => 'bg-green-100 text-green-800',
                    'failed' => 'bg-red-100 text-red-800'
                ];
                ?>
                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $payment_class[$order['payment_status']]; ?>">
                  <?php echo ucfirst($order['payment_status']); ?>
                </span>
              </td>
              <td class="px-6 py-4">
                <?php
                $status_class = [
                    'pending' => 'bg-gray-100 text-gray-800',
                    'processing' => 'bg-blue-100 text-blue-800',
                    'completed' => 'bg-green-100 text-green-800',
                    'cancelled' => 'bg-red-100 text-red-800'
                ];
                ?>
                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class[$order['order_status']]; ?>">
                  <?php echo ucfirst($order['order_status']); ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?>
              </td>
   <td class="px-6 py-4">
      <div class="flex gap-2">
       <!-- View Button -->
<button onclick="viewOrderDetail(<?php echo $order['id']; ?>)" 
  class="bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 p-2 rounded-full" 
  title="View Details">
  <i class="fa-solid fa-eye"></i>
</button>

<!-- Delete Button -->
<button onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>')" 
  class="bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-800 p-2 rounded-full ml-1" 
  title="Delete Order">
  <i class="fa-solid fa-trash"></i>
</button>

        
        <!-- Update Status Dropdown -->
        <select onchange="updateStatus(<?php echo $order['id']; ?>, this.value)" class="border rounded px-2 py-1 text-xs">
          <option value="">Update...</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="px-6 py-4 border-t flex justify-center gap-2">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?page=<?php echo $i; ?><?php echo $filter_payment ? '&payment='.$filter_payment : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>" 
             class="px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
<script>
  function updateStatus(orderId, status) {
    if (!status) return;
    
    Swal.fire({
      title: 'Update Status?',
      text: `Change order status to ${status}?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#1A2E55',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, update it!'
    }).then((result) => {
      if (result.isConfirmed) {
        fetch('update_status.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `order_id=${orderId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire('Updated!', 'Order status has been updated.', 'success')
              .then(() => location.reload());
          } else {
            Swal.fire('Error!', 'Failed to update status.', 'error');
          }
        });
      }
    });
  }

  function viewOrderDetail(orderId) {
    // Show loading
    Swal.fire({
      title: 'Loading...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    // Fetch order details
    fetch(`get_order_detail.php?order_id=${orderId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          let filesHtml = '';
          
          if (data.files.length > 0) {
            filesHtml = `
              <div class="text-left mt-4">
                <h4 class="font-semibold mb-2">Uploaded Files:</h4>
                <div class="space-y-2">
                  ${data.files.map(file => `
                    <div class="flex items-center justify-between bg-gray-50 p-3 rounded">
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-file-pdf text-red-500"></i>
                        <div>
                          <p class="text-sm font-medium">${file.file_name}</p>
                          <p class="text-xs text-gray-500">${file.file_pages} pages • ${file.file_size}</p>
                        </div>
                      </div>
                      <a href="../${file.file_path}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fa-solid fa-external-link-alt"></i> Open
                      </a>
                    </div>
                  `).join('')}
                </div>
              </div>
            `;
          } else {
            filesHtml = '<p class="text-gray-500 text-sm mt-4">No files uploaded</p>';
          }

          Swal.fire({
            title: `Order #${data.order.order_number}`,
            html: `
              <div class="text-left">
                <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                  <div>
                    <p class="text-gray-500">Total Pages</p>
                    <p class="font-semibold">${data.order.total_pages} pages</p>
                  </div>
                  <div>
                    <p class="text-gray-500">Paper Size</p>
                    <p class="font-semibold">${data.order.paper_size}</p>
                  </div>
                  <div>
                    <p class="text-gray-500">Color Type</p>
                    <p class="font-semibold">${data.order.color_type}</p>
                  </div>
                  <div>
                    <p class="text-gray-500">Copies</p>
                    <p class="font-semibold">${data.order.copies}</p>
                  </div>
                  <div>
                    <p class="text-gray-500">Price per Page</p>
          <p class="font-semibold">Rp ${parseInt(data.order.price_per_page).toLocaleString('id-ID')}</p>
                  </div>
                  <div>
                    <p class="text-gray-500">Total Price</p>
         <p class="font-semibold text-blue-600">Rp ${parseInt(data.order.total_price).toLocaleString('id-ID')}</p>
                  </div>
                </div>
                ${filesHtml}
              </div>
            `,
            width: '600px',
            confirmButtonText: 'Close'
          });
        } else {
          Swal.fire('Error!', 'Failed to load order details.', 'error');
        }
      })
      .catch(error => {
        Swal.fire('Error!', 'Failed to load order details.', 'error');
      });
  }

  function deleteOrder(orderId, orderNumber) {
    Swal.fire({
      title: 'Delete Order?',
      html: `Are you sure you want to delete order <strong>${orderNumber}</strong>?<br><span class="text-red-600 text-sm">This will also delete all uploaded files!</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        // Show loading
        Swal.fire({
          title: 'Deleting...',
          text: 'Please wait',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        fetch('delete_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `order_id=${orderId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire('Deleted!', 'Order has been deleted.', 'success')
              .then(() => location.reload());
          } else {
            Swal.fire('Error!', data.message || 'Failed to delete order.', 'error');
          }
        })
        .catch(error => {
          Swal.fire('Error!', 'Failed to delete order.', 'error');
        });
      }
    });
  }
</script>
</body>
</html>