<?php $titleReviews=$titleReviewsByGuidance[(int)$item['id']]??[]; ?>
<?php if($titleReviews): ?><ol class="title-review-timeline">
<?php foreach($titleReviews as $review): $resubmitted=$review['topik']==='Pengajuan Ulang Judul';$approved=$review['status']==='diterima'; ?>
<li><div class="title-review-meta"><span class="badge <?=$resubmitted?'badge-secondary':($approved?'badge-success':'badge-warning')?>"><?=$resubmitted?'Diajukan ulang':($approved?'Disetujui':'Revisi')?></span><time><?=h($review['created_at'])?></time></div>
<p><?=nl2br(h($review['jawaban']??''))?></p><small><?=h($review['reviewer_nama']?:($resubmitted?'Mahasiswa':'Dosen pembimbing'))?></small>
<?php if($resubmitted): ?><details><summary>Judul dan deskripsi sebelumnya</summary><p><?=nl2br(h($review['deskripsi']??''))?></p></details><?php endif; ?>
</li><?php endforeach; ?></ol>
<?php else: ?><p>Belum ada riwayat review judul.</p><?php endif; ?>
