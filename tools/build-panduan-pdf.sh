#!/bin/sh
# Membuat public/docs/panduan-mahasiswa.pdf dari src/views/panduan-isi.html (satu sumber untuk beranda dan PDF).
# Jalankan setiap isi panduan berubah. Membutuhkan Chromium/Chrome: CHROME=/path/ke/chrome sh tools/build-panduan-pdf.sh
set -e
cd "$(dirname "$0")/.."
CHROME="${CHROME:-chromium}"
TMP="$(mktemp -d)"
OUT="$TMP/panduan.html"
{
cat <<'HEAD'
<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Panduan Mahasiswa MyThesis</title>
<style>
@page{size:A4;margin:18mm 16mm}
body{font-family:"DejaVu Sans",Arial,sans-serif;color:#18212F;font-size:11pt;line-height:1.55}
header{border-bottom:3px solid #7B4654;padding-bottom:10px;margin-bottom:18px}
header small{color:#7B4654;font-weight:700;letter-spacing:.12em}
h1{margin:4px 0 4px;font-size:23pt;color:#101722}
header p{margin:0;color:#5C6878}
details{break-inside:avoid;margin:0 0 14px}
summary{list-style:none;font-weight:700;font-size:13.5pt;color:#101722;margin-bottom:6px}
summary span{display:inline-block;width:24px;height:24px;line-height:24px;margin-right:8px;border-radius:50%;background:#7B4654;color:#fff;text-align:center;font-size:11pt}
.guide-body{margin-left:32px}
ol,ul{margin:4px 0;padding-left:20px}li{margin:3px 0}
.guide-note{background:#F7F8FA;border-left:4px solid #7B4654;padding:6px 10px;margin:8px 0}
dt{font-weight:700;margin-top:6px}dd{margin:0 0 4px}
footer{margin-top:20px;border-top:1px solid #C8CFD8;padding-top:8px;color:#5C6878;font-size:9.5pt}
</style></head><body>
<header><small>MYTHESIS</small><h1>Panduan Mahasiswa</h1><p>Langkah demi langkah menyelesaikan bimbingan skripsi di MyThesis, dari pendaftaran sampai seminar proposal.</p></header>
HEAD
sed 's/<details class="guide-step" data-guide-step>/<details open>/' src/views/panduan-isi.html
cat <<'TAIL'
<footer>Panduan ini menjelaskan alur umum MyThesis. Jika program studi atau dosen Anda menetapkan aturan berbeda, ikuti ketentuan tersebut. Bantuan lebih lanjut melalui administrator.</footer>
</body></html>
TAIL
} > "$OUT"
mkdir -p public/docs
"$CHROME" --headless --no-sandbox --disable-gpu --no-pdf-header-footer --print-to-pdf=public/docs/panduan-mahasiswa.pdf "file://$OUT"
rm -rf "$TMP"
echo "OK: public/docs/panduan-mahasiswa.pdf"
