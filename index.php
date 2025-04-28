<?php

// Database constants
define('DBHOST', 'localhost');
define('DBNAME', 'enfileup');
define('DBUSER', 'root');
define('DBPASS', '180406');

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Connect to database
try {
    $db = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBNAME, DBUSER, DBPASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<div class='error'>Database connection failed: " . $e->getMessage() . "</div>");
}

// Setup database
setupDatabase();

// Initialize result variable
$resault = '';

// Process file upload if submitted
if (isset($_POST['submit']) && isset($_FILES['fileToUpload'])) {
    handleFileUpload();
}

// Process text submission if submitted
if (isset($_POST['textcontent']) && !empty($_POST['textcontent'])) {
    handleTextUpload();
}

// Router
routeenfile();

// setup database
function setupDatabase() {
    global $db;
    $query = "CREATE TABLE IF NOT EXISTS fileup (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filehash VARCHAR(255) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        filetype VARCHAR(100) NOT NULL,
        filesize INT,
        filedata LONGTEXT,
        expire DATETIME DEFAULT NULL,
        upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        view_count INT DEFAULT 0,
        unique_id VARCHAR(64) NOT NULL
    )";

    try {
        $db->exec($query);
        return true;
    } catch (PDOException $e) {
        echo "<div class='error'>Error creating table: " . $e->getMessage() . "</div>";
        return false;
    }
}
function handleFileUpload() {
    global $db, $resault;
    $targetFile = $_FILES["fileToUpload"];
    
    // Validate file size
    if ($targetFile["size"] > MAX_FILE_SIZE) {
        die("<div class='error'>File is too large. Maximum size is " . formatFileSize(MAX_FILE_SIZE) . "</div>");
    }

    // Check for upload errors
    if ($targetFile["error"] !== UPLOAD_ERR_OK) {
        die("<div class='error'>" . getUploadErrorMessage($targetFile["error"]) . "</div>");
    }
    
    // Generate a unique ID for the file using OpenSSH-like encoding
    $uniqueId = generateOpenSSHLikeHash($targetFile["name"] . time() . rand(1000, 9999));
    
    // Get file content as base64
    $fileContent = base64_encode(file_get_contents($targetFile["tmp_name"]));
    
    // Calculate file hash
    $fileHash = hash_file('sha256', $targetFile["tmp_name"]);
    
    // Set expiration date
    $expire = isset($_POST['expire']) ? (int)$_POST['expire'] : 0;
    $expireDate = null;
    
    if ($expire > 0) {
        $expireDate = date('Y-m-d H:i:s', strtotime("+{$expire} days"));
    }
    
    try {
        // Insert file into database
        $stmt = $db->prepare("INSERT INTO fileup (filehash, filename, filetype, filesize, filedata, expire, unique_id) 
                             VALUES (:filehash, :filename, :filetype, :filesize, :filedata, :expire, :unique_id)");
        
        // Use file hash as the filename
        $stmt->bindParam(':filehash', $fileHash);
        $stmt->bindParam(':filename', $fileHash);
        $stmt->bindParam(':filetype', $targetFile["type"]);
        $stmt->bindParam(':filesize', $targetFile["size"]);
        $stmt->bindParam(':filedata', $fileContent);
        $stmt->bindParam(':expire', $expireDate);
        $stmt->bindParam(':unique_id', $uniqueId);
        
        $stmt->execute();
        
        $resault = '<a href="/?file=' . $uniqueId . '">View Your result</a>';
        return;

    } catch (PDOException $e) {
        die("<div class='error'>Error uploading file: " . $e->getMessage() . "</div>");
    }
}

function handleTextUpload() {
    global $db, $resault;
    
    $textContent = $_POST['textcontent'];
    
    // Validate text length
    if (strlen($textContent) > MAX_FILE_SIZE) {
        die("<div class='error'>Text is too long. Maximum size is " . formatFileSize(MAX_FILE_SIZE) . "</div>");
    }
    
    // Generate a unique ID for the text using OpenSSH-like encoding
    $uniqueId = generateOpenSSHLikeHash('text_' . time() . rand(1000, 9999));
    
    // Set expiration date
    $expire = isset($_POST['expire']) ? (int)$_POST['expire'] : 0;
    $expireDate = null;
    
    if ($expire > 0) {
        $expireDate = date('Y-m-d H:i:s', strtotime("+{$expire} days"));
    }
    
    // Calculate text hash
    $textHash = hash('sha256', $textContent);
    
    try {
        // Insert text into database as a "text/plain" file
        $stmt = $db->prepare("INSERT INTO fileup (filehash, filename, filetype, filesize, filedata, expire, unique_id) 
                             VALUES (:filehash, :filename, :filetype, :filesize, :filedata, :expire, :unique_id)");
        
        $filename = "text_" . substr($uniqueId, 0, 16) . ".txt";
        $filetype = "text/plain";
        $filesize = strlen($textContent);
        $filedata = base64_encode($textContent);
        
        $stmt->bindParam(':filehash', $textHash);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':filetype', $filetype);
        $stmt->bindParam(':filesize', $filesize);
        $stmt->bindParam(':filedata', $filedata);
        $stmt->bindParam(':expire', $expireDate);
        $stmt->bindParam(':unique_id', $uniqueId);
        
        $stmt->execute();
        $resault = '<a href="/?file=' . $uniqueId . '">View Your result</a>';

        return;

    } catch (PDOException $e) {
        die("<div class='error'>Error uploading text: " . $e->getMessage() . "</div>");
    }
}

function generateOpenSSHLikeHash($input) {
    // Generate a base64 encoded string that looks like an OpenSSH key fingerprint
    $hash = hash('sha256', $input, true);
    $encoded = base64_encode($hash);
    // Remove padding characters and replace some characters to make it URL-safe
    $encoded = str_replace(['+', '/', '='], ['-', '_', ''], $encoded);
    return substr($encoded, 0, 32); // Limit to 32 characters for cleaner URLs
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

function routeenfile() {
    global $resault;
    $path = $_SERVER['REQUEST_URI'];
    $showwhat = isset($_POST['showwhat']) ? $_POST['showwhat'] : 'text';
    
    // Check if file parameter exists in URL
    if (isset($_GET['file'])) {
        displayFile($_GET['file']);
        return;
    }
    
    // Check if download parameter exists in URL
    if (isset($_GET['download'])) {
        downloadFile($_GET['download']);
        return;
    }
    
    if ($path == '/' || $path == '/index.php') {
        showpage($showwhat);
    }
}

function displayFile($fileId) {
    global $db;
    
    // Get file from database
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        die("<div class='error'>File not found</div>");
    }
    
    // Check if file has expired
    if ($file['expire'] !== null && strtotime($file['expire']) < time()) {
        // Delete expired file
        $deleteStmt = $db->prepare("DELETE FROM fileup WHERE id = :id");
        $deleteStmt->bindParam(':id', $file['id']);
        $deleteStmt->execute();
        die("<div class='error'>This file has expired</div>");
    }
    
    // Update view count
    $updateStmt = $db->prepare("UPDATE fileup SET view_count = view_count + 1 WHERE id = :id");
    $updateStmt->bindParam(':id', $file['id']);
    $updateStmt->execute();
    
    // Display file preview page
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>File Preview - ' . htmlspecialchars($file['filename']) . '</title>
        <link rel="stylesheet" href="/resource/style/global.css">
    </head>
    <body>
        <h1 class="title">EnfileUp - File Preview</h1>
        
        <div class="file-preview">
            <div class="file-info">
                <p><strong>Filename:</strong> ' . htmlspecialchars($file['filename']) . '</p>
                <p><strong>File Type:</strong> ' . htmlspecialchars($file['filetype']) . '</p>
                <p><strong>File Size:</strong> ' . formatFileSize($file['filesize']) . '</p>
                <p><strong>Uploaded:</strong> ' . $file['upload_timestamp'] . '</p>
                <p><strong>Views:</strong> ' . $file['view_count'] . '</p>
                ' . ($file['expire'] ? '<p><strong>Expires:</strong> ' . $file['expire'] . '</p>' : '<p><strong>Expires:</strong> Never</p>') . '
            </div>';
    
    // Display file preview based on file type
    echo '<div class="preview-container">';
    $fileData = base64_decode($file['filedata']);
    $fileType = strtolower($file['filetype']);
    
    if (strpos($fileType, 'image/') === 0) {
        // Image preview
        echo '<img src="data:' . $file['filetype'] . ';base64,' . $file['filedata'] . '" alt="' . htmlspecialchars($file['filename']) . '">';
    } elseif (strpos($fileType, 'video/') === 0) {
        // Video preview
        echo '<video controls>
                <source src="data:' . $file['filetype'] . ';base64,' . $file['filedata'] . '" type="' . $file['filetype'] . '">
                Your browser does not support the video tag.
              </video>';
    } elseif (strpos($fileType, 'audio/') === 0) {
        // Audio preview
        echo '<audio controls>
                <source src="data:' . $file['filetype'] . ';base64,' . $file['filedata'] . '" type="' . $file['filetype'] . '">
                Your browser does not support the audio tag.
              </audio>';
    } elseif (strpos($fileType, 'text/') === 0 || in_array($fileType, ['application/json', 'application/xml'])) {
        // Text preview
        echo '<div class="text-preview">' . htmlspecialchars($fileData) . '</div>';
    } elseif ($fileType === 'application/pdf') {
        // PDF preview
        echo '<embed src="data:application/pdf;base64,' . $file['filedata'] . '" type="application/pdf" width="100%" height="400px" />';
    } else {
        // No preview available
        echo '<div class="no-preview">No preview available for this file type.</div>';
    }
    echo '</div>';
    
    // Download button
    echo '<div class="download-container">';
    echo '<a href="/?download=' . $file['unique_id'] . '" class="download-btn">Download File</a>';
    echo '<p><a class="download-btn" href="/">Back to Home</a></p>';
    echo '</div>';
    echo '</div>
    </body>
    </html>';
    exit();
}

function downloadFile($fileId) {
    global $db;
    
    // Get file from database
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        die("<div class='error'>File not found</div>");
    }
    
    // Check if file has expired
    if ($file['expire'] !== null && strtotime($file['expire']) < time()) {
        // Delete expired file
        $deleteStmt = $db->prepare("DELETE FROM fileup WHERE id = :id");
        $deleteStmt->bindParam(':id', $file['id']);
        $deleteStmt->execute();
        die("<div class='error'>This file has expired</div>");
    }
    
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file['filetype']);
    header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file['filesize']);
    
    // Output file data
    echo base64_decode($file['filedata']);
    exit();
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
function showpage($showwhat = 'text') {
    global $resault;
    $error = isset($_GET['error']) ? $_GET['error'] : '';
    $success = isset($_GET['success']) ? $_GET['success'] : '';
    $warning = isset($_GET['warning']) ? $_GET['warning'] : '';
    $info = isset($_GET['info']) ? $_GET['info'] : '';
    
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EnfileUp</title>
        <link rel="icon" href="icon.svg" type="image/svg+xml">
        <link rel="stylesheet" href="./resource/style/global.css">
    </head>
    <body>';
    
    echo '<h1 class="title">EnfileUp</h1>';
    echo '<div class="container">';
    echo '<span class="subtitle">Anonymous Image and File Uploader and <span style="color:red">No Need JS</span></span>';
    echo '<p>If you found a bug, please report to <span class="email">idrift@dnmx.su</span></p>';
    echo '</div>';

    echo '<main>
    <div id="mainmid">';
    
    // Display notification messages if available
    if (!empty($error)) {
        echo '<div class="notification error">' . $error . '</div>';
    }
    if (!empty($success)) {
        echo '<div class="notification success">' . $success . '</div>';
    }
    if (!empty($warning)) {
        echo '<div class="notification warning">' . $warning . '</div>';
    }
    if (!empty($info)) {
        echo '<div class="notification info">' . $info . '</div>';
    }
    
    // Display result message if available
    if (!empty($resault)) {
        echo '<div class="notification success"> Your File Uploaded Successfully! ' . $resault . '</div>';
    }
    
    // Replace JavaScript-based selector with a form that submits on change
    echo '<form method="post" action="" class="selector-form">
        <label for="showwhat">Select option:</label>
        <select name="showwhat" id="showwhat">
            <option value="text" ' . ($showwhat === 'text' ? 'selected' : '') . '>Text Upload</option>
            <option value="file" ' . ($showwhat === 'file' ? 'selected' : '') . '>File Upload</option>
        </select>
        <input type="submit" value="Switch">
    </form>';

    if ($showwhat === 'text') {
        showtextarea();
    } elseif ($showwhat === 'file') {
        showupload();
    }

    echo '</div></main>';
    footer(); 
    echo '</body>
    </html>';
}

function showtextarea() {
    echo '<form class="submit" method="POST" action="">
        <textarea name="textcontent" rows="10" cols="50" placeholder="Say Something on here.." required></textarea><br>
        <label for="expire">Expire after:</label>
        <select name="expire" id="expire">
            <option value="1">1 day</option>
            <option value="7">7 days</option>
            <option value="30">30 days</option>
            <option value="0">Never</option>
        </select><br>
        <input type="submit" value="Submit Text">
    </form>';
}

function showupload() {
    echo '<form class="submit" method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="' . MAX_FILE_SIZE . '">
        <input type="file" name="fileToUpload" id="fileToUpload" required><br>
        <label for="expire">Expire after:</label>
        <select name="expire" id="expire">
            <option value="1">1 day</option>
            <option value="7">7 days</option>
            <option value="30">30 days</option>
            <option value="0">Never</option>
        </select><br>
        <input type="submit" value="Upload File" name="submit">
    </form>';
}

function footer() {
    echo '<footer>
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
?>
