<?php
function routeenfile() {
    global $result;
    $path = $_SERVER['REQUEST_URI'];
$showwhat = isset($_POST['showwhat']) ? $_POST['showwhat'] : 'text';
    if(superadmincheck()){
        // login page route admin
    if ($_GET['action']=="admin") {
        showAdminPanel();
        return;
    }
    // menangani post 
    if(isset($_POST)){
    if (isset($_POST['uploadfile']) && isset($_FILES['fileToUpload'])) {
    handleFileUpload();
    }else if (isset($_POST['textcontent']) && isset($_POST['textcontent'])) {
    handleTextUpload();
    }else if (isset($_POST['bulk_submit']) && isset($_FILES['bulkFiles'])) {
    handleBulkUpload();
}
    }
    //menangani get
    if(isset($_GET)){
    if (isset($_GET['delete']) && isset($_GET['token'])) {
        handleFileDeletion($_GET['delete'], $_GET['token']);
        return;
    }elseif (isset($_GET['file'])) {
        displayFile($_GET['file']);
        return;
    }else if (isset($_GET['download'])) {
        downloadFile($_GET['download']);
        return;
    }else if ($_GET['action']=="gallery") {
        showGallery();
        return;
    }
    }
    // Default route (upload page)
    if ($path == '/' || $path == '/index.php') {
        showpage($showwhat);
    }}else{
        if(isset($_POST['action']) && $_POST['action']=="adminregis"){
            adminregis();
        }else{
        regisadminform();
        }   
    }
}
function api(){
    
}