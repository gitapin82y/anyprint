<?php
require_once 'includes/config.php';

if (!isset($_SESSION['user_logged_in'])) {
    redirect('login_user.php');
}

$user_id = $_SESSION['user_id'];

// Get user stats
$stmt = $conn->prepare("SELECT total_prints, total_spent FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get print history
$stmt = $conn->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_files WHERE order_id = o.id) as file_count
    FROM orders o
    WHERE o.user_id = ? AND o.total_price > 0
    ORDER BY o.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard - Anyprint</title>
  <link rel="icon" type="image/jpeg" href="assets/logo-anyprint.jpeg" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="bg-gray-100">
  <header class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center gap-3">
          <img src="assets/logo-anyprint.jpeg" width="120px" alt="">
          <div>
            <h1 class="text-xl font-bold text-gray-800">My Dashboard</h1>
            <p class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
          </div>
        </div>
        <div class="flex gap-3">
          <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
            <i class="fa-solid fa-print mr-1"></i> New Print
          </a>
          <a href="logout_user.php" class="text-red-600 hover:text-red-700 font-medium text-sm px-4 py-2">
            <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Total Prints</p>
            <p class="text-3xl font-bold text-gray-800"><?php echo $user_stats['total_prints']; ?></p>
          </div>
          <div class="bg-blue-100 text-blue-600 rounded-full w-14 h-14 flex items-center justify-center">
            <i class="fa-solid fa-print text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Total Spent</p>
            <p class="text-3xl font-bold text-green-600"><?php echo formatPrice($user_stats['total_spent']); ?></p>
          </div>
          <div class="bg-green-100 text-green-600 rounded-full w-14 h-14 flex items-center justify-center">
            <i class="fa-solid fa-wallet text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Account</p>
            <p class="text-lg font-bold text-purple-600"><?php echo htmlspecialchars($_SESSION['user_username']); ?></p>
            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
          </div>
          <div class="bg-purple-100 text-purple-600 rounded-full w-14 h-14 flex items-center justify-center">
            <i class="fa-solid fa-user text-2xl"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Print History -->
    <div class="bg-white rounded-xl shadow">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Print History</h2>
        <p class="text-sm text-gray-500">View your recent print orders</p>
      </div>

      <div class="overflow-x-auto">
        <?php if ($history->num_rows > 0): ?>
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Files</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($row = $history->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <span class="font-medium text-gray-800"><?php echo $row['order_number']; ?></span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?php echo $row['total_pages']; ?> pages<br>
                <?php echo $row['paper_size']; ?> • <?php echo $row['copies']; ?> cop<?php echo $row['copies'] > 1 ? 'ies' : 'y'; ?>
              </td>
              <td class="px-6 py-4 text-sm">
                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
                  <?php echo $row['file_count']; ?> file<?php echo $row['file_count'] > 1 ? 's' : ''; ?>
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="font-semibold text-gray-800"><?php echo formatPrice($row['total_price']); ?></span>
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
                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class[$row['order_status']]; ?>">
                  <?php echo ucfirst($row['order_status']); ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                <span class="text-xs text-gray-400"><?php echo date('H:i', strtotime($row['created_at'])); ?></span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="text-center py-12">
          <i class="fa-solid fa-inbox text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500 mb-4">No print history yet</p>
          <a href="index.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition inline-block">
            <i class="fa-solid fa-print mr-2"></i> Start Printing
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>