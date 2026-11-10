<?php
require_once __DIR__ . '/config/koneksi.php';
require_role(['admin', 'bendahara', 'jamaah']);

$bisa_edit = is_role('admin');
$waktu_list = ['Subuh', 'Dzuhur', 'Ashar', 'Maghrib', 'Isya', 'Jumat'];

/* Daftar petugas aktif untuk dropdown */
$imam_opts   = db_all("SELECT id, nama, jabatan FROM petugas WHERE status='Aktif' AND jabatan IN ('Imam','Khatib') ORDER BY nama");
$muazin_opts = db_all("SELECT id, nama, jabatan FROM petugas WHERE status='Aktif' AND jabatan = 'Muazin' ORDER BY nama");
$khatib_opts = db_all("SELECT id, nama, jabatan FROM petugas WHERE status='Aktif' AND jabatan IN ('Khatib','Imam') ORDER BY nama");

function petugas_valid($id, $opts)
{
    foreach ($opts as $o) {
        if ((int)$o['id'] === (int)$id) {
            return true;
        }
    }
    return false;
}

/* ---------- Proses POST (khusus admin) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bisa_edit) {
        set_flash('error', 'Hanya admin yang dapat mengubah jadwal.');
        header('Location: jadwal.php');
        exit;
    }
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        header('Location: jadwal.php');
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if (db_exec('DELETE FROM jadwal WHERE id = ?', 'i', [$id]) > 0) {
            set_flash('sukses', 'Jadwal berhasil dihapus.');
        } else {
            set_flash('error', 'Jadwal tidak ditemukan.');
        }
        header('Location: jadwal.php');
        exit;
    }

    if ($aksi === 'simpan') {
        $id      = (int)($_POST['id'] ?? 0);
        $tanggal = trim($_POST['tanggal'] ?? '');
        $waktu   = $_POST['waktu'] ?? '';
        $imam    = (int)($_POST['imam_id'] ?? 0);
        $muazin  = (int)($_POST['muazin_id'] ?? 0);
        $khatib  = (int)($_POST['khatib_id'] ?? 0);
        $tema    = trim($_POST['tema'] ?? '');

        $err = '';
        if (!valid_date($tanggal)) {
            $err = 'Tanggal tidak valid.';
        } elseif (!in_array($waktu, $waktu_list, true)) {
            $err = 'Waktu sholat tidak valid.';
        } elseif (!petugas_valid($imam, $imam_opts)) {
            $err = 'Pilih imam dari daftar petugas aktif.';
        } elseif (!petugas_valid($muazin, $muazin_opts)) {
            $err = 'Pilih muazin dari daftar petugas aktif.';
        } elseif ($waktu === 'Jumat' && !petugas_valid($khatib, $khatib_opts)) {
            $err = 'Sholat Jumat wajib memiliki khatib.';
        } elseif ($waktu === 'Jumat' && nama_hari($tanggal) !== 'Jumat') {
            $err = 'Tanggal yang dipilih bukan hari Jumat.';
        } elseif (mb_strlen($tema) > 150) {
            $err = 'Tema khutbah maksimal 150 karakter.';
        }

        if ($err !== '') {
            set_flash('error', $err);
            header('Location: jadwal.php' . ($id > 0 ? '?edit=' . $id : ''));
            exit;
        }

        if ($waktu !== 'Jumat') {
            $khatib = 0;
            $tema = '';
        }
        $khatib_val = $khatib > 0 ? $khatib : null;
        $tema_val   = $tema !== '' ? $tema : null;

        try {
            if ($id > 0) {
                db_exec(
                    'UPDATE jadwal SET tanggal=?, waktu=?, imam_id=?, muazin_id=?, khatib_id=?, tema=? WHERE id=?',
                    'ssiiisi', [$tanggal, $waktu, $imam, $muazin, $khatib_val, $tema_val, $id]
                );
                set_flash('sukses', 'Jadwal berhasil diperbarui.');
            } else {
                db_exec(
                    'INSERT INTO jadwal (tanggal, waktu, imam_id, muazin_id, khatib_id, tema) VALUES (?,?,?,?,?,?)',
                    'ssiiis', [$tanggal, $waktu, $imam, $muazin, $khatib_val, $tema_val]
                );
                set_flash('sukses', 'Jadwal berhasil ditambahkan.');
            }
        } catch (mysqli_sql_exception $ex) {
            if ((int)$ex->getCode() === 1062) {
                set_flash('error', 'Jadwal untuk tanggal dan waktu tersebut sudah ada. Ubah jadwal yang sudah ada.');
                header('Location: jadwal.php' . ($id > 0 ? '?edit=' . $id : ''));
                exit;
            }
            throw $ex;
        }
        header('Location: jadwal.php?bulan=' . substr($tanggal, 0, 7));
        exit;
    }

    header('Location: jadwal.php');
    exit;
}

/* ---------- Mode edit ---------- */
$edit = null;
if ($bisa_edit && isset($_GET['edit'])) {
    $edit = db_one('SELECT * FROM jadwal WHERE id = ?', 'i', [(int)$_GET['edit']]);
    if (!$edit) {
        set_flash('error', 'Jadwal tidak ditemukan.');
        header('Location: jadwal.php');
        exit;
    }
}

