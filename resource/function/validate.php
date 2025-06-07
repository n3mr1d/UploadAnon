<?php
// handle single upload
function handleFileUpload($idBulk = "") {
    global $db, $result, $errors;

    $ip = $_SERVER['REMOTE_ADDR'];

    $location = './upload/';
    $thumbLocation = './upload/thumbnails/';

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
        $stmt = $db->prepare("INSERT INTO fileup (filehash, filename, original_filename, filetype, filesize, fileloc, thumbnail_path, expire, upload_timestamp, unique_id, ip_address, password_hash, is_private, max_downloads, description, user_agent, id_bulk) 
            VALUES (:filehash, :filename, :original_filename, :filetype, :filesize, :fileloc, :thumbnail_path, :expire, NOW(), :unique_id, :ip_address, :password_hash, :is_private, :max_downloads, :description, :user_agent, :id_bulk)");

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
        $stmt->bindParam(':id_bulk', $idBulk);

        $stmt->execute();

        $statsStmt = $db->prepare("INSERT INTO upload_stats (ip_address, upload_time, file_size) VALUES (:ip, NOW(), :size)");
        $statsStmt->bindParam(':ip', $ip);
        $statsStmt->bindParam(':size', $targetFile["size"]);
        $statsStmt->execute();

        if (!empty($idBulk)) {
            $result = '
            <div class="upload-success">
                <h3>File uploaded successfully!</h3>
                <p><strong>File URL:</strong></p>
                <input type="text" value="' . $_SERVER['HTTP_HOST'] . '/?bulks=' . $idBulk . '" readonly />
                <p style="margin-top: 15px;">
                    <a href="/?bulks=' . $idBulk . '" target="_blank" style="margin-right:10px; padding:8px 16px;"><i class="fas fa-link"></i> View File</a>
                    <a href="/?bulkdellkun=' . $idBulk . '&token=' . generateBulkDeleteToken($idBulk) . '" target="_blank" style="padding:8px 16px;"><i class="fas fa-trash-alt"></i> Delete File</a>
                </p>
            </div>';
            $_SESSION['upload_result'] = $result;
            header("Location: ?bulks=$idBulk");
            return true;
        } else {
            $result = '
            <div class="upload-success">
                <h3>File uploaded successfully!</h3>
                <p><strong>File URL:</strong></p>
                <input type="text" value="' . $_SERVER['HTTP_HOST'] . '/?file=' . $uniqueId . '" readonly />
                <p style="margin-top: 15px;">
                    <a href="/?file=' . $uniqueId . '" target="_blank" style="margin-right:10px; padding:8px 16px;"><i class="fas fa-eye"></i> View File</a>
                    <a href="/?delete=' . $uniqueId . '&token=' . generateDeleteToken($uniqueId) . '" target="_blank" style="padding:8px 16px;"><i class="fas fa-trash-alt"></i> Delete File</a>
                </p>

            </div>';
            $_SESSION['upload_result'] = $result;
            header("Location: ?file=$uniqueId");
            return true;
        }

    } catch (PDOException $e) {
        $errors[] = "Database error for file " . $originalName . ": " . $e->getMessage();
        $result = "Database error: " . $e->getMessage();

        if (file_exists($finalPath)) unlink($finalPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) unlink($thumbnailPath);

        return false;
    }
}
// Generate delete token for bulk files
function generateBulkDeleteToken($bulkId) {
    return hash('sha256', $bulkId . $_SERVER['HTTP_USER_AGENT'] . date('Y-m-d'));
}

// handle upload file plural multiple 
function handleBulkUpload() {
    global $result, $errors;
    
    $files = $_FILES['bulkFiles'];
    $uploadedFiles = [];
    $failedFiles = [];
    
    // Validate maximum file count
    if (count($files['name']) > 6) {
        $_SESSION["errors"] = "Maximum 6 files allowed for bulk upload";
        header("Location: /");
        exit();
    }
    
    // Generate unique bulk ID
    $bulkid = bin2hex(random_bytes(16)); // Generate 32-character hexadecimal unique ID
    
    // Process each file
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            // Prepare file data for single upload
            $_FILES['fileToUpload'] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            // Store current state
            $oldResult = $result;
            $oldErrors = $errors;
            
            // Process single file
            handleFileUpload($bulkid);
            
            // Track success/failure
            if (empty($errors) || count($errors) == count($oldErrors)) {
                $uploadedFiles[] = $files['name'][$i];
            } else {
                $failedFiles[] = $files['name'][$i];
            }
        } else {
            $failedFiles[] = $files['name'][$i];
        }
    }
    
    // Generate result message
    $result = '<div class="bulk-upload-result">
        <h3>Bulk Upload Complete</h3>
        <p><strong>Successfully Uploaded:</strong> ' . count($uploadedFiles) . ' files</p>
        <p><strong>Failed Uploads:</strong> ' . count($failedFiles) . ' files</p>';
        
    // Add failed files list if any
    if (!empty($failedFiles)) {
        $result .= '<p><strong>Failed Files:</strong></p><ul>';
        foreach ($failedFiles as $file) {
            $result .= '<li>' . htmlspecialchars($file) . '</li>';
        }
        $result .= '</ul>';
    }
    
    $result .= '</div>';
}

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
// admin validate login page
function validatelogin() {
    global $db;

    try {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = $_POST['password'] ?? '';

        // Simpan username agar tetap muncul di form setelah gagal login
        $_SESSION['login_username'] = $username;

        if (empty($username) || empty($password)) {
            $_SESSION['login_errors'][] = "Username and password are required.";
            return false;
        }

        $stmt = $db->prepare("SELECT id, password_hash FROM admin_users WHERE username = :username LIMIT 1");
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

            header("Location: /?action=dashboard");
            exit;
        } else {
            $_SESSION['login_errors'][] = "Invalid credentials.";
            header("Location: /?action=loginmin");

        }

    } catch (PDOException $e) {
        $_SESSION['login_errors'][] = "Database error. Please try again later.";
        // Optional: error_log($e->getMessage());
        return false;
    }
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
// file txt upload 
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
    $isPrivate = isset($_POST['is_private']) ? $_POST['is_private'] : 0;
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    
    $expireDate = null;
    if ($expire > 0) {
        $expireDate = date('Y-m-d H:i:s', strtotime("+{$expire} days"));
    }
    
    $textHash = hash('sha256', $textContent);
    $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    try {
        $stmt = $db->prepare("INSERT INTO fileup (filehash, filename, original_filename, filetype, filesize, fileloc, expire, unique_id, ip_address, password_hash, is_private, description, user_agent) 
                             VALUES (:filehash, :filename, :original_filename, :filetype, :filesize, :fileloc, :expire, :unique_id, :ip_address, :password_hash, :is_private, :description, :user_agent)");
        
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
                <p><strong>Text URL:</strong></p>
                <input type="text" value="' . $_SERVER['HTTP_HOST'] . '/?file=' . $uniqueId . '" readonly />
                <p style="margin-top: 15px;">
                    <a href="/?file=' . $uniqueId . '" target="_blank" style="margin-right:10px; padding:8px 16px;"><i class="fas fa-eye"></i> View Text</a>
                    <a href="/?delete=' . $uniqueId . '&token=' . generateDeleteToken($uniqueId) . '" target="_blank" style="padding:8px 16px;"><i class="fas fa-trash-alt"></i> Delete Text</a>
                </p>
            </div>';

        $_SESSION['upload_result'] = $result;
        header("Location: ?file=$uniqueId");
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
        if (file_exists($filepath)) unlink($filepath);
    }
}

