<?php
// preview singgle file

function displayFile($fileId) {
    global $db;

    // Always fetch file info first
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        die("<div class='error'>File not found</div>");
    }

    // Handle password submission
    

    // Check password protection (after possible unlock above)
    if (!empty($file['password_hash']) && !isset($_SESSION['authenticated_files'][$fileId])) {
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

    showFilePreview($file);
}
function passwordcheck($fileId){
    global $db;
    $stmt = $db->prepare("SELECT * FROM fileup WHERE unique_id = :unique_id");
    $stmt->bindParam(':unique_id', $fileId);
    $stmt->execute();
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

        $password = isset($_POST['file_password']) ? $_POST['file_password'] : '';
        // Use the hash from the file we just fetched
        if (!empty($file['password_hash']) && password_verify($password, $file['password_hash'])) {
            $_SESSION['authenticated_files'][$fileId] = true;
            displayFile($fileId);
        } else {
            showPasswordPrompt($fileId, "Incorrect password");
        }
        return true;
 
}
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
                <p><strong>Downloads:</strong> ' . $file['download_count'] . '</p>
                ';
                
    
    if ($file['description']) {
        echo '<p><strong>Description:</strong> ' . htmlspecialchars($file['description']) . '</p>';
    }
    
  
    
    if ($file['expire']) {
        echo '<p><strong>Expires:</strong> ' . date('Y-m-d H:i:s', strtotime($file['expire'])) . '</p>';
    }else{
        echo '<p><strong>Expires:</strong>Never</p>';
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
    
    echo '    <div class="file-actions">
                        <a href="/?download='.($file['unique_id']).'" class="btn btn-secondary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                  
                        <a href="/" class="btn btn-secondary btn-sm">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
        </div>';
        endtags();
    exit();
}
// preview bulk file
// Function to delete bulk files by bulk ID
function deleteBulkFiles($bulkId, $token) {
    global $db, $errors;

    // Validate bulk ID and token
    if (empty($bulkId) || empty($token)) {
        $errors[] = "Invalid bulk ID or token provided";
        showBulkDeleteResult(false, "Invalid bulk ID or token provided");
        return false;
    }

    try {
        // Get all files in the bulk
        $stmt = $db->prepare("SELECT * FROM fileup WHERE id_bulk = :bulk_id");
        $stmt->bindParam(':bulk_id', $bulkId, PDO::PARAM_STR);
        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($files)) {
            $errors[] = "No files found with this bulk ID";
            showBulkDeleteResult(false, "No files found with this bulk ID");
            return false;
        }

        // Verify delete token for the first file (all files in bulk should have same access)
        $validToken =   generateBulkDeleteToken($bulkId);
        if (!hash_equals($validToken, $token)) {
            $errors[] = "Invalid delete token";
            showBulkDeleteResult(false, "Invalid delete token");
            return false;
        }

        // Delete each file physically and its thumbnail if exists
        $deletedFiles = [];
        $failedDeletions = [];
        foreach ($files as $file) {
            // Delete physical file
            if (file_exists($file['fileloc'])) {
                if (!unlink($file['fileloc'])) {
                    $errors[] = "Failed to delete file: " . $file['original_filename'];
                    $failedDeletions[] = $file['original_filename'];
                } else {
                    $deletedFiles[] = $file['original_filename'];
                }
            }

            // Delete thumbnail if exists
            if (!empty($file['thumbnail_path']) && file_exists($file['thumbnail_path'])) {
                unlink($file['thumbnail_path']);
            }
        }

        // Delete all files from database in one query
        $deleteStmt = $db->prepare("DELETE FROM fileup WHERE id_bulk = :bulk_id");
        $deleteStmt->bindParam(':bulk_id', $bulkId, PDO::PARAM_STR);
        $deleteStmt->execute();

        // Prepare success/failure message
        $message = '';
        if (!empty($deletedFiles)) {
            $message .= '<div class="success"><h3>Successfully deleted files:</h3><ul>';
            foreach ($deletedFiles as $fname) {
                $message .= '<li>' . htmlspecialchars($fname) . '</li>';
            }
            $message .= '</ul></div>';
        }
        
        if (!empty($failedDeletions)) {
            $message .= '<div class="error"><h3>Failed to delete files:</h3><ul>';
            foreach ($failedDeletions as $fname) {
                $message .= '<li>' . htmlspecialchars($fname) . '</li>';
            }
            $message .= '</ul></div>';
        }

        if (empty($deletedFiles) && empty($failedDeletions)) {
            $message = '<div class="warning"><p>No files were deleted (files may not exist on server).</p></div>';
        }

        showBulkDeleteResult(true, $message);
        return [
            'success' => true,
            'message' => $message
        ];

    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
        showBulkDeleteResult(false, "Database error occurred: " . $e->getMessage());
        return false;
    }
}

