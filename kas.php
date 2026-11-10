<?php
require_once __DIR__ . '/config/koneksi.php';
require_role(['admin', 'bendahara']);

$user = current_user();

$kategori = [
    'masuk'  => ['Infaq Jumat', 'Donasi', 'Zakat', 'Sedekah', 'Wakaf', 'Lainnya'],
    'keluar' => ['Listrik & Air', 'Kebersihan', 'Perawatan', 'Honor Petugas', 'Kegiatan', 'Lainnya'],
];

/* ---------- Proses POST (simpan / hapus) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        header('Location: kas.php');
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $lama = db_one('SELECT jenis, jumlah FROM kas WHERE id = ?', 'i', [$id]);
        if (!$lama) {
            set_flash('error', 'Data transaksi tidak ditemukan.');
        } else {
            $efek = $lama['jenis'] === 'masuk' ? -(float)$lama['jumlah'] : (float)$lama['jumlah'];
            if (saldo_kas() + $efek < 0) {
                set_flash('error', 'Transaksi tidak dapat dihapus karena saldo kas akan menjadi minus.');
            } else {
                db_exec('DELETE FROM kas WHERE id = ?', 'i', [$id]);
                set_flash('sukses', 'Transaksi berhasil dihapus.');
            }
        }
        header('Location: kas.php');
        exit;
    }

    if ($aksi === 'simpan') {
        $id         = (int)($_POST['id'] ?? 0);
        $tanggal    = trim($_POST['tanggal'] ?? '');
        $jenis      = $_POST['jenis'] ?? '';
        $kat        = trim($_POST['kategori'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $jumlah_raw = trim($_POST['jumlah'] ?? '');

        $err = '';
        if (!valid_date($tanggal)) {
            $err = 'Tanggal tidak valid.';
        } elseif (!in_array($jenis, ['masuk', 'keluar'], true)) {
            $err = 'Jenis transaksi tidak valid.';
        } elseif (!in_array($kat, $kategori[$jenis], true)) {
            $err = 'Kategori tidak valid untuk jenis transaksi tersebut.';
        } elseif (!is_numeric($jumlah_raw) || (float)$jumlah_raw <= 0) {
            $err = 'Jumlah harus berupa angka lebih dari 0.';
        } elseif ((float)$jumlah_raw > 9999999999999) {
            $err = 'Jumlah terlalu besar.';
        } elseif (mb_strlen($keterangan) > 255) {
            $err = 'Keterangan maksimal 255 karakter.';
        }

        $jumlah = (float)$jumlah_raw;

        if ($err === '') {
            $saldo_baru = saldo_kas();
            if ($id > 0) {
                $lama = db_one('SELECT jenis, jumlah FROM kas WHERE id = ?', 'i', [$id]);
                if (!$lama) {
                    $err = 'Data transaksi tidak ditemukan.';
                } else {
                    $saldo_baru -= $lama['jenis'] === 'masuk' ? (float)$lama['jumlah'] : -(float)$lama['jumlah'];
                }
            }
            if ($err === '') {
                $saldo_baru += $jenis === 'masuk' ? $jumlah : -$jumlah;
                if ($saldo_baru < 0) {
                    $err = 'Saldo kas tidak mencukupi. Saldo akan menjadi ' . rupiah($saldo_baru) . '.';
                }
            }
        }

        if ($err !== '') {
            set_flash('error', $err);
            header('Location: kas.php' . ($id > 0 ? '?edit=' . $id : ''));
            exit;
        }

        if ($id > 0) {
            db_exec(
                'UPDATE kas SET tanggal=?, jenis=?, kategori=?, keterangan=?, jumlah=? WHERE id=?',
                'ssssdi', [$tanggal, $jenis, $kat, $keterangan, $jumlah, $id]
            );
            set_flash('sukses', 'Transaksi berhasil diperbarui.');
        } else {
            db_exec(
                'INSERT INTO kas (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES (?,?,?,?,?,?)',
                'ssssdi', [$tanggal, $jenis, $kat, $keterangan, $jumlah, $user['id']]
            );
            set_flash('sukses', 'Transaksi berhasil ditambahkan.');
        }
        header('Location: kas.php');
        exit;
    }

    header('Location: kas.php');
    exit;
}

/* ---------- Mode edit ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one('SELECT * FROM kas WHERE id = ?', 'i', [(int)$_GET['edit']]);
    if (!$edit) {
        set_flash('error', 'Data transaksi tidak ditemukan.');
        header('Location: kas.php');
        exit;
    }
}

/* ---------- Filter ---------- */
$f_bulan = $_GET['bulan'] ?? '';
$f_jenis = $_GET['jenis'] ?? '';
$f_q     = trim($_GET['q'] ?? '');

$where = [];
$types = '';
$params = [];

