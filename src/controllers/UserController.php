<?php

class UserController {
    private mysqli $conn;
    public function __construct(mysqli $connection) { $this->conn = $connection; }

    public function getAllUsers(int $limit=20, int $offset=0): array {
        $limit=max(1,min(100,$limit)); $offset=max(0,$offset);
        $stmt=$this->conn->prepare('SELECT id, username, email, nama_lengkap, role, no_telp, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('ii',$limit,$offset); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }
    public function getTotalUsers(): int { $r=$this->conn->query('SELECT COUNT(*) total FROM users'); return (int)($r->fetch_assoc()['total'] ?? 0); }
    public function getUsersByRole(string $role): array {
        $stmt=$this->conn->prepare('SELECT id, username, nama_lengkap, email FROM users WHERE role = ? ORDER BY nama_lengkap'); $stmt->bind_param('s',$role); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }
    public function create(string $username,string $email,string $nama,string $password,string $role,string $telp=''): array {
        if(!in_array($role,['admin','dosen','mahasiswa'],true)) return ['success'=>false,'error'=>'Role tidak valid.'];
        $stmt=$this->conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1'); $stmt->bind_param('ss',$username,$email); $stmt->execute(); if($stmt->get_result()->num_rows){$stmt->close();return ['success'=>false,'error'=>'Username atau email sudah terdaftar.'];} $stmt->close();
        $hash=password_hash($password,PASSWORD_DEFAULT); $stmt=$this->conn->prepare('INSERT INTO users (username,email,nama_lengkap,password,role,no_telp) VALUES (?,?,?,?,?,?)'); $stmt->bind_param('ssssss',$username,$email,$nama,$hash,$role,$telp); $ok=$stmt->execute(); $id=$this->conn->insert_id; $err=$stmt->error; $stmt->close(); return $ok?['success'=>true,'id'=>$id]:['success'=>false,'error'=>$err];
    }
    public function update(int $id,string $nama,string $email,string $telp): array {
        $stmt=$this->conn->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1'); $stmt->bind_param('si',$email,$id); $stmt->execute(); if($stmt->get_result()->num_rows){$stmt->close();return ['success'=>false,'error'=>'Email sudah digunakan pengguna lain.'];} $stmt->close();
        $stmt=$this->conn->prepare('UPDATE users SET nama_lengkap=?, email=?, no_telp=? WHERE id=?'); $stmt->bind_param('sssi',$nama,$email,$telp,$id); $ok=$stmt->execute(); $stmt->close(); return ['success'=>$ok];
    }
    public function delete(int $id): array { $stmt=$this->conn->prepare('DELETE FROM users WHERE id=?'); $stmt->bind_param('i',$id); $ok=$stmt->execute(); $err=$stmt->error; $stmt->close(); return $ok?['success'=>true]:['success'=>false,'error'=>$err]; }
    public function changePassword(int $id,string $old,string $new): array {
        $stmt=$this->conn->prepare('SELECT password FROM users WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if(!$row || !password_verify($old,$row['password'])) return ['success'=>false,'error'=>'Password lama tidak sesuai.'];
        $hash=password_hash($new,PASSWORD_DEFAULT); $stmt=$this->conn->prepare('UPDATE users SET password=? WHERE id=?'); $stmt->bind_param('si',$hash,$id); $ok=$stmt->execute(); $stmt->close(); return ['success'=>$ok];
    }
}
