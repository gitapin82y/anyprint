<?php
require_once 'includes/config.php';

// Redirect if no files in session
if (!isset($_SESSION['temp_files']) || empty($_SESSION['temp_files'])) {
    redirect('index.php');
}

$files_array = $_SESSION['temp_files'];

// Calculate total pages
$total_pages = 0;
foreach ($files_array as $file) {
    $total_pages += $file['pages'];
}

// Default values
$paper_size = 'A4';
$color_type = 'Black & White';
$copies = 1;

// Handle form submission - BARU BUAT ORDER DI SINI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_size = sanitize($_POST['paper_size']);
    $color_type = sanitize($_POST['color_type']);
    $copies = intval($_POST['copies']);
    
    $price_per_page = getPricePerPage($paper_size, $color_type);
    $total_price = $total_pages * $price_per_page * $copies;
    
    // CREATE ORDER - BARU DI SINI dengan data LENGKAP
    $order_number = isset($_SESSION['temp_order_number']) ? $_SESSION['temp_order_number'] : generateOrderNumber();

    $customer_ip = getClientIP();
    
    $stmt = $conn->prepare("INSERT INTO orders (order_number, total_pages, paper_size, color_type, copies, price_per_page, total_price, customer_ip, order_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
    $stmt->bind_param("sissidds", $order_number, $total_pages, $paper_size, $color_type, $copies, $price_per_page, $total_price, $customer_ip);
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        $_SESSION['order_id'] = $order_id;
        $_SESSION['order_number'] = $order_number;
        
        // NOW save files to database and move to permanent storage
        $upload_dir = 'uploads/' . $order_id . '/';
        
        if (!file_exists('uploads/')) {
            mkdir('uploads/', 0777, true);
        }
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($files_array as $index => $file) {
            // Generate unique filename
            $safe_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $new_filename = time() . '_' . $index . '_' . $safe_name . '.' . $file['ext'];
            $destination = $upload_dir . $new_filename;
            
            // Move from temp to permanent
            if (file_exists($file['tmp_name'])) {
                move_uploaded_file($file['tmp_name'], $destination);
            }
            
            // Save to database
            $stmt = $conn->prepare("INSERT INTO order_files (order_id, file_name, file_size, file_pages, file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $order_id, $file['name'], $file['size_formatted'], $file['pages'], $destination);
            $stmt->execute();
            $stmt->close();
        }
        
        // Clear temp files from session
        unset($_SESSION['temp_files']);
        
        // Redirect to payment
        redirect('payment.php');
    } else {
        $error = "Failed to create order: " . $conn->error;
    }
    
    $stmt->close();
}

// Handle file deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $file_index = intval($_GET['delete']);
    
    if (isset($_SESSION['temp_files'][$file_index])) {
        // Remove from session array
        array_splice($_SESSION['temp_files'], $file_index, 1);
        
        // Redirect back if no files left
        if (empty($_SESSION['temp_files'])) {
            unset($_SESSION['temp_files']);
            redirect('index.php');
        }
        
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
  <link rel="icon" type="image/jpeg" href="assets/logo-anyprint.jpeg" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-[#f1f5ff] font-sans">
  <header class="flex justify-between items-center px-8 py-4 bg-white shadow-sm">
    <div class="flex items-center gap-2">
      <img src="assets/logo-anyprint.jpeg" width="150px" alt="">
    </div>
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
        <?php foreach ($files_array as $index => $file): ?>
        <?php
          $icon_class = 'fa-file';
          $icon_color = 'red';
          
          if ($file['ext'] === 'pdf') {
              $icon_class = 'fa-file-pdf';
              $icon_color = 'red';
          } elseif (in_array($file['ext'], ['doc', 'docx'])) {
              $icon_class = 'fa-file-word';
              $icon_color = 'blue';
          } elseif (in_array($file['ext'], ['jpg', 'jpeg', 'png'])) {
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
              <p class="font-medium text-gray-800"><?php echo htmlspecialchars($file['name']); ?></p>
              <p class="text-sm text-gray-400"><?php echo $file['pages']; ?> page<?php echo $file['pages'] > 1 ? 's' : ''; ?> • <?php echo $file['size_formatted']; ?></p>
            </div>
          </div>
          <button onclick="confirmDelete(<?php echo $index; ?>)" class="text-gray-400 hover:text-red-500 transition">
            <i class="fa-solid fa-trash text-lg"></i>
          </button>
        </div>
        <?php endforeach; ?>
      </div>

      <form method="POST" id="reviewForm">
        <div class="bg-gray-50 rounded-xl p-6 mb-8 border">
          <h3 class="font-semibold mb-4">Print Settings</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="text-sm text-gray-600">Paper Size</label>
              <select id="paperSize" name="paper_size" class="w-full border rounded-lg px-3 py-2 mt-1">
                <option>A4</option>
                <option>A5</option>
                <option>A3</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-gray-600">Color</label>
              <select id="colorType" name="color_type" class="w-full border rounded-lg px-3 py-2 mt-1">
                <option>Black & White</option>
                <option>Color</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-gray-600">Copies</label>
              <input type="number" id="copies" name="copies" min="1" value="1" class="w-full border rounded-lg px-3 py-2 mt-1" />
            </div>
          </div>
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
    const colorSelect = document.getElementById('colorType');
    const copiesInput = document.getElementById('copies');
    const summaryText = document.getElementById('summaryText');
    const totalPriceEl = document.getElementById('totalPrice');

    const prices = {
      'A5': { 'Black & White': 500, 'Color': 750 },
      'A4': { 'Black & White': 750, 'Color': 1000 },
      'A3': { 'Black & White': 1000, 'Color': 1250 }
    };

    function updatePrice() {
      const paperSize = paperSizeSelect.value;
      const color = colorSelect.value;
      const copies = parseInt(copiesInput.value) || 1;
      const pricePerPage = prices[paperSize][color];
      const total = totalPages * pricePerPage * copies;

      summaryText.textContent = `${totalPages} pages × Rp ${pricePerPage.toLocaleString('id-ID')} × ${copies} cop${copies > 1 ? 'ies' : 'y'}`;
      totalPriceEl.textContent = `Rp ${total.toLocaleString('id-ID')}`;
    }

    paperSizeSelect.addEventListener('change', updatePrice);
    colorSelect.addEventListener('change', updatePrice);
    copiesInput.addEventListener('input', updatePrice);
    updatePrice();

    function confirmDelete(fileIndex) {
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
          window.location.href = '?delete=' + fileIndex;
        }
      });
    }
  </script>
</body>
</html>