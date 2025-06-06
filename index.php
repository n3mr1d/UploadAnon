<?php
// autoload function
require_once __DIR__ . '/autoload.php';

// validattte function autoload
setupDatabase();
routeenfile();


// global string for error or succes message
$result = '';
$errors = [];
$success = [];



function print_start(string $title) {
    global $errors;

    // Convert to lowercase and split by space
    $dann = explode(" ", strtolower($title));
    // Use first word as class, or 'default' if empty
    $className = !empty($dann[0]) ? $dann[0] : 'default';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnfileUp - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <link rel="stylesheet" href="/resource/style/global.css">
    <link rel="stylesheet" href="/font/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body>' . navbar() . '<main class="' . $className . '">';

    // Show errors from $errors or from $_SESSION['errors']
    if (!empty($errors) || !empty($_SESSION['errors'])) {
        echo '<div class="error">';
        // Show errors from $errors array
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<div class="text-error"><i class="fas fa-triangle-exclamation"></i> ' . htmlspecialchars($error) . '</div>';
            }
        }
        // Show error from $_SESSION['errors'] (string or array)
        if (!empty($_SESSION['errors'])) {
            if (is_array($_SESSION['errors'])) {
                foreach ($_SESSION['errors'] as $err) {
                    echo '<div class="text-error"><i class="fas fa-triangle-exclamation"></i> ' . htmlspecialchars($err) . '</div>';
                }
            } else {
                echo '<div class="text-error"><i class="fas fa-triangle-exclamation"></i> ' . htmlspecialchars($_SESSION['errors']) . '</div>';
            }
            unset($_SESSION['errors']);
        }
        echo '</div>';
    }
}


function endtags(){
    echo '</main>' . footer() . '</body></html>';
}
function footer() {
    return '
    <footer>
        <div class="about">
            <h3>About EnfileUp</h3>
            <p>EnfileUp is an anonymous image and file uploader platform operating on the darkweb, allowing users to upload files without leaving behind identifying metadata.</p>
            <p>Our platform provides strong security features and is completely free of charge, making it a popular choice for those who value privacy and security.</p>
            <div class="features">
                <div class="feature-item">
                    <span class="feature-icon">🔒</span>
                    <span class="feature-text">End-to-end encryption</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">🕵️</span>
                    <span class="feature-text">No logs policy</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">⚡</span>
                    <span class="feature-text">Fast uploads</span>
                </div>
            </div>
        </div>
        <div class="donate">
            <h3>Support Our Service</h3>
            <p>Help us keep EnfileUp running and ad-free by donating:</p>
            <div class="crypto-donations">
                <div class="crypto">
                    <span class="crypto-name">Bitcoin:</span>
                    <span class="crypto-address">bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh</span>
                </div>
                <div class="crypto">
                    <span class="crypto-name">Ethereum:</span>
                    <span class="crypto-address">0x71C7656EC7ab88b098defB751B7401B5f6d8976F</span>
                </div>
            </div>
            <div class="donate-message">
                <p>Your support helps us maintain our infrastructure and develop new features.</p>
                <p>Thank you for being part of our community!</p>
            </div>
        </div>
    </footer>';
}






// Generate thumbnail
function generateThumbnail($sourcePath, $destPath, $extension) {
    if (!extension_loaded('gd')) return false;
    
    try {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) return false;
        
        $width = imagesx($source);
        $height = imagesy($source);
        
        // Calculate new dimensions
        $ratio = min(THUMBNAIL_SIZE / $width, THUMBNAIL_SIZE / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnail, $destPath, 85);
                break;
            case 'png':
                imagepng($thumbnail, $destPath);
                break;
            case 'gif':
                imagegif($thumbnail, $destPath);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}




