<?php
/** Pembatasan percobaan (login, lupa password) memakai tabel rate_limits. Aman bila tabel belum ada: dianggap tanpa batas. */
function rl_key(string $v): string { return substr(hash('sha256', strtolower($v)), 0, 32); }
function client_ip(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
function rl_count(mysqli $c, string $bucket, string $key, int $seconds): int {
    $s = $c->prepare('SELECT COUNT(*) n FROM rate_limits WHERE bucket=? AND rkey=? AND created_at>DATE_SUB(NOW(),INTERVAL ? SECOND)');
    if (!$s) return 0;
    $s->bind_param('ssi', $bucket, $key, $seconds);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    return (int)($row['n'] ?? 0);
}
function rl_hit(mysqli $c, string $bucket, string $key): void {
    $s = $c->prepare('INSERT INTO rate_limits(bucket,rkey) VALUES(?,?)');
    if ($s) { $s->bind_param('ss', $bucket, $key); $s->execute(); $s->close(); }
    if (mt_rand(1, 50) === 1) { $c->query('DELETE FROM rate_limits WHERE created_at<DATE_SUB(NOW(),INTERVAL 1 DAY)'); }
}
