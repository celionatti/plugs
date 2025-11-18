<?php

// ============================================
// config/upload.php - Upload Configuration File
// ============================================

return [
    /*
    |--------------------------------------------------------------------------
    | Default Upload Path
    |--------------------------------------------------------------------------
    |
    | The default directory where uploaded files will be stored.
    | This should be a writable directory, preferably outside the public root.
    |
    */
    'path' => BASE_PATH . '/storage/uploads',

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes. This should not exceed your PHP settings.
    | Current PHP limits:
    | - upload_max_filesize: <?= ini_get('upload_max_filesize') . "\n" ?>
    | - post_max_size: <?= ini_get('post_max_size') . "\n" ?>
    |
    */
    'max_size' => 10 * 1024 * 1024, // 10MB

    /*
    |--------------------------------------------------------------------------
    | Image Upload Settings
    |--------------------------------------------------------------------------
    */
    'images' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ],
        'max_width' => 2000,
        'max_height' => 2000,
        'min_width' => 100,
        'min_height' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Upload Settings
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'max_size' => 20 * 1024 * 1024, // 20MB
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],
        'allowed_mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Allow SVG uploads (disabled by default for security)
        'allow_svg' => false,

        // Rate limiting (uploads per minute per user/IP)
        'rate_limit' => 10,

        // Prevent duplicate file uploads based on hash
        'prevent_duplicates' => true,

        // Organize uploads by date (YYYY/MM/DD structure)
        'organize_by_date' => true,

        // Generate unique filenames
        'generate_unique_names' => true,

        // Block dangerous file extensions
        'block_dangerous_extensions' => true,

        // Block suspicious double extensions
        'block_double_extensions' => true,

        // Validate actual MIME type from file content
        'check_actual_mime_type' => true,

        // Validate image content integrity
        'validate_image_content' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Presets
    |--------------------------------------------------------------------------
    |
    | Define common upload configurations that can be reused
    |
    */
    'presets' => [
        'avatar' => [
            'preset' => 'images',
            'maxSize' => 2 * 1024 * 1024, // 2MB
            'maxWidth' => 500,
            'maxHeight' => 500,
            'minWidth' => 100,
            'minHeight' => 100,
        ],

        'profile_cover' => [
            'preset' => 'images',
            'maxSize' => 5 * 1024 * 1024, // 5MB
            'maxWidth' => 1920,
            'maxHeight' => 1080,
        ],

        'gallery' => [
            'preset' => 'images',
            'maxSize' => 10 * 1024 * 1024, // 10MB
            'maxWidth' => 3000,
            'maxHeight' => 3000,
        ],

        'invoice' => [
            'preset' => 'documents',
            'allowed' => ['pdf'],
            'maxSize' => 5 * 1024 * 1024, // 5MB
        ],
    ],
];

?>

<!-- ============================================ -->
<!-- HTML FORM EXAMPLES -->
<!-- ============================================ -->

<!-- Example 1: Simple Single File Upload -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ccc;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="file"]:hover {
            border-color: #666;
        }
        button {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #2563eb;
        }
        .error {
            color: #dc2626;
            margin-top: 5px;
            font-size: 14px;
        }
        .success {
            color: #16a34a;
            margin-top: 5px;
            font-size: 14px;
        }
        .file-info {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>Upload Profile Picture</h1>
    
    <form action="/profile/upload-avatar" method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="form-group">
            <label for="avatar">Choose Profile Picture</label>
            <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/gif" required>
            <div class="file-info" id="fileInfo" style="display: none;"></div>
        </div>
        
        <button type="submit">Upload Avatar</button>
    </form>

    <div id="message"></div>

    <script>
        // Show file information on selection
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileInfo = document.getElementById('fileInfo');
            const message = document.getElementById('message');
            
            message.innerHTML = '';
            
            if (file) {
                // Validate client-side
                const maxSize = 2 * 1024 * 1024; // 2MB
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                
                if (!allowedTypes.includes(file.type)) {
                    message.innerHTML = '<p class="error">Invalid file type. Only JPG, PNG, and GIF are allowed.</p>';
                    e.target.value = '';
                    fileInfo.style.display = 'none';
                    return;
                }
                
                if (file.size > maxSize) {
                    message.innerHTML = '<p class="error">File is too large. Maximum size is 2MB.</p>';
                    e.target.value = '';
                    fileInfo.style.display = 'none';
                    return;
                }
                
                // Show file info
                fileInfo.innerHTML = `
                    <strong>Selected file:</strong><br>
                    Name: ${file.name}<br>
                    Size: ${(file.size / 1024 / 1024).toFixed(2)} MB<br>
                    Type: ${file.type}
                `;
                fileInfo.style.display = 'block';
            }
        });

        // Handle form submission with AJAX
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const message = document.getElementById('message');
            const button = this.querySelector('button');
            
            button.disabled = true;
            button.textContent = 'Uploading...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    message.innerHTML = `<p class="success">${data.message}</p>`;
                    // Show uploaded image
                    if (data.avatar && data.avatar.url) {
                        message.innerHTML += `<img src="${data.avatar.url}" style="max-width: 200px; margin-top: 10px; border-radius: 8px;">`;
                    }
                } else {
                    message.innerHTML = `<p class="error">${data.error}</p>`;
                }
            })
            .catch(error => {
                message.innerHTML = `<p class="error">Upload failed: ${error.message}</p>`;
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = 'Upload Avatar';
            });
        });
    </script>