// Enhanced cron job
function cron() {
    global $db;

    try {
        // Get expired files
        $query = "SELECT fileloc, thumbnail_path FROM fileup WHERE expire IS NOT NULL AND expire < NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $expiredFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete expired files from filesystem
        foreach ($expiredFiles as $file) {
            if ($file['fileloc'] && file_exists($file['fileloc'])) {
                unlink($file['fileloc']);
            }
            if ($file['thumbnail_path'] && file_exists($file['thumbnail_path'])) {
                unlink($file['thumbnail_path']);
            }
        }
        
        // Delete expired records from database
        $deleteQuery = "DELETE FROM fileup WHERE expire IS NOT NULL AND expire < NOW()";
        $db->exec($deleteQuery);
        
   
        
        // Clean old access logs (older than 90 days)
        $cleanLogsQuery = "DELETE FROM access_logs WHERE access_time < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $db->exec($cleanLogsQuery);
        
    } catch (Exception $e) {
        error_log("Cron job error: " . $e->getMessage());
    }
}

cron();

// Generate secure ID
function generateSecureId($input) {
    $hash = hash('sha256', $input . random_bytes(16), true);
    $encoded = base64_encode($hash);
    $encoded = str_replace(['+', '/', '='], ['-', '_', ''], $encoded);
    return substr($encoded, 0, 32);
}

// Generate delete token
function generateDeleteToken($uniqueId) {
    return hash('sha256', $uniqueId . $_SERVER['HTTP_USER_AGENT'] . date('Y-m-d'));
}

function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

// File download handler
function downloadFile($fileId) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        die("File not found");
    }
    
    // Check password protection
    if ($file['password_hash'] && !isset($_SESSION['authenticated_files'][$fileId])) {
        header("Location: /?file=" . $fileId);
        exit();
    }
    
    // Check file existence
    if (!file_exists($file['fileloc'])) {
        die("File not found on server");
    }
    
    // Check expiration
    if ($file['expire'] !== null && strtotime($file['expire']) < time()) {
        cleanupExpiredFile($file);
        die("This file has expired");
    }
    
    // Check download limits
    if ($file['max_downloads'] > 0 && $file['download_count'] >= $file['max_downloads']) {
        die("Download limit exceeded");
    }
    
    // Update download count and log access
    $updateStmt = $db->prepare("UPDATE fileup SET download_count = download_count + 1 WHERE id = :id");
    $updateStmt->bindParam(':id', $file['id']);
    $updateStmt->execute();
    
    logFileAccess($file['id'], 'download');
    
    // Serve file
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file['filesize']);
    
    // Stream file in chunks
    $handle = fopen($file['fileloc'], 'rb');
    while (!feof($handle)) {
        echo fread($handle, CHUNK_SIZE);
        flush();
    }
    fclose($handle);
    exit();
}

