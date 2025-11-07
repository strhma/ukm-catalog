<?php
/**
 * Enhanced file upload function with better error handling
 * 
 * @param array $file $_FILES array element
 * @param array $allowedTypes Array of allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @param string $uploadDir Custom upload directory
 * @return array Result array with success status
 */
function uploadFileEnhanced($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 2097152, $uploadDir = null) {
    // Default upload directory
    if ($uploadDir === null) {
        $uploadDir = UPLOAD_PATH;
    }
    
    // Check if upload directory exists and is writable
    if (!is_dir($uploadDir)) {
        // Try to create directory
        if (!mkdir($uploadDir, 0777, true)) {
            return [
                'success' => false, 
                'message' => 'Upload directory does not exist and cannot be created'
            ];
        }
    }
    
    if (!is_writable($uploadDir)) {
        // Try to make writable
        if (!chmod($uploadDir, 0777)) {
            return [
                'success' => false, 
                'message' => 'Upload directory is not writable'
            ];
        }
    }
    
    // Check file upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (exceeds php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        return [
            'success' => false, 
            'message' => $errorMessages[$errorCode] ?? 'Unknown upload error'
        ];
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / 1048576, 2);
        return [
            'success' => false, 
            'message' => "File too large. Maximum size: {$maxSizeMB}MB"
        ];
    }
    
    // Validate file type using MIME
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($fileInfo === false) {
        return [
            'success' => false, 
            'message' => 'Cannot determine file type'
        ];
    }
    
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $allowedExtensions = array_map(function($type) {
            return str_replace(['image/', 'application/'], '', $type);
        }, $allowedTypes);
        return [
            'success' => false, 
            'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)
        ];
    }
    
    // Additional image validation for images
    if (strpos($mimeType, 'image/') === 0) {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'success' => false, 
                'message' => 'Invalid image file'
            ];
        }
        
        // Check image dimensions (max 2000x2000)
        $maxWidth = 2000;
        $maxHeight = 2000;
        if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
            return [
                'success' => false, 
                'message' => "Image too large. Maximum dimensions: {$maxWidth}x{$maxHeight}px"
            ];
        }
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('', true) . '_' . time() . '.' . strtolower($extension);
    
    // Ensure unique filename
    $counter = 0;
    $originalFilename = $filename;
    while (file_exists($uploadDir . $filename)) {
        $counter++;
        $filename = pathinfo($originalFilename, PATHINFO_FILENAME) . "_$counter." . $extension;
        
        // Prevent infinite loop
        if ($counter > 100) {
            return [
                'success' => false, 
                'message' => 'Cannot generate unique filename'
            ];
        }
    }
    
    // Move uploaded file
    $uploadPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Set proper permissions
        chmod($uploadPath, 0644);
        
        return [
            'success' => true, 
            'filename' => $filename,
            'path' => $uploadPath,
            'url' => (defined('UPLOAD_URL') ? UPLOAD_URL : '') . $filename,
            'size' => $file['size'],
            'type' => $mimeType
        ];
    } else {
        return [
            'success' => false, 
            'message' => 'Failed to save uploaded file'
        ];
    }
}

/**
 * Delete uploaded file
 * 
 * @param string $filename Filename to delete
 * @param string $uploadDir Upload directory
 * @return bool Success status
 */
function deleteUploadedFile($filename, $uploadDir = null) {
    if ($uploadDir === null) {
        $uploadDir = UPLOAD_PATH;
    }
    
    $filePath = $uploadDir . $filename;
    
    if (file_exists($filePath) && is_file($filePath)) {
        return unlink($filePath);
    }
    
    return false;
}

/**
 * Get file info safely
 * 
 * @param string $filename Filename
 * @param string $uploadDir Upload directory
 * @return array|null File info or null if not found
 */
function getFileInfo($filename, $uploadDir = null) {
    if ($uploadDir === null) {
        $uploadDir = UPLOAD_PATH;
    }
    
    $filePath = $uploadDir . $filename;
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        return null;
    }
    
    return [
        'name' => $filename,
        'path' => $filePath,
        'size' => filesize($filePath),
        'modified' => filemtime($filePath),
        'url' => (defined('UPLOAD_URL') ? UPLOAD_URL : '') . $filename
    ];
}
?>