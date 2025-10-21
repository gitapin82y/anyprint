<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_logged_in'])) {
    if ($_SESSION['user_role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
 $stmt = $conn->prepare("SELECT id, username, password, full_name, email, role FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
             $_SESSION['user_role'] = $user['role'];


            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
               if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('dashboard.php');
            }
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
  <title>Login - Anyprint</title>
  <link rel="icon" type="image/jpeg" href="assets/logo-anyprint.jpeg" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="bg-gradient-to-br from-[#1D4A80] to-[#828275] min-h-screen flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
    <div class="text-center mb-6">
        <a href="https://anyprint.my.id/">
      <img src="assets/logo-anyprint.jpeg" width="120px" class="mx-auto mb-3" alt="">
</a>
      <h1 class="text-2xl font-bold text-gray-800">Welcome Back</h1>
      <p class="text-gray-600 text-sm">Login to access your account</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
      <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Username or Email</label>
        <input type="text" name="username" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Enter username or email" />
      </div>

      <div class="mb-6">
        <label class="block text-gray-700 text-sm font-medium mb-2">Password</label>
        <input type="password" name="password" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Enter password" />
      </div>

      <button type="submit" class="w-full bg-gradient-to-r from-[#1D4A80] to-[#828275] text-white font-medium py-3 rounded-lg hover:opacity-90 transition mb-4">
        <i class="fa-solid fa-sign-in-alt mr-2"></i> Login
      </button>

      <div class="text-center text-sm text-gray-600">
        Don't have an account? 
        <a href="register.php" class="text-blue-600 hover:text-blue-700 font-medium">Register here</a>
      </div>

      <div class="text-center mt-3">
        <a href="index.php" class="text-gray-500 hover:text-gray-700 text-sm">
          <i class="fa-solid fa-arrow-left mr-1"></i> Continue as Guest
        </a>
      </div>
    </form>
  </div>
</body>
</html>