// File deletion handler
function handleFileDeletion($fileId, $token) {
    global $db;
    
    // Verify delete token
    $expectedToken = generateDeleteToken($fileId);
    if (!hash_equals($expectedToken, $token)) {
        showBulkDeleteResult(false, "Invalid delete token");
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
        $stmt->bindParam(':unique_id', $fileId);
        $stmt->execute();
        
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            showBulkDeleteResult(false, "File not found");
            return;
        }
        
        // Delete file from filesystem
        if (file_exists($file['fileloc'])) {
            if (!unlink($file['fileloc'])) {
                showBulkDeleteResult(false, "Failed to delete file from filesystem");
                return;
            }
        }
        
        if ($file['thumbnail_path'] && file_exists($file['thumbnail_path'])) {
            unlink($file['thumbnail_path']);
        }
        
        // Delete from database
        $deleteStmt = $db->prepare("DELETE FROM fileup WHERE id = :id");
        $deleteStmt->bindParam(':id', $file['id']);
        if (!$deleteStmt->execute()) {
            showBulkDeleteResult(false, "Failed to delete file record from database");
            return;
        }
        
        showBulkDeleteResult(true, "File deleted successfully");
        
    } catch(Exception $e) {
        error_log("Error deleting file: " . $e->getMessage());
        showBulkDeleteResult(false, "An error occurred while deleting the file");
    }
}
// Gallery view
function showGallery() {
    global $db;
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 8;
    $offset = ($page - 1) * $limit;
    
    // Get public image files with additional metadata
    // image
    $stmt = $db->prepare("SELECT f.unique_id, f.filename, f.thumbnail_path, f.filesize, 
                         f.upload_timestamp, f.view_count, f.description, f.download_count,
                         f.filetype, f.expire,f.password_hash
                         FROM fileup f
                         WHERE f.filetype LIKE 'image/%' 
                         AND f.is_private = 0 
                         AND (f.expire IS NULL OR f.expire > NOW())
                         AND f.fileloc IS NOT NULL
                         ORDER BY f.upload_timestamp DESC 
                         LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $text= "SELECT f.unique_id, f.filename, f.thumbnail_path, f.filesize, 
                         f.upload_timestamp, f.view_count, f.description, f.download_count,
                         f.filetype, f.expire, f.fileloc, f.password_hash
                         FROM fileup f
                         WHERE f.filetype LIKE 'text/%' 
                         AND f.is_private = 0 
                         AND (f.expire IS NULL OR f.expire > NOW())
                         AND f.fileloc IS NOT NULL
                         ORDER BY f.upload_timestamp DESC 
                         LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($text);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $text = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Get total count for pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM fileup 
                              WHERE filetype LIKE 'image/%' 
                              AND is_private = 0 
                              AND (expire IS NULL OR expire > NOW())
                              AND fileloc IS NOT NULL");
    $countStmt->execute();
    $totalImages = $countStmt->fetchColumn();
    $totalPages = ceil($totalImages / $limit);

    print_start("Gallery");
    echo '<div class="gallery-container">';
    echo'<div class="file-preview">';
    echo '<h1><i class="fas fa-images"></i> Image Gallery</h1>';
    echo'<span class="notif">
    <i class="fas fa-info-circle"></i>
    <p>if you find cp/gore photos in the public gallery you can contact us. <a href="mailto:idrift@dnmx.su">idrift@dnmx.su</a></p>
    </span>';
    echo'</div>';
  
    echo '<div class="gallery-grid">';
    
    if (empty($images)) {
        echo '<div class="no-images">No images found in the gallery.</div>';
    } else {
        foreach ($images as $image) {
            $thumbnailSrc = $image['thumbnail_path'] ?: '/upload/' . $image['unique_id'];
            $expireDate = $image['expire'] ? date('M j, Y', strtotime($image['expire'])) : 'Never';
            $isProtected = isset($image['password_hash']);
            
            echo '<div class="gallery-item">
                    <div class="gallery-image">';
            if ($isProtected) {
                echo '<div class="protected-image">
                        <i class="fas fa-lock fa-3x" style="margin: 10px;"></i>
                        <p>Password Protected</p>
                      </div>';
            } else {
                echo '<img src="' . $thumbnailSrc . '" 
                          alt="' . htmlspecialchars($image['filename']) . '"
                          loading="lazy">';
            }
            echo '</div>
                  <div class="gallery-item-info">
                      <h4>' . htmlspecialchars($image['filename']) . '</h4>
                      <div class="gallery-meta">
                          <p><i class="fas fa-file"></i> ' . formatFileSize($image['filesize']) . '</p>
                          <p><i class="fas fa-eye"></i> ' . $image['view_count'] . ' views</p>
                          <p><i class="fas fa-download"></i> ' . $image['download_count'] . ' downloads</p>
                          <p><i class="fas fa-calendar"></i> ' . date('M j, Y', strtotime($image['upload_timestamp'])) . '</p>
                          <p><i class="fas fa-clock"></i> Expires: ' . $expireDate . '</p>
                          <p><i class="fas fa-lock"></i> ' . ($image['password_hash'] ? 'Protected' : 'No password') . '</p>
                          <div class="file-actions">';
            
            if($isProtected) {
                echo '<a href="/?download='.$image['unique_id'].'"><i class="fas fa-download"></i> Download</a>';

                echo '<a href="/?file='.$image['unique_id'].'"><i class="fas fa-eye"></i> View</a>';
            } else {
                echo '<a href="/?download='.$image['unique_id'].'"><i class="fas fa-download"></i> Download</a>
                      <a href="/?file='.$image['unique_id'].'"><i class="fas fa-eye"></i> View</a>';
            }
            
            echo '</div>
                  </div>
                  </div>
                  </div>';
        }
    }

 
    
    echo '</div>'; // Close gallery-grid
    
    // Enhanced pagination
    if ($totalPages > 1) {
        echo '<div class="pagination">';
        
        // Previous page
        if ($page > 1) {
            echo '<a href="/?action=gallery&page=' . ($page - 1) . '" class="pagination-prev">Previous</a>';
        }
        
        // Page numbers
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1) {
            echo '<a href="/?action=gallery&page=">1</a>';
            if ($startPage > 2) echo '<span class="pagination-ellipsis">...</span>';
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = ($i == $page) ? ' class="active"' : '';
            echo '<a href="/?action=gallery&page=' . $i . '"' . $active . '>' . $i . '</a>';
        }
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) echo '<span class="pagination-ellipsis">...</span>';
            echo '<a href="/?action=gallery&page=' . $totalPages . '">' . $totalPages . '</a>';
        }
        
        // Next page
        if ($page < $totalPages) {
            echo '<a href="/?action=gallery&page=' . ($page + 1) . '" class="pagination-next">Next</a>';
        }
        
        echo '</div>';
    }
    
    echo '</div>'; // Close gallery-container
    echo'<div class="text-container">';
    echo '<div class="file-preview">
        <h1><i class="fas fa-file-alt"></i> Text Files</h1>
    </div>
    <div class="kontainer-text-bulk">';
    
    foreach ($text as $texts) {
        $content = file_get_contents($texts['fileloc']);
        $fileSize = filesize($texts['fileloc']);
        $fileDate = date("F d Y H:i", filemtime($texts['fileloc']));
        
        echo '<div class="gallery-item-text">
            <div class="text">
                <h4><i class="fas fa-file-code"></i> ' . htmlspecialchars(substr($texts['filename'], 0, 20)) . (strlen($texts['filename']) > 20 ? '...' : '') . 
                (isset($texts['password_hash']) && $texts['password_hash'] ? ' <i class="fas fa-lock" title="Protected"></i>' : '') . '</h4>
                <div class="file-meta">
                    <span><i class="fas fa-calendar-alt"></i> ' . $fileDate . '</span>
                    <span><i class="fas fa-file-archive"></i> ' . formatFileSize($fileSize) . '</span>
                    <span><i class="fas fa-eye"></i> ' . $texts['view_count'] . ' views</span>
                    <span><i class="fas fa-download"></i> ' . $texts['download_count'] . ' downloads</span>
                    <span><i class="fas fa-lock"></i> ' . ($texts['password_hash'] ? 'Protected' : 'None password') . '</span>
                </div>
                <div class="text-content">';
                if (isset($texts['password_hash']) && $texts['password_hash']) {
                    echo '<div class="notif"><i class="fas fa-lock"></i><p>This content is protected</p></div>';
                } else {
                    echo '<pre>' . htmlspecialchars(substr($content, 0, 100)) . (strlen($content) > 100 ? '...' : '') . '</pre>';
                }
                echo '</div>
                <div class="file-actions">
                    <a href="/?file=' . $texts['unique_id'] . '" class="btn-view"><i class="fas fa-eye"></i> View</a>
                    <a href="/?download=' . $texts['unique_id'] . '" class="btn-download"><i class="fas fa-download"></i> Download</a>
                </div>
            </div>
        </div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    endtags();
    exit();
}




