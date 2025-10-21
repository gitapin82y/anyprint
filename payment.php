<?php
require_once 'includes/config.php';

// Redirect if no order session
if (!isset($_SESSION['order_id'])) {
    redirect('index.php');
}

// Get order data
$order_id = $_SESSION['order_id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect if order data is incomplete
if (!$order || $order['total_price'] <= 0) {
    redirect('review.php');
}

// Handle payment simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_payment'])) {
    // Update payment status
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'success', order_status = 'processing' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
    
    // Log payment
    $payment_method = 'QRIS';
    $amount = $order['total_price'];
    $status = 'success';

       if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        // Update total prints and total spent
        $stmt = $conn->prepare("UPDATE users SET total_prints = total_prints + 1, total_spent = total_spent + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Log print history
        $stmt = $conn->prepare("INSERT INTO user_print_history (user_id, order_id, pages_printed, amount_paid) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $user_id, $order_id, $order['total_pages'], $amount);
        $stmt->execute();
        $stmt->close();
    }
    
    $stmt = $conn->prepare("INSERT INTO payment_logs (order_id, payment_method, amount, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isds", $order_id, $payment_method, $amount, $status);
    $stmt->execute();
    $stmt->close();
    
    // Set success flag and clear order session
    $_SESSION['payment_success'] = true;
    $_SESSION['completed_order'] = $order['order_number'];
    unset($_SESSION['order_id']);
    unset($_SESSION['order_number']);
}

// Check if payment was successful
$payment_success = isset($_SESSION['payment_success']) ? $_SESSION['payment_success'] : false;
if ($payment_success) {
    $completed_order = $_SESSION['completed_order'];
    unset($_SESSION['payment_success']);
    unset($_SESSION['completed_order']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Anyprint - Payment Simulation</title>
  <!-- Favicon -->
<link rel="icon" type="image/jpeg" href="assets/logo-anyprint.jpeg" />
<link rel="apple-touch-icon" href="assets/logo-anyprint.jpeg" />
<link rel="shortcut icon" type="image/x-icon" href="assets/logo-anyprint.jpeg" />
<meta name="theme-color" content="#1A2E55" />

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
  @keyframes pulseScale {
    0%, 100% {
      transform: scale(1);
    }
    50% {
      transform: scale(1.1);
    }
  }

  .animate-qris {
    animation: pulseScale 1.8s ease-in-out infinite;
  }
</style>
</head>
<body class="bg-[#f1f5ff] font-sans">
  <header class="flex justify-between items-center px-8 py-4 bg-white shadow-sm">
    <div class="flex items-center gap-2">
      <img src="assets/logo-anyprint.jpeg" width="150px" alt="">
    </div>
    <p class="text-gray-500 text-sm">Payment</p>
  </header>

  <small class="text-[#828275] mt-8 ms-8">Created By Group 50.</small>

  <main class="flex flex-col items-center justify-center min-h-[80vh]">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-[90%] max-w-md text-center">
      <h2 class="text-2xl font-bold mb-2">Complete Payment</h2>
      <p class="text-gray-600 mb-6">Scan the QR code below to pay securely</p>

      <!-- Order Summary -->
    <div class="bg-gray-50 p-4 rounded-xl mb-4">
        <p class="font-semibold text-gray-800 mb-2">Order Summary</p>
        <p class="text-gray-600 text-sm mb-2">
          <?php echo $order['total_pages']; ?> pages • Black & White • <?php echo $order['copies']; ?> cop<?php echo $order['copies'] > 1 ? 'ies' : 'y'; ?>
        </p>
        <p class="text-3xl font-bold text-blue-600 mb-4"><?php echo formatPrice($order['total_price']); ?></p>
        <div class="bg-gradient-to-r from-[#1D4A80] to-[#828275] p-4 inline-block rounded-xl animate-qris">
          <img src="assets/qris.jpg" width="200px" alt="">
        </div>
      </div>

      <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 p-3 rounded-lg mb-4 text-sm">
        <strong>Demo Payment</strong> — This is a sample payment interface. In a real implementation, this would connect to a payment processor.
      </div>

      <p class="text-gray-500 text-xs mb-4">Support payment ke QRIS,Gopay,OVO, E wallet lainnya</p>

      <form method="POST" id="paymentForm">
        <input type="hidden" name="simulate_payment" value="1">
        <button type="submit" id="simulateBtn" class="bg-gradient-to-r from-green-500 to-blue-500 text-white font-medium px-6 py-3 rounded-xl w-full hover:opacity-90 transition">
          Simulate Payment
        </button>
      </form>
    </div>
  </main>

  <footer class="text-center text-gray-400 text-xs mt-6">
    Your documents will print automatically after payment confirmation
  </footer>
<?php if ($payment_success): ?>
  <script>
    let autoDeleteTimer;
    let countdownSeconds = 15;

    function deleteOrderFiles() {
      fetch('delete_order_files.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=<?php echo $order_id; ?>'
      }).then(() => {
        showFinalMessage();
      });
    }

    function showFinalMessage() {
      let deleteCountdown = 5;
      
      Swal.fire({
        icon: 'success',
        title: 'Order Completed',
        html: `
          <p class="text-gray-700 mb-3">Your file has been permanently deleted for your privacy</p>
          <p class="text-sm text-gray-500">Redirecting in <strong id="deleteTimer">${deleteCountdown}</strong> seconds...</p>
          <p class="mt-4 text-lg font-semibold text-blue-600">Thank you for trusting Anyprint</p>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: () => {
          const timerEl = document.getElementById('deleteTimer');
          const interval = setInterval(() => {
            deleteCountdown--;
            if (timerEl) timerEl.textContent = deleteCountdown;
            if (deleteCountdown <= 0) clearInterval(interval);
          }, 1000);
        }
      }).then(() => {
        window.location.href = 'index.php';
      });
    }

    Swal.fire({
      icon: 'success',
      title: 'Payment Successful!',
      html: `
        <p class="text-gray-600 mt-2">Your documents are now printing.<br>Please collect them from the output tray.</p>
        <p class="text-sm text-gray-500 mt-2">Order #: <strong><?php echo $completed_order; ?></strong></p>
        <p class="mt-4 text-sm text-orange-600">
          <i class="fa-solid fa-clock"></i> Auto-delete in <strong id="countdown">15</strong> seconds
        </p>
        <div class="mt-5 flex gap-3 justify-center">
          <button id="printAnother" class="bg-gradient-to-r from-[#1D4A80] to-[#828275] text-white px-5 py-2 rounded-lg font-medium hover:opacity-90 transition">
            Print Another
          </button>
          <button id="finishOrder" class="bg-green-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-green-700 transition">
            Finish Order
          </button>
        </div>
      `,
      showConfirmButton: false,
      allowOutsideClick: false,
      didOpen: () => {
        const countdownEl = document.getElementById('countdown');
        
        autoDeleteTimer = setInterval(() => {
          countdownSeconds--;
          if (countdownEl) countdownEl.textContent = countdownSeconds;
          
          if (countdownSeconds <= 0) {
            clearInterval(autoDeleteTimer);
            Swal.close();
            deleteOrderFiles();
          }
        }, 1000);

        document.getElementById('printAnother').addEventListener('click', () => {
          clearInterval(autoDeleteTimer);
          Swal.close();
          deleteOrderFiles();
          setTimeout(() => {
            window.location.href = 'index.php';
          }, 500);
        });

        document.getElementById('finishOrder').addEventListener('click', () => {
          clearInterval(autoDeleteTimer);
          Swal.close();
          deleteOrderFiles();
        });
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>