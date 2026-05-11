<?php

function get_system_setting($conn, $setting_key) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $setting_key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

function broadcast_notification_to_admins($conn, $message) {
    $status = 'unread';
    $query = "SELECT user_id FROM users WHERE user_role = 'admin'"; 
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, notification_message, notification_status, notification_created_at) VALUES (?, ?, ?, NOW())");
        while ($admin = $result->fetch_assoc()) {
            $stmt->bind_param("iss", $admin['user_id'], $message, $status);
            $stmt->execute();
        }
        $stmt->close();
    }
}
?>