if (preg_match('/^\d{4}-\d{2}$/', $f_bulan)) {
    $where[] = "DATE_FORMAT(tanggal,'%Y-%m') = ?";
    $types .= 's';
    $params[] = $f_bulan;
} else {
    $f_bulan = '';
}
if (in_array($f_jenis, ['masuk', 'keluar'], true)) {
    $where[] = 'jenis = ?';
    $types .= 's';
    $params[] = $f_jenis;
} else {
    $f_jenis = '';
}
if ($f_q !== '') {
    $where[] = '(kategori LIKE ? OR keterangan LIKE ?)';
    $types .= 'ss';
    $like = '%' . $f_q . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$data = db_all("SELECT * FROM kas $sql_where ORDER BY tanggal DESC, id DESC", $types, $params);

$tot_masuk = 0;
$tot_keluar = 0;
foreach ($data as $d) {
    if ($d['jenis'] === 'masuk') {
        $tot_masuk += (float)$d['jumlah'];
    } else {
        $tot_keluar += (float)$d['jumlah'];
    }
}

$saldo = saldo_kas();
$all = db_one("SELECT
        COALESCE(SUM(CASE WHEN jenis='masuk'  THEN jumlah END),0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis='keluar' THEN jumlah END),0) AS keluar FROM kas");

$form_jenis = $edit ? $edit['jenis'] : 'masuk';

render_header('Kas Masjid', 'kas');
?>

<div class="grid grid-3" style="margin-bottom:1.25rem">
    <div class="stat saldo">
        <div class="stat-label">Saldo kas</div>
        <div class="stat-value"><?= e(rupiah($saldo)) ?></div>
        <div class="stat-note">Dihitung otomatis dari seluruh transaksi</div>
    </div>
    <div class="stat masuk">
        <div class="stat-label">Total uang masuk</div>
        <div class="stat-value"><?= e(rupiah($all['masuk'])) ?></div>
    </div>
    <div class="stat keluar">
        <div class="stat-label">Total uang keluar</div>
        <div class="stat-value"><?= e(rupiah($all['keluar'])) ?></div>
    </div>
</div>

<div class="card" id="form-kas">
    <div class="card-head">
        <h2><?= $edit ? 'Ubah transaksi' : 'Tambah transaksi' ?></h2>
        <?php if ($edit): ?><a href="kas.php" class="btn btn-outline btn-sm">Batal ubah</a><?php endif; ?>
    </div>
    <form method="post" action="kas.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= e($edit ? $edit['tanggal'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label for="jenis">Jenis</label>
                <select id="jenis" name="jenis" required>
                    <option value="masuk"  <?= $form_jenis === 'masuk'  ? 'selected' : '' ?>>Uang masuk</option>
                    <option value="keluar" <?= $form_jenis === 'keluar' ? 'selected' : '' ?>>Uang keluar</option>
                </select>
            </div>
            <div class="form-group">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" data-selected="<?= e($edit ? $edit['kategori'] : '') ?>" required></select>
            </div>
            <div class="form-group">
                <label for="jumlah">Jumlah (Rp)</label>
                <input type="number" id="jumlah" name="jumlah" min="1" step="1" inputmode="numeric"
                       value="<?= $edit ? e((string)(int)$edit['jumlah']) : '' ?>" placeholder="Contoh: 500000" required>
            </div>
            <div class="form-group full">
                <label for="keterangan">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" maxlength="255"
                       value="<?= e($edit ? $edit['keterangan'] : '') ?>" placeholder="Catatan singkat transaksi">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?= $edit ? 'Simpan perubahan' : 'Simpan transaksi' ?></button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2>Riwayat transaksi</h2>
    </div>
    <form method="get" action="kas.php" class="filter-bar" style="margin-bottom:1rem">
        <div class="form-group">
            <label for="f_bulan">Bulan</label>
            <input type="month" id="f_bulan" name="bulan" value="<?= e($f_bulan) ?>">
        </div>
        <div class="form-group">
            <label for="f_jenis">Jenis</label>
            <select id="f_jenis" name="jenis">
                <option value="">Semua</option>
                <option value="masuk"  <?= $f_jenis === 'masuk'  ? 'selected' : '' ?>>Uang masuk</option>
                <option value="keluar" <?= $f_jenis === 'keluar' ? 'selected' : '' ?>>Uang keluar</option>
            </select>
        </div>
        <div class="form-group">
            <label for="f_q">Cari</label>
            <input type="text" id="f_q" name="q" value="<?= e($f_q) ?>" placeholder="Kategori atau keterangan">
        </div>
        <button type="submit" class="btn">Terapkan</button>
        <a href="kas.php" class="btn btn-outline">Atur ulang</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th>
                    <th class="num">Jumlah</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="7" class="empty">Tidak ada transaksi yang cocok dengan filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($data as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e(tgl_indo($d['tanggal'])) ?></td>
                    <td><span class="badge badge-<?= e($d['jenis']) ?>"><?= $d['jenis'] === 'masuk' ? 'Masuk' : 'Keluar' ?></span></td>
                    <td><?= e($d['kategori']) ?></td>
                    <td><?= e($d['keterangan']) ?></td>
                    <td class="num <?= $d['jenis'] === 'masuk' ? 'text-ok' : 'text-red' ?>"><?= e(rupiah($d['jumlah'])) ?></td>
                    <td class="aksi">
                        <a href="kas.php?edit=<?= (int)$d['id'] ?>#form-kas" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" action="kas.php" data-confirm="Hapus transaksi ini?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($data): ?>
            <tfoot>
                <tr><td colspan="5">Total masuk (sesuai filter)</td><td class="num text-ok"><?= e(rupiah($tot_masuk)) ?></td><td></td></tr>
                <tr><td colspan="5">Total keluar (sesuai filter)</td><td class="num text-red"><?= e(rupiah($tot_keluar)) ?></td><td></td></tr>
                <tr><td colspan="5">Selisih (sesuai filter)</td><td class="num"><?= e(rupiah($tot_masuk - $tot_keluar)) ?></td><td></td></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
(function () {
    var kategori = <?= json_encode($kategori, JSON_UNESCAPED_UNICODE) ?>;
    var jenis = document.getElementById('jenis');
    var kat = document.getElementById('kategori');

    function isi() {
        var pilih = kat.getAttribute('data-selected') || kat.value;
        kat.innerHTML = '';
        kategori[jenis.value].forEach(function (k) {
            var o = document.createElement('option');
            o.value = k;
            o.textContent = k;
            if (k === pilih) { o.selected = true; }
            kat.appendChild(o);
        });
        kat.removeAttribute('data-selected');
    }
    jenis.addEventListener('change', isi);
    isi();
})();
</script>

<?php render_footer(); ?>
