<?php
session_start();
require_once '../../config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate file upload
if (!isset($_FILES['file'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    $error_message = $upload_errors[$file['error']] ?? 'Unknown upload error';
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => $error_message]);
    exit;
}

$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['error' => 'File too large']);
    exit;
}

// ImgBB API configuration
$api_key = '643885b88cdae3183c2ddd0e9ae4b5bc';
$api_url = 'https://api.imgbb.com/1/upload';

// Prepare file for upload to ImgBB
$file_tmp_path = $file['tmp_name'];
$file_name = $file['name'];
$image_data = base64_encode(file_get_contents($file_tmp_path));

// Prepare POST data for ImgBB
$post_data = [
    'key' => $api_key,
    'image' => $image_data,
    'name' => $file_name
];

// Send request to ImgBB
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add timeout
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || $curl_error) {
    error_log('ImgBB upload failed: HTTP ' . $http_code . ', Error: ' . $curl_error);
    echo json_encode(['error' => 'Failed to upload to ImgBB: ' . ($curl_error ?: 'HTTP ' . $http_code)]);
    exit;
}

$response_data = json_decode($response, true);
if (!$response_data || !isset($response_data['data']['url'])) {
    error_log('Invalid ImgBB response: ' . $response);
    echo json_encode(['error' => 'Invalid response from ImgBB']);
    exit;
}

$image_url = $response_data['data']['url'];

// Update user avatar in database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->bind_param("si", $image_url, $user_id);
if ($stmt->execute()) {
    echo json_encode(['url' => $image_url]);
} else {
    error_log('Database update failed: ' . $conn->error);
    echo json_encode(['error' => 'Failed to update database: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>