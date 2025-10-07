<?php
require_once '../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    redirect('dashboard.php');
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, username, password, full_name FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['full_name'];
            
            redirect('dashboard.php');
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Invalid username or password';
    }
    
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login - Anyprint</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-[#1D4A80] to-[#828275] min-h-screen flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-2xl p-8 w-[90%] max-w-md">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Anyprint Admin</h1>
      <p class="text-gray-600 text-sm">Login to access dashboard</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
      <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Username</label>
        <input type="text" name="username" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter username" />
      </div>

        <div class="mb-6">
        <label class="block text-gray-700 text-sm font-medium mb-2">Password</label>
        <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter password" />
    </div>

      <button type="submit" class="w-full bg-gradient-to-r from-[#1D4A80] to-[#828275] text-white font-medium py-3 rounded-lg hover:opacity-90 transition">
        Login
      </button>
  
    </form>

  </div>
</body>
</html>

    