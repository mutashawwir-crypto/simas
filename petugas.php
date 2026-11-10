<?php
require_once __DIR__ . '/config/koneksi.php';
require_role(['admin']);

$jabatan_list = ['Imam', 'Muazin', 'Marbot', 'Khatib', 'Pengurus'];
$status_list  = ['Aktif', 'Nonaktif'];

/* ---------- Proses POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        header('Location: petugas.php');
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if (db_exec('DELETE FROM petugas WHERE id = ?', 'i', [$id]) > 0) {
            set_flash('sukses', 'Data petugas berhasil dihapus. Jadwal yang memakai petugas ini dikosongkan otomatis.');
        } else {
            set_flash('error', 'Data petugas tidak ditemukan.');
        }
        header('Location: petugas.php');
        exit;
    }

    if ($aksi === 'simpan') {
        $id      = (int)($_POST['id'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $jabatan = $_POST['jabatan'] ?? '';
        $telepon = trim($_POST['telepon'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');
        $status  = $_POST['status'] ?? 'Aktif';

        $err = '';
        if ($nama === '' || mb_strlen($nama) > 100) {
            $err = 'Nama wajib diisi dan maksimal 100 karakter.';
        } elseif (!in_array($jabatan, $jabatan_list, true)) {
            $err = 'Jabatan tidak valid.';
        } elseif (!in_array($status, $status_list, true)) {
            $err = 'Status tidak valid.';
        } elseif (!preg_match('/^[0-9+\-\s]{0,20}$/', $telepon)) {
            $err = 'Nomor telepon hanya boleh berisi angka, spasi, tanda + dan -, maksimal 20 karakter.';
        } elseif (mb_strlen($alamat) > 255) {
            $err = 'Alamat maksimal 255 karakter.';
        }

        if ($err !== '') {
            set_flash('error', $err);
            header('Location: petugas.php' . ($id > 0 ? '?edit=' . $id : ''));
            exit;
        }

        if ($id > 0) {
            db_exec(
                'UPDATE petugas SET nama=?, jabatan=?, telepon=?, alamat=?, status=? WHERE id=?',
                'sssssi', [$nama, $jabatan, $telepon, $alamat, $status, $id]
            );
            set_flash('sukses', 'Data petugas berhasil diperbarui.');
        } else {
            db_exec(
                'INSERT INTO petugas (nama, jabatan, telepon, alamat, status) VALUES (?,?,?,?,?)',
                'sssss', [$nama, $jabatan, $telepon, $alamat, $status]
            );
            set_flash('sukses', 'Petugas baru berhasil ditambahkan.');
        }
        header('Location: petugas.php');
        exit;
    }

    header('Location: petugas.php');
    exit;
}

/* ---------- Mode edit ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_one('SELECT * FROM petugas WHERE id = ?', 'i', [(int)$_GET['edit']]);
    if (!$edit) {
        set_flash('error', 'Data petugas tidak ditemukan.');
        header('Location: petugas.php');
        exit;
    }
}

/* ---------- Filter ---------- */
$f_jabatan = $_GET['jabatan'] ?? '';
$f_q       = trim($_GET['q'] ?? '');

$where = [];
$types = '';
$params = [];

if (in_array($f_jabatan, $jabatan_list, true)) {
    $where[] = 'jabatan = ?';
    $types .= 's';
    $params[] = $f_jabatan;
} else {
    $f_jabatan = '';
}
if ($f_q !== '') {
    $where[] = '(nama LIKE ? OR telepon LIKE ? OR alamat LIKE ?)';
    $types .= 'sss';
    $like = '%' . $f_q . '%';
    array_push($params, $like, $like, $like);
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$data = db_all(
    "SELECT * FROM petugas $sql_where ORDER BY FIELD(jabatan,'Imam','Muazin','Khatib','Marbot','Pengurus'), nama",
    $types, $params
);

$rekap = db_all("SELECT jabatan, COUNT(*) AS n FROM petugas WHERE status='Aktif' GROUP BY jabatan");
$rekap_map = [];
foreach ($rekap as $r) {
    $rekap_map[$r['jabatan']] = (int)$r['n'];
}

render_header('Data Petugas', 'petugas');
?>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:1.25rem">
    <?php foreach ($jabatan_list as $j): ?>
        <div class="stat info">
            <div class="stat-label"><?= e($j) ?> aktif</div>
            <div class="stat-value"><?= $rekap_map[$j] ?? 0 ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" id="form-petugas">
    <div class="card-head">
        <h2><?= $edit ? 'Ubah data petugas' : 'Tambah petugas' ?></h2>
        <?php if ($edit): ?><a href="petugas.php" class="btn btn-outline btn-sm">Batal ubah</a><?php endif; ?>
    </div>
    <form method="post" action="petugas.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="nama">Nama lengkap</label>
                <input type="text" id="nama" name="nama" maxlength="100" value="<?= e($edit['nama'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="jabatan">Jabatan</label>
                <select id="jabatan" name="jabatan" required>
                    <?php foreach ($jabatan_list as $j): ?>
                        <option value="<?= e($j) ?>" <?= ($edit['jabatan'] ?? 'Imam') === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="telepon">Telepon / WhatsApp</label>
                <input type="text" id="telepon" name="telepon" maxlength="20" inputmode="tel" value="<?= e($edit['telepon'] ?? '') ?>" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($status_list as $s): ?>
                        <option value="<?= e($s) ?>" <?= ($edit['status'] ?? 'Aktif') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label for="alamat">Alamat</label>
                <input type="text" id="alamat" name="alamat" maxlength="255" value="<?= e($edit['alamat'] ?? '') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?= $edit ? 'Simpan perubahan' : 'Simpan petugas' ?></button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head"><h2>Daftar petugas</h2></div>
    <form method="get" action="petugas.php" class="filter-bar" style="margin-bottom:1rem">
        <div class="form-group">
            <label for="f_jabatan">Jabatan</label>
            <select id="f_jabatan" name="jabatan">
                <option value="">Semua jabatan</option>
                <?php foreach ($jabatan_list as $j): ?>
                    <option value="<?= e($j) ?>" <?= $f_jabatan === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="f_q">Cari</label>
            <input type="text" id="f_q" name="q" value="<?= e($f_q) ?>" placeholder="Nama, telepon, atau alamat">
        </div>
        <button type="submit" class="btn">Terapkan</button>
        <a href="petugas.php" class="btn btn-outline">Atur ulang</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>No</th><th>Nama</th><th>Jabatan</th><th>Telepon</th><th>Alamat</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="7" class="empty">Tidak ada data petugas yang cocok.</td></tr>
            <?php endif; ?>
            <?php foreach ($data as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($p['nama']) ?></td>
                    <td><span class="badge badge-jabatan"><?= e($p['jabatan']) ?></span></td>
                    <td><?= e($p['telepon'] ?: '-') ?></td>
                    <td><?= e($p['alamat'] ?: '-') ?></td>
                    <td><span class="badge <?= $p['status'] === 'Aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>"><?= e($p['status']) ?></span></td>
                    <td class="aksi">
                        <a href="petugas.php?edit=<?= (int)$p['id'] ?>#form-petugas" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" action="petugas.php" data-confirm="Hapus petugas <?= e($p['nama']) ?>? Jadwal terkait akan dikosongkan.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
