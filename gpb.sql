-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 18 jan. 2026 à 17:38
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gpb`
--

-- --------------------------------------------------------

--
-- Structure de la table `bateau_entrant`
--

DROP TABLE IF EXISTS `bateau_entrant`;
CREATE TABLE IF NOT EXISTS `bateau_entrant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_type_bateau` int DEFAULT NULL,
  `id_port` int DEFAULT NULL,
  `nom_navire` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `immatriculation` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_capitaine` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom_capitaine` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tel_capitaine` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agence` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hauteur` decimal(10,2) DEFAULT NULL,
  `longueur` decimal(10,2) DEFAULT NULL,
  `largeur` decimal(10,2) DEFAULT NULL,
  `date_entree` datetime DEFAULT CURRENT_TIMESTAMP,
  `etat` enum('vide','chargé') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vide',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `agent_enregistrement` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_type_bateau` (`id_type_bateau`),
  KEY `id_port` (`id_port`)
) ENGINE=MyISAM AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bateau_entrant`
--

INSERT INTO `bateau_entrant` (`id`, `id_type_bateau`, `id_port`, `nom_navire`, `immatriculation`, `nom_capitaine`, `prenom_capitaine`, `tel_capitaine`, `agence`, `hauteur`, `longueur`, `largeur`, `date_entree`, `etat`, `note`, `agent_enregistrement`, `created_at`, `updated_at`) VALUES
(12, 4, 1, 'DERICLAK', 'BUR-2176', 'ETSE', 'KOMI', '93563420', 'Helvetica', 8.00, 15.00, 12.00, '2026-01-16 12:36:08', 'vide', 'Rien à signaler', 'Arnold ESIAKU', '2026-01-16 12:36:08', '2026-01-16 12:36:08'),
(11, 1, 1, 'TITANIC', 'BUR-0310', 'DOGBE', 'FRED', '93563420', 'TEATRE', 9.00, 13.00, 12.00, '2026-01-16 12:27:29', 'chargé', 'Aucune observation', 'Arnold ESIAKU', '2026-01-16 12:27:29', '2026-01-16 12:29:36'),
(13, 1, 2, 'MV LAKE VICTORIA', 'RDC-4501', 'MUKENDI', 'Joseph', '+243 971234567', 'Lake Freighters', 12.50, 45.30, 15.80, '2024-11-05 08:30:00', 'chargé', 'Arrivé avec retard de 2h', 'Arnold ESIAKU', '2024-11-05 08:30:00', '2024-11-05 08:30:00'),
(14, 4, 3, 'MV GOMA EXPRESS', 'RDC-4502', 'KABONGO', 'Marc', '+243 892345678', 'East Africa Shipping', 10.20, 38.70, 12.40, '2024-11-08 14:15:00', 'chargé', 'Conteneurs réfrigérés à bord', 'Arnold ESIAKU', '2024-11-08 14:15:00', '2024-11-08 14:15:00'),
(15, 5, 1, 'MV COLD STORAGE', 'BUR-2177', 'NKURUNZIZA', 'Jean', '+257 79563410', 'Cool Transport SA', 9.80, 42.10, 14.30, '2024-11-12 10:45:00', 'chargé', 'Poisson congelé - température -20°C', 'Arnold ESIAKU', '2024-11-12 10:45:00', '2024-11-12 10:45:00'),
(16, 2, 4, 'MV TANGANYIKA', 'TZ-3401', 'MWAMBA', 'Samuel', '+255 784561234', 'Tanzania Maritime', 11.30, 50.20, 16.50, '2024-11-15 16:20:00', 'vide', 'En attente de chargement', 'Arnold ESIAKU', '2024-11-15 16:20:00', '2024-11-15 16:20:00'),
(17, 6, 2, 'MV OIL TANKER', 'RDC-4503', 'LUBANGA', 'David', '+243 813456789', 'PetroTrans RDC', 14.50, 65.80, 22.40, '2024-11-18 09:10:00', 'chargé', 'Pétrole brut - 5000 tonnes', 'Arnold ESIAKU', '2024-11-18 09:10:00', '2024-11-18 09:10:00'),
(18, 3, 5, 'MV PASSENGER I', 'RDC-4504', 'BAHATI', 'Eric', '+243 924567890', 'Great Lakes Travel', 8.90, 35.40, 11.20, '2024-11-22 12:30:00', 'chargé', '150 passagers à bord', 'Arnold ESIAKU', '2024-11-22 12:30:00', '2024-11-22 12:30:00'),
(19, 1, 1, 'MV BUJUMBURA CARGO', 'BUR-2178', 'NZEYIMANA', 'Patrick', '+257 71234567', 'Port Services Ltd', 13.20, 48.60, 17.10, '2024-11-25 07:45:00', 'chargé', 'Café et cacao', 'Arnold ESIAKU', '2024-11-25 07:45:00', '2024-11-25 07:45:00'),
(20, 4, 6, 'MV UVIRA CONTAINER', 'RDC-4505', 'MULONDA', 'Jacques', '+243 835678901', 'Container Lines Africa', 10.80, 40.30, 13.70, '2024-11-28 15:40:00', 'chargé', '45 conteneurs standards', 'Arnold ESIAKU', '2024-11-28 15:40:00', '2024-11-28 15:40:00'),
(21, 7, 1, 'MV FISHER KING', 'BUR-2179', 'HAKIZIMANA', 'Pierre', '+257 62345678', 'Lake Fisheries', 7.40, 28.90, 9.80, '2024-12-03 06:15:00', 'chargé', 'Poisson frais - 10 tonnes', 'Arnold ESIAKU', '2024-12-03 06:15:00', '2024-12-03 06:15:00'),
(22, 2, 3, 'MV RORO EXPRESS', 'RDC-4506', 'KATUMBA', 'Paul', '+243 946789012', 'Vehicle Transport Co', 9.10, 55.80, 18.30, '2024-12-07 11:20:00', 'chargé', '12 véhicules à bord', 'Arnold ESIAKU', '2024-12-07 11:20:00', '2024-12-07 11:20:00'),
(23, 5, 4, 'MV COLD CHAIN', 'TZ-3402', 'KIMAMBO', 'John', '+255 795612345', 'Fresh Logistics', 8.70, 39.50, 12.90, '2024-12-10 13:55:00', 'chargé', 'Viande congelée', 'Arnold ESIAKU', '2024-12-10 13:55:00', '2024-12-10 13:55:00'),
(24, 1, 2, 'MV MINERAL CARRIER', 'RDC-4507', 'KABUYA', 'Simon', '+243 857890123', 'Mining Transport', 12.90, 47.20, 16.40, '2024-12-14 09:30:00', 'chargé', 'Minerais de coltan', 'Arnold ESIAKU', '2024-12-14 09:30:00', '2024-12-14 09:30:00'),
(25, 3, 1, 'MV PASSENGER II', 'BUR-2180', 'NDAYISENGA', 'François', '+257 83456789', 'Lake Express', 9.30, 36.80, 11.90, '2024-12-18 08:45:00', 'chargé', '120 passagers', 'Arnold ESIAKU', '2024-12-18 08:45:00', '2024-12-18 08:45:00'),
(26, 4, 5, 'MV BENI CONTAINER', 'RDC-4508', 'MASUKA', 'André', '+243 968901234', 'Central Africa Shipping', 11.10, 43.70, 14.80, '2024-12-22 14:10:00', 'vide', 'Conteneurs vides pour export', 'Arnold ESIAKU', '2024-12-22 14:10:00', '2024-12-22 14:10:00'),
(27, 6, 3, 'MV FUEL CARRIER', 'RDC-4509', 'MUTOMBO', 'Luc', '+243 819012345', 'Fuel Distributors', 13.80, 60.40, 20.70, '2024-12-26 10:25:00', 'chargé', 'Diesel - 4000 tonnes', 'Arnold ESIAKU', '2024-12-26 10:25:00', '2024-12-26 10:25:00'),
(28, 2, 1, 'MV CAR TRANSPORT', 'BUR-2181', 'SINDAYIGAYA', 'Emmanuel', '+257 74567890', 'Auto Shipping Ltd', 8.50, 52.30, 17.60, '2024-12-29 16:50:00', 'chargé', '8 camions neufs', 'Arnold ESIAKU', '2024-12-29 16:50:00', '2024-12-29 16:50:00'),
(29, 1, 4, 'MV KIGOMA FREIGHT', 'TZ-3403', 'MSHANA', 'Robert', '+255 786123456', 'Tanzania Cargo', 12.30, 46.50, 15.90, '2025-01-03 07:15:00', 'chargé', 'Ciment et matériaux construction', 'Arnold ESIAKU', '2025-01-03 07:15:00', '2025-01-03 07:15:00'),
(30, 5, 2, 'MV FROZEN FOOD', 'RDC-4510', 'KALALA', 'Henri', '+243 930123456', 'Food Transport SA', 9.50, 41.20, 13.50, '2025-01-07 12:40:00', 'chargé', 'Fruits et légumes surgelés', 'Arnold ESIAKU', '2025-01-07 12:40:00', '2025-01-07 12:40:00'),
(31, 3, 6, 'MV PASSENGER III', 'RDC-4511', 'BILULU', 'Philippe', '+243 841234567', 'Uvira Travel', 8.80, 34.70, 10.80, '2025-01-11 09:05:00', 'chargé', '180 passagers', 'Arnold ESIAKU', '2025-01-11 09:05:00', '2025-01-11 09:05:00'),
(32, 4, 1, 'MV PORT CONTAINER', 'BUR-2182', 'MANIRAKIZA', 'Antoine', '+257 85678901', 'Port Authority', 10.50, 39.80, 13.20, '2025-01-15 15:20:00', 'chargé', 'Conteneurs divers', 'Arnold ESIAKU', '2025-01-15 15:20:00', '2025-01-15 15:20:00'),
(33, 1, 3, 'MV GOMA CARGO', 'RDC-4512', 'TSHIBANGU', 'Olivier', '+243 952345678', 'North Kivu Transport', 13.40, 49.10, 16.80, '2025-01-19 08:35:00', 'vide', 'Navire vide pour chargement', 'Arnold ESIAKU', '2025-01-19 08:35:00', '2025-01-19 08:35:00'),
(34, 6, 5, 'MV OIL PRODUCTS', 'RDC-4513', 'KASHALA', 'Georges', '+243 823456789', 'Oil Products Ltd', 14.20, 63.50, 21.90, '2025-01-23 11:50:00', 'chargé', 'Essence et kérosène', 'Arnold ESIAKU', '2025-01-23 11:50:00', '2025-01-23 11:50:00'),
(35, 2, 4, 'MV VEHICLE CARRIER', 'TZ-3404', 'KILEO', 'Daniel', '+255 797234567', 'Vehicle Logistics', 9.70, 57.40, 19.10, '2025-01-27 14:05:00', 'chargé', '15 voitures et 5 camionnettes', 'Arnold ESIAKU', '2025-01-27 14:05:00', '2025-01-27 14:05:00'),
(36, 5, 1, 'MV REFRIGERATED', 'BUR-2183', 'NIYONKURU', 'Claude', '+257 76789012', 'Cold Storage Transport', 8.90, 40.60, 13.80, '2025-01-31 10:30:00', 'chargé', 'Produits pharmaceutiques', 'Arnold ESIAKU', '2025-01-31 10:30:00', '2025-01-31 10:30:00'),
(37, 1, 2, 'MV MINERAL SHIP', 'RDC-4514', 'MULUME', 'Albert', '+243 934567890', 'Mineral Exporters', 12.10, 44.90, 15.30, '2025-02-04 07:55:00', 'chargé', 'Or et minerais précieux', 'Arnold ESIAKU', '2025-02-04 07:55:00', '2025-02-04 07:55:00'),
(38, 3, 3, 'MV PASSENGER IV', 'RDC-4515', 'KAMBALE', 'Jules', '+243 845678901', 'Goma Passenger Lines', 9.00, 37.20, 12.10, '2025-02-08 13:10:00', 'chargé', '200 passagers', 'Arnold ESIAKU', '2025-02-08 13:10:00', '2025-02-08 13:10:00'),
(39, 4, 6, 'MV CONTAINER EXPRESS', 'RDC-4516', 'TSHILOMBO', 'Marcel', '+243 956789012', 'Express Container Service', 11.60, 42.40, 14.60, '2025-02-12 16:25:00', 'vide', 'Retour conteneurs vides', 'Arnold ESIAKU', '2025-02-12 16:25:00', '2025-02-12 16:25:00'),
(40, 1, 1, 'MV GENERAL CARGO', 'BUR-2184', 'NSENGIYUMVA', 'Romain', '+257 87890123', 'General Shipping', 13.70, 47.80, 16.20, '2025-02-16 09:40:00', 'chargé', 'Marchandises diverses', 'Arnold ESIAKU', '2025-02-16 09:40:00', '2025-02-16 09:40:00'),
(41, 6, 4, 'MV CHEMICAL TANKER', 'TZ-3405', 'MWAKYEMBE', 'Thomas', '+255 788345678', 'Chemical Transport', 15.10, 68.30, 23.50, '2025-02-20 12:55:00', 'chargé', 'Produits chimiques industriels', 'Arnold ESIAKU', '2025-02-20 12:55:00', '2025-02-20 12:55:00'),
(42, 2, 5, 'MV RO-RO CARRIER', 'RDC-4517', 'MUKENA', 'Léon', '+243 827890123', 'Roll-on Roll-off Services', 10.30, 53.70, 18.80, '2025-02-24 08:10:00', 'chargé', 'Machinerie lourde', 'Arnold ESIAKU', '2025-02-24 08:10:00', '2025-02-24 08:10:00'),
(43, 5, 2, 'MV FROZEN CARGO', 'RDC-4518', 'KABANGU', 'Victor', '+243 938901234', 'Frozen Goods Transport', 9.20, 41.80, 14.10, '2025-02-28 15:35:00', 'chargé', 'Glaces et produits laitiers', 'Arnold ESIAKU', '2025-02-28 15:35:00', '2025-02-28 15:35:00'),
(44, 1, 3, 'MV BULK CARRIER', 'RDC-4519', 'MUTEMBO', 'Gérard', '+243 849012345', 'Bulk Transport Ltd', 14.00, 58.20, 19.70, '2025-03-04 11:00:00', 'chargé', 'Céréales en vrac', 'Arnold ESIAKU', '2025-03-04 11:00:00', '2025-03-04 11:00:00'),
(45, 3, 1, 'MV PASSENGER V', 'BUR-2185', 'HITIMANA', 'Benoît', '+257 78901234', 'Lake Travel Agency', 8.60, 35.90, 11.50, '2025-03-08 14:15:00', 'chargé', '150 passagers', 'Arnold ESIAKU', '2025-03-08 14:15:00', '2025-03-08 14:15:00'),
(46, 4, 6, 'MV CONTAINER LINE', 'RDC-4520', 'KASONGO', 'René', '+243 959123456', 'Container Line Africa', 10.90, 44.20, 15.00, '2025-03-12 10:30:00', 'chargé', 'Electronique et textiles', 'Arnold ESIAKU', '2025-03-12 10:30:00', '2025-03-12 10:30:00'),
(47, 1, 4, 'MV FREIGHT LINER', 'TZ-3406', 'NGONYANI', 'William', '+255 799456789', 'Freight Liner Co', 12.80, 45.70, 15.60, '2025-03-16 07:45:00', 'vide', 'Navire en transit', 'Arnold ESIAKU', '2025-03-16 07:45:00', '2025-03-16 07:45:00'),
(48, 6, 1, 'MV LIQUID CARGO', 'BUR-2186', 'NKURIKIYIMANA', 'Charles', '+257 89012345', 'Liquid Transport', 13.90, 61.80, 21.30, '2025-03-20 12:00:00', 'chargé', 'Eau potable - 3000 tonnes', 'Arnold ESIAKU', '2025-03-20 12:00:00', '2025-03-20 12:00:00'),
(49, 2, 2, 'MV VEHICLE FERRY', 'RDC-4521', 'KABEYA', 'Alexandre', '+243 821234567', 'Vehicle Ferry Service', 9.40, 56.10, 18.50, '2025-03-24 15:15:00', 'chargé', 'Véhicules de transport public', 'Arnold ESIAKU', '2025-03-24 15:15:00', '2025-03-24 15:15:00'),
(50, 5, 5, 'MV COLD TRANSPORT', 'RDC-4522', 'MULUMBA', 'Richard', '+243 932345678', 'Cold Chain Logistics', 8.80, 40.10, 13.60, '2025-03-28 09:30:00', 'chargé', 'Vaccins et produits médicaux', 'Arnold ESIAKU', '2025-03-28 09:30:00', '2025-03-28 09:30:00'),
(51, 1, 3, 'MV CARGO MASTER', 'RDC-4523', 'TSHISEKEDI', 'Martin', '+243 843456789', 'Cargo Master Ltd', 13.50, 48.40, 16.70, '2025-04-01 14:45:00', 'chargé', 'Matériel de construction', 'Arnold ESIAKU', '2025-04-01 14:45:00', '2025-04-01 14:45:00'),
(52, 3, 1, 'MV PASSENGER VI', 'BUR-2187', 'NDIKUMANA', 'Louis', '+257 90123456', 'Passenger Lines Intl', 9.10, 36.50, 11.80, '2025-04-05 08:00:00', 'chargé', '130 passagers', 'Arnold ESIAKU', '2025-04-05 08:00:00', '2025-04-05 08:00:00'),
(53, 4, 4, 'MV CONTAINER SHIP', 'TZ-3407', 'MAKAMBA', 'Joseph', '+255 780567890', 'Container Shipping Co', 11.30, 43.60, 14.90, '2025-04-09 11:15:00', 'chargé', 'Conteneurs 20 et 40 pieds', 'Arnold ESIAKU', '2025-04-09 11:15:00', '2025-04-09 11:15:00'),
(54, 1, 6, 'MV BULK FREIGHTER', 'RDC-4524', 'KABANGE', 'Pierre', '+243 954567890', 'Bulk Freight Ltd', 14.30, 59.70, 20.40, '2025-04-13 16:30:00', 'chargé', 'Charbon et minerais', 'Arnold ESIAKU', '2025-04-13 16:30:00', '2025-04-13 16:30:00'),
(55, 6, 2, 'MV TANKER V', 'RDC-4525', 'MUKUNZI', 'André', '+243 825678901', 'Tanker Services', 14.80, 64.20, 22.10, '2025-04-17 10:45:00', 'vide', 'Citerne vide pour nettoyage', 'Arnold ESIAKU', '2025-04-17 10:45:00', '2025-04-17 10:45:00'),
(56, 2, 1, 'MV CAR TRANSPORTER', 'BUR-2188', 'SIBOMANA', 'Philippe', '+257 91234567', 'Car Transporter Ltd', 10.10, 54.80, 18.90, '2025-04-21 13:00:00', 'chargé', 'Véhicules neufs pour concessionnaires', 'Arnold ESIAKU', '2025-04-21 13:00:00', '2025-04-21 13:00:00'),
(57, 5, 5, 'MV REFRIGERATOR', 'RDC-4526', 'KALALA', 'Jean', '+243 936789012', 'Refrigerated Transport', 9.00, 39.90, 13.40, '2025-04-25 07:15:00', 'chargé', 'Poisson frais du lac', 'Arnold ESIAKU', '2025-04-25 07:15:00', '2025-04-25 07:15:00'),
(58, 1, 3, 'MV FREIGHT CARRIER', 'RDC-4527', 'TSHIBANGU', 'Paul', '+243 846890123', 'Freight Carrier Inc', 12.60, 46.90, 16.00, '2025-04-29 12:30:00', 'chargé', 'Marchandises générales', 'Arnold ESIAKU', '2025-04-29 12:30:00', '2025-04-29 12:30:00'),
(59, 3, 4, 'MV PASSENGER VII', 'TZ-3408', 'MWAKAPUSA', 'David', '+255 791678901', 'Passenger Ferry Service', 8.70, 34.20, 10.70, '2025-05-03 15:45:00', 'chargé', '160 passagers', 'Arnold ESIAKU', '2025-05-03 15:45:00', '2025-05-03 15:45:00'),
(60, 4, 1, 'MV CONTAINER CARRIER', 'BUR-2189', 'NKURUNZIZA', 'Eric', '+257 92345678', 'Container Carrier Ltd', 11.80, 41.50, 14.30, '2025-05-07 09:00:00', 'vide', 'Conteneurs pour export café', 'Arnold ESIAKU', '2025-05-07 09:00:00', '2025-05-07 09:00:00'),
(61, 1, 6, 'MV MINERAL CARRIER II', 'RDC-4528', 'MULONDA', 'Jacques', '+243 957890123', 'Mineral Carrier Ltd', 13.20, 47.50, 16.30, '2025-05-11 14:15:00', 'chargé', 'Minerais de cuivre', 'Arnold ESIAKU', '2025-05-11 14:15:00', '2025-05-11 14:15:00'),
(62, 6, 2, 'MV FUEL TRANSPORT', 'RDC-4529', 'KABEYA', 'Simon', '+243 828901234', 'Fuel Transport Co', 14.10, 62.90, 21.60, '2025-05-15 17:30:00', 'chargé', 'Carburant aviation', 'Arnold ESIAKU', '2025-05-15 17:30:00', '2025-05-15 17:30:00');

-- --------------------------------------------------------

--
-- Structure de la table `bateau_sortant`
--

DROP TABLE IF EXISTS `bateau_sortant`;
CREATE TABLE IF NOT EXISTS `bateau_sortant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_type_bateau` int DEFAULT NULL,
  `nom_navire` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `immatriculation` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_capitaine` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_capitaine` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tel_capitaine` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agence` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hauteur` decimal(10,2) DEFAULT NULL,
  `longueur` decimal(10,2) DEFAULT NULL,
  `largeur` decimal(10,2) DEFAULT NULL,
  `id_destination_port` int DEFAULT NULL,
  `etat` enum('vide','chargé') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'vide',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_sortie` datetime DEFAULT CURRENT_TIMESTAMP,
  `agent_enregistrement` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_bateau` (`id_type_bateau`),
  KEY `id_destination_port` (`id_destination_port`)
) ENGINE=MyISAM AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bateau_sortant`
--

INSERT INTO `bateau_sortant` (`id`, `id_type_bateau`, `nom_navire`, `immatriculation`, `nom_capitaine`, `prenom_capitaine`, `tel_capitaine`, `agence`, `hauteur`, `longueur`, `largeur`, `id_destination_port`, `etat`, `note`, `date_sortie`, `agent_enregistrement`) VALUES
(1, 1, 'TITANIC', 'BUR-4390', 'FOLLY', 'JAMES', '93563420', 'DOUFELITA', 10.00, 13.00, 12.00, 1, 'chargé', 'Note', '2026-01-16 14:11:57', 'Patrice AYITE'),
(2, 1, 'MV EXPORT CARGO', 'BUR-2190', 'NZEYIMANA', 'Jean-Paul', '+257 71234568', 'Export Lines Ltd', 12.80, 46.20, 15.90, 4, 'chargé', 'Export vers Tanzanie', '2024-11-06 14:30:00', 'Patrice AYITE'),
(3, 4, 'MV CONTAINER EXPORT', 'BUR-2191', 'NKURUNZIZA', 'David', '+257 79563411', 'Container Export Co', 10.40, 39.10, 13.30, 2, 'chargé', 'Conteneurs pleins pour RDC', '2024-11-09 09:45:00', 'Patrice AYITE'),
(4, 5, 'MV COLD EXPORT', 'BUR-2192', 'HAKIZIMANA', 'Paul', '+257 62345679', 'Cold Export SA', 9.10, 41.30, 14.00, 3, 'chargé', 'Produits congelés pour Goma', '2024-11-13 16:20:00', 'Patrice AYITE'),
(5, 2, 'MV RORO EXPORT', 'BUR-2193', 'NDAYISENGA', 'Pierre', '+257 83456790', 'RORO Export Services', 9.30, 54.20, 18.10, 6, 'chargé', 'Véhicules pour Uvira', '2024-11-16 11:10:00', 'Patrice AYITE'),
(6, 1, 'MV COFFEE CARRIER', 'BUR-2194', 'MANIRAKIZA', 'Antoine', '+257 85678902', 'Coffee Export Ltd', 13.50, 48.10, 16.60, 9, 'chargé', 'Café burundais pour Mombasa', '2024-11-20 08:25:00', 'Patrice AYITE'),
(7, 3, 'MV PASSENGER EXPORT', 'BUR-2195', 'HITIMANA', 'Benoît', '+257 78901235', 'Passenger Export', 8.90, 36.20, 11.70, 4, 'chargé', 'Passagers pour Kigoma', '2024-11-24 13:40:00', 'Patrice AYITE'),
(8, 4, 'MV CONTAINER SHIP II', 'BUR-2196', 'NKURIKIYIMANA', 'Charles', '+257 89012346', 'Container Shipping', 11.20, 42.70, 14.50, 5, 'chargé', 'Conteneurs pour Beni', '2024-11-27 15:55:00', 'Patrice AYITE'),
(9, 6, 'MV TANKER EXPORT', 'BUR-2197', 'SIBOMANA', 'Philippe', '+257 91234568', 'Tanker Export Co', 14.30, 63.10, 21.80, 2, 'chargé', 'Pétrole raffiné pour Kalemie', '2024-12-01 10:05:00', 'Patrice AYITE'),
(10, 1, 'MV GENERAL EXPORT', 'BUR-2198', 'NDIKUMANA', 'Louis', '+257 90123457', 'General Export Ltd', 12.40, 45.80, 15.70, 3, 'chargé', 'Marchandises diverses pour Goma', '2024-12-04 07:20:00', 'Patrice AYITE'),
(11, 5, 'MV FROZEN EXPORT', 'BUR-2199', 'NSENGIYUMVA', 'Romain', '+257 87890124', 'Frozen Export SA', 8.60, 40.80, 13.90, 4, 'chargé', 'Produits surgelés pour Kigoma', '2024-12-08 12:35:00', 'Patrice AYITE'),
(12, 2, 'MV VEHICLE EXPORT', 'BUR-2200', 'SINDAYIGAYA', 'Emmanuel', '+257 74567891', 'Vehicle Export Ltd', 10.00, 55.10, 18.40, 6, 'chargé', 'Véhicules pour Uvira', '2024-12-11 14:50:00', 'Patrice AYITE'),
(13, 1, 'MV MINERAL EXPORT', 'BUR-2201', 'NZEYIMANA', 'Jean-Paul', '+257 71234569', 'Mineral Export Ltd', 13.80, 49.30, 16.90, 2, 'chargé', 'Minerais pour Kalemie', '2024-12-15 09:05:00', 'Patrice AYITE'),
(14, 3, 'MV PASSENGER EXPRESS', 'BUR-2202', 'NKURUNZIZA', 'David', '+257 79563412', 'Passenger Express', 9.20, 37.40, 12.20, 4, 'chargé', 'Passagers pour Tanzanie', '2024-12-19 16:20:00', 'Patrice AYITE'),
(15, 4, 'MV CONTAINER EXPRESS II', 'BUR-2203', 'HAKIZIMANA', 'Paul', '+257 62345680', 'Container Express', 11.60, 43.90, 14.90, 5, 'chargé', 'Conteneurs express pour Beni', '2024-12-23 11:35:00', 'Patrice AYITE'),
(16, 1, 'MV BULK EXPORT', 'BUR-2204', 'NDAYISENGA', 'Pierre', '+257 83456791', 'Bulk Export Ltd', 14.20, 57.90, 19.60, 3, 'chargé', 'Céréales en vrac pour Goma', '2024-12-27 08:50:00', 'Patrice AYITE'),
(17, 6, 'MV FUEL EXPORT', 'BUR-2205', 'MANIRAKIZA', 'Antoine', '+257 85678903', 'Fuel Export Co', 14.90, 64.70, 22.30, 2, 'chargé', 'Carburant pour Kalemie', '2024-12-30 13:05:00', 'Patrice AYITE'),
(18, 5, 'MV PHARMA EXPORT', 'BUR-2206', 'HITIMANA', 'Benoît', '+257 78901236', 'Pharma Export SA', 9.40, 41.90, 14.20, 4, 'chargé', 'Produits pharmaceutiques', '2025-01-02 10:20:00', 'Patrice AYITE'),
(19, 1, 'MV TEXTILE EXPORT', 'BUR-2207', 'NKURIKIYIMANA', 'Charles', '+257 89012347', 'Textile Export Ltd', 12.70, 47.20, 16.10, 6, 'chargé', 'Textiles pour Uvira', '2025-01-06 15:35:00', 'Patrice AYITE'),
(20, 2, 'MV MACHINERY EXPORT', 'BUR-2208', 'SIBOMANA', 'Philippe', '+257 91234569', 'Machinery Export', 10.50, 56.30, 19.00, 5, 'chargé', 'Machinerie pour Beni', '2025-01-10 08:50:00', 'Patrice AYITE'),
(21, 3, 'MV TOURIST EXPRESS', 'BUR-2209', 'NDIKUMANA', 'Louis', '+257 90123458', 'Tourist Express', 8.80, 35.70, 11.60, 4, 'chargé', 'Touristes pour Kigoma', '2025-01-14 12:05:00', 'Patrice AYITE'),
(22, 4, 'MV CONTAINER FREIGHT', 'BUR-2210', 'NSENGIYUMVA', 'Romain', '+257 87890125', 'Container Freight', 11.90, 44.40, 15.10, 2, 'chargé', 'Conteneurs fret pour Kalemie', '2025-01-18 07:20:00', 'Patrice AYITE'),
(23, 1, 'MV AGRICULTURAL EXPORT', 'BUR-2211', 'SINDAYIGAYA', 'Emmanuel', '+257 74567892', 'Agricultural Export', 13.10, 46.80, 15.80, 3, 'chargé', 'Produits agricoles pour Goma', '2025-01-22 14:35:00', 'Patrice AYITE'),
(24, 6, 'MV CHEMICAL EXPORT', 'BUR-2212', 'NZEYIMANA', 'Jean-Paul', '+257 71234570', 'Chemical Export Co', 15.20, 67.50, 23.20, 4, 'chargé', 'Produits chimiques pour Kigoma', '2025-01-26 09:50:00', 'Patrice AYITE'),
(25, 5, 'MV DAIRY EXPORT', 'BUR-2213', 'NKURUNZIZA', 'David', '+257 79563413', 'Dairy Export SA', 9.00, 40.50, 13.70, 5, 'chargé', 'Produits laitiers pour Beni', '2025-01-30 16:05:00', 'Patrice AYITE'),
(26, 1, 'MV CONSTRUCTION EXPORT', 'BUR-2214', 'HAKIZIMANA', 'Paul', '+257 62345681', 'Construction Export', 13.90, 48.90, 16.80, 6, 'chargé', 'Matériaux construction pour Uvira', '2025-02-03 11:20:00', 'Patrice AYITE'),
(27, 2, 'MV BUS EXPORT', 'BUR-2215', 'NDAYISENGA', 'Pierre', '+257 83456792', 'Bus Export Ltd', 9.60, 53.60, 18.70, 2, 'chargé', 'Bus pour transports publics', '2025-02-07 08:35:00', 'Patrice AYITE'),
(28, 3, 'MV COMMUTER EXPRESS', 'BUR-2216', 'MANIRAKIZA', 'Antoine', '+257 85678904', 'Commuter Express', 9.30, 36.90, 12.00, 4, 'chargé', 'Navette quotidienne', '2025-02-11 13:50:00', 'Patrice AYITE'),
(29, 4, 'MV LOGISTICS CONTAINER', 'BUR-2217', 'HITIMANA', 'Benoît', '+257 78901237', 'Logistics Container', 10.70, 42.20, 14.40, 3, 'chargé', 'Logistique pour Goma', '2025-02-15 10:05:00', 'Patrice AYITE'),
(30, 1, 'MV FOOD EXPORT', 'BUR-2218', 'NKURIKIYIMANA', 'Charles', '+257 89012348', 'Food Export Ltd', 12.20, 45.10, 15.40, 5, 'chargé', 'Denrées alimentaires pour Beni', '2025-02-19 15:20:00', 'Patrice AYITE'),
(31, 6, 'MV WATER EXPORT', 'BUR-2219', 'SIBOMANA', 'Philippe', '+257 91234570', 'Water Export Co', 13.70, 62.40, 21.50, 4, 'chargé', 'Eau potable pour Kigoma', '2025-02-23 08:35:00', 'Patrice AYITE'),
(32, 5, 'MV MEAT EXPORT', 'BUR-2220', 'NDIKUMANA', 'Louis', '+257 90123459', 'Meat Export SA', 8.50, 39.20, 13.30, 2, 'chargé', 'Viande congelée pour Kalemie', '2025-02-27 12:50:00', 'Patrice AYITE'),
(33, 1, 'MV ELECTRONICS EXPORT', 'BUR-2221', 'NSENGIYUMVA', 'Romain', '+257 87890126', 'Electronics Export', 12.90, 47.70, 16.30, 6, 'chargé', 'Électronique pour Uvira', '2025-03-03 09:05:00', 'Patrice AYITE'),
(34, 2, 'MV TRUCK EXPORT', 'BUR-2222', 'SINDAYIGAYA', 'Emmanuel', '+257 74567893', 'Truck Export Ltd', 10.20, 54.90, 18.60, 3, 'chargé', 'Camions pour Goma', '2025-03-07 14:20:00', 'Patrice AYITE'),
(35, 3, 'MV FERRY EXPRESS', 'BUR-2223', 'NZEYIMANA', 'Jean-Paul', '+257 71234571', 'Ferry Express', 8.90, 35.10, 11.40, 4, 'chargé', 'Traversée régulière', '2025-03-11 11:35:00', 'Patrice AYITE'),
(36, 4, 'MV SHIPPING CONTAINER', 'BUR-2224', 'NKURUNZIZA', 'David', '+257 79563414', 'Shipping Container Co', 11.40, 43.10, 14.70, 5, 'chargé', 'Conteneurs maritimes', '2025-03-15 16:50:00', 'Patrice AYITE'),
(37, 1, 'MV RAW MATERIALS EXPORT', 'BUR-2225', 'HAKIZIMANA', 'Paul', '+257 62345682', 'Raw Materials Export', 14.10, 58.60, 19.90, 2, 'chargé', 'Matières premières pour Kalemie', '2025-03-19 10:05:00', 'Patrice AYITE'),
(38, 6, 'MV GAS EXPORT', 'BUR-2226', 'NDAYISENGA', 'Pierre', '+257 83456793', 'Gas Export Co', 15.00, 65.80, 22.70, 4, 'chargé', 'Gaz butane pour Kigoma', '2025-03-23 13:20:00', 'Patrice AYITE'),
(39, 5, 'MV VEGETABLE EXPORT', 'BUR-2227', 'MANIRAKIZA', 'Antoine', '+257 85678905', 'Vegetable Export SA', 8.70, 40.20, 13.60, 6, 'chargé', 'Légumes frais pour Uvira', '2025-03-27 08:35:00', 'Patrice AYITE'),
(40, 1, 'MV BEVERAGE EXPORT', 'BUR-2228', 'HITIMANA', 'Benoît', '+257 78901238', 'Beverage Export Ltd', 12.50, 46.40, 15.80, 3, 'chargé', 'Boissons pour Goma', '2025-03-31 12:50:00', 'Patrice AYITE'),
(41, 2, 'MV EQUIPMENT EXPORT', 'BUR-2229', 'NKURIKIYIMANA', 'Charles', '+257 89012349', 'Equipment Export', 9.80, 55.70, 19.20, 5, 'chargé', 'Équipements pour Beni', '2025-04-04 09:05:00', 'Patrice AYITE');

-- --------------------------------------------------------

--
-- Structure de la table `camions_entrants`
--

DROP TABLE IF EXISTS `camions_entrants`;
CREATE TABLE IF NOT EXISTS `camions_entrants` (
  `idEntree` int NOT NULL AUTO_INCREMENT,
  `idTypeCamion` int NOT NULL,
  `marque` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `immatriculation` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_chauffeur` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_chauffeur` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone_chauffeur` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agence` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idPort` int NOT NULL,
  `destinataire` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etat` enum('Chargé','Vide') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chargé',
  `raison` enum('Pesage','Déchargement','Chargement','Déchargement et chargement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pesage',
  `date_entree` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_sortie` datetime DEFAULT NULL,
  `idUser` int DEFAULT NULL,
  `poids` int DEFAULT NULL,
  PRIMARY KEY (`idEntree`),
  UNIQUE KEY `immatriculation` (`immatriculation`),
  KEY `idTypeCamion` (`idTypeCamion`),
  KEY `idPort` (`idPort`),
  KEY `idUser` (`idUser`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `camions_entrants`
--

INSERT INTO `camions_entrants` (`idEntree`, `idTypeCamion`, `marque`, `immatriculation`, `nom_chauffeur`, `prenom_chauffeur`, `telephone_chauffeur`, `agence`, `nif`, `idPort`, `destinataire`, `t1`, `etat`, `raison`, `date_entree`, `date_sortie`, `idUser`, `poids`) VALUES
(1, 2, 'Volvo', 'BUR-4810', 'WANGRE', 'David', '+228 97732644', 'VALHALA', 'ER-789', 1, 'HERCUL', '1200', 'Chargé', 'Déchargement', '2026-01-14 12:35:37', NULL, NULL, 300),
(2, 3, 'Volvo', 'BUR-4390', 'DEGBE', 'Jean', '+228 99754220', '', '', 1, '', '', 'Vide', 'Chargement', '2026-01-14 14:39:34', NULL, NULL, 0),
(3, 1, 'Mercedes', 'BUR-4811', 'KABURA', 'Eric', '+257 71234567', 'Trans Africa', 'NIF-001', 1, 'Société ABC', '1500', 'Chargé', 'Déchargement', '2024-11-03 08:15:00', NULL, NULL, 0),
(4, 2, 'Volvo', 'BUR-4812', 'NIYONGABO', 'Jean', '+257 79563420', 'Global Transport', 'NIF-002', 1, 'Entreprise XYZ', '1800', 'Chargé', 'Pesage', '2024-11-04 09:30:00', NULL, NULL, 0),
(5, 3, 'MAN', 'BUR-4813', 'NDAYIZEYE', 'Pierre', '+257 62345678', 'Fast Cargo', 'NIF-003', 1, 'Compagnie 123', '2000', 'Vide', 'Chargement', '2024-11-05 10:45:00', NULL, NULL, 0),
(6, 4, 'Scania', 'BUR-4814', 'HABIMANA', 'Paul', '+257 83456789', 'Heavy Haul', 'NIF-004', 1, 'Société DEF', '2200', 'Chargé', 'Déchargement', '2024-11-06 11:20:00', NULL, NULL, 0),
(7, 5, 'Iveco', 'BUR-4815', 'NKURUNZIZA', 'David', '+257 85678901', 'Bulk Transport', 'NIF-005', 1, 'Entreprise GHI', '2500', 'Chargé', 'Pesage', '2024-11-07 12:35:00', NULL, NULL, 0),
(8, 6, 'DAF', 'BUR-4816', 'NZEYIMANA', 'Antoine', '+257 87890123', 'Construction Haul', 'NIF-006', 1, 'Compagnie JKL', '2800', 'Vide', 'Chargement', '2024-11-08 13:50:00', NULL, NULL, 0),
(9, 7, 'Renault', 'BUR-4817', 'HAKIZIMANA', 'Claude', '+257 89012345', 'Fuel Transport', 'NIF-007', 1, 'Société MNO', '3000', 'Chargé', 'Déchargement', '2024-11-09 14:05:00', NULL, NULL, 0),
(10, 8, 'Ford', 'BUR-4818', 'NDAYISENGA', 'François', '+257 90123456', 'Cold Chain', 'NIF-008', 1, 'Entreprise PQR', '3200', 'Chargé', 'Pesage', '2024-11-10 15:20:00', NULL, NULL, 0),
(11, 1, 'Mercedes', 'BUR-4819', 'MANIRAKIZA', 'Emmanuel', '+257 91234567', 'General Freight', 'NIF-009', 1, 'Compagnie STU', '1500', 'Vide', 'Chargement', '2024-11-11 16:35:00', NULL, NULL, 0),
(12, 2, 'Volvo', 'BUR-4820', 'HITIMANA', 'Benoît', '+257 92345678', 'Express Transport', 'NIF-010', 1, 'Société VWX', '1800', 'Chargé', 'Déchargement', '2024-11-12 17:50:00', NULL, NULL, 0),
(13, 3, 'MAN', 'BUR-4821', 'NKURIKIYIMANA', 'Charles', '+257 93456789', 'Agricultural Haul', 'NIF-011', 1, 'Entreprise YZ', '2000', 'Chargé', 'Pesage', '2024-11-13 08:25:00', NULL, NULL, 0),
(14, 4, 'Scania', 'BUR-4822', 'SIBOMANA', 'Philippe', '+257 94567890', 'Mineral Transport', 'NIF-012', 1, 'Compagnie 456', '2200', 'Vide', 'Chargement', '2024-11-14 09:40:00', NULL, NULL, 0),
(15, 5, 'Iveco', 'BUR-4823', 'NDIKUMANA', 'Louis', '+257 95678901', 'Textile Transport', 'NIF-013', 1, 'Société 789', '2500', 'Chargé', 'Déchargement', '2024-11-15 10:55:00', NULL, NULL, 0),
(16, 6, 'DAF', 'BUR-4824', 'NSENGIYUMVA', 'Romain', '+257 96789012', 'Electronics Transport', 'NIF-014', 1, 'Entreprise 012', '2800', 'Chargé', 'Pesage', '2024-11-16 12:10:00', NULL, NULL, 0),
(17, 7, 'Renault', 'BUR-4825', 'SINDAYIGAYA', 'Eric', '+257 97890123', 'Pharma Transport', 'NIF-015', 1, 'Compagnie 345', '3000', 'Vide', 'Chargement', '2024-11-17 13:25:00', NULL, NULL, 0),
(18, 8, 'Ford', 'BUR-4826', 'NZEYIMANA', 'Jean-Paul', '+257 98901234', 'Beverage Transport', 'NIF-016', 1, 'Société 678', '3200', 'Chargé', 'Déchargement', '2024-11-18 14:40:00', NULL, NULL, 0),
(19, 1, 'Mercedes', 'BUR-4827', 'NKURUNZIZA', 'David', '+257 99012345', 'Construction Transport', 'NIF-017', 1, 'Entreprise 901', '1500', 'Chargé', 'Pesage', '2024-11-19 15:55:00', NULL, NULL, 0),
(20, 2, 'Volvo', 'BUR-4828', 'HAKIZIMANA', 'Paul', '+257 90123456', 'Food Transport', 'NIF-018', 1, 'Compagnie 234', '1800', 'Vide', 'Chargement', '2024-11-20 17:10:00', NULL, NULL, 0),
(21, 3, 'MAN', 'BUR-4829', 'NDAYISENGA', 'Pierre', '+257 91234567', 'General Cargo', 'NIF-019', 1, 'Société 567', '2000', 'Chargé', 'Déchargement', '2024-11-21 08:45:00', NULL, NULL, 0),
(22, 4, 'Scania', 'BUR-4830', 'MANIRAKIZA', 'Antoine', '+257 92345678', 'Bulk Cargo', 'NIF-020', 1, 'Entreprise 890', '2200', 'Chargé', 'Pesage', '2024-11-22 10:00:00', NULL, NULL, 0),
(23, 1, 'Mercedes', 'BUR-4831', 'HAKIZIMANA', 'Eric', '+257 93456789', 'Fast Logistics', 'NIF-021', 1, 'SARL Delta', '1500', 'Vide', 'Chargement', '2024-11-23 11:15:00', NULL, NULL, 0),
(24, 2, 'Volvo', 'BUR-4832', 'NZEYIMANA', 'Claude', '+257 94567890', 'Heavy Transport', 'NIF-022', 1, 'Compagnie Omega', '1800', 'Chargé', 'Déchargement', '2024-11-24 12:30:00', NULL, NULL, 0),
(25, 3, 'MAN', 'BUR-4833', 'NDAYISENGA', 'Philippe', '+257 95678901', 'Bulk Cargo Ltd', 'NIF-023', 1, 'Entreprise Gamma', '2000', 'Chargé', 'Pesage', '2024-11-25 13:45:00', NULL, NULL, 0),
(26, 4, 'Scania', 'BUR-4834', 'MANIRAKIZA', 'Robert', '+257 96789012', 'Construction Haul', 'NIF-024', 1, 'Société Sigma', '2200', 'Vide', 'Chargement', '2024-11-26 14:00:00', NULL, NULL, 0),
(27, 5, 'Iveco', 'BUR-4835', 'HITIMANA', 'Thomas', '+257 97890123', 'Food Transport SA', 'NIF-025', 1, 'Compagnie Zeta', '2500', 'Chargé', 'Déchargement', '2024-11-27 15:15:00', NULL, NULL, 0),
(28, 6, 'DAF', 'BUR-4836', 'NKURIKIYIMANA', 'Samuel', '+257 98901234', 'Mining Transport', 'NIF-026', 1, 'SARL Theta', '2800', 'Chargé', 'Pesage', '2024-11-28 16:30:00', NULL, NULL, 0),
(29, 7, 'Renault', 'BUR-4837', 'SIBOMANA', 'Alexandre', '+257 99012345', 'Chemical Transport', 'NIF-027', 1, 'Entreprise Kappa', '3000', 'Vide', 'Chargement', '2024-11-29 17:45:00', NULL, NULL, 0),
(30, 8, 'Ford', 'BUR-4838', 'NDIKUMANA', 'Dominique', '+257 90123456', 'Cold Storage Ltd', 'NIF-028', 1, 'Compagnie Lambda', '3200', 'Chargé', 'Déchargement', '2024-11-30 18:00:00', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `camions_sortants`
--

DROP TABLE IF EXISTS `camions_sortants`;
CREATE TABLE IF NOT EXISTS `camions_sortants` (
  `idSortie` int NOT NULL AUTO_INCREMENT,
  `idEntree` int NOT NULL,
  `idChargement` int DEFAULT NULL,
  `idDechargement` int DEFAULT NULL,
  `date_sortie` datetime NOT NULL,
  `type_sortie` enum('charge','decharge') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSortie`),
  KEY `idx_date_sortie` (`date_sortie`),
  KEY `idx_type_sortie` (`type_sortie`),
  KEY `idx_camion` (`idEntree`),
  KEY `idx_chargement` (`idChargement`),
  KEY `idx_dechargement` (`idDechargement`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `camions_sortants`
--

INSERT INTO `camions_sortants` (`idSortie`, `idEntree`, `idChargement`, `idDechargement`, `date_sortie`, `type_sortie`, `created_at`) VALUES
(2, 1, 0, 1, '2026-01-15 22:38:04', 'decharge', '2026-01-15 22:38:04'),
(3, 3, 0, 2, '2024-11-03 11:00:00', 'decharge', '2024-11-03 11:00:00'),
(4, 4, 0, 3, '2024-11-04 13:15:00', 'decharge', '2024-11-04 13:15:00'),
(5, 5, 2, 0, '2024-11-05 15:30:00', 'charge', '2024-11-05 15:30:00'),
(6, 6, 0, 4, '2024-11-06 15:00:00', 'decharge', '2024-11-06 15:00:00'),
(7, 7, 0, 5, '2024-11-07 16:15:00', 'decharge', '2024-11-07 16:15:00'),
(8, 8, 3, 0, '2024-11-08 17:45:00', 'charge', '2024-11-08 17:45:00'),
(9, 9, 0, 6, '2024-11-09 18:30:00', 'decharge', '2024-11-09 18:30:00'),
(10, 10, 0, 7, '2024-11-10 19:45:00', 'decharge', '2024-11-10 19:45:00'),
(11, 11, 4, 0, '2024-11-11 20:00:00', 'charge', '2024-11-11 20:00:00'),
(12, 12, 0, 8, '2024-11-12 22:00:00', 'decharge', '2024-11-12 22:00:00'),
(13, 13, 0, 9, '2024-11-13 10:15:00', 'decharge', '2024-11-13 10:15:00'),
(14, 14, 5, 0, '2024-11-14 11:15:00', 'charge', '2024-11-14 11:15:00'),
(15, 15, 0, 10, '2024-11-15 13:30:00', 'decharge', '2024-11-15 13:30:00'),
(16, 16, 0, 11, '2024-11-16 15:45:00', 'decharge', '2024-11-16 15:45:00'),
(17, 17, 6, 0, '2024-11-17 14:30:00', 'charge', '2024-11-17 14:30:00'),
(18, 18, 0, 12, '2024-11-18 18:00:00', 'decharge', '2024-11-18 18:00:00'),
(19, 19, 0, 13, '2024-11-19 20:15:00', 'decharge', '2024-11-19 20:15:00'),
(20, 20, 7, 0, '2024-11-20 17:45:00', 'charge', '2024-11-20 17:45:00'),
(21, 21, 0, 14, '2024-11-21 12:30:00', 'decharge', '2024-11-21 12:30:00'),
(22, 22, 0, 15, '2024-11-22 14:45:00', 'decharge', '2024-11-22 14:45:00'),
(23, 23, 0, 16, '2024-11-23 15:00:00', 'decharge', '2024-11-23 15:00:00'),
(24, 24, 0, 17, '2024-11-24 16:15:00', 'decharge', '2024-11-24 16:15:00'),
(25, 25, 0, 18, '2024-11-25 17:30:00', 'decharge', '2024-11-25 17:30:00'),
(26, 26, 8, 0, '2024-11-26 18:45:00', 'charge', '2024-11-26 18:45:00'),
(27, 27, 0, 19, '2024-11-27 19:00:00', 'decharge', '2024-11-27 19:00:00'),
(28, 28, 0, 20, '2024-11-28 20:15:00', 'decharge', '2024-11-28 20:15:00'),
(29, 29, 9, 0, '2024-11-29 21:30:00', 'charge', '2024-11-29 21:30:00'),
(30, 30, 0, 21, '2024-11-30 22:45:00', 'decharge', '2024-11-30 22:45:00');

-- --------------------------------------------------------

--
-- Structure de la table `chargement_camions`
--

DROP TABLE IF EXISTS `chargement_camions`;
CREATE TABLE IF NOT EXISTS `chargement_camions` (
  `idChargement` int NOT NULL AUTO_INCREMENT,
  `idEntree` int NOT NULL,
  `date_chargement` datetime DEFAULT CURRENT_TIMESTAMP,
  `note_chargement` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`idChargement`),
  KEY `idEntree` (`idEntree`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `chargement_camions`
--

INSERT INTO `chargement_camions` (`idChargement`, `idEntree`, `date_chargement`, `note_chargement`) VALUES
(1, 2, '2026-01-15 19:44:57', ''),
(2, 5, '2024-11-05 14:30:00', 'Chargement complet'),
(3, 8, '2024-11-08 16:45:00', 'Chargement partiel'),
(4, 11, '2024-11-11 19:00:00', 'Chargement urgent'),
(5, 14, '2024-11-14 10:15:00', 'Chargement standard'),
(6, 17, '2024-11-17 13:30:00', 'Chargement contrôlé'),
(7, 20, '2024-11-20 16:45:00', 'Chargement express'),
(8, 23, '2024-11-23 12:00:00', 'Chargement standard'),
(9, 24, '2024-11-24 13:15:00', 'Chargement urgent'),
(10, 25, '2024-11-25 14:30:00', 'Chargement contrôlé'),
(11, 26, '2024-11-26 15:45:00', 'Chargement partiel'),
(12, 27, '2024-11-27 16:00:00', 'Chargement complet'),
(13, 28, '2024-11-28 17:15:00', 'Chargement express'),
(14, 29, '2024-11-29 18:30:00', 'Chargement standard'),
(15, 30, '2024-11-30 19:45:00', 'Chargement contrôlé'),
(16, 1, '2024-12-01 08:00:00', 'Chargement urgent'),
(17, 3, '2024-12-02 09:15:00', 'Chargement partiel'),
(18, 5, '2024-12-03 10:30:00', 'Chargement complet'),
(19, 7, '2024-12-04 11:45:00', 'Chargement standard'),
(20, 9, '2024-12-05 12:00:00', 'Chargement contrôlé'),
(21, 11, '2024-12-06 13:15:00', 'Chargement urgent'),
(22, 13, '2024-12-07 14:30:00', 'Chargement partiel'),
(23, 15, '2024-12-08 15:45:00', 'Chargement complet'),
(24, 17, '2024-12-09 16:00:00', 'Chargement standard'),
(25, 19, '2024-12-10 17:15:00', 'Chargement contrôlé'),
(26, 21, '2024-12-11 18:30:00', 'Chargement urgent'),
(27, 23, '2024-12-12 19:45:00', 'Chargement partiel'),
(28, 25, '2024-12-13 20:00:00', 'Chargement complet'),
(29, 27, '2024-12-14 21:15:00', 'Chargement standard'),
(30, 29, '2024-12-15 22:30:00', 'Chargement contrôlé');

-- --------------------------------------------------------

--
-- Structure de la table `dechargements_camions`
--

DROP TABLE IF EXISTS `dechargements_camions`;
CREATE TABLE IF NOT EXISTS `dechargements_camions` (
  `idDechargement` int NOT NULL AUTO_INCREMENT,
  `idEntree` int NOT NULL,
  `idChargement` int NOT NULL,
  `date_dechargement` datetime DEFAULT CURRENT_TIMESTAMP,
  `note_dechargement` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`idDechargement`),
  KEY `idx_date_dechargement` (`date_dechargement`),
  KEY `idx_idEntree` (`idEntree`),
  KEY `idx_idChargement` (`idChargement`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `dechargements_camions`
--

INSERT INTO `dechargements_camions` (`idDechargement`, `idEntree`, `idChargement`, `date_dechargement`, `note_dechargement`) VALUES
(1, 1, 0, '2026-01-15 19:38:18', ''),
(2, 3, 0, '2024-11-03 10:30:00', 'Déchargement rapide'),
(3, 4, 0, '2024-11-04 12:45:00', 'Déchargement contrôlé'),
(4, 6, 0, '2024-11-06 14:00:00', 'Déchargement standard'),
(5, 7, 0, '2024-11-07 15:15:00', 'Déchargement urgent'),
(6, 9, 0, '2024-11-09 17:30:00', 'Déchargement complet'),
(7, 10, 0, '2024-11-10 18:45:00', 'Déchargement partiel'),
(8, 12, 0, '2024-11-12 21:00:00', 'Déchargement rapide'),
(9, 13, 0, '2024-11-13 09:15:00', 'Déchargement contrôlé'),
(10, 15, 0, '2024-11-15 12:30:00', 'Déchargement standard'),
(11, 16, 0, '2024-11-16 14:45:00', 'Déchargement urgent'),
(12, 18, 0, '2024-11-18 17:00:00', 'Déchargement complet'),
(13, 19, 0, '2024-11-19 19:15:00', 'Déchargement partiel'),
(14, 21, 0, '2024-11-21 11:30:00', 'Déchargement rapide'),
(15, 22, 0, '2024-11-22 13:45:00', 'Déchargement contrôlé'),
(16, 23, 0, '2024-11-23 14:00:00', 'Déchargement standard'),
(17, 24, 0, '2024-11-24 15:15:00', 'Déchargement urgent'),
(18, 25, 0, '2024-11-25 16:30:00', 'Déchargement contrôlé'),
(19, 26, 0, '2024-11-26 17:45:00', 'Déchargement partiel'),
(20, 27, 0, '2024-11-27 18:00:00', 'Déchargement complet'),
(21, 28, 0, '2024-11-28 19:15:00', 'Déchargement express'),
(22, 29, 0, '2024-11-29 20:30:00', 'Déchargement standard'),
(23, 30, 0, '2024-11-30 21:45:00', 'Déchargement contrôlé'),
(24, 2, 0, '2024-12-01 10:00:00', 'Déchargement urgent'),
(25, 4, 0, '2024-12-02 11:15:00', 'Déchargement partiel'),
(26, 6, 0, '2024-12-03 12:30:00', 'Déchargement complet'),
(27, 8, 0, '2024-12-04 13:45:00', 'Déchargement standard'),
(28, 10, 0, '2024-12-05 14:00:00', 'Déchargement contrôlé'),
(29, 12, 0, '2024-12-06 15:15:00', 'Déchargement urgent'),
(30, 14, 0, '2024-12-07 16:30:00', 'Déchargement partiel');

-- --------------------------------------------------------

--
-- Structure de la table `frais_transit`
--

DROP TABLE IF EXISTS `frais_transit`;
CREATE TABLE IF NOT EXISTS `frais_transit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type_entite` enum('camion_entrant','camion_sortant','bateau_entrant','bateau_sortant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_entite` int NOT NULL,
  `frais_thc` decimal(10,2) DEFAULT NULL,
  `frais_magasinage` decimal(10,2) DEFAULT NULL,
  `droits_douane` decimal(10,2) DEFAULT NULL,
  `surestaries` decimal(10,2) DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_frais` (`type_entite`,`id_entite`),
  KEY `idx_type_entite` (`type_entite`,`id_entite`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `frais_transit`
--

INSERT INTO `frais_transit` (`id`, `type_entite`, `id_entite`, `frais_thc`, `frais_magasinage`, `droits_douane`, `surestaries`, `commentaire`, `date_ajout`, `date_modification`) VALUES
(1, 'camion_entrant', 1, 150.00, 75.00, 300.00, 0.00, 'Frais standard pour camion entrant', '2024-11-14 09:00:00', '2024-11-14 09:00:00'),
(2, 'camion_entrant', 3, 180.00, 90.00, 350.00, 25.00, 'Retard de 2 jours', '2024-11-03 10:00:00', '2024-11-05 10:00:00'),
(3, 'camion_entrant', 5, 200.00, 100.00, 400.00, 0.00, 'Frais pour camion vide', '2024-11-05 12:00:00', '2024-11-05 12:00:00'),
(4, 'bateau_entrant', 13, 1500.00, 750.00, 3000.00, 500.00, 'Bateau avec retard de déchargement', '2024-11-05 10:00:00', '2024-11-07 10:00:00'),
(5, 'bateau_entrant', 15, 1200.00, 600.00, 2500.00, 0.00, 'Frais pour bateau frigorifique', '2024-11-12 12:00:00', '2024-11-12 12:00:00'),
(6, 'bateau_sortant', 2, 1300.00, 650.00, 2800.00, 0.00, 'Export vers Tanzanie', '2024-11-06 15:00:00', '2024-11-06 15:00:00'),
(7, 'bateau_sortant', 4, 1100.00, 550.00, 2300.00, 100.00, 'Retrait retardé', '2024-11-13 17:00:00', '2024-11-15 17:00:00'),
(8, 'camion_sortant', 3, 160.00, 80.00, 320.00, 0.00, 'Sortie après déchargement', '2024-11-03 12:00:00', '2024-11-03 12:00:00'),
(9, 'camion_entrant', 7, 220.00, 110.00, 450.00, 0.00, 'Camion citerne', '2024-11-07 14:00:00', '2024-11-07 14:00:00'),
(10, 'bateau_entrant', 18, 800.00, 400.00, 1800.00, 0.00, 'Bateau passager', '2024-11-22 13:30:00', '2024-11-22 13:30:00'),
(11, 'bateau_entrant', 20, 1400.00, 700.00, 2900.00, 200.00, 'Conteneurs avec inspection douanière', '2024-11-28 16:00:00', '2024-11-30 16:00:00'),
(12, 'camion_entrant', 9, 250.00, 125.00, 500.00, 0.00, 'Camion avec produits dangereux', '2024-11-09 15:00:00', '2024-11-09 15:00:00'),
(13, 'bateau_sortant', 6, 1600.00, 800.00, 3200.00, 0.00, 'Export café premium', '2024-11-20 09:00:00', '2024-11-20 09:00:00'),
(14, 'camion_sortant', 5, 210.00, 105.00, 420.00, 0.00, 'Sortie après chargement', '2024-11-05 16:00:00', '2024-11-05 16:00:00'),
(15, 'bateau_entrant', 25, 900.00, 450.00, 1900.00, 0.00, 'Bateau passager local', '2024-12-18 09:30:00', '2024-12-18 09:30:00'),
(16, 'camion_entrant', 11, 170.00, 85.00, 340.00, 50.00, 'Retard de déclaration', '2024-11-11 17:00:00', '2024-11-13 17:00:00'),
(17, 'bateau_sortant', 9, 1800.00, 900.00, 3500.00, 300.00, 'Pétrole avec frais spéciaux', '2024-12-01 11:00:00', '2024-12-04 11:00:00'),
(18, 'bateau_entrant', 30, 1150.00, 575.00, 2400.00, 0.00, 'Produits surgelés', '2025-01-07 13:00:00', '2025-01-07 13:00:00'),
(19, 'camion_sortant', 8, 230.00, 115.00, 460.00, 0.00, 'Sortie normale', '2024-11-08 18:00:00', '2024-11-08 18:00:00'),
(20, 'bateau_sortant', 12, 1250.00, 625.00, 2600.00, 0.00, 'Export véhicules', '2024-12-11 16:00:00', '2024-12-11 16:00:00'),
(21, 'camion_entrant', 13, 190.00, 95.00, 380.00, 0.00, 'Camion agricole', '2024-11-13 09:30:00', '2024-11-13 09:30:00'),
(22, 'bateau_entrant', 35, 1350.00, 675.00, 2700.00, 150.00, 'Véhicules avec inspection', '2025-01-27 15:00:00', '2025-01-29 15:00:00'),
(23, 'bateau_sortant', 15, 1050.00, 525.00, 2200.00, 0.00, 'Export conteneurs', '2024-12-23 12:00:00', '2024-12-23 12:00:00'),
(24, 'camion_sortant', 10, 240.00, 120.00, 480.00, 0.00, 'Sortie standard', '2024-11-10 20:00:00', '2024-11-10 20:00:00'),
(25, 'bateau_entrant', 40, 1550.00, 775.00, 3100.00, 0.00, 'Cargaison générale', '2025-02-16 10:30:00', '2025-02-16 10:30:00'),
(26, 'camion_entrant', 15, 260.00, 130.00, 520.00, 75.00, 'Retard de déchargement', '2024-11-15 11:45:00', '2024-11-17 11:45:00'),
(27, 'bateau_sortant', 18, 950.00, 475.00, 2000.00, 0.00, 'Export produits pharmaceutiques', '2025-01-02 11:15:00', '2025-01-02 11:15:00'),
(28, 'bateau_entrant', 45, 850.00, 425.00, 1800.00, 0.00, 'Bateau passager touristique', '2025-03-08 14:45:00', '2025-03-08 14:45:00'),
(29, 'camion_sortant', 12, 270.00, 135.00, 540.00, 0.00, 'Sortie rapide', '2024-11-12 22:30:00', '2024-11-12 22:30:00'),
(30, 'bateau_sortant', 21, 1150.00, 575.00, 2400.00, 100.00, 'Export touristes', '2025-01-14 13:30:00', '2025-01-16 13:30:00');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_timestamp` (`timestamp`),
  KEY `idx_logs_user_id` (`user_id`),
  KEY `idx_logs_action` (`action`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `timestamp`) VALUES
(1, 8, 'ENREGISTREMENT', 'Enregistrement du bateau entrant MV LAKE VICTORIA', '192.168.1.100', '2024-11-05 08:30:00'),
(2, 9, 'ENREGISTREMENT', 'Enregistrement du bateau sortant MV EXPORT CARGO', '192.168.1.101', '2024-11-06 14:30:00'),
(3, 5, 'ENREGISTREMENT', 'Enregistrement du camion entrant BUR-4811', '192.168.1.102', '2024-11-03 08:15:00'),
(4, 7, 'PESAGE', 'Pesage du camion BUR-4811 effectué', '192.168.1.103', '2024-11-03 08:30:00'),
(5, 6, 'SORTIE', 'Sortie du camion BUR-4811 enregistrée', '192.168.1.104', '2024-11-03 11:00:00'),
(6, 8, 'ENREGISTREMENT', 'Enregistrement du bateau entrant MV GOMA EXPRESS', '192.168.1.100', '2024-11-08 14:15:00'),
(7, 9, 'ENREGISTREMENT', 'Enregistrement du bateau sortant MV CONTAINER EXPORT', '192.168.1.101', '2024-11-09 09:45:00'),
(8, 5, 'ENREGISTREMENT', 'Enregistrement du camion entrant BUR-4812', '192.168.1.102', '2024-11-04 09:30:00'),
(9, 7, 'PESAGE', 'Pesage du camion BUR-4812 effectué', '192.168.1.103', '2024-11-04 09:45:00'),
(10, 6, 'SORTIE', 'Sortie du camion BUR-4812 enregistrée', '192.168.1.104', '2024-11-04 13:15:00'),
(11, 5, 'ENREGISTREMENT', 'Enregistrement du camion entrant BUR-4831', '192.168.1.105', '2024-11-23 11:15:00'),
(12, 7, 'PESAGE', 'Pesage du camion BUR-4831 effectué', '192.168.1.106', '2024-11-23 11:30:00'),
(13, 6, 'SORTIE', 'Sortie du camion BUR-4831 enregistrée', '192.168.1.107', '2024-11-23 15:00:00'),
(14, 8, 'ENREGISTREMENT', 'Enregistrement du bateau entrant MV PORT CONTAINER', '192.168.1.108', '2025-01-15 15:20:00'),
(15, 9, 'ENREGISTREMENT', 'Enregistrement du bateau sortant MV GENERAL EXPORT', '192.168.1.109', '2024-12-04 07:20:00'),
(16, 10, 'DOUANE', 'Validation douanière pour le bateau MV LAKE VICTORIA', '192.168.1.110', '2024-11-05 11:00:00'),
(17, 5, 'ENREGISTREMENT', 'Enregistrement du camion entrant BUR-4832', '192.168.1.105', '2024-11-24 12:30:00'),
(18, 7, 'PESAGE', 'Pesage du camion BUR-4832 effectué', '192.168.1.106', '2024-11-24 12:45:00'),
(19, 6, 'SORTIE', 'Sortie du camion BUR-4832 enregistrée', '192.168.1.107', '2024-11-24 16:15:00'),
(20, 8, 'ENREGISTREMENT', 'Enregistrement du bateau entrant MV GOMA CARGO', '192.168.1.108', '2025-01-19 08:35:00'),
(21, 9, 'ENREGISTREMENT', 'Enregistrement du bateau sortant MV FROZEN EXPORT', '192.168.1.109', '2024-12-08 12:35:00'),
(22, 10, 'DOUANE', 'Validation douanière pour le camion BUR-4811', '192.168.1.110', '2024-11-03 09:00:00'),
(23, 3, 'ENTREPOT', 'Réception marchandises bateau MV GOMA EXPRESS', '192.168.1.111', '2024-11-08 15:00:00'),
(24, 3, 'ENTREPOT', 'Préparation chargement bateau MV EXPORT CARGO', '192.168.1.111', '2024-11-06 10:00:00'),
(25, 5, 'ENREGISTREMENT', 'Enregistrement du camion entrant BUR-4833', '192.168.1.105', '2024-11-25 13:45:00'),
(26, 7, 'PESAGE', 'Pesage du camion BUR-4833 effectué', '192.168.1.106', '2024-11-25 14:00:00'),
(27, 6, 'SORTIE', 'Sortie du camion BUR-4833 enregistrée', '192.168.1.107', '2024-11-25 17:30:00'),
(28, 8, 'ENREGISTREMENT', 'Enregistrement du bateau entrant MV OIL PRODUCTS', '192.168.1.108', '2025-01-23 11:50:00'),
(29, 9, 'ENREGISTREMENT', 'Enregistrement du bateau sortant MV VEHICLE EXPORT', '192.168.1.109', '2024-12-11 14:50:00'),
(30, 10, 'DOUANE', 'Validation douanière pour le bateau MV COLD STORAGE', '192.168.1.110', '2024-11-12 11:00:00');

-- --------------------------------------------------------

--
-- Structure de la table `marchandises_pesage`
--

DROP TABLE IF EXISTS `marchandises_pesage`;
CREATE TABLE IF NOT EXISTS `marchandises_pesage` (
  `idMarchandise` int NOT NULL AUTO_INCREMENT,
  `idPesage` int NOT NULL,
  `idTypeMarchandise` int NOT NULL,
  `poids` decimal(10,2) NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idMarchandise`),
  KEY `idx_idPesage` (`idPesage`),
  KEY `idx_idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandises_pesage`
--

INSERT INTO `marchandises_pesage` (`idMarchandise`, `idPesage`, `idTypeMarchandise`, `poids`, `note`, `date_ajout`, `created_at`, `updated_at`) VALUES
(4, 1, 3, 500.00, '', '2026-01-14 16:37:39', '2026-01-14 16:37:39', '2026-01-14 16:37:39'),
(3, 1, 2, 500.00, '', '2026-01-14 16:37:39', '2026-01-14 16:37:39', '2026-01-14 16:37:39'),
(5, 3, 1, 300.00, 'Café en sacs', '2024-11-03 08:35:00', '2024-11-03 08:35:00', '2024-11-03 08:35:00'),
(6, 3, 2, 400.00, 'Cacao en sacs', '2024-11-03 08:35:00', '2024-11-03 08:35:00', '2024-11-03 08:35:00'),
(7, 4, 7, 500.00, 'Ciment', '2024-11-04 09:50:00', '2024-11-04 09:50:00', '2024-11-04 09:50:00'),
(8, 4, 8, 300.00, 'Acier', '2024-11-04 09:50:00', '2024-11-04 09:50:00', '2024-11-04 09:50:00'),
(9, 6, 4, 400.00, 'Riz', '2024-11-06 11:40:00', '2024-11-06 11:40:00', '2024-11-06 11:40:00'),
(10, 6, 5, 400.00, 'Sucre', '2024-11-06 11:40:00', '2024-11-06 11:40:00', '2024-11-06 11:40:00'),
(11, 7, 10, 350.00, 'Électronique', '2024-11-07 12:55:00', '2024-11-07 12:55:00', '2024-11-07 12:55:00'),
(12, 7, 11, 350.00, 'Textiles', '2024-11-07 12:55:00', '2024-11-07 12:55:00', '2024-11-07 12:55:00'),
(13, 9, 13, 400.00, 'Poisson', '2024-11-09 14:25:00', '2024-11-09 14:25:00', '2024-11-09 14:25:00'),
(14, 9, 14, 400.00, 'Viande', '2024-11-09 14:25:00', '2024-11-09 14:25:00', '2024-11-09 14:25:00'),
(15, 10, 12, 400.00, 'Médicaments', '2024-11-10 15:40:00', '2024-11-10 15:40:00', '2024-11-10 15:40:00'),
(16, 10, 17, 400.00, 'Boissons', '2024-11-10 15:40:00', '2024-11-10 15:40:00', '2024-11-10 15:40:00'),
(17, 12, 1, 400.00, 'Café', '2024-11-12 18:10:00', '2024-11-12 18:10:00', '2024-11-12 18:10:00'),
(18, 12, 2, 400.00, 'Cacao', '2024-11-12 18:10:00', '2024-11-12 18:10:00', '2024-11-12 18:10:00'),
(19, 13, 7, 400.00, 'Ciment', '2024-11-13 08:45:00', '2024-11-13 08:45:00', '2024-11-13 08:45:00'),
(20, 13, 8, 400.00, 'Acier', '2024-11-13 08:45:00', '2024-11-13 08:45:00', '2024-11-13 08:45:00'),
(21, 15, 4, 350.00, 'Riz', '2024-11-15 11:15:00', '2024-11-15 11:15:00', '2024-11-15 11:15:00'),
(22, 15, 5, 350.00, 'Sucre', '2024-11-15 11:15:00', '2024-11-15 11:15:00', '2024-11-15 11:15:00'),
(23, 16, 10, 350.00, 'Électronique', '2024-11-16 12:30:00', '2024-11-16 12:30:00', '2024-11-16 12:30:00'),
(24, 16, 11, 350.00, 'Textiles', '2024-11-16 12:30:00', '2024-11-16 12:30:00', '2024-11-16 12:30:00'),
(25, 18, 13, 400.00, 'Poisson', '2024-11-18 15:00:00', '2024-11-18 15:00:00', '2024-11-18 15:00:00'),
(26, 18, 14, 400.00, 'Viande', '2024-11-18 15:00:00', '2024-11-18 15:00:00', '2024-11-18 15:00:00'),
(27, 19, 12, 350.00, 'Médicaments', '2024-11-19 16:15:00', '2024-11-19 16:15:00', '2024-11-19 16:15:00'),
(28, 19, 17, 350.00, 'Boissons', '2024-11-19 16:15:00', '2024-11-19 16:15:00', '2024-11-19 16:15:00'),
(29, 21, 1, 400.00, 'Café', '2024-11-21 09:05:00', '2024-11-21 09:05:00', '2024-11-21 09:05:00'),
(30, 21, 2, 400.00, 'Cacao', '2024-11-21 09:05:00', '2024-11-21 09:05:00', '2024-11-21 09:05:00'),
(31, 22, 7, 400.00, 'Ciment', '2024-11-22 10:20:00', '2024-11-22 10:20:00', '2024-11-22 10:20:00'),
(32, 22, 8, 400.00, 'Acier', '2024-11-22 10:20:00', '2024-11-22 10:20:00', '2024-11-22 10:20:00');

-- --------------------------------------------------------

--
-- Structure de la table `marchandises_pesage_camion`
--

DROP TABLE IF EXISTS `marchandises_pesage_camion`;
CREATE TABLE IF NOT EXISTS `marchandises_pesage_camion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idPesageChargement` int NOT NULL,
  `idTypeMarchandise` int NOT NULL,
  `poids` decimal(10,2) NOT NULL COMMENT 'Poids en kg',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_marchandise_pesage` (`idPesageChargement`,`idTypeMarchandise`),
  KEY `idx_idPesageChargement` (`idPesageChargement`),
  KEY `idx_idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandises_pesage_camion`
--

INSERT INTO `marchandises_pesage_camion` (`id`, `idPesageChargement`, `idTypeMarchandise`, `poids`, `note`, `created_at`) VALUES
(1, 1, 1, 150.00, 'Café en sacs', '2024-11-05 14:40:00'),
(2, 1, 2, 100.00, 'Cacao en sacs', '2024-11-05 14:40:00'),
(3, 2, 7, 200.00, 'Ciment Portland', '2024-11-08 17:00:00'),
(4, 2, 8, 150.00, 'Acier de construction', '2024-11-08 17:00:00'),
(5, 3, 4, 180.00, 'Riz basmati', '2024-11-11 19:10:00'),
(6, 3, 5, 120.00, 'Sucre raffiné', '2024-11-11 19:10:00'),
(7, 4, 10, 160.00, 'Équipements électroniques', '2024-11-14 10:25:00'),
(8, 4, 11, 140.00, 'Textiles', '2024-11-14 10:25:00'),
(9, 5, 13, 200.00, 'Poisson congelé', '2024-11-17 13:40:00'),
(10, 5, 14, 150.00, 'Viande congelée', '2024-11-17 13:40:00'),
(11, 6, 12, 130.00, 'Médicaments', '2024-11-20 16:55:00'),
(12, 6, 17, 170.00, 'Boissons', '2024-11-20 16:55:00'),
(13, 7, 1, 190.00, 'Café robusta', '2024-11-23 12:05:00'),
(14, 7, 2, 110.00, 'Cacao en fèves', '2024-11-23 12:05:00'),
(15, 8, 7, 210.00, 'Ciment', '2024-11-24 13:20:00'),
(16, 8, 8, 140.00, 'Acier', '2024-11-24 13:20:00'),
(17, 9, 4, 170.00, 'Riz', '2024-11-25 14:35:00'),
(18, 9, 5, 130.00, 'Sucre', '2024-11-25 14:35:00'),
(19, 10, 10, 155.00, 'Électronique', '2024-11-26 15:50:00'),
(20, 10, 11, 145.00, 'Textiles', '2024-11-26 15:50:00'),
(21, 11, 13, 195.00, 'Poisson', '2024-11-27 16:05:00'),
(22, 11, 14, 155.00, 'Viande', '2024-11-27 16:05:00'),
(23, 12, 12, 140.00, 'Médicaments', '2024-11-28 17:20:00'),
(24, 12, 17, 160.00, 'Boissons', '2024-11-28 17:20:00'),
(25, 13, 1, 185.00, 'Café', '2024-11-29 18:35:00'),
(26, 13, 2, 115.00, 'Cacao', '2024-11-29 18:35:00'),
(27, 14, 7, 205.00, 'Ciment', '2024-11-30 19:50:00'),
(28, 14, 8, 145.00, 'Acier', '2024-11-30 19:50:00'),
(29, 15, 4, 175.00, 'Riz', '2024-12-01 08:05:00'),
(30, 15, 5, 125.00, 'Sucre', '2024-12-01 08:05:00');

-- --------------------------------------------------------

--
-- Structure de la table `marchandise_bateau_entrant`
--

DROP TABLE IF EXISTS `marchandise_bateau_entrant`;
CREATE TABLE IF NOT EXISTS `marchandise_bateau_entrant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_bateau_entrant` int NOT NULL,
  `id_type_marchandise` int DEFAULT NULL,
  `poids` decimal(10,2) DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_bateau_entrant` (`id_bateau_entrant`),
  KEY `id_type_marchandise` (`id_type_marchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_bateau_entrant`
--

INSERT INTO `marchandise_bateau_entrant` (`id`, `id_bateau_entrant`, `id_type_marchandise`, `poids`, `note`, `date_ajout`) VALUES
(7, 11, 3, 35.00, '', '2026-01-16 12:29:36'),
(8, 13, 1, 1500.00, 'Café arabica premium', '2024-11-05 08:35:00'),
(9, 13, 2, 800.00, 'Cacao de qualité supérieure', '2024-11-05 08:35:00'),
(10, 14, 7, 2500.00, 'Ciment Portland', '2024-11-08 14:20:00'),
(11, 14, 8, 1800.00, 'Acier de construction', '2024-11-08 14:20:00'),
(12, 15, 13, 1200.00, 'Poisson tilapia congelé', '2024-11-12 10:50:00'),
(13, 15, 14, 800.00, 'Viande bovine congelée', '2024-11-12 10:50:00'),
(14, 16, 4, 3000.00, 'Riz basmati', '2024-11-15 16:25:00'),
(15, 17, 18, 5000.00, 'Pétrole brut', '2024-11-18 09:15:00'),
(16, 18, 10, 500.00, 'Équipements électroniques', '2024-11-22 12:35:00'),
(17, 18, 12, 300.00, 'Médicaments essentiels', '2024-11-22 12:35:00'),
(18, 19, 1, 2000.00, 'Café robusta', '2024-11-25 07:50:00'),
(19, 19, 2, 1500.00, 'Cacao en fèves', '2024-11-25 07:50:00'),
(20, 20, 10, 1800.00, 'Téléviseurs et électroménager', '2024-11-28 15:45:00'),
(21, 20, 11, 1200.00, 'Textiles et vêtements', '2024-11-28 15:45:00'),
(22, 21, 13, 10000.00, 'Poisson frais du lac Tanganyika', '2024-12-03 06:20:00'),
(23, 22, 8, 3500.00, 'Véhicules et pièces détachées', '2024-12-07 11:25:00'),
(24, 23, 14, 2800.00, 'Viande de porc congelée', '2024-12-10 14:00:00'),
(25, 24, 19, 4500.00, 'Minerais de coltan', '2024-12-14 09:35:00'),
(26, 25, 17, 800.00, 'Boissons non alcoolisées', '2024-12-18 08:50:00'),
(27, 25, 15, 400.00, 'Fruits frais', '2024-12-18 08:50:00'),
(28, 27, 18, 4000.00, 'Diesel', '2024-12-26 10:30:00'),
(29, 28, 8, 3200.00, 'Camions et véhicules lourds', '2024-12-29 16:55:00'),
(30, 29, 7, 3800.00, 'Ciment et matériaux de construction', '2025-01-03 07:20:00'),
(31, 30, 15, 2200.00, 'Fruits surgelés', '2025-01-07 12:45:00'),
(32, 30, 16, 1800.00, 'Légumes surgelés', '2025-01-07 12:45:00'),
(33, 31, 17, 600.00, 'Eau minérale', '2025-01-11 09:10:00'),
(34, 32, 1, 2800.00, 'Café pour export', '2025-01-15 15:25:00'),
(35, 32, 2, 2200.00, 'Cacao pour export', '2025-01-15 15:25:00'),
(36, 34, 18, 3500.00, 'Essence sans plomb', '2025-01-23 11:55:00'),
(37, 34, 18, 1500.00, 'Kérosène', '2025-01-23 11:55:00'),
(38, 35, 8, 4200.00, 'Véhicules automobiles', '2025-01-27 14:10:00'),
(39, 36, 12, 900.00, 'Produits pharmaceutiques réfrigérés', '2025-01-31 10:35:00'),
(40, 37, 19, 5000.00, 'Or et minerais précieux', '2025-02-04 08:00:00'),
(41, 38, 17, 700.00, 'Boissons diverses', '2025-02-08 13:15:00'),
(42, 40, 4, 3200.00, 'Riz et céréales', '2025-02-16 09:45:00'),
(43, 40, 5, 1500.00, 'Sucre raffiné', '2025-02-16 09:45:00'),
(44, 41, 9, 2800.00, 'Engrais chimiques', '2025-02-20 13:00:00'),
(45, 42, 8, 3800.00, 'Machinerie industrielle', '2025-02-24 08:15:00'),
(46, 43, 14, 2400.00, 'Produits laitiers congelés', '2025-02-28 15:40:00'),
(47, 44, 4, 4800.00, 'Céréales en vrac', '2025-03-04 11:05:00'),
(48, 45, 17, 850.00, 'Boissons gazeuses', '2025-03-08 14:20:00'),
(49, 46, 10, 2100.00, 'Électronique grand public', '2025-03-12 10:35:00'),
(50, 46, 11, 1900.00, 'Textiles et habillement', '2025-03-12 10:35:00'),
(51, 48, 17, 3000.00, 'Eau potable en bouteilles', '2025-03-20 12:05:00'),
(52, 49, 8, 3300.00, 'Bus et minibus', '2025-03-24 15:20:00'),
(53, 50, 12, 1200.00, 'Vaccins et produits biologiques', '2025-03-28 09:35:00'),
(54, 51, 7, 3600.00, 'Matériaux de construction', '2025-04-01 14:50:00'),
(55, 51, 8, 2400.00, 'Fer et acier', '2025-04-01 14:50:00'),
(56, 52, 15, 650.00, 'Fruits frais', '2025-04-05 08:05:00'),
(57, 53, 1, 2900.00, 'Café en conteneurs', '2025-04-09 11:20:00'),
(58, 53, 2, 2100.00, 'Cacao en conteneurs', '2025-04-09 11:20:00'),
(59, 54, 19, 5200.00, 'Charbon minéral', '2025-04-13 16:35:00'),
(60, 56, 8, 4100.00, 'Véhicules neufs', '2025-04-21 13:05:00'),
(61, 57, 13, 8500.00, 'Poisson frais', '2025-04-25 07:20:00'),
(62, 58, 5, 1800.00, 'Sucre et produits sucrés', '2025-04-29 12:35:00'),
(63, 58, 6, 1200.00, 'Huile de palme', '2025-04-29 12:35:00'),
(64, 59, 17, 720.00, 'Jus et boissons', '2025-05-03 15:50:00'),
(65, 61, 19, 4800.00, 'Minerais de cuivre', '2025-05-11 14:20:00'),
(66, 62, 18, 4200.00, 'Carburant aviation Jet A-1', '2025-05-15 17:35:00');

-- --------------------------------------------------------

--
-- Structure de la table `marchandise_bateau_sortant`
--

DROP TABLE IF EXISTS `marchandise_bateau_sortant`;
CREATE TABLE IF NOT EXISTS `marchandise_bateau_sortant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_bateau_sortant` int NOT NULL,
  `id_type_marchandise` int NOT NULL,
  `poids` decimal(10,2) DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_bateau_sortant` (`id_bateau_sortant`),
  KEY `id_type_marchandise` (`id_type_marchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_bateau_sortant`
--

INSERT INTO `marchandise_bateau_sortant` (`id`, `id_bateau_sortant`, `id_type_marchandise`, `poids`, `note`, `date_ajout`) VALUES
(1, 1, 2, 10.00, '', '2026-01-16 14:12:36'),
(2, 2, 1, 1800.00, 'Café pour export', '2024-11-06 14:35:00'),
(3, 2, 2, 1200.00, 'Cacao pour export', '2024-11-06 14:35:00'),
(4, 3, 10, 1500.00, 'Électronique', '2024-11-09 09:50:00'),
(5, 3, 11, 1100.00, 'Textiles', '2024-11-09 09:50:00'),
(6, 4, 14, 2000.00, 'Viande congelée', '2024-11-13 16:25:00'),
(7, 4, 15, 800.00, 'Fruits surgelés', '2024-11-13 16:25:00'),
(8, 5, 8, 3000.00, 'Véhicules', '2024-11-16 11:15:00'),
(9, 6, 1, 2500.00, 'Café premium', '2024-11-20 08:30:00'),
(10, 7, 17, 500.00, 'Boissons', '2024-11-24 13:45:00'),
(11, 8, 7, 2200.00, 'Ciment', '2024-11-27 16:00:00'),
(12, 8, 8, 1800.00, 'Acier', '2024-11-27 16:00:00'),
(13, 9, 18, 4200.00, 'Pétrole raffiné', '2024-12-01 10:10:00'),
(14, 10, 4, 2800.00, 'Riz', '2024-12-04 07:25:00'),
(15, 10, 5, 1400.00, 'Sucre', '2024-12-04 07:25:00'),
(16, 11, 13, 1600.00, 'Poisson congelé', '2024-12-08 12:40:00'),
(17, 12, 8, 3500.00, 'Véhicules utilitaires', '2024-12-11 14:55:00'),
(18, 13, 19, 4500.00, 'Minerais', '2024-12-15 09:10:00'),
(19, 14, 17, 600.00, 'Eau minérale', '2024-12-19 16:25:00'),
(20, 15, 10, 1900.00, 'Équipements', '2024-12-23 11:40:00'),
(21, 16, 4, 3800.00, 'Céréales', '2024-12-27 08:55:00'),
(22, 17, 18, 3800.00, 'Carburant', '2024-12-30 13:10:00'),
(23, 18, 12, 1100.00, 'Médicaments', '2025-01-02 10:25:00'),
(24, 19, 11, 2300.00, 'Textiles', '2025-01-06 15:40:00'),
(25, 20, 8, 3200.00, 'Machinerie', '2025-01-10 08:55:00'),
(26, 21, 15, 700.00, 'Fruits', '2025-01-14 12:10:00'),
(27, 22, 1, 2700.00, 'Café', '2025-01-18 07:25:00'),
(28, 23, 4, 3100.00, 'Produits agricoles', '2025-01-22 14:40:00'),
(29, 24, 9, 2600.00, 'Produits chimiques', '2025-01-26 09:55:00'),
(30, 25, 14, 2100.00, 'Produits laitiers', '2025-01-30 16:10:00'),
(31, 26, 7, 3400.00, 'Matériaux construction', '2025-02-03 11:25:00'),
(32, 27, 8, 2900.00, 'Bus', '2025-02-07 08:40:00'),
(33, 28, 17, 550.00, 'Boissons', '2025-02-11 13:55:00'),
(34, 29, 10, 1700.00, 'Électronique', '2025-02-15 10:10:00'),
(35, 30, 5, 1600.00, 'Denrées alimentaires', '2025-02-19 15:25:00'),
(36, 31, 17, 3200.00, 'Eau potable', '2025-02-23 08:40:00'),
(37, 32, 14, 1800.00, 'Viande', '2025-02-27 12:55:00'),
(38, 33, 10, 2400.00, 'Électronique', '2025-03-03 09:10:00'),
(39, 34, 8, 3300.00, 'Camions', '2025-03-07 14:25:00'),
(40, 35, 17, 480.00, 'Boissons', '2025-03-11 11:40:00'),
(41, 36, 2, 2000.00, 'Cacao', '2025-03-15 16:55:00'),
(42, 37, 19, 4700.00, 'Matières premières', '2025-03-19 10:10:00'),
(43, 38, 18, 3500.00, 'Gaz', '2025-03-23 13:25:00'),
(44, 39, 16, 1900.00, 'Légumes', '2025-03-27 08:40:00'),
(45, 40, 17, 1400.00, 'Boissons', '2025-03-31 12:55:00'),
(46, 41, 8, 3100.00, 'Équipements', '2025-04-04 09:10:00');

-- --------------------------------------------------------

--
-- Structure de la table `marchandise_chargement_camion`
--

DROP TABLE IF EXISTS `marchandise_chargement_camion`;
CREATE TABLE IF NOT EXISTS `marchandise_chargement_camion` (
  `idMarchandiseChargement` int NOT NULL AUTO_INCREMENT,
  `idChargement` int NOT NULL,
  `idTypeMarchandise` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `poids` int NOT NULL,
  PRIMARY KEY (`idMarchandiseChargement`),
  KEY `idChargement` (`idChargement`),
  KEY `idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_chargement_camion`
--

INSERT INTO `marchandise_chargement_camion` (`idMarchandiseChargement`, `idChargement`, `idTypeMarchandise`, `note`, `date_ajout`, `poids`) VALUES
(8, 1, 1, '', '2026-01-15 19:44:57', 0),
(7, 1, 2, '', '2026-01-15 19:44:57', 0),
(9, 2, 1, 'Café pour export', '2024-11-05 14:35:00', 500),
(10, 2, 2, 'Cacao pour export', '2024-11-05 14:35:00', 500),
(11, 3, 7, 'Ciment', '2024-11-08 16:50:00', 600),
(12, 3, 8, 'Acier', '2024-11-08 16:50:00', 400),
(13, 4, 4, 'Riz', '2024-11-11 19:05:00', 550),
(14, 4, 5, 'Sucre', '2024-11-11 19:05:00', 450),
(15, 5, 10, 'Électronique', '2024-11-14 10:20:00', 500),
(16, 5, 11, 'Textiles', '2024-11-14 10:20:00', 500),
(17, 6, 13, 'Poisson', '2024-11-17 13:35:00', 600),
(18, 6, 14, 'Viande', '2024-11-17 13:35:00', 400),
(19, 7, 12, 'Médicaments', '2024-11-20 16:50:00', 450),
(20, 7, 17, 'Boissons', '2024-11-20 16:50:00', 550),
(21, 8, 1, 'Café premium', '2024-11-23 12:05:00', 300),
(22, 8, 2, 'Cacao qualité', '2024-11-23 12:05:00', 200),
(23, 9, 7, 'Ciment rapide', '2024-11-24 13:20:00', 350),
(24, 9, 8, 'Acier galvanisé', '2024-11-24 13:20:00', 250),
(25, 10, 4, 'Riz parfumé', '2024-11-25 14:35:00', 400),
(26, 10, 5, 'Sucre blanc', '2024-11-25 14:35:00', 200),
(27, 11, 10, 'Téléphones mobiles', '2024-11-26 15:50:00', 150),
(28, 11, 11, 'Vêtements', '2024-11-26 15:50:00', 250),
(29, 12, 13, 'Poisson frais', '2024-11-27 16:05:00', 300),
(30, 12, 14, 'Viande bovine', '2024-11-27 16:05:00', 300);

-- --------------------------------------------------------

--
-- Structure de la table `marchandise_dechargement_camion`
--

DROP TABLE IF EXISTS `marchandise_dechargement_camion`;
CREATE TABLE IF NOT EXISTS `marchandise_dechargement_camion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idDechargement` int NOT NULL,
  `idTypeMarchandise` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_idDechargement` (`idDechargement`),
  KEY `idx_idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_dechargement_camion`
--

INSERT INTO `marchandise_dechargement_camion` (`id`, `idDechargement`, `idTypeMarchandise`, `note`, `date_ajout`) VALUES
(1, 1, 3, '', '2026-01-15 19:38:18'),
(2, 1, 2, '', '2026-01-15 19:38:18'),
(3, 2, 1, 'Café reçu', '2024-11-03 10:35:00'),
(4, 2, 2, 'Cacao reçu', '2024-11-03 10:35:00'),
(5, 3, 7, 'Ciment reçu', '2024-11-04 12:50:00'),
(6, 3, 8, 'Acier reçu', '2024-11-04 12:50:00'),
(7, 4, 4, 'Riz reçu', '2024-11-06 14:05:00'),
(8, 4, 5, 'Sucre reçu', '2024-11-06 14:05:00'),
(9, 5, 10, 'Électronique reçu', '2024-11-07 15:20:00'),
(10, 5, 11, 'Textiles reçu', '2024-11-07 15:20:00'),
(11, 6, 13, 'Poisson reçu', '2024-11-09 17:35:00'),
(12, 6, 14, 'Viande reçu', '2024-11-09 17:35:00'),
(13, 7, 12, 'Médicaments reçu', '2024-11-10 18:50:00'),
(14, 7, 17, 'Boissons reçu', '2024-11-10 18:50:00'),
(15, 8, 1, 'Café reçu', '2024-11-12 21:05:00'),
(16, 8, 2, 'Cacao reçu', '2024-11-12 21:05:00'),
(17, 9, 7, 'Ciment reçu', '2024-11-13 09:20:00'),
(18, 9, 8, 'Acier reçu', '2024-11-13 09:20:00'),
(19, 10, 4, 'Riz reçu', '2024-11-15 12:35:00'),
(20, 10, 5, 'Sucre reçu', '2024-11-15 12:35:00'),
(21, 11, 10, 'Électronique reçu', '2024-11-16 14:50:00'),
(22, 11, 11, 'Textiles reçu', '2024-11-16 14:50:00'),
(23, 12, 13, 'Poisson reçu', '2024-11-18 17:05:00'),
(24, 12, 14, 'Viande reçu', '2024-11-18 17:05:00'),
(25, 13, 12, 'Médicaments reçu', '2024-11-19 19:20:00'),
(26, 13, 17, 'Boissons reçu', '2024-11-19 19:20:00'),
(27, 14, 1, 'Café reçu', '2024-11-21 11:35:00'),
(28, 14, 2, 'Cacao reçu', '2024-11-21 11:35:00'),
(29, 15, 7, 'Ciment reçu', '2024-11-22 13:50:00'),
(30, 15, 8, 'Acier reçu', '2024-11-22 13:50:00');

-- --------------------------------------------------------

--
-- Structure de la table `pesages`
--

DROP TABLE IF EXISTS `pesages`;
CREATE TABLE IF NOT EXISTS `pesages` (
  `idPesage` int NOT NULL AUTO_INCREMENT,
  `idEntree` int NOT NULL,
  `ptav` decimal(10,2) NOT NULL COMMENT 'Poids Total à Vide',
  `ptac` decimal(10,2) NOT NULL COMMENT 'Poids Total Autorisé en Charge',
  `ptra` decimal(10,2) NOT NULL COMMENT 'Poids Total Roulant Autorisé',
  `charge_essieu` decimal(10,2) DEFAULT NULL,
  `poids_total_marchandises` decimal(10,2) DEFAULT '0.00',
  `surcharge` tinyint(1) DEFAULT '0',
  `note_surcharge` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_pesage` datetime DEFAULT CURRENT_TIMESTAMP,
  `agent_bascule` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPesage`),
  KEY `idx_idEntree` (`idEntree`),
  KEY `idx_date_pesage` (`date_pesage`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pesages`
--

INSERT INTO `pesages` (`idPesage`, `idEntree`, `ptav`, `ptac`, `ptra`, `charge_essieu`, `poids_total_marchandises`, `surcharge`, `note_surcharge`, `date_pesage`, `agent_bascule`, `created_at`, `updated_at`) VALUES
(1, 1, 1200.00, 1800.00, 2000.00, 3000.00, 1000.00, 1, '', '2026-01-14 16:37:39', 'Patrice TETE', '2026-01-14 15:24:31', '2026-01-14 16:37:39'),
(2, 2, 1500.00, 1800.00, 2300.00, 2500.00, 0.00, 0, '', '2026-01-14 16:35:36', 'Patrice TETE', '2026-01-14 16:35:36', '2026-01-14 16:35:36'),
(3, 3, 1500.00, 2200.00, 2500.00, 2800.00, 700.00, 0, NULL, '2024-11-03 08:30:00', 'Patrice TETE', '2024-11-03 08:30:00', '2024-11-03 08:30:00'),
(4, 4, 1800.00, 2600.00, 2900.00, 3200.00, 800.00, 1, 'Surcharge de 5%', '2024-11-04 09:45:00', 'Patrice TETE', '2024-11-04 09:45:00', '2024-11-04 09:45:00'),
(5, 5, 2000.00, 2800.00, 3100.00, 3400.00, 0.00, 0, NULL, '2024-11-05 11:00:00', 'Patrice TETE', '2024-11-05 11:00:00', '2024-11-05 11:00:00'),
(6, 6, 2200.00, 3000.00, 3300.00, 3600.00, 800.00, 0, NULL, '2024-11-06 11:35:00', 'Patrice TETE', '2024-11-06 11:35:00', '2024-11-06 11:35:00'),
(7, 7, 2500.00, 3200.00, 3500.00, 3800.00, 700.00, 1, 'Surcharge mineure', '2024-11-07 12:50:00', 'Patrice TETE', '2024-11-07 12:50:00', '2024-11-07 12:50:00'),
(8, 8, 2800.00, 3500.00, 3800.00, 4100.00, 0.00, 0, NULL, '2024-11-08 14:05:00', 'Patrice TETE', '2024-11-08 14:05:00', '2024-11-08 14:05:00'),
(9, 9, 3000.00, 3800.00, 4100.00, 4400.00, 800.00, 0, NULL, '2024-11-09 14:20:00', 'Patrice TETE', '2024-11-09 14:20:00', '2024-11-09 14:20:00'),
(10, 10, 3200.00, 4000.00, 4300.00, 4600.00, 800.00, 1, 'Dépassement essieu avant', '2024-11-10 15:35:00', 'Patrice TETE', '2024-11-10 15:35:00', '2024-11-10 15:35:00'),
(11, 11, 1500.00, 2200.00, 2500.00, 2800.00, 0.00, 0, NULL, '2024-11-11 16:50:00', 'Patrice TETE', '2024-11-11 16:50:00', '2024-11-11 16:50:00'),
(12, 12, 1800.00, 2600.00, 2900.00, 3200.00, 800.00, 0, NULL, '2024-11-12 18:05:00', 'Patrice TETE', '2024-11-12 18:05:00', '2024-11-12 18:05:00'),
(13, 13, 2000.00, 2800.00, 3100.00, 3400.00, 800.00, 1, 'Surcharge de 3%', '2024-11-13 08:40:00', 'Patrice TETE', '2024-11-13 08:40:00', '2024-11-13 08:40:00'),
(14, 14, 2200.00, 3000.00, 3300.00, 3600.00, 0.00, 0, NULL, '2024-11-14 09:55:00', 'Patrice TETE', '2024-11-14 09:55:00', '2024-11-14 09:55:00'),
(15, 15, 2500.00, 3200.00, 3500.00, 3800.00, 700.00, 0, NULL, '2024-11-15 11:10:00', 'Patrice TETE', '2024-11-15 11:10:00', '2024-11-15 11:10:00'),
(16, 16, 2800.00, 3500.00, 3800.00, 4100.00, 700.00, 1, 'Dépassement limite', '2024-11-16 12:25:00', 'Patrice TETE', '2024-11-16 12:25:00', '2024-11-16 12:25:00'),
(17, 17, 3000.00, 3800.00, 4100.00, 4400.00, 0.00, 0, NULL, '2024-11-17 13:40:00', 'Patrice TETE', '2024-11-17 13:40:00', '2024-11-17 13:40:00'),
(18, 18, 3200.00, 4000.00, 4300.00, 4600.00, 800.00, 0, NULL, '2024-11-18 14:55:00', 'Patrice TETE', '2024-11-18 14:55:00', '2024-11-18 14:55:00'),
(19, 19, 1500.00, 2200.00, 2500.00, 2800.00, 700.00, 1, 'Surcharge acceptable', '2024-11-19 16:10:00', 'Patrice TETE', '2024-11-19 16:10:00', '2024-11-19 16:10:00'),
(20, 20, 1800.00, 2600.00, 2900.00, 3200.00, 0.00, 0, NULL, '2024-11-20 17:25:00', 'Patrice TETE', '2024-11-20 17:25:00', '2024-11-20 17:25:00'),
(21, 21, 2000.00, 2800.00, 3100.00, 3400.00, 800.00, 0, NULL, '2024-11-21 09:00:00', 'Patrice TETE', '2024-11-21 09:00:00', '2024-11-21 09:00:00'),
(22, 22, 2200.00, 3000.00, 3300.00, 3600.00, 800.00, 1, 'Surcharge essieu arrière', '2024-11-22 10:15:00', 'Patrice TETE', '2024-11-22 10:15:00', '2024-11-22 10:15:00'),
(23, 23, 1500.00, 2200.00, 2500.00, 2800.00, 700.00, 0, NULL, '2024-11-23 11:30:00', 'Patrice TETE', '2024-11-23 11:30:00', '2024-11-23 11:30:00'),
(24, 24, 1800.00, 2600.00, 2900.00, 3200.00, 800.00, 1, 'Surcharge mineure', '2024-11-24 12:45:00', 'Patrice TETE', '2024-11-24 12:45:00', '2024-11-24 12:45:00'),
(25, 25, 2000.00, 2800.00, 3100.00, 3400.00, 800.00, 0, NULL, '2024-11-25 14:00:00', 'Patrice TETE', '2024-11-25 14:00:00', '2024-11-25 14:00:00'),
(26, 26, 2200.00, 3000.00, 3300.00, 3600.00, 800.00, 1, 'Dépassement essieu avant', '2024-11-26 15:15:00', 'Patrice TETE', '2024-11-26 15:15:00', '2024-11-26 15:15:00'),
(27, 27, 2500.00, 3200.00, 3500.00, 3800.00, 700.00, 0, NULL, '2024-11-27 16:30:00', 'Patrice TETE', '2024-11-27 16:30:00', '2024-11-27 16:30:00'),
(28, 28, 2800.00, 3500.00, 3800.00, 4100.00, 700.00, 1, 'Surcharge de 2%', '2024-11-28 17:45:00', 'Patrice TETE', '2024-11-28 17:45:00', '2024-11-28 17:45:00'),
(29, 29, 3000.00, 3800.00, 4100.00, 4400.00, 800.00, 0, NULL, '2024-11-29 19:00:00', 'Patrice TETE', '2024-11-29 19:00:00', '2024-11-29 19:00:00'),
(30, 30, 3200.00, 4000.00, 4300.00, 4600.00, 800.00, 1, 'Dépassement limite autorisée', '2024-11-30 20:15:00', 'Patrice TETE', '2024-11-30 20:15:00', '2024-11-30 20:15:00');

-- --------------------------------------------------------

--
-- Structure de la table `pesage_chargement_camion`
--

DROP TABLE IF EXISTS `pesage_chargement_camion`;
CREATE TABLE IF NOT EXISTS `pesage_chargement_camion` (
  `idPesageChargement` int NOT NULL AUTO_INCREMENT,
  `idChargement` int NOT NULL,
  `date_pesage` datetime NOT NULL,
  `note_pesage` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPesageChargement`),
  KEY `idx_idChargement` (`idChargement`),
  KEY `idx_date_pesage` (`date_pesage`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pesage_chargement_camion`
--

INSERT INTO `pesage_chargement_camion` (`idPesageChargement`, `idChargement`, `date_pesage`, `note_pesage`, `created_at`) VALUES
(1, 1, '2026-01-15 19:50:00', 'Pesage après chargement', '2026-01-15 19:50:00'),
(2, 2, '2024-11-05 14:45:00', 'Pesage standard', '2024-11-05 14:45:00'),
(3, 3, '2024-11-08 17:05:00', 'Pesage contrôlé', '2024-11-08 17:05:00'),
(4, 4, '2024-11-11 19:15:00', 'Pesage urgent', '2024-11-11 19:15:00'),
(5, 5, '2024-11-14 10:30:00', 'Pesage standard', '2024-11-14 10:30:00'),
(6, 6, '2024-11-17 13:45:00', 'Pesage contrôlé', '2024-11-17 13:45:00'),
(7, 7, '2024-11-20 17:00:00', 'Pesage express', '2024-11-20 17:00:00'),
(8, 8, '2024-11-23 12:10:00', 'Pesage standard', '2024-11-23 12:10:00'),
(9, 9, '2024-11-24 13:25:00', 'Pesage contrôlé', '2024-11-24 13:25:00'),
(10, 10, '2024-11-25 14:40:00', 'Pesage standard', '2024-11-25 14:40:00'),
(11, 11, '2024-11-26 15:55:00', 'Pesage contrôlé', '2024-11-26 15:55:00'),
(12, 12, '2024-11-27 17:10:00', 'Pesage standard', '2024-11-27 17:10:00'),
(13, 13, '2024-11-28 18:25:00', 'Pesage contrôlé', '2024-11-28 18:25:00'),
(14, 14, '2024-11-29 19:40:00', 'Pesage standard', '2024-11-29 19:40:00'),
(15, 15, '2024-11-30 20:55:00', 'Pesage contrôlé', '2024-11-30 20:55:00'),
(16, 16, '2024-12-01 08:10:00', 'Pesage standard', '2024-12-01 08:10:00'),
(17, 17, '2024-12-02 09:25:00', 'Pesage contrôlé', '2024-12-02 09:25:00'),
(18, 18, '2024-12-03 10:40:00', 'Pesage standard', '2024-12-03 10:40:00'),
(19, 19, '2024-12-04 11:55:00', 'Pesage contrôlé', '2024-12-04 11:55:00'),
(20, 20, '2024-12-05 13:10:00', 'Pesage standard', '2024-12-05 13:10:00'),
(21, 21, '2024-12-06 14:25:00', 'Pesage contrôlé', '2024-12-06 14:25:00'),
(22, 22, '2024-12-07 15:40:00', 'Pesage standard', '2024-12-07 15:40:00'),
(23, 23, '2024-12-08 16:55:00', 'Pesage contrôlé', '2024-12-08 16:55:00'),
(24, 24, '2024-12-09 18:10:00', 'Pesage standard', '2024-12-09 18:10:00'),
(25, 25, '2024-12-10 19:25:00', 'Pesage contrôlé', '2024-12-10 19:25:00'),
(26, 26, '2024-12-11 20:40:00', 'Pesage standard', '2024-12-11 20:40:00'),
(27, 27, '2024-12-12 21:55:00', 'Pesage contrôlé', '2024-12-12 21:55:00'),
(28, 28, '2024-12-13 23:10:00', 'Pesage standard', '2024-12-13 23:10:00'),
(29, 29, '2024-12-14 00:25:00', 'Pesage contrôlé', '2024-12-14 00:25:00'),
(30, 30, '2024-12-15 01:40:00', 'Pesage final', '2024-12-15 01:40:00');

-- --------------------------------------------------------

--
-- Structure de la table `port`
--

DROP TABLE IF EXISTS `port`;
CREATE TABLE IF NOT EXISTS `port` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pays` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `port`
--

INSERT INTO `port` (`id`, `nom`, `pays`) VALUES
(1, 'BUJUMBURA', 'BURUNDI'),
(2, 'KALAMIE', 'RDC'),
(3, 'GOMA', 'RDC'),
(4, 'KIGOMA', 'TANZANIE'),
(5, 'BENI', 'RDC'),
(6, 'UVIRA', 'RDC'),
(7, 'BUTEMBO', 'RDC'),
(8, 'KINDU', 'RDC'),
(9, 'MOMBASA', 'KENYA'),
(10, 'DAR ES SALAAM', 'TANZANIE');

-- --------------------------------------------------------

--
-- Structure de la table `type_bateau`
--

DROP TABLE IF EXISTS `type_bateau`;
CREATE TABLE IF NOT EXISTS `type_bateau` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `type_bateau`
--

INSERT INTO `type_bateau` (`id`, `nom`) VALUES
(1, 'Bateau cargo'),
(2, 'Bateau RoRO'),
(3, 'Bateau passager'),
(4, 'Bateau conteneur'),
(5, 'Bateau frigorifique'),
(6, 'Bateau citerne'),
(7, 'Bateau de pêche'),
(8, 'Bateau de recherche');

-- --------------------------------------------------------

--
-- Structure de la table `type_camion`
--

DROP TABLE IF EXISTS `type_camion`;
CREATE TABLE IF NOT EXISTS `type_camion` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `type_camion`
--

INSERT INTO `type_camion` (`id`, `nom`) VALUES
(1, 'Titan'),
(2, 'DAF'),
(3, 'JEEP'),
(4, 'Remorque'),
(5, 'Semi-remorque'),
(6, 'Camion benne'),
(7, 'Camion citernne'),
(8, 'Camion frigorifique');

-- --------------------------------------------------------

--
-- Structure de la table `type_marchandise`
--

DROP TABLE IF EXISTS `type_marchandise`;
CREATE TABLE IF NOT EXISTS `type_marchandise` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `type_marchandise`
--

INSERT INTO `type_marchandise` (`id`, `nom`) VALUES
(1, 'Café'),
(2, 'Cacao'),
(3, 'Farine'),
(4, 'Riz'),
(5, 'Sucre'),
(6, 'Huile végétale'),
(7, 'Ciment'),
(8, 'Acier'),
(9, 'Engrais'),
(10, 'Matériel électronique'),
(11, 'Textiles'),
(12, 'Médicaments'),
(13, 'Poisson'),
(14, 'Viande'),
(15, 'Fruits'),
(16, 'Légumes'),
(17, 'Boissons'),
(18, 'Pétrole'),
(19, 'Charbon'),
(20, 'Bois');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `idUser` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mdp` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','autorite','enregistreurEntreeCamion','enregistreurSortieCamion','agentBascule','agentEntrepot','enregistreurEntreeBateau','enregistreurSortieBateau','agentDouane') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mcp` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`idUser`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`idUser`, `nom`, `prenom`, `email`, `mdp`, `role`, `mcp`) VALUES
(1, 'Admin', 'System', 'portbujumbura@gmail.com', '$2y$10$yGheUNTSbVjbKWs1JiA/Re4tSiDGtO44UHxL8y8gAUx9/VMtFDlC.', 'admin', 0),
(3, 'AKAKPO', 'Jean', 'jeanakakpo@gmail.com', '$2y$10$mgTfcxIX4VkBiBkZZYmn6uCs2oeNHZMFlJxNG3l2krpdhfoDhivJq', 'agentEntrepot', 0),
(4, 'GOUDO', 'Fabio', 'fabiodab83@gmail.com', '$2y$10$BQxyEoU/xH/j.7jfz48TQOmIqDjwpVIktrCCBmw7s/iFd870c.Gyu', 'autorite', 0),
(5, 'FOLLY', 'David', 'davidfolly@gmail.com', '$2y$10$KhaetmTWKn4mYvkUawV5zOpHxoL5sbolh632gmrsseUH7MIRQij7q', 'enregistreurEntreeCamion', 0),
(6, 'ATTISSO', 'Elom', 'elomattisso@gmail.com', '$2y$10$7mOkE82NKEb71707X79Coe800aCTpmwzvz6HXUTPwxjqZ3UpNDvPK', 'enregistreurSortieCamion', 0),
(7, 'TETE', 'Patrice', 'patricetete@gmail.com', '$2y$10$6LTa3f7oIH.8ZIlArDmWnuRpgJXJ02CQM2dQtfaf5DBi1Epua8xM2', 'agentBascule', 0),
(8, 'ESIAKU', 'Arnold', 'arnoldesiaku@gmail.com', '$2y$10$aYbvhKGHsf7TWuO291eKkOvL3Eb7UquVI1mJFW.nmBlYL1RF7HXBu', 'enregistreurEntreeBateau', 0),
(9, 'AYITE', 'Patrice', 'patriceayite@gmail.com', '$2y$10$1/p.lzi9HCTTul1GvV86a.vxzWBTYQ4e2RvNpizMiUx0Gd2TxoWNC', 'enregistreurSortieBateau', 0),
(10, 'NAPO', 'Alexandre', 'alexandrenapo@gmail.com', '$2y$10$s02u1WqGoSLBTxGPj7Q/iOd9U.iKTmWg57Yqtd6g3rM57QZNibygu', 'agentDouane', 0),
(12, 'TRAORE', 'Moussa', 'moussatraore@gmail.com', '$2y$10$fkHW5kUibm0L6pjXwT.V.uzl.wupuUtFRComJEHgiA/EjFolEMLHO', 'enregistreurEntreeCamion', 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
