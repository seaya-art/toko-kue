-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2026 at 05:16 AM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `toko_kue`
--
CREATE DATABASE IF NOT EXISTS `toko_kue` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `toko_kue`;

-- --------------------------------------------------------

--
-- Table structure for table `alamat_pembeli`
--

CREATE TABLE `alamat_pembeli` (
  `id_alamat` int(11) NOT NULL,
  `id_pembeli` int(11) NOT NULL,
  `nama_jalan` varchar(150) NOT NULL,
  `rt_rw` varchar(6) NOT NULL,
  `no_rumah` varchar(20) NOT NULL,
  `kelurahan` varchar(80) NOT NULL,
  `kecamatan` varchar(80) NOT NULL,
  `kota_kabupaten` varchar(80) NOT NULL,
  `patokan` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `catatan_transaksi`
--

CREATE TABLE `catatan_transaksi` (
  `id_pembayaran` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `jenis` enum('tf','qris','cash') NOT NULL,
  `tanggal_bayar` datetime NOT NULL DEFAULT current_timestamp(),
  `dikonfirmasi_oleh` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_pesanan`
--

CREATE TABLE `item_pesanan` (
  `id_item` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 0,
  `harga_satuan` int(11) NOT NULL,
  `catatan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kuota_tanggal`
--

CREATE TABLE `kuota_tanggal` (
  `id_kuota` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kategori` enum('kering','ultah') NOT NULL,
  `batas` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id_menu` int(11) NOT NULL,
  `nama_kue` varchar(100) NOT NULL,
  `harga` int(11) NOT NULL,
  `kategori` enum('kering','ultah') NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `bahan` mediumtext DEFAULT NULL,
  `deskripsi` mediumtext DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pembeli`
--

CREATE TABLE `pembeli` (
  `id_pembeli` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `username_ig` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id_pengaturan` int(11) NOT NULL,
  `jenis_dp` enum('persen','rupiah') NOT NULL,
  `nilai_dp` int(11) NOT NULL,
  `batas_batal_ultah` int(11) NOT NULL DEFAULT 3,
  `batas_batal_kering` int(11) NOT NULL DEFAULT 1,
  `kuota_default_ultah` int(11) NOT NULL DEFAULT 2,
  `kuota_default_kering` int(11) NOT NULL,
  `satuan_kuota_kering` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pengguna` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `peran` enum('owner','staf') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `id_pembeli` int(11) NOT NULL,
  `tanggal_pesanan` datetime NOT NULL DEFAULT current_timestamp(),
  `tanggal_ambil` date NOT NULL,
  `jam_ambil` time DEFAULT NULL,
  `sumber` enum('website','wa','ig','titipan','telepon','toko') NOT NULL,
  `cara_pengiriman` enum('antar','ambil') NOT NULL,
  `id_alamat` int(11) DEFAULT NULL,
  `id_zona` int(11) DEFAULT NULL,
  `ongkir` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `tipe_bayar` enum('lunas','dp','belum_bayar') NOT NULL,
  `status_bayar` enum('belum_bayar','dp_masuk','lunas') NOT NULL DEFAULT 'belum_bayar',
  `status_pesanan` enum('menunggu_konfirmasi','dikonfirmasi','dibuat','siap','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu_konfirmasi',
  `nominal_dp` int(11) NOT NULL DEFAULT 0,
  `status_dp` enum('dikembalikan','hangus') DEFAULT NULL,
  `alasan_batal` text DEFAULT NULL,
  `dibuat_oleh` int(11) DEFAULT NULL,
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zona`
--

CREATE TABLE `zona` (
  `id_zona` int(11) NOT NULL,
  `nama_zona` varchar(50) NOT NULL,
  `tarif` int(11) NOT NULL,
  `diantar_oleh` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alamat_pembeli`
--
ALTER TABLE `alamat_pembeli`
  ADD PRIMARY KEY (`id_alamat`),
  ADD KEY `id_pembeli` (`id_pembeli`);

--
-- Indexes for table `catatan_transaksi`
--
ALTER TABLE `catatan_transaksi`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `dikonfirmasi_oleh` (`dikonfirmasi_oleh`);

--
-- Indexes for table `item_pesanan`
--
ALTER TABLE `item_pesanan`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_menu` (`id_menu`);

--
-- Indexes for table `kuota_tanggal`
--
ALTER TABLE `kuota_tanggal`
  ADD PRIMARY KEY (`id_kuota`),
  ADD UNIQUE KEY `tanggal` (`tanggal`,`kategori`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id_menu`);

--
-- Indexes for table `pembeli`
--
ALTER TABLE `pembeli`
  ADD PRIMARY KEY (`id_pembeli`),
  ADD UNIQUE KEY `no_wa` (`no_wa`),
  ADD UNIQUE KEY `username_ig` (`username_ig`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id_pengaturan`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD KEY `id_pembeli` (`id_pembeli`),
  ADD KEY `id_alamat` (`id_alamat`),
  ADD KEY `id_zona` (`id_zona`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indexes for table `zona`
--
ALTER TABLE `zona`
  ADD PRIMARY KEY (`id_zona`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alamat_pembeli`
--
ALTER TABLE `alamat_pembeli`
  MODIFY `id_alamat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `catatan_transaksi`
--
ALTER TABLE `catatan_transaksi`
  MODIFY `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_pesanan`
--
ALTER TABLE `item_pesanan`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kuota_tanggal`
--
ALTER TABLE `kuota_tanggal`
  MODIFY `id_kuota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id_menu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pembeli`
--
ALTER TABLE `pembeli`
  MODIFY `id_pembeli` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id_pengaturan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id_pengguna` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zona`
--
ALTER TABLE `zona`
  MODIFY `id_zona` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alamat_pembeli`
--
ALTER TABLE `alamat_pembeli`
  ADD CONSTRAINT `alamat_pembeli_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `pembeli` (`id_pembeli`);

--
-- Constraints for table `catatan_transaksi`
--
ALTER TABLE `catatan_transaksi`
  ADD CONSTRAINT `catatan_transaksi_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`),
  ADD CONSTRAINT `catatan_transaksi_ibfk_2` FOREIGN KEY (`dikonfirmasi_oleh`) REFERENCES `pengguna` (`id_pengguna`);

--
-- Constraints for table `item_pesanan`
--
ALTER TABLE `item_pesanan`
  ADD CONSTRAINT `item_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`),
  ADD CONSTRAINT `item_pesanan_ibfk_2` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`);

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `pembeli` (`id_pembeli`),
  ADD CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`id_alamat`) REFERENCES `alamat_pembeli` (`id_alamat`),
  ADD CONSTRAINT `pesanan_ibfk_3` FOREIGN KEY (`id_zona`) REFERENCES `zona` (`id_zona`),
  ADD CONSTRAINT `pesanan_ibfk_4` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id_pengguna`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
