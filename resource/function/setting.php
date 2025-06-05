<?php
$target = dirname(__DIR__,2);

// Cek apakah config sudah ada
if (!is_writable($target)) {
    die("Folder tidak bisa ditulis. Silakan ubah permission folder.");
}else{
if (file_exists($target. '/config.php')) {
    require_once $target . '/config.php';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_db'])) {
    // Proses form input dan simpan ke config.php
    $host = trim($_POST['dbhost']);
    $name = trim($_POST['dbname']);
    $user = trim($_POST['dbuser']);
    $pass = trim($_POST['dbpass']);

    try {
        $db = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Simpan ke config.php
        $configTemplate = file_get_contents($target. '/config-template.php');
        $configContent = str_replace(
            ['{{DBHOST}}', '{{DBNAME}}', '{{DBUSER}}', '{{DBPASS}}'],
            [$host, $name, $user, $pass],
            $configTemplate
        );
        file_put_contents($target . '/config.php', "$configContent");

        echo "<p style='color:green;'>Database connection successful. Reloading...</p>";
        header("Refresh:1"); 
        exit;

    } catch (PDOException $e) {
        showDbSetupForm("Connection failed: " . $e->getMessage());
        exit;
    }
} else {
    // Tampilkan form jika belum ada config
    showDbSetupForm();
    exit;
}

try {
    $db = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBNAME, DBUSER, DBPASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    showDbSetupForm("Connection failed: " . $e->getMessage());
    exit;
}
}

function showDbSetupForm($error = '') {
    echo "<style>
        form { max-width: 400px; margin: 50px auto; font-family: sans-serif; }
        input { width: 100%; padding: 10px; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 15px; }
    </style>";

    echo "<form method='POST'>
        <h2>Database Setup</h2>";
    if ($error) echo "<div class='error'>$error</div>";

    echo "<input type='text' name='dbhost' placeholder='Database Host' required />
          <input type='text' name='dbname' placeholder='Database Name' required />
          <input type='text' name='dbuser' placeholder='Database User' required />
          <input type='password' name='dbpass' placeholder='Database Password' />
          <input type='submit' name='setup_db' value='Connect' />
    </form>";
}
// cekkk apakah ada admin atau nggak 

function superadmincheck(){
    global $db;
    $sql="SELECT COUNT(*) as total FROM admin_users";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $total = $stmt->Fetch(PDO::FETCH_ASSOC);
     return $total['total'] == 1;
}
// setup database
function setupDatabase() {
    global $db;
    
    // Main files table
    $query1 = "CREATE TABLE IF NOT EXISTS fileup (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filehash VARCHAR(255) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        filetype VARCHAR(100) NOT NULL,
        filesize INT,
        fileloc VARCHAR(255) NOT NULL,
        thumbnail_path VARCHAR(255) DEFAULT NULL,
        expire DATETIME DEFAULT NULL,
        upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        view_count INT DEFAULT 0,
        download_count INT DEFAULT 0,
        unique_id VARCHAR(64) NOT NULL,
        ip_address VARCHAR(45),
        password_hash VARCHAR(255) DEFAULT NULL,
        is_private BOOLEAN DEFAULT 0,
        max_downloads INT DEFAULT 0,
        description TEXT DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        INDEX(unique_id),
        INDEX(expire),
        INDEX(upload_timestamp)
    )";

    $query2 = "CREATE TABLE IF NOT EXISTS upload_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        file_size INT,
        INDEX(ip_address, upload_time)
    )";

    $query3 = "CREATE TABLE IF NOT EXISTS access_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT,
        ip_address VARCHAR(45),
        access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        access_type ENUM('view', 'download') NOT NULL,
        user_agent TEXT,
        FOREIGN KEY (file_id) REFERENCES fileup(id) ON DELETE CASCADE
    )";

    $query4 = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    )";

    try {
        $db->exec($query1);
        $db->exec($query2);
        $db->exec($query3);
        $db->exec($query4);
              
        
        return true;
    } catch (PDOException $e) {
        echo "<div class='error'>Error creating tables: " . $e->getMessage() . "</div>";
        return false;
    }
}

// setting file upload
define('MAX_FILE_SIZE', 100 * 1024 * 1024);
define('CHUNK_SIZE', 8192); 
define('THUMBNAIL_SIZE', 300);
define('MAX_DOWNLOADS', 1000); 
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'mp4', 'mp3', 'zip', 'rar']);
