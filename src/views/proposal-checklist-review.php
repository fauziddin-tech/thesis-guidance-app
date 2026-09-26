<?php
// Pemeriksaan kelengkapan dan tata tulis proposal oleh Pembimbing 1 (di popup dokumen mahasiswa).
// Membutuhkan $document (baris bab_skripsi, termasuk kolom kelengkapan).
$reviewChecklist = proposal_checklist_decode($document['kelengkapan'] ?? '');
$reviewSaved = $reviewChecklist['dosen']['hasil'] ?? [];
$reviewOk = count(proposal_components_with($reviewChecklist, 'sesuai'));
$reviewTotal = count(PROPOSAL_COMPONENTS);
$reviewNotFound = proposal_components_not_found($reviewChecklist);
?>
<?php if($reviewNotFound): ?><small class="table-subtext attention-text">Cek otomatis: tidak ditemukan <?=h(implode(', ', $reviewNotFound))?></small><?php endif; ?>
<?php if(!empty($document['perlu_review_saya'])): ?>
<details class="proposal-review"<?=$reviewSaved?'':' open'?>><summary><?=$reviewSaved?'Pemeriksaan kelengkapan: '.$reviewOk.'/'.$reviewTotal.' sesuai':'Periksa kelengkapan proposal'?></summary>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_proposal_checklist"><input type="hidden" name="bab_id" value="<?=(int)$document['id']?>">
<table class="proposal-review-table"><thead><tr><th>Komponen</th><th>Sesuai</th><th>Perbaiki</th></tr></thead><tbody>
<?php foreach(PROPOSAL_COMPONENTS as $componentKey=>$componentLabel): $auto=$reviewChecklist['otomatis'][$componentKey]??null;$saved=$reviewSaved[$componentKey]??''; ?>
<tr><td><?=h($componentLabel)?><?php if($auto===false): ?> <span class="auto-tag is-missing" title="Judul bagian tidak ditemukan oleh pemindaian otomatis">tidak terdeteksi</span><?php elseif($auto===true): ?> <span class="auto-tag" title="Judul bagian ditemukan oleh pemindaian otomatis">terdeteksi</span><?php endif; ?></td>
<td><input type="radio" name="hasil[<?=h($componentKey)?>]" value="sesuai" aria-label="<?=h($componentLabel)?> sesuai" required<?=$saved==='sesuai'?' checked':''?>></td>
<td><input type="radio" name="hasil[<?=h($componentKey)?>]" value="perbaikan" aria-label="<?=h($componentLabel)?> perlu perbaikan"<?=$saved==='perbaikan'?' checked':''?>></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="field-help">Setujui hanya bila semua komponen Sesuai. Komponen bertanda Perbaiki otomatis tercantum di catatan saat Anda mengirim Revisi.</p>
<button class="btn btn-primary btn-small" type="submit">Simpan Pemeriksaan</button></form></details>
<?php elseif($reviewSaved): ?><small class="table-subtext">Pemeriksaan kelengkapan: <?=$reviewOk?>/<?=$reviewTotal?> sesuai</small><?php endif; ?>
