<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_logged_in'])) {
    redirect('user_dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    
    // Validasi
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'All fields except phone are required';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check existing username/email
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $phone);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Redirecting to login...';
                header("refresh:2;url=login_user.php");
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register - Anyprint</title>
  <link rel="icon" type="image/jpeg" href="assets/logo-anyprint.jpeg" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="bg-gradient-to-br from-[#1D4A80] to-[#828275] min-h-screen flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <img src="assets/logo-anyprint.jpeg" width="120px" class="mx-auto mb-3" alt="">
      <h1 class="text-2xl font-bold text-gray-800">Create Account</h1>
      <p class="text-gray-600 text-sm">Sign up to track your print history</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
      <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
      <i class="fa-solid fa-circle-check mr-1"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Username *</label>
        <input type="text" name="username" required minlength="3" 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Enter username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
      </div>

      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Full Name *</label>
        <input type="text" name="full_name" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Enter full name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" />
      </div>

      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Email *</label>
        <input type="email" name="email" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Enter email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
      </div>

      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Phone (Optional)</label>
        <input type="tel" name="phone" 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Enter phone number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" />
      </div>

      <div class="mb-4">
        <label class="block text-gray-700 text-sm font-medium mb-2">Password *</label>
        <input type="password" name="password" required minlength="6" 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Minimum 6 characters" />
      </div>

      <div class="mb-6">
        <label class="block text-gray-700 text-sm font-medium mb-2">Confirm Password *</label>
        <input type="password" name="confirm_password" required minlength="6" 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
               placeholder="Re-enter password" />
      </div>

      <button type="submit" class="w-full bg-gradient-to-r from-[#1D4A80] to-[#828275] text-white font-medium py-3 rounded-lg hover:opacity-90 transition mb-4">
        <i class="fa-solid fa-user-plus mr-2"></i> Register
      </button>

      <div class="text-center text-sm text-gray-600">
        Already have an account? 
        <a href="login_user.php" class="text-blue-600 hover:text-blue-700 font-medium">Login here</a>
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