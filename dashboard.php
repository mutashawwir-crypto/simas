<?php
require_once __DIR__ . '/config/koneksi.php';
require_login();

$user  = current_user();
$role  = $user['role'];
$today = date('Y-m-d');
$awal_bulan = date('Y-m-01');

/* ---------- Ringkasan kas (transparan untuk semua role) ---------- */
$saldo = saldo_kas();
$bulan = db_one(
    "SELECT
        COALESCE(SUM(CASE WHEN jenis='masuk'  THEN jumlah END),0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis='keluar' THEN jumlah END),0) AS keluar
     FROM kas WHERE tanggal >= ? AND tanggal <= LAST_DAY(?)",
    'ss', [$awal_bulan, $awal_bulan]
);

/* ---------- Grafik 6 bulan terakhir ---------- */
$bulan_list = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -$i month", strtotime($awal_bulan));
    $bulan_list[date('Y-m', $ts)] = ['label' => substr(nama_bulan(date('n', $ts)), 0, 3), 'masuk' => 0, 'keluar' => 0];
}
$mulai_grafik = array_key_first($bulan_list) . '-01';
$rows = db_all(
    "SELECT DATE_FORMAT(tanggal,'%Y-%m') AS ym, jenis, SUM(jumlah) AS total
     FROM kas WHERE tanggal >= ? GROUP BY ym, jenis",
    's', [$mulai_grafik]
);
foreach ($rows as $r) {
    if (isset($bulan_list[$r['ym']])) {
        $bulan_list[$r['ym']][$r['jenis']] = (float)$r['total'];
    }
}
$maks = 1;
foreach ($bulan_list as $b) {
    $maks = max($maks, $b['masuk'], $b['keluar']);
}

/* ---------- Jadwal mendatang ---------- */
$jadwal = db_all(
    "SELECT j.*, i.nama AS imam, m.nama AS muazin, k.nama AS khatib
     FROM jadwal j
     LEFT JOIN petugas i ON i.id = j.imam_id
     LEFT JOIN petugas m ON m.id = j.muazin_id
     LEFT JOIN petugas k ON k.id = j.khatib_id
     WHERE j.tanggal >= ?
     ORDER BY j.tanggal, FIELD(j.waktu,'Subuh','Dzuhur','Ashar','Maghrib','Isya','Jumat')
     LIMIT 8",
    's', [$today]
);

$jumat = db_all(
    "SELECT j.*, i.nama AS imam, m.nama AS muazin, k.nama AS khatib
     FROM jadwal j
     LEFT JOIN petugas i ON i.id = j.imam_id
     LEFT JOIN petugas m ON m.id = j.muazin_id
     LEFT JOIN petugas k ON k.id = j.khatib_id
     WHERE j.waktu = 'Jumat' AND j.tanggal >= ?
     ORDER BY j.tanggal LIMIT 3",
    's', [$today]
);

/* ---------- Transaksi terakhir ---------- */
$terakhir = db_all("SELECT * FROM kas ORDER BY tanggal DESC, id DESC LIMIT 5");

/* ---------- Statistik khusus admin ---------- */
$stat_admin = null;
if ($role === 'admin') {
    $stat_admin = [
        'petugas' => (int)db_one("SELECT COUNT(*) AS n FROM petugas WHERE status='Aktif'")['n'],
        'jadwal'  => (int)db_one("SELECT COUNT(*) AS n FROM jadwal WHERE tanggal >= ?", 's', [$today])['n'],
        'users'   => (int)db_one("SELECT COUNT(*) AS n FROM users")['n'],
    ];
}

render_header('Dashboard', 'dashboard');
?>

<div class="card">
    <h2>Assalamu'alaikum, <?= e($user['nama']) ?></h2>
    <p style="margin:0;color:var(--ink-soft)">
        <?php if ($role === 'admin'): ?>
            Anda masuk sebagai Admin dengan akses penuh ke seluruh menu SIMAS.
        <?php elseif ($role === 'bendahara'): ?>
            Anda masuk sebagai Bendahara. Kelola kas masjid dan cetak laporan keuangan dari menu di samping.
        <?php else: ?>
            Selamat datang. Anda dapat melihat jadwal petugas dan laporan keuangan masjid secara transparan.
        <?php endif; ?>
    </p>
</div>

