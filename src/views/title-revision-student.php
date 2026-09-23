<?php
$bid=(int)$item['id'];$history=$titleReviewsByGuidance[$bid]??[];
$states=p2_decisions($conn,'judul',$bid);$currentStates=[];
foreach(p2_team($conn,$bid) as $teacher)if(isset($states[$teacher['id']]))$currentStates[]=$states[$teacher['id']];
$canRevise=title_revision_requested($item,$history,$currentStates);
$draft=$_SESSION['title_revision_draft']??[];$hasDraft=(int)($draft['bimbingan_id']??0)===$bid;
?>
<?php if($history): ?><details><summary>Riwayat revisi judul</summary><?php include __DIR__.'/title-history.php'; ?></details><?php endif; ?>
<?php if($canRevise): ?>
<details <?=$hasDraft?'open':''?> style="margin-top:12px">
<summary class="btn btn-primary btn-small">Perbaiki dan Ajukan Ulang Judul</summary>
<form method="post" style="margin-top:16px">
<?=csrf_field()?>
<input type="hidden" name="action" value="resubmit_title">
<input type="hidden" name="bimbingan_id" value="<?=$bid?>">
<input type="hidden" name="title_fingerprint" value="<?=h(title_revision_fingerprint($item))?>">
<label for="judul-perbaikan-<?=$bid?>">Judul perbaikan</label>
<textarea id="judul-perbaikan-<?=$bid?>" name="judul_perbaikan" rows="3" minlength="5" maxlength="255" required><?=h($hasDraft?$draft['judul']:($item['judul_skripsi']??''))?></textarea>
<label for="deskripsi-perbaikan-<?=$bid?>">Deskripsi penelitian</label>
<textarea id="deskripsi-perbaikan-<?=$bid?>" name="deskripsi_perbaikan" rows="3" maxlength="2000"><?=h($hasDraft?$draft['deskripsi']:($item['deskripsi']??''))?></textarea>
<p class="field-help">Judul perbaikan akan direview kembali oleh Pembimbing 1. Setelah ACC, review dilanjutkan ke Pembimbing 2.</p>
<button class="btn btn-primary" type="submit">Kirim Pengajuan Ulang</button>
</form></details>
<?php elseif(($history[0]['topik']??'')==='Pengajuan Ulang Judul'): ?><p class="field-help">Perbaikan sudah dikirim. Menunggu review Pembimbing 1.</p><?php endif; ?>
