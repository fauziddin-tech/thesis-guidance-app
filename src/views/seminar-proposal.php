<?php
// Tandai seminar proposal selesai (Pembimbing 1 dan admin), muncul setelah proposal penelitian disetujui.
// Admin juga dapat membatalkan tanda seminar selama Bab 4 belum diunggah. Membutuhkan $item dan $role.
if (!proposal_ready($conn)) return;
$seminarStage = proposal_stage($conn, (int)$item['id']);
if ($seminarStage['tahap'] === 'siap_seminar'): ?>
<details class="seminar-form"><summary class="link-button">Tandai seminar proposal selesai</summary><form method="post" data-confirm-title="Seminar proposal selesai?" data-confirm="Bab 4 akan terbuka bagi mahasiswa ini dan mahasiswa menerima notifikasi." data-confirm-ok="Tandai selesai"><?=csrf_field()?><input type="hidden" name="action" value="mark_seminar_done"><input type="hidden" name="bimbingan_id" value="<?=(int)$item['id']?>"><label for="seminar-date-<?=h($role)?>-<?=(int)$item['id']?>">Tanggal seminar</label><input id="seminar-date-<?=h($role)?>-<?=(int)$item['id']?>" type="date" name="tanggal_seminar" max="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required><button class="btn btn-primary btn-small" type="submit">Simpan</button></form></details>
<?php elseif ($role === 'admin' && $seminarStage['tahap'] === 'selesai' && !p2_latest_documents($conn, (int)$item['id'])['bab'][4]): ?>
<form method="post" class="seminar-undo" data-confirm-title="Batalkan tanda seminar?" data-confirm="Bab 4 kembali terkunci sampai seminar proposal ditandai selesai lagi." data-confirm-ok="Batalkan tanda"><?=csrf_field()?><input type="hidden" name="action" value="unmark_seminar"><input type="hidden" name="bimbingan_id" value="<?=(int)$item['id']?>"><button class="link-button" type="submit">Batalkan tanda seminar</button></form>
<?php endif; ?>