function showBulkDeleteResult($success, $message) {
    print_start(" Delete - " . ($success ? 'Success' : 'Error'));
    echo '
        <div style="max-width: 800px; margin: 2rem auto; padding: 2rem; background: var(--bg-secondary); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); border-left: 4px solid ' . ($success ? 'var(--accent-primary)' : 'var(--danger)') . ';">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="font-size: 2.5rem; color: ' . ($success ? 'var(--accent-primary)' : 'var(--danger)') . ';">
                    <i class="fas ' . ($success ? 'fa-check-circle' : 'fa-times-circle') . '"></i>
                </div>
                <h1 style="font-size: 1.8rem; font-weight: 600; color: var(--text-primary); margin: 0;">Delete ' . ($success ? 'Successful' : 'Failed') . '</h1>
            </div>
            
            <div style="background: var(--bg-tertiary); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                ' . $message . '
            </div>
            
            <div style="display: block; justify-content: space-between; align-items: center; margin-top: 2rem;">
                <a href="/" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: var(--accent-primary); color: white; text-decoration: none; border-radius: var(--radius-md); font-weight: 500; transition: all 0.2s ease;">
                    <i class="fas fa-home"></i> Back to Home
                </a>
           
                <div style="display: block; align-items: center; gap: 0.5rem; color: var(--text-secondary);">
                    <i class="fas fa-clock"></i>
                    <span>Redirecting in <span style="font-weight: 600; color: var(--text-primary);">5</span> seconds...</span>
                </div>
            </div>
            <meta http-equiv="refresh" content="5; url=/" />
        </div>';
    endtags();
    exit();
}


