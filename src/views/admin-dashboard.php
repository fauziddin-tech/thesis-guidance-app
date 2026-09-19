<?php
require_role(['admin']);

$user=$_SESSION['user'];
$uid=(int)$user['id'];

/* ---------- helper ---------- */
if(!function_exists('adm_rows')){
    function adm_rows(mysqli $c,string $sql,string $types='',array $vals=[]): array {
        $s=$c->prepare($sql);
        if(!$s) return [];
        if($types!=='') $s->bind_param($types,...$vals);
        $s->execute();
        $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
        return $rows;
    }
}
if(!function_exists('adm_count')){
    function adm_count(mysqli $c,string $sql,string $types='',array $vals=[]): int {
        $r=adm_rows($c,$sql,$types,$vals);
        return (int)($r[0]['total']??0);
    }
}
if(!function_exists('adm_url')){
    function adm_url(array $params,string $sep='&amp;'): string {
        $clean=['page'=>'admin-dashboard'];
        foreach($params as $k=>$v){ if($v!==null&&$v!==''&&$v!==false) $clean[$k]=$v; }
        return '?'.http_build_query($clean,'',$sep);
    }
}
if(!function_exists('adm_paginate')){
    function adm_paginate(int $total,int $limit): array {
        $totalPages=max(1,(int)ceil($total/$limit));
        $curPage=min(max(1,(int)($_GET['p']??1)),$totalPages);
        return [$curPage,$totalPages,($curPage-1)*$limit];
    }
}
if(!function_exists('adm_params')){
    function adm_params(array $base): array {
        $out=['page'=>'admin-dashboard'];
        foreach($base as $k=>$v){ if($v!==null&&$v!==''&&$v!==false) $out[$k]=$v; }
        return $out;
    }
}

/* ---------- parameter ---------- */
$tabs=['ringkasan'=>'Ringkasan','bimbingan'=>'Bimbingan','dosen'=>'Dosen','akun'=>'Akun','log'=>'Log Akses'];
$tab=(string)($_GET['tab']??'ringkasan');
if(!isset($tabs[$tab])) $tab='ringkasan';

$statusMeta=[
    'pengajuan_judul'=>['Pengajuan Judul','#e0a800'],
    'revisi_judul'=>['Revisi Judul','#e8590c'],
    'aktif'=>['Aktif','#315d82'],
    'ditangguhkan'=>['Ditangguhkan','#8a97a3'],
    'selesai'=>['Selesai','#2f855a'],
];
$roleLabels=['mahasiswa'=>'Mahasiswa','dosen'=>'Dosen','admin'=>'Admin'];

$q=substr(trim((string)($_GET['q']??'')),0,100);
$limit=(int)($_GET['limit']??10);
if(!in_array($limit,[10,20,50],true)) $limit=10;
$statusFilter=(string)($_GET['status']??'');
if(!isset($statusMeta[$statusFilter])) $statusFilter='';
$roleFilter=(string)($_GET['role']??'');
if(!isset($roleLabels[$roleFilter])) $roleFilter='';

$old=$_SESSION['old_dosen_form']??[];
unset($_SESSION['old_dosen_form']);

/* ---------- angka ringkas (3 query untuk semua tab) ---------- */
$ub=adm_rows($conn,"SELECT COUNT(*) total,COALESCE(SUM(role='mahasiswa'),0) mahasiswa,COALESCE(SUM(role='dosen'),0) dosen,COALESCE(SUM(role='admin'),0) admin FROM users")[0]??[];
$bb=adm_rows($conn,"SELECT COUNT(*) total,COALESCE(SUM(status='pengajuan_judul'),0) pengajuan_judul,COALESCE(SUM(status='revisi_judul'),0) revisi_judul,COALESCE(SUM(status='aktif'),0) aktif,COALESCE(SUM(status='ditangguhkan'),0) ditangguhkan,COALESCE(SUM(status='selesai'),0) selesai FROM bimbingan")[0]??[];
$totalUsers=(int)($ub['total']??0);
$userCount=['mahasiswa'=>(int)($ub['mahasiswa']??0),'dosen'=>(int)($ub['dosen']??0),'admin'=>(int)($ub['admin']??0)];
$totalBimbingan=(int)($bb['total']??0);
$statusCount=[];
foreach($statusMeta as $k=>$meta) $statusCount[$k]=(int)($bb[$k]??0);
$totalReview=adm_count($conn,"SELECT COUNT(*) total
    FROM bimbingan b
    JOIN bab_skripsi bs ON bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=b.id ORDER BY x.versi DESC,x.id DESC LIMIT 1)
    WHERE b.status='aktif' AND bs.status='menunggu_review'");
