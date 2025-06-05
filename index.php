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
// API endpoint handler
if (isset($_GET['api'])) {
    handleAPI();
    exit;
}


function print_start(string $title) {
    global $errors;
    // Ubah ke huruf kecil dan pecah berdasarkan spasi
    $dann = explode(" ", strtolower($title));

    // Ambil kata pertama untuk kelas, jika kosong gunakan default
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
 if (!empty($errors)) {
        echo '<div class="errors">';
        foreach ($errors as $error) {
            echo '<div class="error">' . htmlspecialchars($error) . '</div>';
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


// Enhanced file upload handler
function handleFileUpload() {
    global $db, $result, $errors;

    $ip = $_SERVER['REMOTE_ADDR'];
    
    $location = './upload/';
    $thumbLocation = './upload/thumbnails/';
    if(!is_dir($location)){
       mkdir($location,0775,true );
    }
    if (!is_dir($location)) mkdir($location, 0775, true);
    if (!is_dir($thumbLocation)) mkdir($thumbLocation, 0775, true);

    $targetFile = $_FILES["fileToUpload"];

    if (!validateFile($targetFile)) {
        $result = "Invalid file upload.";
        return false;
    }

    $tmpfileloc = $targetFile['tmp_name'];
    $originalName = $targetFile['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $hash = hash('sha256', $originalName . microtime() . rand());
    $uniqueName = time() . '_' . substr($hash, 0, 16) . '.' . $extension;
    $finalPath = $location . $uniqueName;
    $thumbnailPath = null;

    if (!move_uploaded_file($tmpfileloc, $finalPath)) {
        $errors[] = "Failed to upload file: " . $originalName;
        $result = "Failed to upload file: " . $originalName;
        return false;
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $thumbnailPath = $thumbLocation . 'thumb_' . $uniqueName;
        if (!generateThumbnail($finalPath, $thumbnailPath, $extension)) {
            $errors[] = "Failed to generate thumbnail for: " . $originalName;
        }
    }

    $expire = isset($_POST['expire']) ? (int)$_POST['expire'] : 0;
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $isPrivate = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 0;
    $maxDownloads = isset($_POST['max_downloads']) ? (int)$_POST['max_downloads'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    $expireDate = $expire > 0 ? date('Y-m-d H:i:s', strtotime("+{$expire} days")) : null;
    $uniqueId = generateSecureId($originalName . time());
    $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

    try {
        $stmt = $db->prepare("INSERT INTO fileup (filehash, filename, original_filename, filetype, filesize, fileloc, thumbnail_path, expire,upload_timestamp, unique_id, ip_address, password_hash, is_private, max_downloads, description, user_agent) 
                              VALUES (:filehash, :filename, :original_filename, :filetype, :filesize, :fileloc, :thumbnail_path, :expire, NOW(), :unique_id, :ip_address, :password_hash, :is_private, :max_downloads, :description, :user_agent)");

        $stmt->bindParam(':filehash', $hash);
        $stmt->bindParam(':filename', $uniqueName);
        $stmt->bindParam(':original_filename', $originalName);
        $stmt->bindParam(':filetype', $targetFile["type"]);
        $stmt->bindParam(':filesize', $targetFile["size"]);
        $stmt->bindParam(':fileloc', $finalPath);
        $stmt->bindParam(':thumbnail_path', $thumbnailPath);
        $stmt->bindParam(':expire', $expireDate);
        $stmt->bindParam(':unique_id', $uniqueId);
        $stmt->bindParam(':ip_address', $ip);
        $stmt->bindParam(':password_hash', $passwordHash);
        $stmt->bindParam(':is_private', $isPrivate);
        $stmt->bindParam(':max_downloads', $maxDownloads);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);

        $stmt->execute();

        $statsStmt = $db->prepare("INSERT INTO upload_stats (ip_address, upload_time,file_size ) VALUES (:ip, NOW(), :size)");
        $statsStmt->bindParam(':ip', $ip);
        $statsStmt->bindParam(':size', $targetFile["size"]);
        $statsStmt->execute();

   $result = '
<div class="upload-success">
    <h3>File uploaded successfully!</h3>

 
        <p><strong>File URL:</strong></p>
        <input type="text" value="' . $_SERVER['HTTP_HOST'] . '/?file=' . $uniqueId . '" readonly />
        <p style="margin-top: 15px;">
            <button type="submit" formaction="/?file=' . $uniqueId . '" formtarget="_blank" style="margin-right:10px; padding:8px 16px;">🔗 View File</button>
            <button type="submit" formaction="/?delete=' . $uniqueId . '&token=' . generateDeleteToken($uniqueId) . '" formtarget="_blank" style="padding:8px 16px;">🗑️ Delete File</button>
        </p>

</div>';
$_SESSION['upload_result'] = $result;
header("Location: ?file=$uniqueId");
return true;


    } catch (PDOException $e) {
        $errors[] = "Database error for file " . $originalName . ": " . $e->getMessage();
        $result = "Database error: " . $e->getMessage();

        if (file_exists($finalPath)) unlink($finalPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) unlink($thumbnailPath);

        return false;
    }
}

// Bulk upload handler
function handleBulkUpload() {
    global $result, $errors;
    
    $files = $_FILES['bulkFiles'];
    $uploadedFiles = [];
    $failedFiles = [];
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $_FILES['fileToUpload'] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $oldResult = $result;
            $oldErrors = $errors;
            
            handleFileUpload();
            
            if (empty($errors) || count($errors) == count($oldErrors)) {
                $uploadedFiles[] = $files['name'][$i];
            } else {
                $failedFiles[] = $files['name'][$i];
            }
        } else {
            $failedFiles[] = $files['name'][$i];
        }
    }
    
    $result = '<div class="bulk-upload-result">
        <h3>Bulk Upload Complete</h3>
        <p><strong>Uploaded:</strong> ' . count($uploadedFiles) . ' files</p>
        <p><strong>Failed:</strong> ' . count($failedFiles) . ' files</p>
    </div>';
}

// Enhanced file validation
function validateFile($file) {
    global $errors;
    
    // Check file size
    if ($file["size"] > MAX_FILE_SIZE) {
        $errors[] = "File is too large. Maximum size is " . formatFileSize(MAX_FILE_SIZE);
        return false;
    }
    
    // Check for upload errors
    if ($file["error"] !== UPLOAD_ERR_OK) {
        $errors[] = getUploadErrorMessage($file["error"]);
        return false;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "File type not allowed. Allowed types: " . implode(', ', ALLOWED_EXTENSIONS);
        return false;
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 
        'application/pdf', 'text/plain', 
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4', 'audio/mpeg', 
        'application/zip', 'application/x-rar-compressed'
    ];
    
    if (!in_array($mimeType, $allowedMimes)) {
        $errors[] = "Invalid file type detected";
        return false;
    }
    
    return true;
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

// API handler
function handleAPI() {
    global $db;
    header('Content-Type: application/json');
    
    $action = $_GET['api'];
    $response = ['success' => false, 'message' => 'Invalid API action'];
    
    switch ($action) {
        case 'stats':
            $response = getSystemStats();
            break;
        case 'search':
            $query = $_GET['q'] ?? '';
            $response = searchFiles($query);
            break;
        case 'file_info':
            $fileId = $_GET['id'] ?? '';
            $response = getFileInfo($fileId);
            break;
    }
    
    echo json_encode($response);
}

// Get system statistics

function getSystemStats() {
    global $db;
    
    try {
        $totalFiles = $db->query("SELECT COUNT(*) FROM fileup")->fetchColumn();
        $totalSize = $db->query("SELECT SUM(filesize) FROM fileup")->fetchColumn();
        $totalViews = $db->query("SELECT SUM(view_count) FROM fileup")->fetchColumn();
        $totalDownloads = $db->query("SELECT SUM(download_count) FROM fileup")->fetchColumn();
        $todayUploads = $db->query("SELECT COUNT(*) FROM fileup WHERE DATE(upload_timestamp) = CURDATE()")->fetchColumn();
        
        return [
            'success' => true,
            'data' => [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
                'total_size_formatted' => formatFileSize($totalSize),
                'total_views' => $totalViews,
                'total_downloads' => $totalDownloads,
                'today_uploads' => $todayUploads
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error'];
    }
}

// Search files
function searchFiles($query) {
    global $db;
    
    if (empty($query)) {
        return ['success' => false, 'message' => 'Query parameter required'];
    }
    
    try {
        $stmt = $db->prepare("SELECT unique_id, original_filename, filetype, filesize, upload_timestamp, view_count 
                             FROM fileup 
                             WHERE (original_filename LIKE :query OR description LIKE :query) 
                             AND is_private = FALSE 
                             AND (expire IS NULL OR expire > NOW()) 
                             ORDER BY upload_timestamp DESC 
                             LIMIT 20");
        $searchQuery = '%' . $query . '%';
        $stmt->bindParam(':query', $searchQuery);
        $stmt->execute();
        
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $files
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Search failed'];
    }
}

// Enhanced text upload
function handleTextUpload() {
    global $db, $result, $errors;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    

    
    $textContent = $_POST['textcontent'];
    
    if (strlen($textContent) > MAX_FILE_SIZE) {
        $errors[] = "Text is too long. Maximum size is " . formatFileSize(MAX_FILE_SIZE);
        return;
    }
    
    $location = './upload/';
    if (!is_dir($location)) mkdir($location, 0775, true);
    
    $uniqueId = generateSecureId('text_' . time());
    $filename = "text_" . $uniqueId . ".txt";
    $filepath = $location . $filename;
    
    if (file_put_contents($filepath, $textContent) === false) {
        $errors[] = "Failed to save text file";
        return;
    }
    
    // Get additional form data
    $expire = isset($_POST['expire']) ? (int)$_POST['expire'] : 0;
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $isPrivate = $_POST['is_private'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    
    $expireDate = null;
    if ($expire > 0) {
        $expireDate = date('Y-m-d H:i:s', strtotime("+{$expire} days"));
    }
    
    $textHash = hash('sha256', $textContent);
    $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    try {
        $stmt = $db->prepare("INSERT INTO fileup (filehash, filename, original_filename, filetype, filesize, fileloc, expire, unique_id, ip_address, password_hash, is_private, description, user_agent) 
                             VALUES (:filehash, :filename, :original_filename, :filetype, :filesize, :fileloc, :expire, :unique_id, :ip_address, :password_hash, :is_private,  :description, :user_agent)");
        
        $filetype = "text/plain";
        $filesize = strlen($textContent);
        
        $stmt->bindParam(':filehash', $textHash);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':original_filename', $filename);
        $stmt->bindParam(':filetype', $filetype);
        $stmt->bindParam(':filesize', $filesize);
        $stmt->bindParam(':fileloc', $filepath);
        $stmt->bindParam(':expire', $expireDate);
        $stmt->bindParam(':unique_id', $uniqueId);
        $stmt->bindParam(':ip_address', $ip);
        $stmt->bindParam(':password_hash', $passwordHash);
        $stmt->bindParam(':is_private', $isPrivate);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        
        $stmt->execute();
        
        // Log upload statistics
        $statsStmt = $db->prepare("INSERT INTO upload_stats (ip_address, file_size) VALUES (:ip, :size)");
        $statsStmt->bindParam(':ip', $ip);
        $statsStmt->bindParam(':size', $filesize);
        $statsStmt->execute();
        
        $result = '<div class="upload-success">
            <h3>Text uploaded successfully!</h3>
            <p><strong>Text URL:</strong> <a href="/?file=' . $uniqueId . '">View Text</a></p>
            <p><strong>Direct Link:</strong> <input type="text" value="' . $_SERVER['HTTP_HOST'] . '/?file=' . $uniqueId . '" readonly " /></p>
        </div>';

    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
        if (file_exists($filepath)) unlink($filepath);
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

// admin validate
function adminregis(){
global $db, $errors;
$username = $_POST['username'];
$password = password_hash($_POST['password'],PASSWORD_DEFAULT);
try{
$sql = "INSERT INTO admin_users(username,password_hash) VALUES(:username, :password_hash)";
$stmt = $db->prepare($sql);
$stmt->bindParam(":username",$username);
$stmt->bindParam(":password_hash",$password);
$stmt->execute();
showpage();

}catch(PDOException $e){
      $errors[] = 'Error register' . $e->getMessage();

       regisadminform();
}
}

function displayFile($fileId) {
    global $db;
    
    // Check for password protection
    if (isset($_POST['password_submit'])) {
        $password = $_POST['file_password'];
        $stmt = $db->prepare("SELECT password_hash FROM fileup WHERE unique_id = :unique_id");
        $stmt->bindParam(':unique_id', $fileId);
        $stmt->execute();
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && password_verify($password, $file['password_hash'])) {
            $_SESSION['authenticated_files'][$fileId] = true;
        } else {
            showPasswordPrompt($fileId, "Incorrect password");
            return;
        }
    }
    
    // Get file from database
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        die("<div class='error'>File not found</div>");
    }
    
    // Check password protection
    if ($file['password_hash'] && !isset($_SESSION['authenticated_files'][$fileId])) {
        showPasswordPrompt($fileId);
        return;
    }
    
    // Check file existence
    if (!file_exists($file['fileloc'])) {
        die("<div class='error'>File not found on server</div>");
    }
    
    // Check expiration
    if ($file['expire'] !== null && strtotime($file['expire']) < time()) {
        cleanupExpiredFile($file);
        die("<div class='error'>This file has expired</div>");
    }
    
    // Update view count and log access
    $updateStmt = $db->prepare("UPDATE fileup SET view_count = view_count + 1 WHERE id = :id");
    $updateStmt->bindParam(':id', $file['id']);
    $updateStmt->execute();
    
    logFileAccess($file['id'], 'view');
    
    // Display file preview page
    showFilePreview($file);
}


// Continuation of the file preview function
function showFilePreview($file) {
print_start("Preview file");
    echo '
        <div class="file-preview">';
        if (isset($_SESSION['upload_result'])) {
    echo $_SESSION['upload_result'];
    unset($_SESSION['upload_result']);
}echo'
            <h1>File Preview</h1>
            <div class="file-info">
                <h2>' . htmlspecialchars($file['original_filename']) . '</h2>
                <p><strong>Size:</strong> ' . formatFileSize($file['filesize']) . '</p>
                <p><strong>Type:</strong> ' . htmlspecialchars($file['filetype']) . '</p>
                <p><strong>Uploaded:</strong> ' . date('Y-m-d H:i:s', strtotime($file['upload_timestamp'])) . '</p>
                <p><strong>Views:</strong> ' . $file['view_count'] . '</p>
                <p><strong>Downloads:</strong> ' . $file['download_count'] . '</p>';
    
    if ($file['description']) {
        echo '<p><strong>Description:</strong> ' . htmlspecialchars($file['description']) . '</p>';
    }
    
  
    
    if ($file['expire']) {
        echo '<p><strong>Expires:</strong> ' . date('Y-m-d H:i:s', strtotime($file['expire'])) . '</p>';
    }
    
    echo '</div>';
    
    // Display file content based on type
    $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        // Image preview
        echo '<div class="image-preview">
                <img src="' . $file['fileloc'] . '" alt="' . htmlspecialchars($file['original_filename']) . '" style="max-width: 100%; height: auto;">
              </div>';
        
       
    } elseif ($extension === 'txt') {
        // Text preview
        $content = file_get_contents($file['fileloc']);
        echo '<div class="text-preview">
                <pre>' . htmlspecialchars($content) . '</pre>
              </div>';
    } elseif (in_array($extension, ['mp4', 'webm', 'ogg'])) {
        // Video preview
        echo '<div class="video-preview">
                <video controls style="max-width: 100%; height: auto;">
                    <source src="' . $file['fileloc'] . '" type="' . $file['filetype'] . '">
                    Your browser does not support the video tag.
                </video>
              </div>';
    } elseif (in_array($extension, ['mp3', 'wav', 'ogg'])) {
        // Audio preview
        echo '<div class="audio-preview">
                <audio controls>
                    <source src="' . $file['fileloc'] . '" type="' . $file['filetype'] . '">
                    Your browser does not support the audio element.
                </audio>
              </div>';
    } elseif ($extension === 'pdf') {
        // PDF preview
        echo '<div class="pdf-preview">
                <embed src="' . $file['fileloc'] . '" type="application/pdf" width="100%" height="600px">
              </div>';
    }
    
    echo '<div class="file-actions">
            <a href="/?download=' . $file['unique_id'] . '" class="btn btn-primary">Download File</a>
            <a href="/" class="btn btn-secondary">Back to Home</a>
          </div>
        </div>';
        endtags();
    exit();
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
        die("Invalid delete token");
    }
    
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        die("File not found");
    }
    
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
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>File Deleted - EnfileUp</title>
        <link rel="stylesheet" href="/resource/style/global.css">
    </head>
    <body>
        <div class="success">
            <h1>File Deleted Successfully</h1>
            <p>The file "' . htmlspecialchars($file['original_filename']) . '" has been deleted.</p>
            <p><a href="/">Back to Home</a></p>
        </div>
    </body>
    </html>';
    exit();
}

// Gallery view
function showGallery() {
    global $db;
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Get public image files
    $stmt = $db->prepare("SELECT unique_id, original_filename, thumbnail_path, filesize, upload_timestamp, view_count 
                         FROM fileup 
                         WHERE filetype LIKE 'image/%' 
                         AND is_private = 0 
                         AND (expire IS NULL OR expire > NOW()) 
                         ORDER BY upload_timestamp DESC 
                         LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    // Get total count for pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM fileup 
                              WHERE filetype LIKE 'image/%' 
                              AND is_private = 0 
                              AND (expire IS NULL OR expire > NOW())");
    $countStmt->execute();
    $totalImages = $countStmt->fetchColumn();
    $totalPages = ceil($totalImages / $limit);
    print_start("Gallery");
    echo'<div class="gallery">';
    
    foreach ($images as $image) {
        $thumbnailSrc = $image['thumbnail_path'] ?: '/upload/' . $image['unique_id'];
        echo '<div class="gallery-item">
                <a href="/?file=' . $image['unique_id'] . '">
                    <img src="' . $thumbnailSrc . '" alt="' . htmlspecialchars($image['original_filename']) . '">
                </a>
                <div class="gallery-item-info">
                    <h4>' . htmlspecialchars($image['original_filename']) . '</h4>
                    <p>Size: ' . formatFileSize($image['filesize']) . '</p>
                    <p>Views: ' . $image['view_count'] . '</p>
                    <p>Uploaded: ' . date('M j, Y', strtotime($image['upload_timestamp'])) . '</p>
                </div>
              </div>';
    }
    
    echo '</div>';
       
    // Pagination
    if ($totalPages > 1) {
        echo '<div class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? ' style="background-color: #007cba; color: white;"' : '';
            echo '<a href="/?gallery&page=' . $i . '"' . $active . '>' . $i . '</a>';
        }
        echo '</div>';
    }
    
    endtags();

    exit();
}

// Admin panel
function showAdminPanel() {
    global $db ,$errors;
    
    // Simple authentication check
    if (!isset($_SESSION['admin_logged_in'])) {
        if (isset($_POST['admin_login'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            $stmt = $db->prepare("SELECT id, password_hash FROM admin_users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                
                // Update last login
                $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
                $updateStmt->bindParam(':id', $admin['id']);
                $updateStmt->execute();
            } else {
                      $errors[] = "invalid credentials";

            }
        }
        
        if (!isset($_SESSION['admin_logged_in'])) {
            showAdminLogin();
            return;
        }
    }
    
    // Admin dashboard
    $stats = getSystemStats();
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Admin Panel - EnfileUp</title>
        <link rel="stylesheet" href="/resource/style/global.css">
    </head>
    <body>
        <h1>Admin Panel</h1>
        <nav>
            <a href="/?admin&action=dashboard">Dashboard</a> |
            <a href="/?admin&action=files">Manage Files</a> |
            <a href="/?admin&action=stats">Statistics</a> |
            <a href="/?admin&action=logout">Logout</a>
        </nav>';
    
    
    
    echo '</body></html>';
    exit();
}



function showAdminDashboard($stats) {
    echo '<div class="admin-dashboard">
        <h2>Dashboard</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Files</h3>
                <p>' . $stats['data']['total_files'] . '</p>
            </div>
            <div class="stat-card">
                <h3>Total Size</h3>
                <p>' . $stats['data']['total_size_formatted'] . '</p>
            </div>
            <div class="stat-card">
                <h3>Total Views</h3>
                <p>' . $stats['data']['total_views'] . '</p>
            </div>
            <div class="stat-card">
                <h3>Total Downloads</h3>
                <p>' . $stats['data']['total_downloads'] . '</p>
            </div>
            <div class="stat-card">
                <h3>Today\'s Uploads</h3>
                <p>' . $stats['data']['today_uploads'] . '</p>
            </div>
        </div>
    </div>';
}

function showAdminFiles() {
    global $db;
    
    // Handle file deletion
    if (isset($_POST['delete_file'])) {
        $fileId = $_POST['file_id'];
        deleteFileById($fileId);
        echo '<div class="success">File deleted successfully!</div>';
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
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $searchCondition = '';
    $searchParam = '';
    
 
    // Get files
    $stmt = $db->prepare("SELECT id, unique_id, original_filename, filetype, filesize, upload_timestamp, 
                         view_count, download_count, expire, is_private, ip_address 
                         FROM fileup 
                         $searchCondition
                         ORDER BY upload_timestamp DESC 
                         LIMIT :limit OFFSET :offset");
    
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM fileup $searchCondition");
    if (!empty($search)) {
        $countStmt->bindParam(':search', $searchParam);
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
                <input type="hidden" name="action" value="files">
                <input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="Search files...">
                <input type="submit" value="Search">
                ' . (!empty($search) ? '<a href="/?admin&action=files">Clear</a>' : '') . '
            </form>
        </div>
        
       <form method="post" id="files-form">
    <div class="bulk-actions">
        <select name="bulk_action" required>
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
                    <a href="/?file=' . $file['unique_id'] . '" target="_blank">View</a> |
                    <a href="/?download=' . $file['unique_id'] . '" target="_blank">Download</a> |
                    <button type="submit" name="delete_file" value="1"  
                            style="background:none;border:none;color:red;cursor:pointer;">Delete</button>
                    <input type="hidden" name="file_id" value="' . $file['id'] . '">
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
        $baseUrl = '/?admin&action=files' . (!empty($search) ? '&search=' . urlencode($search) : '');
        
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

function getFileInfo($fileId) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT unique_id, original_filename, filetype, filesize, upload_timestamp, view_count, download_count 
                             FROM fileup 
                             WHERE unique_id = :unique_id AND is_private = FALSE");
        $stmt->bindParam(':unique_id', $fileId);
        $stmt->execute();
        
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            return [
                'success' => true,
                'data' => $file
            ];
        } else {
            return ['success' => false, 'message' => 'File not found or private'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error'];
    }
}

function showpage($showwhat = 'text') {
    global  $db;
 print_start("Uploader");

 
    $uploadstat = getSystemStats();


  
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
                        <i class="fas fa-sync-alt"></i>
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
                    <p>' . htmlspecialchars($uploadstat['data']['total_files']) . '</p>
                  </div>
                   <div class="stat-card">
                    <h3>Total Views</h3>
                    <p>' . htmlspecialchars($uploadstat['data']['total_views'] ).' </p>
                  </div>
                     <div class="stat-card">
                    <h3>Total Downloads</h3>
                    <p>' . htmlspecialchars($uploadstat['data']['total_downloads']) .' </p>
                  </div>
                  <div class="stat-card">
                    <h3>Daily Size</h3>
                    <p>' . round($uploadstat['data']['total_size'] / 1024 / 1024, 2) . ' MB</p>
                  </div>';

      
    }
        echo '</div>';
    

  endtags();
}


?>