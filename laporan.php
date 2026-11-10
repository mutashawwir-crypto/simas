<?php
require_once __DIR__ . '/config/koneksi.php';
require_role(['admin', 'bendahara', 'jamaah']);

/* ---------- Periode ---------- */
$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');

if (!valid_date($dari)) {
    $dari = date('Y-m-01');
}
if (!valid_date($sampai)) {
    $sampai = date('Y-m-t');
}
if ($dari > $sampai) {
    $tmp = $dari;
    $dari = $sampai;
    $sampai = $tmp;
}

/* Saldo awal = saldo sampai sehari sebelum periode */
$hari_sebelum = date('Y-m-d', strtotime($dari . ' -1 day'));
$saldo_awal = saldo_kas($hari_sebelum);

$data = db_all(
    'SELECT * FROM kas WHERE tanggal >= ? AND tanggal <= ? ORDER BY tanggal ASC, id ASC',
    'ss', [$dari, $sampai]
);

$tot_masuk = 0;
$tot_keluar = 0;
$saldo_jalan = $saldo_awal;
foreach ($data as $k => $d) {
    if ($d['jenis'] === 'masuk') {
        $tot_masuk += (float)$d['jumlah'];
        $saldo_jalan += (float)$d['jumlah'];
    } else {
        $tot_keluar += (float)$d['jumlah'];
        $saldo_jalan -= (float)$d['jumlah'];
    }
    $data[$k]['saldo'] = $saldo_jalan;
}
$saldo_akhir = $saldo_awal + $tot_masuk - $tot_keluar;

/* Rekap per kategori */
$rekap = db_all(
    'SELECT jenis, kategori, COUNT(*) AS n, SUM(jumlah) AS total
     FROM kas WHERE tanggal >= ? AND tanggal <= ?
     GROUP BY jenis, kategori ORDER BY jenis, total DESC',
    'ss', [$dari, $sampai]
);
$rekap_masuk = array_filter($rekap, function ($r) { return $r['jenis'] === 'masuk'; });
$rekap_keluar = array_filter($rekap, function ($r) { return $r['jenis'] === 'keluar'; });

/* Pintasan periode */
$p_bulan_ini = ['dari' => date('Y-m-01'), 'sampai' => date('Y-m-t')];
$p_bulan_lalu = [
    'dari'   => date('Y-m-01', strtotime('first day of last month')),
    'sampai' => date('Y-m-t', strtotime('last day of last month')),
];
$p_tahun_ini = ['dari' => date('Y-01-01'), 'sampai' => date('Y-12-31')];

$user = current_user();
$role_label = ['admin' => 'Admin', 'bendahara' => 'Bendahara', 'jamaah' => 'Jamaah'];

render_header('Laporan Kas', 'laporan');
?>

<div class="card no-print">
    <div class="card-head">
        <h2>Pilih periode laporan</h2>
        <button type="button" class="btn btn-brass" onclick="window.print()">Cetak laporan</button>
    </div>
    <form method="get" action="laporan.php" class="filter-bar">
        <div class="form-group">
            <label for="dari">Dari tanggal</label>
            <input type="date" id="dari" name="dari" value="<?= e($dari) ?>" required>
        </div>
        <div class="form-group">
            <label for="sampai">Sampai tanggal</label>
            <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>" required>
        </div>
        <button type="submit" class="btn">Tampilkan</button>
    </form>
    <p class="help" style="margin-top:.8rem">
        Pintasan:
        <a href="laporan.php?dari=<?= e($p_bulan_ini['dari']) ?>&amp;sampai=<?= e($p_bulan_ini['sampai']) ?>">Bulan ini</a> |
        <a href="laporan.php?dari=<?= e($p_bulan_lalu['dari']) ?>&amp;sampai=<?= e($p_bulan_lalu['sampai']) ?>">Bulan lalu</a> |
        <a href="laporan.php?dari=<?= e($p_tahun_ini['dari']) ?>&amp;sampai=<?= e($p_tahun_ini['sampai']) ?>">Tahun ini</a>
    </p>
