<?php
// Dashboard mahasiswa: contoh dokumen PDF dari dosen/admin sebagai pedoman tata tulis dan struktur.
// Membutuhkan $examples dari dashboard.php. Pratinjau memakai #example-dialog (contoh-dialog.php).
?>
<section class="card" id="contoh-dokumen"><div class="section-heading"><span class="eyebrow">PEDOMAN</span><h2>Contoh Dokumen</h2><p>Contoh dari dosen untuk panduan tata tulis, struktur, dan pola penulisan proposal maupun skripsi. Klik Lihat untuk membuka pratinjau.</p></div>
<?php if($examples): ?><div data-paginate="contoh"><?=paginate_tools('example-search','Cari judul, jenis, atau nama dosen…','contoh')?><div class="example-grid">
<?php foreach($examples as $example): ?><article class="example-card" data-paginate-item><span class="example-type"><?=h(EXAMPLE_CATEGORIES[$example['kategori']]??'Contoh')?></span><h3><?=h($example['judul'])?></h3><?php if($example['keterangan']): ?><p><?=h($example['keterangan'])?></p><?php endif; ?><small class="table-subtext"><?=$example['pengunggah_role']==='admin'?'Program studi':h($example['pengunggah_nama'])?> · <?=h(tanggal_id($example['created_at']))?> · <?=h(examples_size_label((int)$example['ukuran']))?></small><div class="example-actions"><button class="btn btn-primary btn-small" type="button" data-example-open data-src="?action=contoh&amp;id=<?=(int)$example['id']?>" data-title="<?=h($example['judul'])?>">Lihat</button><a class="btn btn-secondary btn-small" href="?action=contoh&amp;id=<?=(int)$example['id']?>&amp;unduh=1">Unduh PDF</a></div></article><?php endforeach; ?>
</div><?=paginate_footer('contoh')?></div>
<?php else: ?><div class="empty-state"><strong>Belum ada contoh dokumen.</strong><span>Contoh proposal atau skripsi akan tampil di sini setelah diunggah dosen.</span></div><?php endif; ?>
</section>
