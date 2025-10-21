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

// Get uploaded files
$stmt = $conn->prepare("SELECT * FROM order_files WHERE order_id = ? ORDER BY uploaded_at ASC");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$files = $stmt->get_result();
$stmt->close();

// Calculate total pages from uploaded files
$total_pages = 0;
$files_array = [];
while ($file = $files->fetch_assoc()) {
    $total_pages += $file['file_pages'];
    $files_array[] = $file;
}

// Redirect back if no files uploaded
if (empty($files_array)) {
    redirect('index.php');
}

$paper_size = $order['paper_size'] ?: 'A4';
$copies = $order['copies'] ?: 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_size = sanitize($_POST['paper_size']);
    $copies = intval($_POST['copies']);
    
    $price_per_page = getPricePerPage($paper_size);
    $total_price = $total_pages * $price_per_page * $copies;
    
    // Color type always Black & White
    $color_type = 'Black & White';
    
    $stmt = $conn->prepare("UPDATE orders SET paper_size = ?, color_type = ?, copies = ?, price_per_page = ?, total_price = ?, total_pages = ? WHERE id = ?");
    $stmt->bind_param("ssiidii", $paper_size, $color_type, $copies, $price_per_page, $total_price, $total_pages, $order_id);
    $stmt->execute();
    $stmt->close();
    
    redirect('payment.php');
}

// Handle file deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $file_id = intval($_GET['delete']);
    
    // Get file info
    $stmt = $conn->prepare("SELECT * FROM order_files WHERE id = ? AND order_id = ?");
    $stmt->bind_param("ii", $file_id, $order_id);
    $stmt->execute();
    $file_to_delete = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($file_to_delete) {
        // Delete physical file
        if (file_exists($file_to_delete['file_path'])) {
            unlink($file_to_delete['file_path']);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM order_files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $stmt->close();
        
        redirect('review.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Anyprint - Review</title>
  <!-- Favicon -->
<link rel="icon" type="image/jpeg" href="assets/logo-anyprint.jpeg" />
<link rel="apple-touch-icon" href="assets/logo-anyprint.jpeg" />
<link rel="shortcut icon" type="image/x-icon" href="assets/logo-anyprint.jpeg" />
<meta name="theme-color" content="#1A2E55" />

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- TAMBAHKAN INI -->
</head>
<body class="bg-[#f1f5ff] font-sans">
  <header class="flex justify-between items-center px-8 py-4 bg-white shadow-sm">
    <div class="flex items-center gap-2">
    <img src="assets/logo-anyprint.jpeg" width="150px" alt="">
    </img>
    <p class="text-gray-500 text-sm">File Preview</p>
  </header>

  <small class="text-[#828275] mt-8 ms-8">Created By Group 50.</small>

  <main class="flex flex-col items-center justify-center py-12">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-[90%] max-w-2xl">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Review Your Files</h2>
        <a href="index.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
          <i class="fa-solid fa-plus mr-1"></i> Add More Files
        </a>
      </div>

      <div class="space-y-3 mb-8">
        <?php foreach ($files_array as $file): ?>
        <?php
          $file_ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
          $icon_class = 'fa-file';
          $icon_color = 'red';
          
          if ($file_ext === 'pdf') {
              $icon_class = 'fa-file-pdf';
              $icon_color = 'red';
          } elseif (in_array($file_ext, ['doc', 'docx'])) {
              $icon_class = 'fa-file-word';
              $icon_color = 'blue';
          } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
              $icon_class = 'fa-file-image';
              $icon_color = 'green';
          }
        ?>
        <div class="flex items-center justify-between bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
          <div class="flex items-center gap-3">
            <div class="bg-<?php echo $icon_color; ?>-100 text-<?php echo $icon_color; ?>-500 rounded-lg px-2 py-1">
              <i class="fa-solid <?php echo $icon_class; ?> text-xl"></i>
            </div>
            <div>
              <p class="font-medium text-gray-800"><?php echo htmlspecialchars($file['file_name']); ?></p>
              <p class="text-sm text-gray-400"><?php echo $file['file_pages']; ?> page<?php echo $file['file_pages'] > 1 ? 's' : ''; ?> • <?php echo $file['file_size']; ?></p>
            </div>
          </div>
<button onclick="confirmDelete(<?php echo $file['id']; ?>)" class="text-gray-400 hover:text-red-500 transition">
  <i class="fa-solid fa-trash text-lg"></i>
</button>
        </div>
        <?php endforeach; ?>
      </div>

      <form method="POST" id="reviewForm">
        <div class="bg-gray-50 rounded-xl p-6 mb-8 border">
          <h3 class="font-semibold mb-4">Print Settings</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-gray-600">Paper Size</label>
              <select id="paperSize" name="paper_size" class="w-full border rounded-lg px-3 py-2 mt-1">
                <option <?php echo $paper_size === 'A4' ? 'selected' : ''; ?>>A4</option>
                <option <?php echo $paper_size === 'A5' ? 'selected' : ''; ?>>A5</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-gray-600">Copies</label>
              <input type="number" id="copies" name="copies" min="1" value="<?php echo $copies; ?>" class="w-full border rounded-lg px-3 py-2 mt-1" />
            </div>
          </div>
          <p class="text-sm text-gray-500 mt-3">
            <i class="fa-solid fa-info-circle mr-1"></i> Black & White only
          </p>
        </div>

        <div class="flex justify-between items-center mb-6">
          <div>
            <h3 class="font-semibold">Total Cost</h3>
            <p id="summaryText" class="text-gray-600 text-sm"></p>
          </div>
          <p id="totalPrice" class="text-xl font-bold text-blue-600"></p>
        </div>

        <button type="submit" class="bg-gradient-to-r from-[#1D4A80] to-[#828275] text-white font-medium px-6 py-3 rounded-xl w-full hover:opacity-90 transition">
          Proceed to Payment
        </button>
      </form>
    </div>
  </main>

<script>
    const totalPages = <?php echo $total_pages; ?>;
    const paperSizeSelect = document.getElementById('paperSize');
    const copiesInput = document.getElementById('copies');
    const summaryText = document.getElementById('summaryText');
    const totalPriceEl = document.getElementById('totalPrice');

const prices = {
      'A5': 300,
      'A4': 500
    };

 function updatePrice() {
      const paperSize = paperSizeSelect.value;
      const copies = parseInt(copiesInput.value) || 1;
      const pricePerPage = prices[paperSize];
      const total = totalPages * pricePerPage * copies;

      summaryText.textContent = `${totalPages} pages × Rp ${pricePerPage.toLocaleString('id-ID')} × ${copies} cop${copies > 1 ? 'ies' : 'y'}`;
      totalPriceEl.textContent = `Rp ${total.toLocaleString('id-ID')}`;
    }

  paperSizeSelect.addEventListener('change', updatePrice);
    copiesInput.addEventListener('input', updatePrice);
    updatePrice();

  // SweetAlert2 Delete Confirmation
  function confirmDelete(fileId) {
    Swal.fire({
      title: 'Delete File?',
      text: "This action cannot be undone!",
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
        
        // Redirect to delete
        window.location.href = '?delete=' + fileId;
      }
    });
  }
</script>
</body>
</html>