<?php
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        echo "<div class='alert alert-{$type}'>{$message}</div>";
    }
}

function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,13}$/', $phone);
}

function uploadFile($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 2097152) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large'];
    }

    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $filename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Failed to save file'];
}

function paginate($totalItems, $itemsPerPage, $currentPage, $url) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $pagination = '';

    if ($totalPages > 1) {
        $pagination .= '<div class="pagination">';
        
        // Previous
        if ($currentPage > 1) {
            $pagination .= '<a href="' . $url . '?page=' . ($currentPage - 1) . '" class="page-link">&laquo; Previous</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            $pagination .= '<a href="' . $url . '?page=' . $i . '" class="page-link ' . $active . '">' . $i . '</a>';
        }

        // Next
        if ($currentPage < $totalPages) {
            $pagination .= '<a href="' . $url . '?page=' . ($currentPage + 1) . '" class="page-link">Next &raquo;</a>';
        }

        $pagination .= '</div>';
    }

    return $pagination;
}
?>