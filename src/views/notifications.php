<?php
require_login();
$uid=(int)$_SESSION['user']['id'];
$stmt=$conn->prepare('SELECT id,tipe,pesan,link,dibaca,created_at FROM notifikasi WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
$stmt->bind_param('i',$uid); $stmt->execute(); $notifications=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
?>
<div class="page-head"><div><h1>Notifikasi</h1><p class="muted">Informasi terbaru tentang bimbingan dan skripsi Anda.</p></div>
<form method="post"><input type="hidden" name="action" value="mark_all_notifications_read"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="btn btn-secondary" type="submit">Tandai semua dibaca</button></form></div>
<div class="card">
<?php if(!$notifications): ?><p class="muted">Belum ada notifikasi.</p><?php else: ?>
<div class="notification-list">
<?php foreach($notifications as $n): ?>
<article class="notification-item <?=((int)$n['dibaca']===0?'unread':'')?>">
<div><strong><?=e(ucfirst(str_replace('_',' ',$n['tipe'])))?></strong><p><?=e($n['pesan'])?></p><small class="muted"><?=e(formatDateTime($n['created_at']))?></small></div>
<div class="notification-actions">
<?php if(!empty($n['link'])): ?><a class="btn btn-primary" href="<?=e($n['link'])?>">Buka</a><?php endif; ?>
<?php if((int)$n['dibaca']===0): ?><form method="post"><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?=e($n['id'])?>"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="btn btn-secondary" type="submit">Tandai dibaca</button></form><?php endif; ?>
</div></article>
<?php endforeach; ?></div><?php endif; ?>
</div>
