<?php

use Plugs\Upload\FileUploader;

// Public folder (most common)
$uploader = new FileUploader();
$uploader->usePublicFolder('uploads')->imagesOnly();

// Private storage
$uploader->useStorageFolder('documents')->documentsOnly();

// Custom path
$uploader->setUploadPath('/your/custom/path')->setBaseUrl('/files');