function bulkdisplay($bulkid) {
    global $db ,$error,$result;
    if(isset($_SESSION['upload_result'])){
        echo $_SESSION['upload_result'];
        unset($_SESSION['upload_result']);
    }
    // Validate bulk ID
    if (empty($bulkid) || !is_string($bulkid)) {
        $error[]=("Invalid bulk ID provided");
        return;
    }
    
    try {
        // Ambil semua file dalam bulk
        $stmt = $db->prepare("SELECT * FROM fileup WHERE id_bulk = :unique_id ORDER BY upload_timestamp DESC");
        $stmt->bindParam(':unique_id', $bulkid, PDO::PARAM_STR);
        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$files || count($files) === 0) {
            $error[]=("No files found in this bulk upload");
            return;
        }
        
        // Ambil hash password dari salah satu file sebagai acuan
        $passwordHash = $files[0]['password_hash'];
        
        // Cek password jika ada proteksi
        if (!empty($passwordHash) && !isset($_SESSION['authenticated_files'][$bulkid])) {
            if (isset($_POST['password_submit'])) {
                $password = $_POST['file_password'] ?? '';
                if (!empty($password) && password_verify($password, $passwordHash)) {
                    $_SESSION['authenticated_files'][$bulkid] = true;
                } else {
                    showPasswordPrompt($bulkid, "Incorrect password. Please try again.");
                    return;
                }
            } else {
                showPasswordPrompt($bulkid);
                return;
            }
        }
        
        // Hapus file yang expired atau tidak ada secara fisik
        $validFiles = [];
        foreach ($files as $file) {
            // Cek apakah file masih ada
            if (!file_exists($file['fileloc'])) {
                // Log missing file
                error_log("Missing file: " . $file['fileloc']);
                continue;
            }
            
            // Cek apakah file expired
            if (!empty($file['expire']) && strtotime($file['expire']) < time()) {
                cleanupExpiredFile($file);
                continue;
            }
            
            // Update view count untuk bulk
            $updateStmt = $db->prepare("UPDATE fileup SET view_count = view_count + 1 WHERE id = :id");
            $updateStmt->bindParam(':id', $file['id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            // Log file access
            logFileAccess($file['id'], 'bulk_view');
            $validFiles[] = $file;
        }
        
        if (empty($validFiles)) {
            $error[]=("All files in this bulk upload are expired or no longer available");
            return;
        }
        
        previewbulk($validFiles, $bulkid);
        
    } catch (PDOException $e) {
        error_log("Database error in bulkdisplay: " . $e->getMessage());
        $error[]=("Database error occurred. Please try again later.");
        return;
    } catch (Exception $e) {
        error_log("Error in bulkdisplay: " . $e->getMessage());
        $error[]=("An error occurred while loading the bulk upload.");
        return;
    }
}

function previewbulk($files, $bulkid) {
    $totalFiles = count($files);
    $totalSize = array_sum(array_column($files, 'filesize'));
    $uploadDate = $files[0]['upload_timestamp'];

    print_start("Bulk File Preview - " . $totalFiles . " Files");
    ?>
    
    <div class="bulk-container">
        <div class="bulk-header">
            <div class="file-preview">
                <h1>
                    <i class="fas fa-box-open"></i> Bulk Upload Preview
                </h1>
            </div>
            <div class="bulk-stats">
                <div class="stat-item">
                    <span class="stat-label"><i class="fas fa-file"></i> Files:</span>
                    <span class="stat-number"><?php echo $totalFiles; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><i class="fas fa-database"></i> Size:</span>
                    <span class="stat-number"><?php echo formatFileSize($totalSize); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><i class="fas fa-calendar-alt"></i> Uploaded:</span>
                    <span class="stat-number"><?php echo date('M j, Y', strtotime($uploadDate)); ?></span>
                </div>
            </div>
            <div class="coppy">
                <i class="fas fa-copy"></i>
                <input type="text" value="<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/?bulks=<?php echo urlencode($bulkid); ?>" readonly />
            </div>
        </div>
        
        <div class="bulk-file-grid">
            <?php
            // Helper: list of image extensions
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'jfif'];
            foreach ($files as $index => $file):
                $ext = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
                $isImage = in_array($ext, $imageExtensions);
                // Thumbnail path: if available, use $file['thumbnail'] else fallback to fileloc for image
                $thumbnailUrl = '';
                if ($isImage) {
                    // If you have a thumbnail path in DB, use it, else use the file itself
                    if (!empty($file['thumbnail']) && file_exists($file['thumbnail'])) {
                        $thumbnailUrl = '/' . ltrim($file['thumbnail'], '/');
                    } else {
                        $thumbnailUrl = '/' . ltrim($file['fileloc'], '/');
                    }
                }
            ?>
                <div class="file-card" data-file-type="<?php echo $ext; ?>">
                    <?php if ($isImage): ?>
                        <div class="file-thumbnail" style="text-align:center; margin-bottom:10px;">
                            <a href="/?file=<?php echo urlencode($file['unique_id']); ?>" target="_blank">
                                <img 
                                    src="<?php echo htmlspecialchars($thumbnailUrl); ?>" 
                                    alt="<?php echo htmlspecialchars($file['filename']); ?>" 
                                    style="max-width:90px; max-height:90px; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,0.12); object-fit:cover; background:#222;"
                                    loading="lazy"
                                />
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="file-icon" style="text-align:center; margin-bottom:10px;">
                            <i class="icon-file"></i>
                        </div>
                    <?php endif; ?>
                    <div class="file-info">
                        <h3 class="file-name" title="<?php echo htmlspecialchars($file['filename']); ?>">
                            <?php echo htmlspecialchars(truncateFileName($file['filename'], 30)); ?>
                        </h3>
                        
                        <div class="file-meta">
                            <span class="file-size"><?php echo formatFileSize($file['filesize']); ?></span>
                            <span class="file-type"><?php echo strtoupper($ext); ?></span>
                        </div>
                        
                        <div class="file-details">
                            <div class="detail-row">
                                <span class="detail-label">Views:</span>
                                <span class="detail-value"><?php echo number_format($file['view_count']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Uploaded:</span>
                                <span class="detail-value"><?php echo date('M j, Y H:i', strtotime($file['upload_timestamp'])); ?></span>
                            </div>
                            <?php if (!empty($file['expire'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Expires:</span>
                                <span class="detail-value expire-date"><?php echo date('M j, Y H:i', strtotime($file['expire'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="file-actions">
                        <a href="/?file=<?php echo urlencode($file['unique_id']); ?>" class="btn btn-primary btn-sm" target="_blank">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="/?download=<?php echo urlencode($file['unique_id']); ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php
    endtags();
    exit();
}
function truncateFileName($filename, $maxLength = 30) {
    if (strlen($filename) <= $maxLength) {
        return $filename;
    }
    
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    
    $maxBasename = $maxLength - strlen($extension) - 4; 
    
    return substr($basename, 0, $maxBasename) . '...' . $extension;
}
// textt

