<?php

class Notification {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Buat notifikasi baru
     */
    public function create($user_id, $tipe, $pesan, $link = null) {
        $query = "INSERT INTO notifikasi (user_id, tipe, pesan, link, dibaca) 
                  VALUES (?, ?, ?, ?, 0)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isss', $user_id, $tipe, $pesan, $link);
        
        return $stmt->execute();
    }
    
    /**
     * Get notifikasi user
     */
    public function getUserNotifications($user_id, $limit = 10) {
        $query = "SELECT * FROM notifikasi 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $user_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Mark sebagai dibaca
     */
    public function markAsRead($notification_id) {
        $query = "UPDATE notifikasi SET dibaca = 1 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $notification_id);
        return $stmt->execute();
    }
    
    /**
     * Count unread notifications
     */
    public function countUnread($user_id) {
        $query = "SELECT COUNT(*) as total FROM notifikasi WHERE user_id = ? AND dibaca = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }
}
?>