function showAdminDashboard($stats) {
    print_start("dashboard admin");
    echo '<div class="admin-dashboard">
        <h2>Dashboard</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Files</h3>
                <p>' . (isset($stats['data']['total_files']) ? $stats['data']['total_files'] : '0') . '</p>
            </div>
            <div class="stat-card">
                <h3>Total Size</h3>
                <p>' . (isset($stats['data']['total_size_formatted']) ? $stats['data']['total_size_formatted'] : '0 B') . '</p>
            </div>
            <div class="stat-card">
                <h3>Total Views</h3>
                <p>' . (isset($stats['data']['total_views']) ? $stats['data']['total_views'] : '0') . '</p>
            </div>
            <div class="stat-card">
                <h3>Total Downloads</h3>
                <p>' . (isset($stats['data']['total_downloads']) ? $stats['data']['total_downloads'] : '0') . '</p>
            </div>
            <div class="stat-card">
                <h3>Today\'s Uploads</h3>
                <p>' . (isset($stats['data']['today_uploads']) ? $stats['data']['today_uploads'] : '0') . '</p>
            </div>
        </div>
    </div>';
    showAdminFiles();
    showAdminStats();
}

function showAdminFiles() {
    global $db;

    // Handle file deletion
    if (isset($_POST['delete_file']) && isset($_POST['file_id'])) {
        $fileId = (int)$_POST['file_id'];
        if (deleteFileById($fileId)) {
            echo '<div class="success">File deleted successfully!</div>';
        } else {
            echo '<div class="error">Failed to delete file.</div>';
        }
    }

    // Handle bulk actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['selected_files'])) {
        $action = $_POST['bulk_action'];
        $selectedFiles = $_POST['selected_files'];

        if ($action === 'delete' && is_array($selectedFiles)) {
            $deletedCount = 0;
            foreach ($selectedFiles as $fileId) {
                $fileId = (int)$fileId;
                if (deleteFileById($fileId)) {
                    $deletedCount++;
                }
            }
            echo '<div class="success">' . $deletedCount . ' file(s) deleted successfully.</div>';
        }
    }

    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;

    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchCondition = '';
    $searchParam = '';

    if (!empty($search)) {
        $searchCondition = "WHERE original_filename LIKE :search OR filetype LIKE :search OR ip_address LIKE :search";
        $searchParam = '%' . $search . '%';
    }

    // Get files
    $sql = "SELECT id, unique_id, original_filename, filetype, filesize, upload_timestamp, 
                         view_count, download_count, expire, is_private, ip_address 
                         FROM fileup 
                         $searchCondition
                         ORDER BY upload_timestamp DESC 
                         LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);

    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countSql = "SELECT COUNT(*) FROM fileup $searchCondition";
    $countStmt = $db->prepare($countSql);
    if (!empty($search)) {
        $countStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalFiles = $countStmt->fetchColumn();
    $totalPages = ceil($totalFiles / $limit);

    echo '<div class="admin-files">
        <h2>Manage Files</h2>
        
        <!-- Search Form -->
        <div class="search-form">
            <form method="get">
                <input type="hidden" name="admin" value="1">
                <input type="hidden" name="action" value="dashboard">
                <input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="Search files...">
                <input type="submit" value="Search">
                ' . (!empty($search) ? '<a href="/?admin&action=dashboard">Clear</a>' : '') . '
            </form>
        </div>
        
        <form method="post" id="files-form">
            <div class="bulk-actions">
                <select name="bulk_action" >
                    <option value="">-- Select Action --</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <input type="submit" value="Apply">
            </div>

            <div class="files-table">
                <table>
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Filename</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Views</th>
                            <th>Downloads</th>
                            <th>Status</th>
                            <th>IP</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($files as $file) {
        $expired = ($file['expire'] && strtotime($file['expire']) < time());
        $statusClass = $expired ? 'expired' : ($file['is_private'] ? 'private' : 'public');
        $statusText = $expired ? 'Expired' : ($file['is_private'] ? 'Private' : 'Public');

        echo '<tr class="' . $statusClass . '">
                <td><input type="checkbox" name="selected_files[]" value="' . $file['id'] . '"></td>
                <td>
                    <a href="/?file=' . $file['unique_id'] . '" target="_blank">' .
                    htmlspecialchars($file['original_filename']) . '</a>
                </td>
                <td>' . htmlspecialchars($file['filetype']) . '</td>
                <td>' . formatFileSize($file['filesize']) . '</td>
                <td>' . date('Y-m-d H:i', strtotime($file['upload_timestamp'])) . '</td>
                <td>' . $file['view_count'] . '</td>
                <td>' . $file['download_count'] . '</td>
                <td><span class="status-badge ' . $statusClass . '">' . $statusText . '</span></td>
                <td>' . htmlspecialchars($file['ip_address']) . '</td>
                <td>
                    <a href="/?file=' . $file['unique_id'] . '" target="_blank" title="View"><i class="fas fa-eye"></i></a> |
                    <a href="/?download=' . $file['unique_id'] . '" target="_blank" title="Download"><i class="fas fa-download"></i></a> |
                    <a href="/?delete=' . $file['unique_id'] . '&token=' . generateDeleteToken($file['unique_id']) . '" target="_blank" title="Delete"><i class="fas fa-trash"></i>
                    </a>
                </td>
              </tr>';
    }

    echo '</tbody>
                </table>
            </div>
        </form>';

    // Pagination
    if ($totalPages > 1) {
        echo '<div class="pagination">';
        $baseUrl = '/?action=dashboard' . (!empty($search) ? '&search=' . urlencode($search) : '');

        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? ' class="active"' : '';
            echo '<a href="' . $baseUrl . '&page=' . $i . '"' . $active . '>' . $i . '</a>';
        }
        echo '</div>';
    }

    echo '<div class="files-summary">
            <p>Showing ' . count($files) . ' of ' . $totalFiles . ' files</p>
          </div>
    </div>';
}

