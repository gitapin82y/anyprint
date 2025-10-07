<?php
require_once 'includes/config.php';

// Debug mode - hapus setelah masalah selesai
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$error = '';
$success = '';
$debug_info = '';

// Create new order session - PASTIKAN INI JALAN
if (!isset($_SESSION['order_id']) || empty($_SESSION['order_id'])) {
    $order_number = generateOrderNumber();
    $customer_ip = getClientIP();
    
    $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_ip, order_status, payment_status) VALUES (?, ?, 'pending', 'pending')");
    $stmt->bind_param("ss", $order_number, $customer_ip);
    
    if ($stmt->execute()) {
        $_SESSION['order_id'] = $conn->insert_id;
        $_SESSION['order_number'] = $order_number;
        $debug_info = "Order created: " . $_SESSION['order_id'];
    } else {
        die("FATAL: Cannot create order - " . $conn->error);
    }
    
    $stmt->close();
}

// Verify order exists in database
$order_id = $_SESSION['order_id'];
$verify = $conn->query("SELECT id, order_number FROM orders WHERE id = " . intval($order_id));
if ($verify->num_rows === 0) {
    // Order tidak ditemukan, buat baru
    unset($_SESSION['order_id']);
    unset($_SESSION['order_number']);
    header("Location: index.php");
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {
    $order_id = intval($_SESSION['order_id']);
    
    // Double check order exists
    $check = $conn->query("SELECT id FROM orders WHERE id = $order_id");
    if ($check->num_rows === 0) {
        $error = "Order not found in database. Order ID: $order_id";
    } else {
        $upload_dir = 'uploads/' . $order_id . '/';
        
        // Create upload directory if not exists
        if (!file_exists('uploads/')) {
            if (!mkdir('uploads/', 0777, true)) {
                $error = "Cannot create uploads folder. Check permissions.";
            }
        }
        
        if (empty($error) && !file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $error = "Cannot create order folder. Check permissions.";
            }
        }
        
        if (empty($error)) {
            $uploaded_count = 0;
            $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            // Handle multiple files
            if (isset($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
                $file_count = count($_FILES['documents']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    // Skip if no file or error
                    if (empty($_FILES['documents']['name'][$i]) || $_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    
                    $file_name = basename($_FILES['documents']['name'][$i]);
                    $file_tmp = $_FILES['documents']['tmp_name'][$i];
                    $file_size = $_FILES['documents']['size'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Validate file type
                    if (!in_array($file_ext, $allowed_types)) {
                        $error .= "File '$file_name': Format tidak didukung. ";
                        continue;
                    }
                    
                    // Validate file size (max 10MB)
                    if ($file_size > 10 * 1024 * 1024) {
                        $error .= "File '$file_name': Ukuran terlalu besar (max 10MB). ";
                        continue;
                    }
                    
                    // Generate unique filename
                    $safe_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
                    $new_filename = time() . '_' . $i . '_' . $safe_name . '.' . $file_ext;
                    $destination = $upload_dir . $new_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file_tmp, $destination)) {
                        // Detect number of pages
                        $pages = detectPages($destination, $file_ext);
                        
                        // Get file size in readable format
                        $file_size_formatted = formatFileSize($file_size);
                        
                        // Save to database
                        $stmt = $conn->prepare("INSERT INTO order_files (order_id, file_name, file_size, file_pages, file_path) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("issis", $order_id, $file_name, $file_size_formatted, $pages, $destination);
                        
                        if ($stmt->execute()) {
                            $uploaded_count++;
                        } else {
                            $error .= "Database error for '$file_name': " . $stmt->error . " ";
                        }
                        
                        $stmt->close();
                    } else {
                        $error .= "Failed to move '$file_name'. Check permissions. ";
                    }
                }
            }
            
            if ($uploaded_count > 0) {
                $success = "$uploaded_count file berhasil diupload!";
                // Redirect to review page after 1.5 seconds
                header("refresh:1.5;url=review.php");
            } elseif (empty($error)) {
                $error = "No files were uploaded. Please select at least one file.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Anyprint - Upload</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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

  .file-upload-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
    width: 100%;
  }

  .file-upload-wrapper input[type=file] {
    position: absolute;
    left: -9999px;
  }

  .file-upload-label {
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .file-upload-label:hover {
    opacity: 0.9;
  }
</style>

</head>
<body class="bg-[#f1f5ff] font-sans">
  <header class="flex justify-between items-center px-8 py-4 bg-white shadow-sm">
    <div class="flex items-center gap-2">
      <div class="bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-full w-8 h-8 flex items-center justify-center font-bold">A</div>
      <h1 class="font-semibold text-gray-800 text-lg">Anyprint</h1>
    </div>
    <p class="text-gray-500 text-sm">Smart Printing Solutions</p>
  </header>

  <main class="flex flex-col items-center justify-center min-h-[80vh] py-8">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-[90%] max-w-md text-center">
      <h2 class="text-2xl font-bold mb-2">Upload Your Files</h2>
      <p class="text-gray-600 mb-6">Scan the QR code below with your phone or upload directly from this device</p>

      <div class="bg-gradient-to-r from-blue-500 to-purple-500 p-4 inline-block rounded-xl mb-4 animate-qris">
        <div class="bg-white w-40 h-40 flex items-center justify-center font-bold text-3xl text-gray-800">▩</div>
      </div>

      <p class="text-gray-500 text-sm mb-4">Order Number: <strong><?php echo htmlspecialchars($_SESSION['order_number']); ?></strong></p>

      <?php if ($debug_info && false): // Set true untuk debug ?>
      <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2 rounded-lg mb-4 text-xs text-left">
        Debug: <?php echo $debug_info; ?> | Session ID: <?php echo $_SESSION['order_id']; ?>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm text-left">
        <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error; ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <i class="fa-solid fa-circle-check mr-1"></i> <?php echo $success; ?>
        <div class="mt-2">
          <i class="fa-solid fa-spinner fa-spin mr-1"></i> Redirecting to review page...
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="file-upload-wrapper mb-4">
          <input type="file" name="documents[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required />
          <label for="fileInput" class="file-upload-label bg-gradient-to-r from-blue-500 to-purple-500 text-white font-medium px-6 py-3 rounded-xl w-full hover:opacity-90 transition text-center block">
            <i class="fa-solid fa-cloud-arrow-up mr-2"></i> Choose Files
          </label>
        </div>

        <div id="fileList" class="mb-4 text-left text-sm text-gray-600 hidden">
          <p class="font-semibold mb-2">Selected files (<span id="fileCount">0</span>):</p>
          <ul id="fileNames" class="list-disc pl-5 space-y-1 max-h-40 overflow-y-auto"></ul>
        </div>

        <button type="submit" id="uploadBtn" class="bg-gradient-to-r from-green-500 to-blue-500 text-white font-medium px-6 py-3 rounded-xl w-full hover:opacity-90 transition disabled:opacity-50" disabled>
          <i class="fa-solid fa-upload mr-2"></i> Upload & Continue
        </button>
      </form>

    </div>

    <footer class="text-center text-gray-400 text-xs mt-6">
      Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB per file)
    </footer>
  </main>

        <script>
  const fileInput = document.getElementById('fileInput');
  const fileList = document.getElementById('fileList');
  const fileNames = document.getElementById('fileNames');
  const fileCount = document.getElementById('fileCount');
  const uploadBtn = document.getElementById('uploadBtn');
  const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB in bytes

  fileInput.addEventListener('change', function() {
    const files = Array.from(this.files);
    const validFiles = [];
    const oversizedFiles = [];
    
    // Check each file size
    files.forEach(file => {
      if (file.size > MAX_FILE_SIZE) {
        oversizedFiles.push(file.name);
      } else {
        validFiles.push(file);
      }
    });
    
    // Show alert if there are oversized files
    if (oversizedFiles.length > 0) {
      Swal.fire({
        icon: 'error',
        title: 'File Too Large!',
        html: `
          <p class="mb-2">The following files exceed 10MB limit:</p>
          <ul class="text-left list-disc pl-5 text-sm">
            ${oversizedFiles.map(name => `<li>${name}</li>`).join('')}
          </ul>
          <p class="mt-3 text-sm text-gray-600">These files have been removed from selection.</p>
        `,
        confirmButtonColor: '#3b82f6'
      });
      
      // Reset file input and use DataTransfer to set only valid files
      const dataTransfer = new DataTransfer();
      validFiles.forEach(file => {
        dataTransfer.items.add(file);
      });
      fileInput.files = dataTransfer.files;
    }
    
    // Display valid files
    if (validFiles.length > 0) {
      fileList.classList.remove('hidden');
      fileNames.innerHTML = '';
      fileCount.textContent = validFiles.length;
      
      validFiles.forEach(file => {
        const li = document.createElement('li');
        li.className = 'text-gray-700';
        li.innerHTML = `<strong>${file.name}</strong> <span class="text-gray-400">(${formatBytes(file.size)})</span>`;
        fileNames.appendChild(li);
      });
      
      uploadBtn.disabled = false;
    } else {
      fileList.classList.add('hidden');
      uploadBtn.disabled = true;
    }
  });

  function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  }

  // Show loading when form submitted
  document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const files = fileInput.files;
    if (files.length === 0) {
      e.preventDefault();
      alert('Please select at least one file!');
      return false;
    }
    
    uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Uploading ' + files.length + ' file(s)...';
    uploadBtn.disabled = true;
  });
</script>

</body>
</html>