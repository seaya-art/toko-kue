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