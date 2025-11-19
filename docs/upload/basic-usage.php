<?php

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;


// Basic usage
$uploader = new FileUploader('/path/to/uploads');
$uploader->imagesOnly(5 * 1024 * 1024);

// If .htaccess causing issues
$uploader->disableSecurityFiles();

// Upload
$file = new UploadedFile($_FILES['photo']);
$result = $uploader->upload($file);