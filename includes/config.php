<?php
// db website real
// define('DB_HOST', 'localhost');
// define('DB_USER', 'u1573484_anyprint');
// define('DB_PASS', 'anyprint123');
// define('DB_NAME', 'u1573484_anyprint');

// define('SITE_URL', 'https://anyprint.my.id/');
// define('ADMIN_URL', SITE_URL . '/admin');

// db localhost
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'anyprint_db');

define('SITE_URL', 'https://anyprint.my.id/');
define('ADMIN_URL', SITE_URL . '/admin');

// Pricing Configuration
// define('PRICE_A5_BW', 500);
// define('PRICE_A4_BW', 750);
// define('PRICE_A3_BW', 1000);
// define('PRICE_A5_COLOR', 750);
// define('PRICE_A4_COLOR', 1000);
// define('PRICE_A3_COLOR', 1250);

define('PRICE_A5_BW', 300);  // A5 Black & White
define('PRICE_A4_BW', 500);  // A4 Black & White (Updated to 500)


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
    return 'Rp ' . number_format($price, 0, ',', '.');
}

function getPricePerPage($paper_size, $color_type) {
   $prices = [
        'A5' => PRICE_A5_BW,
        'A4' => PRICE_A4_BW
    ];
    
    return $prices[$paper_size] ?? PRICE_A4_BW;
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
    $pages = 1; // Default 1 page
    
    if ($file_ext === 'pdf') {
        try {
            // Method 1: Try pdfinfo command (if available)
            if (function_exists('exec') && !stristr(ini_get('disable_functions'), 'exec')) {
                $output = [];
                $return_var = 0;
                @exec("pdfinfo " . escapeshellarg($file_path) . " 2>&1 | grep -i Pages:", $output, $return_var);
                
                if ($return_var === 0 && !empty($output)) {
                    if (preg_match('/(\d+)/', $output[0], $matches)) {
                        $detected_pages = intval($matches[1]);
                        if ($detected_pages > 0) {
                            return $detected_pages;
                        }
                    }
                }
            }
            
            // Method 2: Parse PDF content
            if (file_exists($file_path) && is_readable($file_path)) {
                $content = @file_get_contents($file_path);
                
                if ($content !== false) {
                    // Try multiple patterns
                    $patterns = [
                        "/\/Count\s+(\d+)/",           // Most common
                        "/\/N\s+(\d+)/",                // Alternative
                        "/\/Page\W/",                   // Count /Page occurrences
                        "/\/Type\s*\/Page[^s]/"        // Count /Type /Page
                    ];
                    
                    foreach ($patterns as $pattern) {
                        if ($pattern === "/\/Count\s+(\d+)/" || $pattern === "/\/N\s+(\d+)/") {
                            if (preg_match($pattern, $content, $matches)) {
                                $detected_pages = intval($matches[1]);
                                if ($detected_pages > 0 && $detected_pages < 10000) {
                                    return $detected_pages;
                                }
                            }
                        } else {
                            preg_match_all($pattern, $content, $matches);
                            if (count($matches[0]) > 0) {
                                $detected_pages = count($matches[0]);
                                if ($detected_pages > 0 && $detected_pages < 10000) {
                                    return $detected_pages;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail, return default
            error_log("PDF page detection failed: " . $e->getMessage());
        }
        
        // Fallback: estimate based on file size
        $file_size = filesize($file_path);
        if ($file_size > 0) {
            // Average: 1 page PDF ≈ 50-100KB
            $estimated = max(1, round($file_size / (75 * 1024)));
            return min($estimated, 100); // Cap at 100 pages for safety
        }
        
        return 1; // Ultimate fallback
        
    } elseif (in_array($file_ext, ['doc', 'docx'])) {
        // For Word documents - estimate based on file size
        $file_size = @filesize($file_path);
        if ($file_size > 0) {
            // Average: 1 page ≈ 50KB for Word docs
            $estimated_pages = max(1, round($file_size / (50 * 1024)));
            return min($estimated_pages, 200); // Cap at 200 pages
        }
        return 1;
        
    } else {
        // For images (jpg, png) - always 1 page
        return 1;
    }
}
?>