</body>
</html>

<!-- ============================================ -->
<!-- Example 2: Multiple File Upload (Gallery) -->
<!-- ============================================ -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Gallery Photos</title>
    <style>
        .upload-area {
            border: 3px dashed #ccc;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .upload-area.dragover {
            border-color: #2563eb;
            background: #dbeafe;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .preview-item .remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 38, 38, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: #3b82f6;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <h1>Upload Gallery Photos</h1>
    
    <form id="galleryForm" action="/gallery/upload-photos" method="POST" enctype="multipart/form-data">
        <div class="upload-area" id="uploadArea">
            <input type="file" name="photos[]" id="photos" multiple accept="image/*" style="display: none;">
            <p>📸 Click or drag photos here to upload</p>
            <p style="color: #6b7280; font-size: 14px;">Max 5MB per image, up to 10 images</p>
        </div>
        
        <div class="preview-grid" id="previewGrid"></div>
        
        <div style="margin-top: 20px;">
            <button type="submit" id="uploadBtn" style="display: none;">Upload All Photos</button>
        </div>
        
        <div class="progress-bar" id="progressBar" style="display: none;">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </form>

    <div id="message"></div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('photos');
        const previewGrid = document.getElementById('previewGrid');
        const uploadBtn = document.getElementById('uploadBtn');
        const form = document.getElementById('galleryForm');
        let selectedFiles = [];

        // Click to select files
        uploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        // File selection
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            selectedFiles = Array.from(files).filter((file, index) => {
                // Validate
                if (!file.type.startsWith('image/')) {
                    alert(`${file.name} is not an image`);
                    return false;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert(`${file.name} is too large (max 5MB)`);
                    return false;
                }
                if (index >= 10) {
                    alert('Maximum 10 images allowed');
                    return false;
                }
                return true;
            });

            displayPreviews();
            uploadBtn.style.display = selectedFiles.length > 0 ? 'block' : 'none';
        }

        function displayPreviews() {
            previewGrid.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="remove" onclick="removeFile(${index})">×</button>
                    `;
                    previewGrid.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayPreviews();
            uploadBtn.style.display = selectedFiles.length > 0 ? 'block' : 'none';
        }

        // Upload
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('photos[]', file);
            });

            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            const message = document.getElementById('message');
            
            progressBar.style.display = 'block';
            uploadBtn.disabled = true;

            const xhr = new XMLHttpRequest();
            
            // Progress tracking
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressFill.style.width = percent + '%';
                }
            });

            xhr.onload = function() {
                if (xhr.status === 200) {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        message.innerHTML = `<p class="success">${data.message}</p>`;
                        selectedFiles = [];
                        previewGrid.innerHTML = '';
                        uploadBtn.style.display = 'none';
                    } else {
                        message.innerHTML = `<p class="error">${data.error}</p>`;
                    }
                }
                progressBar.style.display = 'none';
                progressFill.style.width = '0%';
                uploadBtn.disabled = false;
            };

            xhr.onerror = function() {
                message.innerHTML = '<p class="error">Upload failed</p>';
                progressBar.style.display = 'none';
                uploadBtn.disabled = false;
            };

            xhr.open('POST', form.action);
            xhr.send(formData);
        });
    </script>
</body>
</html>

<!-- ============================================ -->
<!-- Example 3: Document Upload with Preview -->
<!-- ============================================ -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Document</title>
</head>
<body>
    <h1>Upload Document</h1>
    
    <form action="/documents/upload" method="POST" enctype="multipart/form-data" id="docForm">
        <div class="form-group">
            <label for="title">Document Title</label>
            <input type="text" name="title" id="title" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label for="category">Category</label>
            <select name="category" id="category" required>
                <option value="">Select Category</option>
                <option value="invoice">Invoice</option>
                <option value="contract">Contract</option>
                <option value="report">Report</option>
            </select>
        </div>

        <div class="form-group">
            <label for="document">Document File (PDF, DOCX, XLSX - Max 20MB)</label>
            <input type="file" name="document" id="document" 
                   accept=".pdf,.doc,.docx,.xls,.xlsx" required>
        </div>

        <button type="submit">Upload Document</button>
    </form>

    <div id="result"></div>
</body>
</html>