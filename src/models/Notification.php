<?php

class Notification {
    private mysqli $conn;
    public function __construct(mysqli $connection) { $this->conn = $connection; }
    public function create(int $userId,string $type,string $message,?string $link=null): bool {
        $stmt=$this->conn->prepare('INSERT INTO notifikasi (user_id, tipe, pesan, link, dibaca) VALUES (?, ?, ?, ?, 0)');
        $stmt->bind_param('isss',$userId,$type,$message,$link); $ok=$stmt->execute(); $stmt->close(); return $ok;
    }
    public function getUserNotifications(int $userId,int $limit=10): array {
        $limit=max(1,min(100,$limit));
        $stmt=$this->conn->prepare('SELECT * FROM notifikasi WHERE user_id=? ORDER BY created_at DESC LIMIT ?');
        $stmt->bind_param('ii',$userId,$limit); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }
    public function markAsRead(int $id): bool { $stmt=$this->conn->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=?'); $stmt->bind_param('i',$id); $ok=$stmt->execute(); $stmt->close(); return $ok; }
    public function countUnread(int $userId): int { $stmt=$this->conn->prepare('SELECT COUNT(*) total FROM notifikasi WHERE user_id=? AND dibaca=0'); $stmt->bind_param('i',$userId); $stmt->execute(); $n=(int)($stmt->get_result()->fetch_assoc()['total']??0); $stmt->close(); return $n; }
}
