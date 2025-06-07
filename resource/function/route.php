<?php
function routeenfile() {
    global $result;
    $uploadstat = getSystemStats();
    $path = $_SERVER['REQUEST_URI'];
    $showwhat = $_POST['showwhat'] ?? 'text';
    
    // Check if super admin exists
    if (superadmincheck()) {
        // Handle POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['uploadfile']) && isset($_FILES['fileToUpload'])) {
                handleFileUpload();
                return;
            } elseif (isset($_POST['textcontent'])) {
                handleTextUpload();
                return;
            } elseif (isset($_POST['bulk_submit']) && isset($_FILES['bulkFiles'])) {
                handleBulkUpload();
                return;
            } elseif (isset($_POST['admin_login'])) {
                validatelogin();
                return;
            }elseif(isset($_POST['passwordpro'])){
                passwordcheck($_GET['file']);
                return true;
            }
        }
        
        // Handle GET requests with search
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
            $query = $_GET['search'];
            $response = searchFiles($query);
            if ($_SERVER['HTTP_ACCEPT'] === 'application/json') {
                echo json_encode($response);
            } else {
                header('Location: /?admin=1&action=dashboard&search=' . urlencode($query));
                exit;
            }
            return;
        }
        
        // Admin Dashboard
        if (isset($_SESSION['admin_logged_in']) && ($_GET['action'] ?? '') === 'dashboard') {
            showAdminDashboard($uploadstat);
            return;
        }
        
        // Handle other GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (isset($_GET['delete'], $_GET['token'])) {
                handleFileDeletion($_GET['delete'], $_GET['token']);
                return;
            } elseif (isset($_GET['file'])) {
                displayFile($_GET['file']);
                return;
            } elseif (isset($_GET['download'])) {
                downloadFile($_GET['download']);
                return;
            } elseif (($_GET['action'] ?? '') === 'gallery') {
                showGallery();
                return;
            } elseif (isset($_GET['bulks'])) {
                bulkdisplay($_GET['bulks']);
                return;
            } elseif (($_GET['action'] ?? '') === 'loginmin' && empty($_SESSION['user_id'])) {
                showAdminLogin();
                return;
            } elseif (isset($_GET['bulkdellkun']) && isset($_GET['token'])) {
                deleteBulkFiles($_GET['bulkdellkun'], $_GET['token']);
                return;
            } elseif (($_GET['action'] ?? '') === 'files' && isset($_SESSION['admin_logged_in'])) {
                showAdminFiles();
                return;
            } elseif (isset($_GET['action']) && $_GET['action'] === 'logout') {
                logout();
                return;
            }
        }
        
        // Default route (upload page)
        if ($path === '/' || $path === '/index.php') {
            showpage($showwhat);
            return;
        }
        
    } else {
        // Handle admin registration when no super admin exists
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adminregis') {
            adminregis();
            return;
        } else {
            regisadminform();
            return;
        }
    }
}