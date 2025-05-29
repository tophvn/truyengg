<?php
require_once __DIR__ . '/../../config/routes.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../google-api/vendor/autoload.php';
session_start();

// Fetch Google API settings from database
$settings_query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri')";
$settings_result = $conn->query($settings_query);
$settings = [];
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Validate required settings
$required_settings = ['google_client_id', 'google_client_secret', 'google_redirect_uri'];
foreach ($required_settings as $key) {
    if (!isset($settings[$key]) || empty(trim($settings[$key]))) {
        error_log("Missing or empty Google API setting: $key");
        header('Location: ' . BASE_URL . '?error=missing_google_settings');
        exit;
    }
}

$client = new Google_Client();
$client->setClientId($settings['google_client_id']);
$client->setClientSecret($settings['google_client_secret']);
$client->setRedirectUri($settings['google_redirect_uri']);
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (isset($token['error'])) {
            error_log('Google OAuth error: ' . $token['error']);
            header('Location: ' . BASE_URL . '?error=google_auth_failed');
            exit;
        }

        $client->setAccessToken($token);
        $oauth2 = new Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        $email = $userInfo->email;
        $name = $userInfo->name;
        $google_id = $userInfo->id;

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, roles FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // User exists, log them in
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['roles'] = $user['roles'];
        } else {
            // Create new user
            $username = str_replace('@gmail.com', '', $email);
            // Ensure unique username
            $base_username = $username;
            $counter = 1;
            while (true) {
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows == 0) {
                    break;
                }
                $username = $base_username . $counter;
                $counter++;
                $check_stmt->close();
            }

            $password = password_hash($google_id, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, name, roles) VALUES (?, ?, ?, ?, 'user')");
            $stmt->bind_param("ssss", $username, $email, $password, $name);
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['email'] = $email;
                $_SESSION['roles'] = 'user';
            } else {
                error_log('Failed to register new user: ' . $conn->error);
                header('Location: ' . BASE_URL . '?error=registration_failed');
                exit;
            }
        }

        $stmt->close();
        $conn->close();
        header('Location: ' . BASE_URL);
        exit;
    } catch (Exception $e) {
        error_log('Google OAuth exception: ' . $e->getMessage());
        header('Location: ' . BASE_URL . '?error=google_auth_exception');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '?error=invalid_request');
    exit;
}
?>