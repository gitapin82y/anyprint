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
      <div class="bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-full w-8 h-8 flex items-center justify-center font-bold">A</div>
      <h1 class="font-semibold text-gray-800 text-lg">Anyprint</h1>
    </div>
    <p class="text-gray-500 text-sm">Payment</p>
  </header>

  <main class="flex flex-col items-center justify-center min-h-[80vh]">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-[90%] max-w-md text-center">
      <h2 class="text-2xl font-bold mb-2">Complete Payment</h2>
      <p class="text-gray-600 mb-6">Scan the QR code below to pay securely</p>

      <!-- Order Summary -->
      <div class="bg-gray-50 p-4 rounded-xl mb-4">
        <p class="font-semibold text-gray-800 mb-2">Order Summary</p>
        <p id="orderDetails" class="text-gray-600 text-sm mb-2">
          <?php echo $order['total_pages']; ?> pages • <?php echo $order['color_type']; ?> • <?php echo $order['copies']; ?> cop<?php echo $order['copies'] > 1 ? 'ies' : 'y'; ?>
        </p>
        <p id="orderPrice" class="text-3xl font-bold text-blue-600 mb-4"><?php echo formatPrice($order['total_price']); ?></p>
        <div class="bg-gradient-to-r from-blue-500 to-purple-500 p-4 inline-block rounded-xl animate-qris">
          <div class="bg-white w-40 h-40 flex items-center justify-center font-bold text-3xl text-gray-800">▦</div>
        </div>
      </div>

      <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 p-3 rounded-lg mb-4 text-sm">
        <strong>Demo Payment</strong> — This is a sample payment interface. In a real implementation, this would connect to a payment processor.
      </div>

      <p class="text-gray-500 text-xs mb-4">Supports: Apple Pay, Google Pay, Credit Cards</p>

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
    Swal.fire({
      icon: 'success',
      title: 'Payment Successful!',
      html: `
        <p class="text-gray-600 mt-2">Your documents are now printing.<br>
        Please collect them from the output tray.</p>
        <p class="text-sm text-gray-500 mt-2">Order #: <strong><?php echo $completed_order; ?></strong></p>
        <button id="printAnother" class="mt-5 bg-gradient-to-r from-blue-500 to-purple-500 text-white px-5 py-2 rounded-lg font-medium hover:opacity-90 transition">
          Print Another Document
        </button>
      `,
      showConfirmButton: false,
      allowOutsideClick: false,
      background: '#ffffff',
      width: '360px',
      didOpen: () => {
        document.getElementById('printAnother').addEventListener('click', () => {
          window.location.href = 'index.php';
        });
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>