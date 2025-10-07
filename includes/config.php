<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'anyprint_db');

// Site Configuration
define('SITE_URL', 'http://localhost/anyprint');
define('ADMIN_URL', SITE_URL . '/admin');

// Pricing Configuration
define('PRICE_BW', 0.30);
define('PRICE_COLOR', 0.75);

// Session Configuration
session_start();

// Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Helper Functions
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function generateOrderNumber() {
    return 'ORD' . date('Ymd') . rand(1000, 9999);
}

function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function detectPages($file_path, $file_ext) {
    $pages = 1; // Default 1 page for images
    
    if ($file_ext === 'pdf') {
        // Method 1: Using pdfinfo (if available on server)
        if (function_exists('exec')) {
            $output = [];
            exec("pdfinfo " . escapeshellarg($file_path) . " | grep Pages:", $output);
            if (!empty($output)) {
                preg_match('/\d+/', $output[0], $matches);
                if (isset($matches[0])) {
                    return intval($matches[0]);
                }
            }
        }
        
        // Method 2: Using regex to count pages in PDF
        $content = file_get_contents($file_path);
        $pattern = "/\/Page\W/";
        preg_match_all($pattern, $content, $matches);
        $pages = count($matches[0]);
        
        // Fallback if no pages detected
        if ($pages === 0) {
            // Alternative method: count /Type /Page
            $pattern = "/\/Type\s*\/Page[^s]/";
            preg_match_all($pattern, $content, $matches);
            $pages = count($matches[0]);
        }
        
        return $pages > 0 ? $pages : 1;
    } elseif (in_array($file_ext, ['doc', 'docx'])) {
        // For Word documents, we estimate based on file size
        // Average: 1 page = ~50KB (rough estimation)
        $file_size = filesize($file_path);
        $estimated_pages = max(1, round($file_size / (50 * 1024)));
        return $estimated_pages;
    } else {
        // For images (jpg, png), always 1 page
        return 1;
    }
}
?>