function showAdminStats() {
    global $db;
    
    echo '<div class="admin-stats">
        <h2>Statistics</h2>';
    
    // File type statistics
    try {
        $fileTypeStats = $db->query("
            SELECT 
                CASE 
                    WHEN filetype LIKE 'image/%' THEN 'Images'
                    WHEN filetype LIKE 'video/%' THEN 'Videos'
                    WHEN filetype LIKE 'audio/%' THEN 'Audio'
                    WHEN filetype LIKE 'text/%' THEN 'Text'
                    WHEN filetype LIKE 'application/pdf' THEN 'PDF'
                    WHEN filetype LIKE 'application/%zip%' OR filetype LIKE 'application/%rar%' THEN 'Archives'
                    ELSE 'Other'
                END as category,
                COUNT(*) as count,
                SUM(filesize) as total_size
            FROM fileup 
            GROUP BY category
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="stats-section">
            <h3>Files by Type</h3>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Count</th>
                        <th>Total Size</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($fileTypeStats as $stat) {
            echo '<tr>
                    <td>' . $stat['category'] . '</td>
                    <td>' . $stat['count'] . '</td>
                    <td>' . formatFileSize($stat['total_size']) . '</td>
                  </tr>';
        }
        
        echo '</tbody></table></div>';
        
    } catch (Exception $e) {
        echo '<div class="error">Error loading file type statistics</div>';
    }
    
    // Upload statistics by date (last 30 days)
    try {
        $uploadStats = $db->query("
            SELECT 
                DATE(upload_timestamp) as upload_date,
                COUNT(*) as daily_uploads,
                SUM(filesize) as daily_size
            FROM fileup 
            WHERE upload_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(upload_timestamp)
            ORDER BY upload_date DESC
            LIMIT 30
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="stats-section">
            <h3>Daily Uploads (Last 30 Days)</h3>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Uploads</th>
                        <th>Total Size</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($uploadStats as $stat) {
            echo '<tr>
                    <td>' . date('M j, Y', strtotime($stat['upload_date'])) . '</td>
                    <td>' . $stat['daily_uploads'] . '</td>
                    <td>' . formatFileSize($stat['daily_size']) . '</td>
                  </tr>';
        }
        
        echo '</tbody></table></div>';
        
    } catch (Exception $e) {
        echo '<div class="error">Error loading upload statistics</div>';
    }
    
    // Top IP addresses by uploads
    try {
        $ipStats = $db->query("
            SELECT 
                ip_address,
                COUNT(*) as upload_count,
                SUM(filesize) as total_size,
                MAX(upload_timestamp) as last_upload
            FROM fileup 
            GROUP BY ip_address
            ORDER BY upload_count DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="stats-section">
            <h3>Top IP Addresses</h3>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Uploads</th>
                        <th>Total Size</th>
                        <th>Last Upload</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($ipStats as $stat) {
            echo '<tr>
                    <td>' . htmlspecialchars($stat['ip_address']) . '</td>
                    <td>' . $stat['upload_count'] . '</td>
                    <td>' . formatFileSize($stat['total_size']) . '</td>
                    <td>' . date('M j, Y H:i', strtotime($stat['last_upload'])) . '</td>
                  </tr>';
        }
        
        echo '</tbody></table></div>';
        
    } catch (Exception $e) {
        echo '<div class="error">Error loading IP statistics</div>';
    }
    
    // Most viewed/downloaded files
    try {
        $popularFiles = $db->query("
            SELECT 
                original_filename,
                unique_id,
                view_count,
                download_count,
                upload_timestamp
            FROM fileup 
            ORDER BY (view_count + download_count) DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="stats-section">
            <h3>Most Popular Files</h3>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Views</th>
                        <th>Downloads</th>
                        <th>Total</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($popularFiles as $file) {
            $total = $file['view_count'] + $file['download_count'];
            echo '<tr>
                    <td><a href="/?file=' . $file['unique_id'] . '" target="_blank">' . 
                    htmlspecialchars($file['original_filename']) . '</a></td>
                    <td>' . $file['view_count'] . '</td>
                    <td>' . $file['download_count'] . '</td>
                    <td><strong>' . $total . '</strong></td>
                    <td>' . date('M j, Y', strtotime($file['upload_timestamp'])) . '</td>
                  </tr>';
        }
        
        echo '</tbody></table></div>';
        
    } catch (Exception $e) {
        echo '<div class="error">Error loading popular files</div>';
    }
    
    // System health check
    echo '<div class="stats-section">
        <h3>System Health</h3>
        <div class="health-check">
            <p><strong>Upload Directory:</strong> ' . 
            (is_writable('./upload/') ? '<span class="status-ok">Writable</span>' : '<span class="status-error">Not Writable</span>') . '</p>
            <p><strong>Thumbnail Directory:</strong> ' . 
            (is_writable('./upload/thumbnails/') ? '<span class="status-ok">Writable</span>' : '<span class="status-error">Not Writable</span>') . '</p>
            <p><strong>GD Extension:</strong> ' . 
            (extension_loaded('gd') ? '<span class="status-ok">Loaded</span>' : '<span class="status-error">Not Loaded</span>') . '</p>
            <p><strong>PDO Extension:</strong> ' . 
            (extension_loaded('pdo') ? '<span class="status-ok">Loaded</span>' : '<span class="status-error">Not Loaded</span>') . '</p>
            <p><strong>File Info Extension:</strong> ' . 
            (extension_loaded('fileinfo') ? '<span class="status-ok">Loaded</span>' : '<span class="status-error">Not Loaded</span>') . '</p>
        </div>
    </div>';
    
    echo '</div>';
}

function deleteFileById($fileId) {
    global $db;
    
    try {
        // Get file info
        $stmt = $db->prepare("SELECT fileloc, thumbnail_path FROM fileup WHERE id = :id");
        $stmt->bindParam(':id', $fileId);
        $stmt->execute();
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // Delete file from filesystem
            if ($file['fileloc'] && file_exists($file['fileloc'])) {
                unlink($file['fileloc']);
            }
            
            if ($file['thumbnail_path'] && file_exists($file['thumbnail_path'])) {
                unlink($file['thumbnail_path']);
            }
            
            // Delete from database
            $deleteStmt = $db->prepare("DELETE FROM fileup WHERE id = :id");
            $deleteStmt->bindParam(':id', $fileId);
            $deleteStmt->execute();
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Failed to delete file: " . $e->getMessage());
        return false;
    }
}

// Utility functions
function formatFileSize($size) {
    if ($size === null) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;
    
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    
    return round($size, 2) . ' ' . $units[$unitIndex];
}

function logFileAccess($fileId, $accessType) {
    global $db;
    
    try {
        $stmt = $db->prepare("INSERT INTO access_logs (file_id, ip_address, access_type, user_agent) VALUES (:file_id, :ip, :type, :user_agent)");
        $stmt->bindParam(':file_id', $fileId);
        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':type', $accessType);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        $stmt->execute();
    } catch (Exception $e) {
        // Silently fail to avoid breaking file access
        error_log("Failed to log file access: " . $e->getMessage());
    }
}

function cleanupExpiredFile($file) {
    global $db;
    
    // Delete file from filesystem
    if (file_exists($file['fileloc'])) {
        unlink($file['fileloc']);
    }
    
    if ($file['thumbnail_path'] && file_exists($file['thumbnail_path'])) {
        unlink($file['thumbnail_path']);
    }
    
    // Delete from database
    $deleteStmt = $db->prepare("DELETE FROM fileup WHERE id = :id");
    $deleteStmt->bindParam(':id', $file['id']);
    $deleteStmt->execute();
}


function showpage($showwhat = 'text') {
    global  $db;
 print_start("Uploader");

 
    $uploadstat = getSystemStats();
echo '<div class="complex-header">
    <div class="header-content">
        <h1 class="main-title">
            <i class="fas fa-cloud-upload-alt"></i> Anonymous File Uploader
        </h1>
        <div class="header-subtitle">
            <p>Secure, private file sharing with no JavaScript required</p>
            <div class="header-features">
                <span><i class="fas fa-lock"></i> Encrypted Uploads</span>
                <span><i class="fas fa-bolt"></i> Fast Transfers</span>
                <span><i class="fas fa-expand"></i> No Size Limits (Limits tor 10mb)</span>
            </div>
        </div>
        <div class="header-notice">
            <i class="fas fa-bug"></i>
            <p>Found a bug? Please report to <a href="mailto:idrift@dnmx.su">idrift@dnmx.su</a></p>
        </div>
    </div>
    <div class="header-decoration">
        <div class="decoration-line"></div>
        <div class="decoration-dots">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>';

  
    echo'
    <div class="kontainer">';

    echo' <form method="post" action="" class="selector-form">
                    <label for="showwhat">Select Upload Type:</label>
                    
                    <select name="showwhat" id="showwhat" >
                        <option value="text" '. ($showwhat === 'text' ? 'selected' : '') .'>Text Upload</option>
                        <option value="file"'. ($showwhat === 'file' ? 'selected' : '') .'>File Upload</option>
                        <option value="filebul" '. ($showwhat === 'filebul' ? 'selected' : '') .'>Bulk File Upload</option>
                    </select>
                    <button type="submit" >
                        <i class="fa fa-refresh fa-spin fa-fw"></i>
                        Switch
                    </button>
                </form>';
     
    if ($showwhat === 'text') {
        textup();
    } elseif ($showwhat === 'file') {
        uploadfile();
    }elseif($showwhat==="filebul"){
        bulkup();
    }
  
           
    if (!empty($uploadstat)) {
        echo '<div class="stats-grid">';

            echo '
                   <div class="stat-card">
                    <h3>Total Uploads</h3>
                    <p>' . htmlspecialchars($uploadstat['data']['total_files']??"0") . '</p>
                  </div>
                   <div class="stat-card">
                    <h3>Total Views</h3>
                    <p>' . htmlspecialchars($uploadstat['data']['total_views']??"0" ).' </p>
                  </div>
                     <div class="stat-card">
                    <h3>Total Downloads</h3>
                    <p>' . htmlspecialchars($uploadstat['data']['total_downloads']??"0") .' </p>
                  </div>
                  <div class="stat-card">
                    <h3>Daily Size</h3>
                    <p>' . round($uploadstat['data']['total_size'] / 1024 / 1024, 2) . ' MB</p>
                  </div>';

      
    }
        echo '</div>';
    

  endtags();
}

function logout() {
    global $db;
    
    // Destroy all session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Update last logout time in database if admin
    if (isset($_SESSION['admin_id'])) {
        try {
            $stmt = $db->prepare("UPDATE admin_users SET last_logout = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['admin_id']);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Logout timestamp update failed: " . $e->getMessage());
        }
    }
    
 
    // Redirect to home page
    header("Location: /");
    exit();
}
?>