<div class="grid grid-3" style="margin-bottom:1.25rem">
    <div class="stat saldo">
        <div class="stat-label">Saldo kas saat ini</div>
        <div class="stat-value"><?= e(rupiah($saldo)) ?></div>
        <div class="stat-note">Per <?= e(tgl_indo($today)) ?></div>
    </div>
    <div class="stat masuk">
        <div class="stat-label">Pemasukan <?= e(nama_bulan(date('n'))) ?></div>
        <div class="stat-value"><?= e(rupiah($bulan['masuk'])) ?></div>
        <div class="stat-note">Bulan berjalan</div>
    </div>
    <div class="stat keluar">
        <div class="stat-label">Pengeluaran <?= e(nama_bulan(date('n'))) ?></div>
        <div class="stat-value"><?= e(rupiah($bulan['keluar'])) ?></div>
        <div class="stat-note">Bulan berjalan</div>
    </div>
</div>

<?php if ($stat_admin): ?>
<div class="grid grid-3" style="margin-bottom:1.25rem">
    <div class="stat info">
        <div class="stat-label">Petugas aktif</div>
        <div class="stat-value"><?= $stat_admin['petugas'] ?></div>
        <div class="stat-note"><a href="petugas.php">Kelola data petugas</a></div>
    </div>
    <div class="stat info">
        <div class="stat-label">Jadwal mendatang</div>
        <div class="stat-value"><?= $stat_admin['jadwal'] ?></div>
        <div class="stat-note"><a href="jadwal.php">Atur jadwal</a></div>
    </div>
    <div class="stat info">
        <div class="stat-label">Akun pengguna</div>
        <div class="stat-value"><?= $stat_admin['users'] ?></div>
        <div class="stat-note">Admin, bendahara, dan jamaah</div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-2" style="margin-bottom:1.25rem">
    <div class="card">
        <div class="card-head">
            <h3>Kas 6 bulan terakhir</h3>
            <div class="legend">
                <span><i style="background:var(--ok)"></i>Masuk</span>
                <span><i style="background:var(--red)"></i>Keluar</span>
            </div>
        </div>
        <div class="chart">
            <?php foreach ($bulan_list as $b): ?>
                <div class="chart-col" title="<?= e($b['label']) ?>: masuk <?= e(rupiah($b['masuk'])) ?>, keluar <?= e(rupiah($b['keluar'])) ?>">
                    <div class="chart-bars">
                        <div class="bar masuk"  style="height:<?= round($b['masuk']  / $maks * 100, 1) ?>%"></div>
                        <div class="bar keluar" style="height:<?= round($b['keluar'] / $maks * 100, 1) ?>%"></div>
                    </div>
                    <div class="chart-label"><?= e($b['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Transaksi terakhir</h3>
            <a href="laporan.php" class="btn btn-outline btn-sm">Lihat laporan</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="num">Jumlah</th></tr></thead>
                <tbody>
                <?php if (!$terakhir): ?>
                    <tr><td colspan="3" class="empty">Belum ada transaksi kas.</td></tr>
                <?php endif; ?>
                <?php foreach ($terakhir as $t): ?>
                    <tr>
                        <td><?= e(tgl_indo($t['tanggal'])) ?></td>
                        <td><?= e($t['kategori']) ?><br><span class="help"><?= e($t['keterangan']) ?></span></td>
                        <td class="num <?= $t['jenis'] === 'masuk' ? 'text-ok' : 'text-red' ?>">
                            <?= $t['jenis'] === 'masuk' ? '+' : '-' ?><?= e(rupiah($t['jumlah'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($jumat): ?>
<h2>Jadwal Khatib Jumat</h2>
<div class="grid grid-3" style="margin-bottom:1.25rem">
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

<div class="card">
    <div class="card-head">
        <h3>Jadwal petugas terdekat</h3>
        <a href="jadwal.php" class="btn btn-outline btn-sm">Semua jadwal</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>Waktu</th><th>Imam</th><th>Muazin</th><th>Khatib</th></tr></thead>
            <tbody>
            <?php if (!$jadwal): ?>
                <tr><td colspan="5" class="empty">Belum ada jadwal mendatang.</td></tr>
            <?php endif; ?>
            <?php foreach ($jadwal as $j): ?>
                <tr>
                    <td><?= e(tgl_indo($j['tanggal'], true)) ?></td>
                    <td><span class="badge <?= $j['waktu'] === 'Jumat' ? 'badge-jumat' : 'badge-jabatan' ?>"><?= e($j['waktu']) ?></span></td>
                    <td><?= e($j['imam'] ?: '-') ?></td>
                    <td><?= e($j['muazin'] ?: '-') ?></td>
                    <td><?= e($j['khatib'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
