<?php
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
// file info 

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