/* ---------- Filter bulan ---------- */
$f_bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $f_bulan)) {
    $f_bulan = date('Y-m');
}
$f_waktu = $_GET['waktu'] ?? '';
if (!in_array($f_waktu, $waktu_list, true)) {
    $f_waktu = '';
}

$where = "DATE_FORMAT(j.tanggal,'%Y-%m') = ?";
$types = 's';
$params = [$f_bulan];
if ($f_waktu !== '') {
    $where .= ' AND j.waktu = ?';
    $types .= 's';
    $params[] = $f_waktu;
}

$data = db_all(
    "SELECT j.*, i.nama AS imam, m.nama AS muazin, k.nama AS khatib
     FROM jadwal j
     LEFT JOIN petugas i ON i.id = j.imam_id
     LEFT JOIN petugas m ON m.id = j.muazin_id
     LEFT JOIN petugas k ON k.id = j.khatib_id
     WHERE $where
     ORDER BY j.tanggal, FIELD(j.waktu,'Subuh','Dzuhur','Ashar','Maghrib','Isya','Jumat')",
    $types, $params
);

/* Jumat mendatang */
$jumat = db_all(
    "SELECT j.*, i.nama AS imam, m.nama AS muazin, k.nama AS khatib
     FROM jadwal j
     LEFT JOIN petugas i ON i.id = j.imam_id
     LEFT JOIN petugas m ON m.id = j.muazin_id
     LEFT JOIN petugas k ON k.id = j.khatib_id
     WHERE j.waktu = 'Jumat' AND j.tanggal >= CURDATE()
     ORDER BY j.tanggal LIMIT 4"
);

render_header('Jadwal Petugas', 'jadwal');
?>

<?php if ($jumat): ?>
<h2>Khatib Jumat mendatang</h2>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));margin-bottom:1.25rem">
    <?php foreach ($jumat as $j): ?>
        <div class="card jumat-card">
            <div class="jumat-date"><?= e(tgl_indo($j['tanggal'], true)) ?></div>
            <div class="jumat-tema"><?= e($j['tema'] ?: 'Tema belum ditentukan') ?></div>
            <dl class="kv">
                <dt>Khatib</dt><dd><?= e($j['khatib'] ?: '-') ?></dd>
                <dt>Imam</dt><dd><?= e($j['imam'] ?: '-') ?></dd>
                <dt>Muazin</dt><dd><?= e($j['muazin'] ?: '-') ?></dd>
            </dl>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($bisa_edit): ?>