</div>

<div class="card">
    <div class="kop">
        <h2>LAPORAN KEUANGAN <?= e(strtoupper(NAMA_MASJID)) ?></h2>
        <p><?= e(ALAMAT_MASJID) ?></p>
        <p>Periode: <b><?= e(tgl_indo($dari)) ?></b> s.d. <b><?= e(tgl_indo($sampai)) ?></b></p>
    </div>

    <div class="grid grid-4" style="margin-bottom:1.25rem">
        <div class="stat info">
            <div class="stat-label">Saldo awal</div>
            <div class="stat-value"><?= e(rupiah($saldo_awal)) ?></div>
        </div>
        <div class="stat masuk">
            <div class="stat-label">Total pemasukan</div>
            <div class="stat-value"><?= e(rupiah($tot_masuk)) ?></div>
        </div>
        <div class="stat keluar">
            <div class="stat-label">Total pengeluaran</div>
            <div class="stat-value"><?= e(rupiah($tot_keluar)) ?></div>
        </div>
        <div class="stat saldo">
            <div class="stat-label">Saldo akhir</div>
            <div class="stat-value"><?= e(rupiah($saldo_akhir)) ?></div>
        </div>
    </div>

    <h3>Rincian transaksi</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th><th>Tanggal</th><th>Kategori</th><th>Keterangan</th>
                    <th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td><td><?= e(tgl_indo($dari)) ?></td><td colspan="2"><i>Saldo awal periode</i></td>
                    <td class="num"></td><td class="num"></td><td class="num"><?= e(rupiah($saldo_awal)) ?></td>
                </tr>
            <?php if (!$data): ?>
                <tr><td colspan="7" class="empty">Tidak ada transaksi pada periode ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($data as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e(tgl_indo($d['tanggal'])) ?></td>
                    <td><?= e($d['kategori']) ?></td>
                    <td><?= e($d['keterangan']) ?></td>
                    <td class="num text-ok"><?= $d['jenis'] === 'masuk' ? e(rupiah($d['jumlah'])) : '' ?></td>
                    <td class="num text-red"><?= $d['jenis'] === 'keluar' ? e(rupiah($d['jumlah'])) : '' ?></td>
                    <td class="num"><?= e(rupiah($d['saldo'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">Jumlah</td>
                    <td class="num"><?= e(rupiah($tot_masuk)) ?></td>
                    <td class="num"><?= e(rupiah($tot_keluar)) ?></td>
                    <td class="num"><?= e(rupiah($saldo_akhir)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="grid grid-2" style="margin-top:1.5rem">
        <div>
            <h3>Rekap pemasukan per kategori</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kategori</th><th class="num">Transaksi</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php if (!$rekap_masuk): ?>
                        <tr><td colspan="3" class="empty">Tidak ada pemasukan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rekap_masuk as $r): ?>
                        <tr><td><?= e($r['kategori']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= e(rupiah($r['total'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <h3>Rekap pengeluaran per kategori</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kategori</th><th class="num">Transaksi</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php if (!$rekap_keluar): ?>
                        <tr><td colspan="3" class="empty">Tidak ada pengeluaran.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rekap_keluar as $r): ?>
                        <tr><td><?= e($r['kategori']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= e(rupiah($r['total'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ttd print-only">
        <div>
            <div>Mengetahui,</div>
            <div>Ketua DKM</div>
            <div class="garis"></div>
            <div class="nama">&nbsp;</div>
        </div>
        <div>
            <div><?= e(tgl_indo(date('Y-m-d'))) ?></div>
            <div>Bendahara</div>
            <div class="garis"></div>
            <div class="nama">&nbsp;</div>
        </div>
    </div>

    <p class="help print-only" style="margin-top:1.5rem">
        Dicetak oleh <?= e($user['nama']) ?> (<?= e($role_label[$user['role']] ?? $user['role']) ?>) pada
        <?= e(tgl_indo(date('Y-m-d'))) ?> pukul <?= e(date('H:i')) ?> WIB.
    </p>
</div>

<?php render_footer(); ?>
