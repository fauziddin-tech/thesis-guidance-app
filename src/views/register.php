<?php
$registerMessage='';$registerType='danger';
$academicReady=academic_ready($conn);
$prodiList=[];
if($academicReady){$prodiResult=$conn->query('SELECT id,kode,nama,jenjang FROM program_studi ORDER BY jenjang,nama');$prodiList=$prodiResult?$prodiResult->fetch_all(MYSQLI_ASSOC):[];}
$prodiIds=array_map('intval',array_column($prodiList,'id'));
$registrationClosed=$academicReady&&!$prodiList;
$angkatanOptions=angkatan_options();
$lecturerChoices=lecturer_options($conn);
$activePeriodLabel='';
if($academicReady){$activeResult=$conn->query('SELECT tahun_ajaran,semester FROM periode_akademik WHERE is_aktif=1 ORDER BY id DESC LIMIT 1');$activeRow=$activeResult?$activeResult->fetch_assoc():null;if($activeRow)$activePeriodLabel=period_label($activeRow);}
$lecturerIds=array_map('intval',array_column($lecturerChoices,'id'));
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim($_POST['username']??'');$email=trim($_POST['email']??'');$nama=trim($_POST['nama_lengkap']??'');$password=$_POST['password']??'';$confirm=$_POST['confirm_password']??'';$role='mahasiswa';$telp=trim($_POST['no_telp']??'');
    $nim=strtoupper(trim((string)($_POST['nim']??'')));$prodiId=(int)($_POST['prodi_id']??0);$angkatan=(int)($_POST['angkatan']??0);
    $lecturerId=(int)($_POST['dosen_id']??0);$title=trim(preg_replace('/\s+/',' ',(string)($_POST['judul_skripsi']??'')));
    if(!verify_csrf()){$registerMessage='Sesi formulir telah berakhir. Muat ulang halaman dan coba kembali.';}
    elseif($registrationClosed){$registerMessage='Pendaftaran belum dibuka karena program studi belum diatur oleh administrator.';}
    elseif(!preg_match('/^[A-Za-z0-9._-]{4,100}$/',$username)){$registerMessage='Username minimal 4 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';}
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)||$nama===''||strlen($password)<8){$registerMessage='Lengkapi data dengan benar. Password minimal 8 karakter.';}
    elseif($password!==$confirm){$registerMessage='Konfirmasi password tidak sama.';}
    elseif($academicReady&&!valid_nim($nim)){$registerMessage='NIM wajib diisi, 5–30 karakter, dan hanya berisi huruf, angka, titik, atau tanda hubung.';}
    elseif($academicReady&&!in_array($prodiId,$prodiIds,true)){$registerMessage='Pilih program studi Anda.';}
    elseif($academicReady&&!in_array($angkatan,$angkatanOptions,true)){$registerMessage='Pilih tahun angkatan Anda.';}
    elseif($lecturerChoices&&!in_array($lecturerId,$lecturerIds,true)){$registerMessage='Pilih dosen pembimbing Anda.';}
    elseif($lecturerChoices&&!valid_thesis_title($title)){$registerMessage='Judul skripsi wajib diisi, 10–255 karakter.';}
    else{
        $stmt=$conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');$stmt->bind_param('ss',$username,$email);$stmt->execute();$exists=$stmt->get_result()->num_rows>0;$stmt->close();
        $nimExists=false;
        if(!$exists&&$academicReady){$stmt=$conn->prepare('SELECT id FROM users WHERE nim=? LIMIT 1');$stmt->bind_param('s',$nim);$stmt->execute();$nimExists=$stmt->get_result()->num_rows>0;$stmt->close();}
        if($exists){$registerMessage='Username atau email sudah terdaftar.';}
        elseif($nimExists){$registerMessage='NIM sudah terdaftar. Hubungi administrator jika NIM tersebut milik Anda.';}
        else{
            $hash=password_hash($password,PASSWORD_DEFAULT);
            if($academicReady){$stmt=$conn->prepare('INSERT INTO users(username,email,password,role,nama_lengkap,nim,prodi_id,angkatan,no_telp) VALUES(?,?,?,?,?,?,?,?,?)');$stmt->bind_param('ssssssiis',$username,$email,$hash,$role,$nama,$nim,$prodiId,$angkatan,$telp);}
            else{$stmt=$conn->prepare('INSERT INTO users(username,email,password,role,nama_lengkap,no_telp) VALUES(?,?,?,?,?,?)');$stmt->bind_param('ssssss',$username,$email,$hash,$role,$nama,$telp);}
            $conn->begin_transaction();
            $ok=$stmt->execute();$newUserId=(int)$conn->insert_id;$stmt->close();
            if($ok&&$lecturerChoices)$ok=create_student_guidance($conn,$newUserId,$nama,$lecturerId,$title,false);
            if($ok)$conn->commit();else $conn->rollback();
            $mailSent=false;
            if($ok){
                // Email dikirim setelah data tersimpan permanen.
                $lecturerName='';foreach($lecturerChoices as $lecturer){if((int)$lecturer['id']===$lecturerId){$lecturerName=$lecturer['nama_lengkap'];break;}}
                $prodiName='';foreach($prodiList as $prodi){if((int)$prodi['id']===$prodiId){$prodiName=prodi_label($prodi);break;}}
                $details=['Username: '.$username,'Email: '.$email];
                if($academicReady){$details[]='NIM: '.$nim;$details[]='Program Studi: '.$prodiName;$details[]='Angkatan: '.$angkatan;}
                if($lecturerChoices){$details[]='Dosen Pembimbing: '.$lecturerName;$details[]='Judul Skripsi: '.$title;}
                $mailSent=send_user_email($conn,$newUserId,'Akun MyThesis Anda telah dibuat','Selamat datang di MyThesis',"Terima kasih telah mendaftar. Berikut informasi akun Anda:\n\n".implode("\n",$details)."\n\nMasuk menggunakan username atau email di atas dengan password yang Anda buat saat mendaftar. Demi keamanan, password tidak dikirim melalui email.",'?page=login','Masuk ke MyThesis');
                if($lecturerChoices)send_guidance_selected_email($conn,$lecturerId,$nama,$title);
            }
            $registerMessage=$ok?'Pendaftaran berhasil'.($lecturerChoices?' dan dosen pembimbing Anda telah diberi notifikasi':'').'.'.($mailSent?' Rincian akun telah dikirim ke '.$email.'.':'').' Silakan masuk menggunakan akun Anda.':'Pendaftaran belum berhasil. Silakan coba kembali.';$registerType=$ok?'success':'danger';
        }
    }
}
$selectedProdi=(string)($_POST['prodi_id']??'');$selectedLecturer=(string)($_POST['dosen_id']??'');$selectedAngkatan=(string)($_POST['angkatan']??'');
?>
<div class="auth-card auth-card-wide card"><span class="eyebrow">PENDAFTARAN MAHASISWA</span><h1>Buat akun MyThesis</h1><p>Lengkapi data berikut untuk memulai proses bimbingan skripsi. Akun dosen dibuat oleh administrator.</p><?php if($registrationClosed): ?><div class="alert alert-danger" role="alert">Pendaftaran belum dibuka karena program studi belum diatur oleh administrator.</div><?php endif; ?><?php if($registerMessage!==''): ?><div class="alert alert-<?=h($registerType)?>" role="alert"><?=h($registerMessage)?><?php if($registerType==='success'): ?> <a href="?page=login">Masuk sekarang</a><?php endif; ?></div><?php endif; ?><form method="post"><?=csrf_field()?><fieldset><legend>Data diri</legend><div class="form-grid"><div class="form-group"><label for="nama_lengkap">Nama Lengkap</label><input id="nama_lengkap" name="nama_lengkap" autocomplete="name" value="<?=h($_POST['nama_lengkap']??'')?>" required maxlength="150"></div><div class="form-group"><label for="no_telp">No. Telepon</label><input id="no_telp" name="no_telp" type="tel" autocomplete="tel" value="<?=h($_POST['no_telp']??'')?>" maxlength="20" placeholder="Contoh: 081234567890"></div></div></fieldset><?php if($academicReady): ?><fieldset><legend>Data akademik</legend><div class="form-grid"><div class="form-group"><label for="nim">NIM</label><input id="nim" name="nim" value="<?=h($_POST['nim']??'')?>" required minlength="5" maxlength="30" autocomplete="off" placeholder="Contoh: 2104012345"><small class="field-help">Sesuai kartu tanda mahasiswa.</small></div><div class="form-group"><label for="angkatan">Tahun Masuk (Angkatan)</label><select id="angkatan" name="angkatan" required><option value="">Pilih angkatan</option><?php foreach($angkatanOptions as $year): ?><option value="<?=(int)$year?>"<?=$selectedAngkatan===(string)$year?' selected':''?>><?=(int)$year?></option><?php endforeach; ?></select><small class="field-help">Tahun pertama kali terdaftar sebagai mahasiswa.</small></div></div><div class="form-group"><label for="prodi_id">Program Studi</label><select id="prodi_id" name="prodi_id" required><option value="">Pilih program studi</option><?php foreach($prodiList as $prodi): ?><option value="<?=(int)$prodi['id']?>"<?=$selectedProdi===(string)$prodi['id']?' selected':''?>><?=h(prodi_label($prodi))?></option><?php endforeach; ?></select></div></fieldset><?php endif; ?><?php if($lecturerChoices): ?><fieldset><legend>Bimbingan skripsi</legend><?php if($activePeriodLabel!==''): ?><div class="period-note"><strong>Periode bimbingan aktif: <?=h($activePeriodLabel)?></strong><span>Bimbingan Anda tercatat mulai periode ini. Periode berbeda dengan tahun masuk (angkatan) di atas. Jika skripsi belum selesai saat periode berganti, bimbingan tetap berlanjut tanpa perlu mendaftar ulang.</span></div><?php endif; ?><div class="form-group"><label for="dosen_id">Dosen Pembimbing</label><select id="dosen_id" name="dosen_id" required><option value="">Pilih dosen pembimbing</option><?php foreach($lecturerChoices as $lecturer): ?><option value="<?=(int)$lecturer['id']?>"<?=$selectedLecturer===(string)$lecturer['id']?' selected':''?>><?=h($lecturer['nama_lengkap'])?></option><?php endforeach; ?></select><small class="field-help">Pilih dosen yang telah ditetapkan sebagai pembimbing Anda.</small></div><div class="form-group"><label for="judul_skripsi">Judul Skripsi</label><textarea id="judul_skripsi" name="judul_skripsi" minlength="10" maxlength="255" rows="2" required placeholder="Tuliskan judul skripsi yang diajukan"><?=h($_POST['judul_skripsi']??'')?></textarea><small class="field-help">Judul dapat diperbarui kemudian oleh administrator.</small></div></fieldset><?php endif; ?><fieldset><legend>Informasi akun</legend><div class="form-grid"><div class="form-group"><label for="username">Username</label><input id="username" name="username" autocomplete="username" value="<?=h($_POST['username']??'')?>" pattern="[A-Za-z0-9._-]{4,100}" required maxlength="100"><small class="field-help">Minimal 4 karakter; tanpa spasi.</small></div><div class="form-group"><label for="email">Email aktif</label><input id="email" name="email" type="email" autocomplete="email" value="<?=h($_POST['email']??'')?>" required maxlength="100"></div></div><div class="account-role"><span>Peran akun</span><strong>Mahasiswa</strong></div></fieldset><fieldset><legend>Keamanan akun</legend><div class="form-grid"><div class="form-group"><label for="register_password">Password</label><div class="password-field"><input id="register_password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required><button type="button" data-password-toggle aria-controls="register_password">Lihat</button></div><small class="field-help">Minimal 8 karakter.</small></div><div class="form-group"><label for="confirm_password">Konfirmasi Password</label><div class="password-field"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required><button type="button" data-password-toggle aria-controls="confirm_password">Lihat</button></div></div></div></fieldset><button class="btn btn-primary btn-block" type="submit"<?=$registrationClosed?' disabled':''?>>Buat Akun</button></form><p class="auth-switch">Sudah punya akun? <a href="?page=login">Masuk ke MyThesis</a></p></div>