<div class="card" id="form-jadwal">
    <div class="card-head">
        <h2><?= $edit ? 'Ubah jadwal' : 'Tambah jadwal' ?></h2>
        <?php if ($edit): ?><a href="jadwal.php" class="btn btn-outline btn-sm">Batal ubah</a><?php endif; ?>
    </div>

    <?php if (!$imam_opts || !$muazin_opts): ?>
        <div class="alert alert-error">Belum ada petugas aktif dengan jabatan Imam/Khatib dan Muazin. Tambahkan dulu di menu <a href="petugas.php">Data Petugas</a>.</div>
    <?php endif; ?>

    <form method="post" action="jadwal.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= e($edit['tanggal'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label for="waktu">Waktu sholat</label>
                <select id="waktu" name="waktu" required>
                    <?php foreach ($waktu_list as $w): ?>
                        <option value="<?= e($w) ?>" <?= ($edit['waktu'] ?? 'Subuh') === $w ? 'selected' : '' ?>><?= e($w) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="imam_id">Imam</label>
                <select id="imam_id" name="imam_id" required>
                    <option value="">Pilih imam</option>
                    <?php foreach ($imam_opts as $o): ?>
                        <option value="<?= (int)$o['id'] ?>" <?= (int)($edit['imam_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['nama']) ?> (<?= e($o['jabatan']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="muazin_id">Muazin</label>
                <select id="muazin_id" name="muazin_id" required>
                    <option value="">Pilih muazin</option>
                    <?php foreach ($muazin_opts as $o): ?>
                        <option value="<?= (int)$o['id'] ?>" <?= (int)($edit['muazin_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="grp-khatib">
                <label for="khatib_id">Khatib (khusus Jumat)</label>
                <select id="khatib_id" name="khatib_id">
                    <option value="">Pilih khatib</option>
                    <?php foreach ($khatib_opts as $o): ?>
                        <option value="<?= (int)$o['id'] ?>" <?= (int)($edit['khatib_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['nama']) ?> (<?= e($o['jabatan']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="grp-tema">
                <label for="tema">Tema khutbah</label>
                <input type="text" id="tema" name="tema" maxlength="150" value="<?= e($edit['tema'] ?? '') ?>" placeholder="Contoh: Keutamaan Bersedekah">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?= $edit ? 'Simpan perubahan' : 'Simpan jadwal' ?></button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><h2>Jadwal bulan <?= e(nama_bulan((int)substr($f_bulan, 5, 2)) . ' ' . substr($f_bulan, 0, 4)) ?></h2></div>
    <form method="get" action="jadwal.php" class="filter-bar" style="margin-bottom:1rem">
        <div class="form-group">
            <label for="f_bulan">Bulan</label>
            <input type="month" id="f_bulan" name="bulan" value="<?= e($f_bulan) ?>">
        </div>
        <div class="form-group">
            <label for="f_waktu">Waktu</label>
            <select id="f_waktu" name="waktu">
                <option value="">Semua waktu</option>
                <?php foreach ($waktu_list as $w): ?>
                    <option value="<?= e($w) ?>" <?= $f_waktu === $w ? 'selected' : '' ?>><?= e($w) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Tampilkan</button>
        <a href="jadwal.php" class="btn btn-outline">Bulan ini</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th><th>Waktu</th><th>Imam</th><th>Muazin</th><th>Khatib</th><th>Tema</th>
                    <?php if ($bisa_edit): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="<?= $bisa_edit ? 7 : 6 ?>" class="empty">Belum ada jadwal pada periode ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($data as $j): ?>
                <tr>
                    <td><?= e(tgl_indo($j['tanggal'], true)) ?></td>
                    <td><span class="badge <?= $j['waktu'] === 'Jumat' ? 'badge-jumat' : 'badge-jabatan' ?>"><?= e($j['waktu']) ?></span></td>
                    <td><?= e($j['imam'] ?: '-') ?></td>
                    <td><?= e($j['muazin'] ?: '-') ?></td>
                    <td><?= e($j['khatib'] ?: '-') ?></td>
                    <td><?= e($j['tema'] ?: '-') ?></td>
                    <?php if ($bisa_edit): ?>
                    <td class="aksi">
                        <a href="jadwal.php?edit=<?= (int)$j['id'] ?>#form-jadwal" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" action="jadwal.php" data-confirm="Hapus jadwal ini?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($bisa_edit): ?>
<script>
(function () {
    var waktu = document.getElementById('waktu');
    var khatib = document.getElementById('khatib_id');
    var tema = document.getElementById('tema');
    var gk = document.getElementById('grp-khatib');
    var gt = document.getElementById('grp-tema');

    function atur() {
        var jumat = waktu.value === 'Jumat';
        gk.style.display = jumat ? '' : 'none';
        gt.style.display = jumat ? '' : 'none';
        khatib.required = jumat;
        khatib.disabled = !jumat;
        tema.disabled = !jumat;
    }
    waktu.addEventListener('change', atur);
    atur();
})();
</script>
<?php endif; ?>

<?php render_footer(); ?>