$perluTindakan=$statusCount['pengajuan_judul']+$totalReview;

$baseParams=['tab'=>$tab,'q'=>$q,'status'=>$statusFilter,'role'=>$roleFilter,'limit'=>$limit!==10?$limit:null];
$backUrl=adm_url($baseParams,'&');
?>
<style>
.adm-tabs{display:flex;gap:4px;overflow-x:auto;border-bottom:1px solid #d5dde4;margin:0 0 18px}
.adm-tab{display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border:1px solid transparent;border-bottom:0;border-radius:8px 8px 0 0;color:#4c5b68;text-decoration:none;font-weight:600;white-space:nowrap}
.adm-tab:hover{background:#f3f6f9}
.adm-tab.active{background:#fff;border-color:#d5dde4;color:#1f3f5b;margin-bottom:-1px}
.adm-badge{background:#c92a2a;color:#fff;border-radius:999px;font-size:12px;line-height:1;padding:4px 8px}
.adm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.adm-stat{display:block;padding:18px 20px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
a.adm-stat:hover{box-shadow:0 4px 14px rgba(31,63,91,.12);transform:translateY(-1px)}
.adm-stat span{display:block;color:#687684;font-size:13px}
.adm-stat strong{display:block;font-size:30px;line-height:1.1;margin:6px 0}
.adm-stat.alert{border-color:#f0c36d;background:#fffaf0}
.adm-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,.8fr);gap:18px;margin-bottom:18px}
.adm-bar{display:flex;height:16px;border-radius:8px;overflow:hidden;background:#e9edf0;margin:6px 0 14px}
.adm-bar span{display:block;height:100%}
.adm-legend{display:flex;flex-wrap:wrap;gap:8px}
.adm-dot{display:inline-block;width:10px;height:10px;border-radius:50%}
.adm-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #e6ebef}
.adm-row:last-child{border-bottom:0}
.adm-row small{color:#687684}
.adm-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin:0 0 12px}
.adm-toolbar label{display:block;font-weight:600;font-size:13px;margin-bottom:5px}
.adm-toolbar input[type=search]{min-width:260px}
.adm-chips{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}
.adm-chip{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border:1px solid #d5dde4;border-radius:999px;background:#fff;color:#33424f;text-decoration:none;font-size:13px}
.adm-chip:hover{background:#f3f6f9}
.adm-chip.active{background:#315d82;border-color:#315d82;color:#fff}
.adm-chip b{font-weight:700}
.adm-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#eef2f5;color:#44525f}
.adm-pill.mahasiswa{background:#e7f0f8;color:#1f4f7a}.adm-pill.dosen{background:#e6f4ec;color:#22693f}.adm-pill.admin{background:#f3e8f7;color:#6b2b82}
.adm-title{max-width:340px;line-height:1.4}
.adm-title small{display:block;color:#687684;margin-top:2px;overflow:hidden;text-overflow:ellipsis}
.adm-mini{min-width:96px}
.adm-mini-track{height:6px;background:#e9edf0;border-radius:4px;overflow:hidden;margin:6px 0 3px}
.adm-mini-track div{height:100%;background:#315d82}
.adm-menu summary{cursor:pointer;list-style:none;display:inline-block}
.adm-menu summary::-webkit-details-marker{display:none}
.adm-menu[open] .adm-menu-panel{margin-top:10px;padding:12px;border:1px solid #e1e7ec;border-radius:8px;background:#f8fbfd;display:grid;gap:12px;min-width:250px}
.adm-menu-panel form{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0}
.adm-menu-panel select{min-width:160px}
.adm-menu-panel label{font-size:12px;font-weight:600;color:#556573;display:block;width:100%}
.adm-actions{display:flex;flex-direction:column;align-items:flex-start;gap:8px}
.adm-note{margin-top:18px}
.adm-note summary{cursor:pointer;font-weight:600}
@media(max-width:1050px){.adm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.adm-grid{grid-template-columns:1fr}}
@media(max-width:640px){.adm-stats{grid-template-columns:1fr}.adm-toolbar input[type=search]{min-width:0;width:100%}.adm-toolbar>div{flex:1 1 100%}}
</style>

<div class="page-head">
  <div>
    <h1>Dashboard Admin</h1>
    <p class="muted">Pantau pengguna, bimbingan, pengajuan judul, dan progres Bab 1–5 dalam satu tempat.</p>
  </div>
  <span class="role-badge">Administrator</span>
  <div class="dashboard-refresh">
    <a class="btn btn-primary" href="<?=adm_url(['tab'=>'dosen'])?>">+ Tambah Dosen</a>
    <button class="btn btn-secondary" type="button" data-refresh-now>Muat Ulang</button>
  </div>
</div>

<nav class="adm-tabs" aria-label="Bagian administrasi">
  <?php foreach($tabs as $key=>$label): ?>
    <a class="adm-tab <?=$tab===$key?'active':''?>" href="<?=adm_url(['tab'=>$key])?>" <?=$tab===$key?'aria-current="page"':''?>>
      <?=e($label)?>
      <?php if($key==='bimbingan'&&$perluTindakan>0): ?><span class="adm-badge" title="Perlu tindakan dosen"><?=e($perluTindakan)?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if($tab==='ringkasan'): ?>
<?php
$attention=[];
foreach(adm_rows($conn,"SELECT b.id,m.nama_lengkap nama,DATEDIFF(NOW(),b.updated_at) hari FROM bimbingan b JOIN users m ON m.id=b.mahasiswa_id WHERE b.status='pengajuan_judul' AND b.updated_at<DATE_SUB(NOW(),INTERVAL 3 DAY) ORDER BY b.updated_at ASC LIMIT 6") as $row){
    $attention[]=['id'=>(int)$row['id'],'nama'=>$row['nama'],'teks'=>'Pengajuan judul menunggu persetujuan dosen','hari'=>(int)$row['hari']];
}
foreach(adm_rows($conn,"SELECT b.id,m.nama_lengkap nama,bs.nama_bab,DATEDIFF(NOW(),bs.uploaded_at) hari FROM bimbingan b JOIN users m ON m.id=b.mahasiswa_id JOIN bab_skripsi bs ON bs.id=(SELECT x.id FROM bab_skripsi x WHERE x.bimbingan_id=b.id ORDER BY x.versi DESC,x.id DESC LIMIT 1) WHERE b.status='aktif' AND bs.status='menunggu_review' AND bs.uploaded_at<DATE_SUB(NOW(),INTERVAL 7 DAY) ORDER BY bs.uploaded_at ASC LIMIT 6") as $row){
    $attention[]=['id'=>(int)$row['id'],'nama'=>$row['nama'],'teks'=>$row['nama_bab'].' menunggu review dosen','hari'=>(int)$row['hari']];
}
usort($attention,function($a,$b){return $b['hari']<=>$a['hari'];});
$attention=array_slice($attention,0,6);
$latestUsers=adm_rows($conn,"SELECT id,nama_lengkap,role,created_at FROM users ORDER BY created_at DESC,id DESC LIMIT 5");
?>
<section class="adm-stats">
  <a class="card adm-stat" href="<?=adm_url(['tab'=>'akun','role'=>'mahasiswa'])?>"><span>Mahasiswa</span><strong><?=e($userCount['mahasiswa'])?></strong><span>Lihat daftar akun</span></a>
  <a class="card adm-stat" href="<?=adm_url(['tab'=>'dosen'])?>"><span>Dosen</span><strong><?=e($userCount['dosen'])?></strong><span>Kelola dosen pembimbing</span></a>
  <a class="card adm-stat" href="<?=adm_url(['tab'=>'bimbingan','status'=>'aktif'])?>"><span>Bimbingan Aktif</span><strong><?=e($statusCount['aktif'])?></strong><span>Dari <?=e($totalBimbingan)?> bimbingan</span></a>
  <a class="card adm-stat <?=$perluTindakan>0?'alert':''?>" href="<?=adm_url(['tab'=>'bimbingan','status'=>'pengajuan_judul'])?>"><span>Perlu Tindakan Dosen</span><strong><?=e($perluTindakan)?></strong><span><?=e($statusCount['pengajuan_judul'])?> judul · <?=e($totalReview)?> review bab</span></a>
</section>

<div class="adm-grid">
  <section class="card">
    <div class="section-heading"><div><h2>Sebaran Status Bimbingan</h2><p class="muted">Klik status untuk melihat daftarnya.</p></div></div>
    <?php if($totalBimbingan<1): ?>
      <div class="empty-state">Belum ada data bimbingan.</div>
    <?php else: ?>
      <div class="adm-bar" role="img" aria-label="Sebaran status bimbingan">
        <?php foreach($statusMeta as $k=>$meta): if($statusCount[$k]<1) continue; ?>
          <span style="width:<?=e(number_format($statusCount[$k]/$totalBimbingan*100,2,'.',''))?>%;background:<?=e($meta[1])?>" title="<?=e($meta[0].': '.$statusCount[$k])?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="adm-legend">
        <?php foreach($statusMeta as $k=>$meta): ?>
          <a class="adm-chip" href="<?=adm_url(['tab'=>'bimbingan','status'=>$k])?>"><span class="adm-dot" style="background:<?=e($meta[1])?>"></span><?=e($meta[0])?> <b><?=e($statusCount[$k])?></b></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="section-heading"><div><h2>Perlu Perhatian</h2><p class="muted">Judul &gt; 3 hari atau review bab &gt; 7 hari belum diproses.</p></div></div>
    <?php if(!$attention): ?>
      <div class="empty-state">Tidak ada yang tertunda. Semua berjalan lancar.</div>
    <?php else: foreach($attention as $a): ?>
      <div class="adm-row">
        <div><strong><?=e($a['nama'])?></strong><br><small><?=e($a['teks'])?> · <?=e($a['hari'])?> hari</small></div>
        <a class="btn btn-secondary" href="?page=bimbingan-detail&amp;id=<?=e($a['id'])?>">Buka</a>
      </div>
    <?php endforeach; endif; ?>
  </section>
</div>

<section class="card">
  <div class="section-heading"><div><h2>Akun Terbaru</h2><p class="muted">5 akun yang terakhir dibuat.</p></div><a class="btn btn-secondary" href="<?=adm_url(['tab'=>'akun'])?>">Semua akun</a></div>
  <?php if(!$latestUsers): ?>
    <div class="empty-state">Belum ada pengguna.</div>
  <?php else: foreach($latestUsers as $lu): ?>
    <div class="adm-row">
      <div><strong><?=e($lu['nama_lengkap'])?></strong> <span class="adm-pill <?=e($lu['role'])?>"><?=e($roleLabels[$lu['role']]??ucfirst($lu['role']))?></span></div>
      <small><?=e(formatDate($lu['created_at']))?></small>
    </div>
  <?php endforeach; endif; ?>
</section>

<details class="card adm-note">
  <summary>Tentang peran admin</summary>
  <p class="muted" style="margin-top:10px">Admin menangani administrasi pengguna dan bimbingan. Persetujuan judul serta review dan ACC Bab 1–5 tetap mengikuti dosen pembimbing. Perubahan dosen atau status dari panel ini mengirim notifikasi kepada mahasiswa terkait.</p>
</details>

<?php elseif($tab==='bimbingan'): ?>
<?php
$where=[];$types='';$vals=[];
if($statusFilter!==''){$where[]='b.status=?';$types.='s';$vals[]=$statusFilter;}
if($q!==''){$where[]='(m.nama_lengkap LIKE ? OR b.judul_skripsi LIKE ? OR d.nama_lengkap LIKE ?)';$like='%'.$q.'%';$types.='sss';array_push($vals,$like,$like,$like);}
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$from="FROM bimbingan b JOIN users m ON m.id=b.mahasiswa_id JOIN users d ON d.id=b.dosen_id $whereSql";
$total=adm_count($conn,"SELECT COUNT(*) total $from",$types,$vals);
[$curPage,$totalPages,$offset]=adm_paginate($total,$limit);
$rows=adm_rows($conn,"SELECT b.id,b.judul_skripsi,b.status,b.updated_at,b.dosen_id,m.nama_lengkap mahasiswa_nama,d.nama_lengkap dosen_nama,
    (SELECT COUNT(*) FROM bab_skripsi bs WHERE bs.bimbingan_id=b.id AND bs.status='disetujui' AND bs.nama_bab IN ('Bab 1','Bab 2','Bab 3','Bab 4','Bab 5')) AS bab_disetujui
    $from ORDER BY b.updated_at DESC,b.id DESC LIMIT ? OFFSET ?",$types.'ii',array_merge($vals,[$limit,$offset]));
$dosenOptions=adm_rows($conn,"SELECT id,nama_lengkap FROM users WHERE role='dosen' ORDER BY nama_lengkap ASC");
$backUrl=adm_url($baseParams+['p'=>$curPage>1?$curPage:null],'&');
?>
<section class="card">
  <div class="section-heading"><div><h2>Manajemen Bimbingan</h2><p class="muted">Cari, saring, lalu klik <strong>Kelola</strong> untuk mengganti dosen atau status.</p></div></div>

  <form method="get" class="adm-toolbar">
    <input type="hidden" name="page" value="admin-dashboard"><input type="hidden" name="tab" value="bimbingan"><input type="hidden" name="status" value="<?=e($statusFilter)?>">
    <div><label for="q">Cari mahasiswa, judul, atau dosen</label><input id="q" type="search" name="q" value="<?=e($q)?>" placeholder="Ketik lalu tekan Cari"></div>
    <div><label for="limit">Per halaman</label><select id="limit" name="limit" onchange="this.form.submit()"><?php foreach([10,20,50] as $n): ?><option value="<?=$n?>" <?=$limit===$n?'selected':''?>><?=$n?></option><?php endforeach; ?></select></div>
    <button class="btn btn-secondary" type="submit">Cari</button>
    <?php if($q!==''||$statusFilter!==''): ?><a class="btn btn-secondary" href="<?=adm_url(['tab'=>'bimbingan'])?>">Reset</a><?php endif; ?>
  </form>

  <div class="adm-chips">
    <a class="adm-chip <?=$statusFilter===''?'active':''?>" href="<?=adm_url(['tab'=>'bimbingan','q'=>$q,'limit'=>$limit!==10?$limit:null])?>">Semua <b><?=e($totalBimbingan)?></b></a>
    <?php foreach($statusMeta as $k=>$meta): ?>
      <a class="adm-chip <?=$statusFilter===$k?'active':''?>" href="<?=adm_url(['tab'=>'bimbingan','status'=>$k,'q'=>$q,'limit'=>$limit!==10?$limit:null])?>"><?=e($meta[0])?> <b><?=e($statusCount[$k])?></b></a>
    <?php endforeach; ?>
  </div>

  <?php if(!$rows): ?>
    <div class="empty-state">Tidak ada bimbingan yang cocok.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Mahasiswa &amp; Judul</th><th>Pembimbing</th><th>Progress</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach($rows as $b): $progress=min(100,(int)$b['bab_disetujui']*20); ?>
        <tr>
          <td><div class="adm-title"><strong><?=e($b['mahasiswa_nama'])?></strong><small><?=e($b['judul_skripsi'])?></small></div></td>
          <td><?=e($b['dosen_nama'])?></td>
          <td class="adm-mini"><strong><?=e($progress)?>%</strong><div class="adm-mini-track"><div style="width:<?=e($progress)?>%"></div></div><small class="muted"><?=e((int)$b['bab_disetujui'])?>/5 bab ACC</small></td>
          <td><?=getStatusBadge($b['status'])?><br><small class="muted"><?=e(formatDate($b['updated_at']))?></small></td>
          <td>
            <div class="adm-actions">
              <a class="btn btn-secondary" href="?page=bimbingan-detail&amp;id=<?=e($b['id'])?>">Detail</a>
              <details class="adm-menu">
                <summary class="btn btn-secondary">Kelola ▾</summary>
                <div class="adm-menu-panel">
                  <form method="post">
                    <input type="hidden" name="action" value="admin_assign_dosen"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="bimbingan_id" value="<?=e($b['id'])?>"><input type="hidden" name="back" value="<?=e($backUrl)?>">
                    <label>Dosen pembimbing</label>
                    <select name="dosen_id" aria-label="Dosen pembimbing"><?php foreach($dosenOptions as $d): ?><option value="<?=e($d['id'])?>" <?=((int)$d['id']===(int)$b['dosen_id']?'selected':'')?>><?=e($d['nama_lengkap'])?></option><?php endforeach; ?></select>
                    <button class="btn btn-secondary" type="submit">Simpan</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="action" value="admin_bimbingan_status"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="bimbingan_id" value="<?=e($b['id'])?>"><input type="hidden" name="back" value="<?=e($backUrl)?>">
                    <label>Status bimbingan</label>
                    <select name="status" aria-label="Status bimbingan">
                      <?php if($b['status']==='revisi_judul'): ?><option value="" disabled selected>Revisi Judul (oleh dosen)</option><?php endif; ?>
                      <option value="pengajuan_judul" <?=$b['status']==='pengajuan_judul'?'selected':''?>>Pengajuan Judul</option>
                      <option value="aktif" <?=$b['status']==='aktif'?'selected':''?>>Aktif</option>
                      <option value="selesai" <?=$b['status']==='selesai'?'selected':''?>>Selesai</option>
                      <option value="ditangguhkan" <?=$b['status']==='ditangguhkan'?'selected':''?>>Ditangguhkan</option>
                    </select>
                    <button class="btn btn-secondary" type="submit">Simpan</button>
                  </form>
                </div>
              </details>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?=render_pagination($curPage,$totalPages,$total,$offset,$limit,adm_params($baseParams),'p','','bimbingan')?>
  <?php endif; ?>
</section>

<?php elseif($tab==='dosen'): ?>
<?php
$where='';$types='';$vals=[];
if($q!==''){$where="AND (u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.no_telp LIKE ?)";$like='%'.$q.'%';$types='ssss';$vals=[$like,$like,$like,$like];}
$total=adm_count($conn,"SELECT COUNT(*) total FROM users u WHERE u.role='dosen' $where",$types,$vals);
[$curPage,$totalPages,$offset]=adm_paginate($total,$limit);
$rows=adm_rows($conn,"SELECT u.id,u.nama_lengkap,u.username,u.email,u.no_telp,(SELECT COUNT(*) FROM bimbingan b WHERE b.dosen_id=u.id) AS total_bimbingan FROM users u WHERE u.role='dosen' $where ORDER BY u.nama_lengkap ASC LIMIT ? OFFSET ?",$types.'ii',array_merge($vals,[$limit,$offset]));
?>
<div class="adm-grid">
  <section class="card">
    <div class="section-heading"><div><h2>Daftar Dosen</h2><p class="muted">Akun dosen yang dapat dipilih sebagai pembimbing.</p></div></div>
    <form method="get" class="adm-toolbar">
      <input type="hidden" name="page" value="admin-dashboard"><input type="hidden" name="tab" value="dosen">
      <div><label for="q">Cari nama, username, email, atau telepon</label><input id="q" type="search" name="q" value="<?=e($q)?>"></div>
      <button class="btn btn-secondary" type="submit">Cari</button>
      <?php if($q!==''): ?><a class="btn btn-secondary" href="<?=adm_url(['tab'=>'dosen'])?>">Reset</a><?php endif; ?>
    </form>
    <?php if(!$rows): ?>
      <div class="empty-state"><?=$q!==''?'Tidak ada dosen yang cocok.':'Belum ada akun dosen. Tambahkan lewat formulir di samping.'?></div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Nama</th><th>Kontak</th><th>Bimbingan</th></tr></thead>
        <tbody>
        <?php foreach($rows as $dl): ?>
          <tr>
            <td><strong><?=e($dl['nama_lengkap'])?></strong><br><small class="muted"><?=e($dl['username'])?></small></td>
            <td><?=e($dl['email'])?><?php if(!empty($dl['no_telp'])): ?><br><small class="muted"><?=e($dl['no_telp'])?></small><?php endif; ?></td>
            <td><a href="<?=adm_url(['tab'=>'bimbingan','q'=>$dl['nama_lengkap']])?>"><?=e((int)$dl['total_bimbingan'])?> mahasiswa</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?=render_pagination($curPage,$totalPages,$total,$offset,$limit,adm_params($baseParams),'p','','dosen')?>
    <?php endif; ?>
  </section>

  <section class="card" id="tambah-dosen">
    <div class="section-heading"><div><h2>Tambah Akun Dosen</h2><p class="muted">Password awal sampaikan langsung kepada dosen.</p></div></div>
    <form method="post" action="<?=adm_url(['tab'=>'dosen'])?>" autocomplete="off">
      <input type="hidden" name="action" value="admin_create_dosen">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <div class="form-group"><label for="dosen_nama">Nama Lengkap</label><input id="dosen_nama" name="nama_lengkap" required maxlength="150" value="<?=e($old['nama_lengkap']??'')?>"></div>
      <div class="form-group"><label for="dosen_username">Username</label><input id="dosen_username" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" title="Huruf, angka, titik, garis bawah, atau strip (3–50 karakter)" value="<?=e($old['username']??'')?>"></div>
      <div class="form-group"><label for="dosen_email">Email</label><input id="dosen_email" name="email" type="email" required maxlength="100" value="<?=e($old['email']??'')?>"></div>
      <div class="form-group"><label for="dosen_telp">No. Telepon (opsional)</label><input id="dosen_telp" name="no_telp" maxlength="15" value="<?=e($old['no_telp']??'')?>"></div>
      <div class="form-group"><label for="dosen_password">Password Awal</label><input id="dosen_password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
      <div class="form-group"><label for="dosen_confirm">Konfirmasi Password</label><input id="dosen_confirm" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></div>
      <button class="btn btn-success" type="submit">Buat Akun Dosen</button>
    </form>
  </section>
</div>

<?php elseif($tab==='akun'): ?>
<?php
$where=[];$types='';$vals=[];
if($roleFilter!==''){$where[]='role=?';$types.='s';$vals[]=$roleFilter;}
if($q!==''){$where[]='(nama_lengkap LIKE ? OR username LIKE ? OR email LIKE ?)';$like='%'.$q.'%';$types.='sss';array_push($vals,$like,$like,$like);}
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$total=adm_count($conn,"SELECT COUNT(*) total FROM users $whereSql",$types,$vals);
[$curPage,$totalPages,$offset]=adm_paginate($total,$limit);
$rows=adm_rows($conn,"SELECT id,username,email,role,nama_lengkap,created_at FROM users $whereSql ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?",$types.'ii',array_merge($vals,[$limit,$offset]));
$backUrl=adm_url($baseParams+['p'=>$curPage>1?$curPage:null],'&');
?>
<section class="card">
  <div class="section-heading"><div><h2>Manajemen Akun</h2><p class="muted">Buka akun mahasiswa untuk menelusuri kendala, atau hapus akun beserta data terkait.</p></div></div>

  <form method="get" class="adm-toolbar">
    <input type="hidden" name="page" value="admin-dashboard"><input type="hidden" name="tab" value="akun"><input type="hidden" name="role" value="<?=e($roleFilter)?>">
    <div><label for="q">Cari nama, username, atau email</label><input id="q" type="search" name="q" value="<?=e($q)?>"></div>
    <div><label for="limit">Per halaman</label><select id="limit" name="limit" onchange="this.form.submit()"><?php foreach([10,20,50] as $n): ?><option value="<?=$n?>" <?=$limit===$n?'selected':''?>><?=$n?></option><?php endforeach; ?></select></div>
    <button class="btn btn-secondary" type="submit">Cari</button>
    <?php if($q!==''||$roleFilter!==''): ?><a class="btn btn-secondary" href="<?=adm_url(['tab'=>'akun'])?>">Reset</a><?php endif; ?>
  </form>

  <div class="adm-chips">
    <a class="adm-chip <?=$roleFilter===''?'active':''?>" href="<?=adm_url(['tab'=>'akun','q'=>$q,'limit'=>$limit!==10?$limit:null])?>">Semua <b><?=e($totalUsers)?></b></a>
    <?php foreach($roleLabels as $k=>$label): ?>
      <a class="adm-chip <?=$roleFilter===$k?'active':''?>" href="<?=adm_url(['tab'=>'akun','role'=>$k,'q'=>$q,'limit'=>$limit!==10?$limit:null])?>"><?=e($label)?> <b><?=e($userCount[$k])?></b></a>
    <?php endforeach; ?>
  </div>

  <?php if(!$rows): ?>
    <div class="empty-state">Tidak ada akun yang cocok.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Dibuat</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach($rows as $u): ?>
        <tr>
          <td><strong><?=e($u['nama_lengkap'])?></strong><br><small class="muted"><?=e($u['username'])?></small></td>
          <td><?=e($u['email'])?></td>
          <td><span class="adm-pill <?=e($u['role'])?>"><?=e($roleLabels[$u['role']]??ucfirst($u['role']))?></span></td>
          <td><?=e(formatDate($u['created_at']))?></td>
          <td>
            <?php if((int)$u['id']===$uid): ?>
              <span class="muted">Akun aktif</span>
            <?php else: ?>
              <details class="adm-menu">
                <summary class="btn btn-secondary">Aksi ▾</summary>
                <div class="adm-menu-panel">
                  <?php if($u['role']==='mahasiswa'): ?>
                    <div><label>Buka akun mahasiswa</label><?=impersonate_form((int)$u['id'],(string)$u['nama_lengkap'])?></div>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm(<?=e(json_encode('Hapus akun '.$u['nama_lengkap'].'? Data bimbingan, revisi, notifikasi, dan file terkait akun ini juga akan dihapus. Tindakan ini tidak dapat dibatalkan.',JSON_UNESCAPED_UNICODE))?>);">
                    <input type="hidden" name="action" value="admin_delete_user"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="user_id" value="<?=e($u['id'])?>"><input type="hidden" name="back" value="<?=e($backUrl)?>">
                    <button class="btn btn-danger" type="submit">Hapus Akun</button>
                  </form>
                </div>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?=render_pagination($curPage,$totalPages,$total,$offset,$limit,adm_params($baseParams),'p','','akun')?>
  <?php endif; ?>
</section>

<?php elseif($tab==='log'): ?>
<?php
$logReady=true;$rows=[];$total=0;$curPage=1;$totalPages=1;
try{
    $where='';$types='';$vals=[];
    if($q!==''){$where='WHERE (impersonator_nama LIKE ? OR target_nama LIKE ?)';$like='%'.$q.'%';$types='ss';$vals=[$like,$like];}
    $total=adm_count($conn,"SELECT COUNT(*) total FROM impersonation_logs $where",$types,$vals);
    [$curPage,$totalPages,$offset]=adm_paginate($total,$limit);
    $rows=adm_rows($conn,"SELECT impersonator_nama,impersonator_role,target_nama,mode,ip_address,started_at,ended_at,TIMESTAMPDIFF(MINUTE,started_at,ended_at) menit FROM impersonation_logs $where ORDER BY id DESC LIMIT ? OFFSET ?",$types.'ii',array_merge($vals,[$limit,$offset]));
}catch(Throwable $ex){$logReady=false;}
?>
<section class="card">
  <div class="section-heading"><div><h2>Log Akses Akun</h2><p class="muted">Riwayat pembukaan akun mahasiswa oleh admin dan dosen (login sebagai).</p></div></div>
  <?php if(!$logReady): ?>
    <div class="empty-state">Tabel log belum tersedia. Jalankan <strong>migration_add_impersonation_logs.sql</strong> di database.</div>
  <?php else: ?>
    <form method="get" class="adm-toolbar">
      <input type="hidden" name="page" value="admin-dashboard"><input type="hidden" name="tab" value="log">
      <div><label for="q">Cari nama pelaku atau mahasiswa</label><input id="q" type="search" name="q" value="<?=e($q)?>"></div>
      <div><label for="limit">Per halaman</label><select id="limit" name="limit" onchange="this.form.submit()"><?php foreach([10,20,50] as $n): ?><option value="<?=$n?>" <?=$limit===$n?'selected':''?>><?=$n?></option><?php endforeach; ?></select></div>
      <button class="btn btn-secondary" type="submit">Cari</button>
      <?php if($q!==''): ?><a class="btn btn-secondary" href="<?=adm_url(['tab'=>'log'])?>">Reset</a><?php endif; ?>
    </form>
    <?php if(!$rows): ?>
      <div class="empty-state">Belum ada catatan akses.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Waktu</th><th>Pelaku</th><th>Membuka akun</th><th>Mode</th><th>Durasi</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach($rows as $lg): ?>
          <tr>
            <td><?=e(formatDateTime($lg['started_at']))?></td>
            <td><strong><?=e($lg['impersonator_nama'])?></strong><br><small class="muted"><?=e(ucfirst($lg['impersonator_role']))?></small></td>
            <td><?=e($lg['target_nama'])?></td>
            <td><span class="adm-pill"><?=e($lg['mode']==='act'?'Login penuh':'Lihat saja')?></span></td>
            <td><?=$lg['ended_at']!==null?e(((int)$lg['menit']).' menit'):'<span class="muted">tidak ditutup</span>'?></td>
            <td><small class="muted"><?=e($lg['ip_address']??'-')?></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?=render_pagination($curPage,$totalPages,$total,$offset,$limit,adm_params($baseParams),'p','','catatan')?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<script>
(function(){
  const button=document.querySelector('[data-refresh-now]');
  if(button) button.addEventListener('click',function(){ window.location.reload(); });
})();
</script>
