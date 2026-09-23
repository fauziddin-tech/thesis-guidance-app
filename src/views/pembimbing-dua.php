<section class="card" id="pembimbing-dua">
<h2>Pembimbing 2</h2>
<p>Daftar ini memuat semua dosen terdaftar kecuali Pembimbing 1. Jika dosen belum tersedia, hubungi admin untuk membuat akunnya.</p>
<p>Pembimbing 2 dapat dipilih sejak awal. Review oleh Pembimbing 2 dibuka setelah Pembimbing 1 memberikan ACC pada dokumen atau paket yang sama.</p>
<?php if(!p2_ready($conn)): ?>
<p>Fitur menunggu aktivasi database oleh administrator.</p>
<?php elseif(!$guidances): ?><p>Belum ada bimbingan. Pembimbing 2 dapat dipilih setelah bimbingan tersedia.</p>
<?php else: foreach($guidances as $guidance): $team=p2_team($conn,(int)$guidance['id']);$second=isset($team[1])?(int)$team[1]['id']:0; ?>
<form method="post" style="margin-bottom:1rem">
<?=csrf_field()?>
<input type="hidden" name="action" value="choose_second_supervisor">
<input type="hidden" name="bimbingan_id" value="<?=(int)$guidance['id']?>">
<strong><?=h($guidance['judul_skripsi']?:'Bimbingan skripsi')?></strong>
<p>Pembimbing 1: <?=h($guidance['dosen_nama'])?></p>
<label for="dosen2-<?=(int)$guidance['id']?>">Nama Pembimbing 2</label>
<select name="dosen2_id" id="dosen2-<?=(int)$guidance['id']?>" required>
<option value="">Pilih dosen pembimbing 2</option>
<?php foreach($availableSecondLecturers as $lecturer): if((int)$lecturer['id']===(int)$guidance['dosen_id'])continue; ?>
<option value="<?=(int)$lecturer['id']?>" <?=$second===(int)$lecturer['id']?'selected':''?>><?=h($lecturer['nama_lengkap'])?></option>
<?php endforeach; ?></select>
<small class="field-help">Saat pembimbing diganti, dosen baru perlu memberikan persetujuannya sendiri. Riwayat komentar tetap tersimpan.</small>
<button type="submit" class="btn btn-primary">Simpan Pembimbing 2</button>
</form>
<?php endforeach; endif; ?></section>
