<?php
function cek_kuota(PDO $pdo, $tanggal, array $items, array $menuById) {
    // total yang diminta per kategori
    $diminta = [];
    foreach ($items as $it) {
        $kat = $menuById[$it['id']]['kategori'];
        $diminta[$kat] = ($diminta[$kat] ?? 0) + $it['jumlah'];
    }

    $set = $pdo->query("SELECT kuota_default_ultah, kuota_default_kering FROM pengaturan LIMIT 1")->fetch();
    $default = [
        'ultah'  => (int) $set['kuota_default_ultah'],
        'kering' => (int) $set['kuota_default_kering'],
    ];

    $peringatan = [];
    foreach ($diminta as $kat => $jml) {
        // batas: pengecualian per tanggal kalau ada, kalau tidak pakai default
        $stmt = $pdo->prepare("SELECT batas FROM kuota_tanggal WHERE tanggal = ? AND kategori = ?");
        $stmt->execute([$tanggal, $kat]);
        $batas = $stmt->fetchColumn();
        $batas = ($batas === false) ? $default[$kat] : (int) $batas;

        // yang sudah terpakai (pesanan batal tidak dihitung)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(i.jumlah), 0)
            FROM item_pesanan i
            JOIN pesanan p ON p.id_pesanan = i.id_pesanan
            JOIN menu m ON m.id_menu = i.id_menu
            WHERE p.tanggal_ambil = ? AND m.kategori = ? AND p.status_pesanan <> 'dibatalkan'");
        $stmt->execute([$tanggal, $kat]);
        $terpakai = (int) $stmt->fetchColumn();

        if ($terpakai + $jml > $batas) {
            $peringatan[] = "Kuota $kat tanggal $tanggal: batas $batas, sudah terpakai $terpakai, pesanan ini menambah $jml.";
        }
    }
    return $peringatan;
}

// daftar tanggal yang penuh untuk isi keranjang ini: ['2026-11-20' => true, ...]
function tanggal_penuh(PDO $pdo, $dari, $sampai, array $diminta) {
    if (!$diminta) {
        return [];
    }
    $set = $pdo->query("SELECT kuota_default_ultah, kuota_default_kering FROM pengaturan LIMIT 1")->fetch();
    $default = [
        'ultah'  => (int) $set['kuota_default_ultah'],
        'kering' => (int) $set['kuota_default_kering'],
    ];

    // kuota khusus per tanggal
    $stmt = $pdo->prepare("SELECT tanggal, kategori, batas FROM kuota_tanggal WHERE tanggal BETWEEN ? AND ?");
    $stmt->execute([$dari, $sampai]);
    $khusus = [];
    foreach ($stmt->fetchAll() as $r) {
        $khusus[$r['tanggal']][$r['kategori']] = (int) $r['batas'];
    }

    // yang sudah terpakai (pesanan batal tidak dihitung)
    $stmt = $pdo->prepare("SELECT p.tanggal_ambil AS tanggal, m.kategori, SUM(i.jumlah) AS jml
        FROM item_pesanan i
        JOIN pesanan p ON p.id_pesanan = i.id_pesanan
        JOIN menu m ON m.id_menu = i.id_menu
        WHERE p.tanggal_ambil BETWEEN ? AND ? AND p.status_pesanan <> 'dibatalkan'
        GROUP BY p.tanggal_ambil, m.kategori");
    $stmt->execute([$dari, $sampai]);
    $pakai = [];
    foreach ($stmt->fetchAll() as $r) {
        $pakai[$r['tanggal']][$r['kategori']] = (int) $r['jml'];
    }

    $penuh = [];
    $akhir = new DateTime($sampai);
    for ($d = new DateTime($dari); $d <= $akhir; $d->modify('+1 day')) {
        $t = $d->format('Y-m-d');
        foreach ($diminta as $kat => $jml) {
            $batas    = $khusus[$t][$kat] ?? $default[$kat];
            $terpakai = $pakai[$t][$kat] ?? 0;
            if ($terpakai + $jml > $batas) {
                $penuh[$t] = true;
                break;
            }
        }
    }
    return $penuh;
}