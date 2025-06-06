<?php
// admin login page
function showAdminLogin() {
    print_start("Login Admin");
    echo '
    <div class="kontainer-login">
        <h1>Admin Login</h1>
        <form method="post">
        <input type="hidden" name="admin_login">  
        <div>
                <label for="username">Username:</label>
                <input type="text" name="username" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        </div>';
        endtags();
    exit();
}


// admin register
function regisadminform(){
 
    print_start("Super Admin Register");

echo'<div class="kontainer-register">

    <h3> Register Admin</h3> 
    <form method="POST">
    <div class="group-form">
    <input type="hidden" name="action" value="adminregis">
    <label for="username">Username:</label>
    <input type="text" name="username">
    </div>
    <br>
    <div class="group-form">
    <label for="password">Password:</label>
    <input type="password" name="password">
    </div>
    <br>
    <button class="button-register" type="submit">Register</button>
    </form>
</div>';

endtags();
}
//password form 
function showPasswordPrompt($fileId, $error = null) {
    print_start("Password Required");
    echo '
        <h1 class="title">Password Protected File</h1>
        <div class="password-form">
            ' . ($error ? '<div class="error">' . $error . '</div>' : '') . '
            <form method="post">
                <label for="file_password">Enter Password:</label>
                <input type="password" name="file_password" required>
                <button type="submit" name="password_submit" value="Access File">
                    <i class="fas fa-lock"></i>
                    Access File
                </button>
            </form>
            <p><a href="/">Back to Home</a></p>
        </div>';
        endtags();
    exit();
}
// function show upload file
function uploadfile() {
    echo '<div id="file-upload" class="upload-section" >
                    <form action="" class="konntainer-upload" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="uploadfile" value="action">
                        <h2><i class="fas fa-file-upload"></i> Upload File</h2>
                        <div class="form-options">
                            <div class="form-group">
                                <label for="fileToUpload">Choose File:</label>
                                <input type="file" name="fileToUpload" id="fileToUpload" required>
                                <div class="file-info">
                                    <p><i class="fas fa-info-circle"></i> <strong>Info:</strong> Maximum file size: 100MB. Supported formats: All file types allowed.</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expire-file">Expiration:</label>
                                    <select name="expire" id="expire-file">
                                        <option value="0">Never Expires</option>
                                        <option value="1">1 Day</option>
                                        <option value="7">1 Week</option>
                                        <option value="30">1 Month</option>
                                        <option value="365">1 Year</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-file">Password (optional):</label>
                                    <input type="password" name="password" id="password-file" placeholder="Leave blank for no password">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_downloads">Max Downloads:</label>
                                <input type="number" name="max_downloads" id="max_downloads" value="0" min="0" placeholder="0 = unlimited">
                                <small>Set to 0 for unlimited downloads</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description-file">Description:</label>
                                <textarea name="description" id="description-file" rows="3" placeholder="Description for your file"></textarea>
                            </div>
                                                                                        <label>Privacy Setting:</label>

                            <div class="kontainer-radio">

                                <input type="radio" id="private-file" name="is_private" value="1" checked>
                                <label for="private-file" class="radio-option">
                                    <i class="fas fa-lock"></i> Private
                                </label>

                                <input type="radio" id="public-file" name="is_private" value="0">
                                <label for="public-file" class="radio-option">
                                    <i class="fas fa-globe"></i> Public
                                </label>
                            </div>
                        </div>
                        <button type="submit">
                            <i class="fas fa-upload"></i>
                            Upload File
                        </button>
                    </form>
                </div>';
}
// function show form textup
function textup() {
    echo '
            <div id="text-upload" class="upload-section">
                    <form class="kontainer-text" action="/" method="post">
                        <h2><i class="fas fa-edit"></i> Upload Text</h2>
                        
                        <div class="form-options">
                            <div class="form-group">
                                <label for="textcontent">Text Content:</label>
                                <textarea name="textcontent" id="textcontent" placeholder="Enter your text here..." required></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expire-text">Expiration:</label>
                                    <select name="expire" id="expire-text">
                                        <option value="0">Never Expires</option>
                                        <option value="1">1 Day</option>
                                        <option value="7">1 Week</option>
                                        <option value="30">1 Month</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-text">Password (optional):</label>
                                    <input type="password" name="password" id="password-text" placeholder="Leave blank for no password">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description-text">Description:</label>
                                <textarea name="description" id="description-text" rows="3" placeholder="Description for your text"></textarea>
                            </div>
                                                                                        <label>Privacy Setting:</label>

                            <div class="kontainer-radio">

                                <input type="radio" id="private-text" name="is_private" value="1" checked>
                                <label for="private-text" class="radio-option">
                                    <i class="fas fa-lock"></i> Private
                                </label>

                                <input type="radio" id="public-text" name="is_private" value="0">
                                <label for="public-text" class="radio-option">
                                    <i class="fas fa-globe"></i> Public
                                </label>
                            </div>
                        </div>
                        <button type="submit">
                            <i class="fas fa-upload"></i>
                            Upload Text
                        </button>
                    </form>
                </div>
';
}
// bulkup form page
function bulkup() {
    echo '
          <div id="bulk-upload" class="upload-section">
            <form action="/" method="post" enctype="multipart/form-data" class="konntainer-upload">
                <h2><i class="fas fa-layer-group"></i> Bulk Upload</h2>
                <div class="form-options">
                    <div class="form-group">
                        <label for="bulkFiles">Choose Multiple Files:</label>
                        <input type="file" name="bulkFiles[]" id="bulkFiles" multiple required>
                        <div class="file-info">
                            <p><i class="fas fa-info-circle"></i> <strong>Info:</strong> Select multiple files to upload at once. All files will use the same privacy setting and expiration.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Privacy Setting:</label>
                        <div class="kontainer-radio">
                            <input type="radio" id="private-bulk" name="is_private" value="1" checked>
                            <label for="private-bulk" class="radio-option">
                                <i class="fas fa-lock"></i> Private
                            </label>

                            <input type="radio" id="public-bulk" name="is_private" value="0">
                            <label for="public-bulk" class="radio-option">
                                <i class="fas fa-globe"></i> Public
                            </label>
                        </div>
                    </div>
                    
                    <!-- Additional bulk options -->
                    <div class="form-group">
                        <label for="expire-bulk">Expire after (days):</label>
                        <select name="expire" id="expire-bulk">
                            <option value="0">Never</option>
                            <option value="1">1 day</option>
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password-bulk">Password protect (optional):</label>
                        <input type="password" name="password" id="password-bulk" placeholder="Leave empty for no password">
                    </div>
                    
                    <div class="form-group">
                        <label for="max-downloads-bulk">Max downloads (0 = unlimited):</label>
                        <input type="number" name="max_downloads" id="max-downloads-bulk" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="description-bulk">Bulk description (optional):</label>
                        <textarea name="description" id="description-bulk" rows="3" placeholder="Description for all files in this bulk upload"></textarea>
                    </div>
                </div>
                
                <button type="submit" name="bulk_submit" class="upload-btn">
                    <i class="fas fa-upload"></i>
                    Upload Files
                </button>
            </form>
        </div>
';
}
