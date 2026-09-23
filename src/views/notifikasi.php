<?php
// Halaman notifikasi pengguna: 200 terbaru, dengan pencarian dan paginasi seragam.
$currentUserId = (int)$_SESSION['user']['id'];
$stmt = $conn->prepare('SELECT id,tipe,pesan,link,dibaca,created_at FROM notifikasi WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 200');
$stmt->bind_param('i', $currentUserId);$stmt->execute();$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
$flash = pull_flash();
$unread = count(array_filter($notifications, function ($item) {return (int)$item['dibaca'] === 0;}));
$typeLabels = ['unggah_bab' => 'Dokumen baru', 'revisi' => 'Revisi', 'persetujuan' => 'Persetujuan', 'bimbingan_baru' => 'Bimbingan', 'judul_disetujui' => 'Judul disetujui', 'judul_direvisi' => 'Revisi judul', 'status_bimbingan' => 'Status bimbingan'];
?>
<div class="section-heading page-intro"><span class="eyebrow">KOTAK MASUK</span><h1>Notifikasi</h1><p><?=$unread?> belum dibaca dari <?=count($notifications)?> notifikasi terbaru.</p></div>
<?php if($flash): ?><div class="alert alert-<?=h($flash['type'])?>" role="status"><?=h($flash['message'])?></div><?php endif; ?>
<section class="card">
<?php if($notifications): ?>
<?php if($unread): ?><form method="post" class="notification-bulk"><?=csrf_field()?><input type="hidden" name="action" value="tandai_semua"><button class="btn btn-secondary btn-small" type="submit">Tandai semua sudah dibaca</button></form><?php endif; ?>
<div data-paginate="notifikasi"><?=paginate_tools('notification-search','Cari isi notifikasi…','notifikasi')?>
<div class="notification-list"><?php foreach($notifications as $item): $isUnread=(int)$item['dibaca']===0; ?><article class="notification-item<?=$isUnread?' is-unread':''?>" data-paginate-item><div class="notification-body"><span class="notification-type"><?=h($typeLabels[$item['tipe']] ?? ucfirst(str_replace('_',' ',$item['tipe'])))?></span><p><?=nl2br(h($item['pesan']))?></p><small><?=h(tanggal_id($item['created_at'], true))?></small></div><div class="notification-actions"><?php if(!empty($item['link'])): ?><a class="btn btn-primary btn-small" href="?action=buka_notifikasi&amp;id=<?=(int)$item['id']?>">Buka</a><?php endif; ?><?php if($isUnread): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="tandai_dibaca"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button class="btn btn-secondary btn-small" type="submit">Tandai dibaca</button></form><?php endif; ?></div></article><?php endforeach; ?></div>
<?=paginate_footer('notifikasi')?></div>
<?php else: ?><div class="empty-state"><strong>Belum ada notifikasi.</strong><span>Informasi tentang bimbingan dan dokumen Anda akan tampil di sini.</span></div><?php endif; ?>
</section>
