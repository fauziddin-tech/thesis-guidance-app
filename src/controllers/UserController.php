<?php

class UserController {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get semua users (untuk admin)
     */
    public function getAllUsers($limit = 20, $offset = 0) {
        $query = "SELECT id, username, email, nama_lengkap, role, no_telp, created_at 
                  FROM users 
                  ORDER BY created_at DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get total users
     */
    public function getTotalUsers() {
        $result = $this->conn->query("SELECT COUNT(*) as total FROM users");
        return $result->fetch_assoc()['total'];
    }
    
    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        $query = "SELECT id, username, nama_lengkap, email FROM users WHERE role = ? ORDER BY nama_lengkap";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $role);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Create user baru
     */
    public function create($username, $email, $nama_lengkap, $password, $role, $no_telp = '') {
        // Cek apakah username/email sudah ada
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'error' => 'Username atau email sudah terdaftar'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $query = "INSERT INTO users (username, email, nama_lengkap, password, role, no_telp) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssssss', $username, $email, $nama_lengkap, $hashed_password, $role, $no_telp);
        
        if ($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->insert_id];
        }
        return ['success' => false, 'error' => $stmt->error];
    }
    
    /**
     * Update user
     */
    public function update($user_id, $nama_lengkap, $email, $no_telp) {
        $query = "UPDATE users SET nama_lengkap = ?, email = ?, no_telp = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sssi', $nama_lengkap, $email, $no_telp, $user_id);
        
        return ['success' => $stmt->execute()];
    }
    
    /**
     * Delete user
     */
    public function delete($user_id) {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        
        return ['success' => $stmt->execute()];
    }
    
    /**
     * Change password
     */
    public function changePassword($user_id, $old_password, $new_password) {
        // Get user password
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            return ['success' => false, 'error' => 'User tidak ditemukan'];
        }
        
        // Verifikasi password lama
        if (!password_verify($old_password, $result['password'])) {
            return ['success' => false, 'error' => 'Password lama tidak sesuai'];
        }
        
        // Update password baru
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $hashed_password, $user_id);
        
        return ['success' => $stmt->execute()];
    }
}
?>
