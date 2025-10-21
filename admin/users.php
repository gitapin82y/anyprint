<?php
require_once '../includes/config.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    redirect('../login.php');
}

// Get all users with stats (exclude admins)
$stmt = $conn->query("
    SELECT 
        u.*,
        COUNT(DISTINCT o.id) as order_count,
        SUM(o.total_pages) as total_pages_printed
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'success'
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users - Admin Anyprint</title>
  <link rel="icon" type="image/jpeg" href="../assets/logo-anyprint.jpeg" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
  <header class="bg-[#1151AB] shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-4">
        <a href="https://anyprint.my.id/" class="flex items-center gap-3">
          <img src="../assets/logo-anyprint.jpeg" width="150px" alt="">
        </a>
        <nav class="flex gap-4">
          <a href="dashboard.php" class="text-white font-medium text-sm">
            <i class="fa-solid fa-dashboard mr-1"></i> Dashboard
          </a>
          <a href="users.php" class="text-white font-medium text-sm">
            <i class="fa-solid fa-users mr-1"></i> Users
          </a>
          <a href="../logout.php" class="text-white font-medium text-sm">
            <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
          </a>
        </nav>
      </div>
    </div>
  </header>

  <small class="text-[#828275] mt-4 ms-8 block">Created By Group 50.</small>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-xl shadow">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Registered Users</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Orders</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Pages</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Spent</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($user = $users->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <div>
                  <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
                  <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                </div>
              </td>
              <td class="px-6 py-4 text-sm">
                <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                <?php if ($user['phone']): ?>
                <p class="text-gray-500"><?php echo htmlspecialchars($user['phone']); ?></p>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4">
                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                  <?php echo $user['order_count']; ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?php echo $user['total_pages_printed'] ?: 0; ?> pages
              </td>
              <td class="px-6 py-4">
                <span class="font-semibold text-green-600"><?php echo formatPrice($user['total_spent']); ?></span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?php echo date('d M Y', strtotime($user['created_at'])); ?>
              </td>
              <td class="px-6 py-4">
                <button onclick="viewUserHistory(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                        class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-1 rounded text-sm">
                  <i class="fa-solid fa-history mr-1"></i> View History
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script>
    function viewUserHistory(userId, username) {
      Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      fetch(`get_user_history.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            let historyHtml = '';
            
            if (data.history.length > 0) {
              historyHtml = `
                <div class="text-left mt-4 max-h-96 overflow-y-auto">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                      <tr>
                        <th class="px-3 py-2 text-left">Order #</th>
                        <th class="px-3 py-2 text-left">Pages</th>
                        <th class="px-3 py-2 text-left">Price</th>
                        <th class="px-3 py-2 text-left">Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${data.history.map(order => `
                        <tr class="border-b">
                          <td class="px-3 py-2 font-medium">${order.order_number}</td>
                          <td class="px-3 py-2">${order.total_pages} pages</td>
                          <td class="px-3 py-2 text-green-600 font-semibold">Rp ${parseInt(order.total_price).toLocaleString('id-ID')}</td>
                          <td class="px-3 py-2 text-gray-500">${order.created_at}</td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              `;
            } else {
              historyHtml = '<p class="text-gray-500 text-center mt-4">No print history</p>';
            }

            Swal.fire({
              title: `@${username} Print History`,
              html: `
                <div class="text-left">
                  <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="bg-blue-50 p-3 rounded">
                      <p class="text-xs text-gray-500">Total Orders</p>
                      <p class="text-2xl font-bold text-blue-600">${data.stats.total_orders}</p>
                    </div>
                    <div class="bg-green-50 p-3 rounded">
                      <p class="text-xs text-gray-500">Total Pages</p>
                      <p class="text-2xl font-bold text-green-600">${data.stats.total_pages}</p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded">
                      <p class="text-xs text-gray-500">Total Spent</p>
                      <p class="text-lg font-bold text-purple-600">Rp ${parseInt(data.stats.total_spent).toLocaleString('id-ID')}</p>
                    </div>
                  </div>
                  ${historyHtml}
                </div>
              `,
              width: '700px',
              confirmButtonText: 'Close'
            });
          } else {
            Swal.fire('Error!', 'Failed to load user history.', 'error');
          }
        })
        .catch(error => {
          Swal.fire('Error!', 'Failed to load user history.', 'error');
        });
    }
  </script>
</body>
</html>