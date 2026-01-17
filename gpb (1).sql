-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 16 jan. 2026 à 20:02
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
  `nom_navire` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `immatriculation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_capitaine` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom_capitaine` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tel_capitaine` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agence` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hauteur` decimal(10,2) DEFAULT NULL,
  `longueur` decimal(10,2) DEFAULT NULL,
  `largeur` decimal(10,2) DEFAULT NULL,
  `date_entree` datetime DEFAULT CURRENT_TIMESTAMP,
  `etat` enum('vide','chargé') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vide',
  `note` text COLLATE utf8mb4_unicode_ci,
  `agent_enregistrement` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_type_bateau` (`id_type_bateau`),
  KEY `id_port` (`id_port`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bateau_entrant`
--

INSERT INTO `bateau_entrant` (`id`, `id_type_bateau`, `id_port`, `nom_navire`, `immatriculation`, `nom_capitaine`, `prenom_capitaine`, `tel_capitaine`, `agence`, `hauteur`, `longueur`, `largeur`, `date_entree`, `etat`, `note`, `agent_enregistrement`, `created_at`, `updated_at`) VALUES
(12, 4, 1, 'DERICLAK', 'BUR-2176', 'ETSE', 'KOMI', '93563420', 'Helvetica', 8.00, 15.00, 12.00, '2026-01-16 12:36:08', 'vide', 'Rien à signaler', 'Arnold ESIAKU', '2026-01-16 12:36:08', '2026-01-16 12:36:08'),
(11, 1, 1, 'TITANIC', 'BUR-0310', 'DOGBE', 'FRED', '93563420', 'TEATRE', 9.00, 13.00, 12.00, '2026-01-16 12:27:29', 'chargé', 'Aucune observation', 'Arnold ESIAKU', '2026-01-16 12:27:29', '2026-01-16 12:29:36');

-- --------------------------------------------------------

--
-- Structure de la table `bateau_sortant`
--

DROP TABLE IF EXISTS `bateau_sortant`;
CREATE TABLE IF NOT EXISTS `bateau_sortant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_type_bateau` int DEFAULT NULL,
  `nom_navire` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `immatriculation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_capitaine` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_capitaine` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tel_capitaine` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agence` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hauteur` decimal(10,2) DEFAULT NULL,
  `longueur` decimal(10,2) DEFAULT NULL,
  `largeur` decimal(10,2) DEFAULT NULL,
  `id_destination_port` int DEFAULT NULL,
  `etat` enum('vide','chargé') COLLATE utf8mb4_unicode_ci DEFAULT 'vide',
  `note` text COLLATE utf8mb4_unicode_ci,
  `date_sortie` datetime DEFAULT CURRENT_TIMESTAMP,
  `agent_enregistrement` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_bateau` (`id_type_bateau`),
  KEY `id_destination_port` (`id_destination_port`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `bateau_sortant`
--

INSERT INTO `bateau_sortant` (`id`, `id_type_bateau`, `nom_navire`, `immatriculation`, `nom_capitaine`, `prenom_capitaine`, `tel_capitaine`, `agence`, `hauteur`, `longueur`, `largeur`, `id_destination_port`, `etat`, `note`, `date_sortie`, `agent_enregistrement`) VALUES
(1, 1, 'TITANIC', 'BUR-4390', 'FOLLY', 'JAMES', '93563420', 'DOUFELITA', 10.00, 13.00, 12.00, 1, 'chargé', 'Note', '2026-01-16 14:11:57', 'Patrice AYITE');

-- --------------------------------------------------------

--
-- Structure de la table `camions_entrants`
--

DROP TABLE IF EXISTS `camions_entrants`;
CREATE TABLE IF NOT EXISTS `camions_entrants` (
  `idEntree` int NOT NULL AUTO_INCREMENT,
  `idTypeCamion` int NOT NULL,
  `marque` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `immatriculation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_chauffeur` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_chauffeur` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone_chauffeur` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agence` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idPort` int NOT NULL,
  `destinataire` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t1` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etat` enum('Chargé','Vide') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chargé',
  `raison` enum('Pesage','Déchargement','Chargement','Déchargement et chargement') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pesage',
  `date_entree` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_sortie` datetime DEFAULT NULL,
  `idUser` int DEFAULT NULL,
  `poids` int DEFAULT NULL,
  PRIMARY KEY (`idEntree`),
  UNIQUE KEY `immatriculation` (`immatriculation`),
  KEY `idTypeCamion` (`idTypeCamion`),
  KEY `idPort` (`idPort`),
  KEY `idUser` (`idUser`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `camions_entrants`
--

INSERT INTO `camions_entrants` (`idEntree`, `idTypeCamion`, `marque`, `immatriculation`, `nom_chauffeur`, `prenom_chauffeur`, `telephone_chauffeur`, `agence`, `nif`, `idPort`, `destinataire`, `t1`, `etat`, `raison`, `date_entree`, `date_sortie`, `idUser`, `poids`) VALUES
(1, 2, 'Volvo', 'BUR-4810', 'WANGRE', 'David', '+228 97732644', 'VALHALA', 'ER-789', 1, 'HERCUL', '1200', 'Chargé', 'Déchargement', '2026-01-14 12:35:37', NULL, NULL, 300),
(2, 3, 'Volvo', 'BUR-4390', 'DEGBE', 'Jean', '+228 99754220', '', '', 1, '', '', 'Vide', 'Chargement', '2026-01-14 14:39:34', NULL, NULL, 0);

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
  `type_sortie` enum('charge','decharge') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSortie`),
  KEY `idx_date_sortie` (`date_sortie`),
  KEY `idx_type_sortie` (`type_sortie`),
  KEY `idx_camion` (`idEntree`),
  KEY `idx_chargement` (`idChargement`),
  KEY `idx_dechargement` (`idDechargement`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `camions_sortants`
--

INSERT INTO `camions_sortants` (`idSortie`, `idEntree`, `idChargement`, `idDechargement`, `date_sortie`, `type_sortie`, `created_at`) VALUES
(2, 1, 0, 1, '2026-01-15 22:38:04', 'decharge', '2026-01-15 22:38:04');

-- --------------------------------------------------------

--
-- Structure de la table `chargement_camions`
--

DROP TABLE IF EXISTS `chargement_camions`;
CREATE TABLE IF NOT EXISTS `chargement_camions` (
  `idChargement` int NOT NULL AUTO_INCREMENT,
  `idEntree` int NOT NULL,
  `date_chargement` datetime DEFAULT CURRENT_TIMESTAMP,
  `note_chargement` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`idChargement`),
  KEY `idEntree` (`idEntree`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `chargement_camions`
--

INSERT INTO `chargement_camions` (`idChargement`, `idEntree`, `date_chargement`, `note_chargement`) VALUES
(1, 2, '2026-01-15 19:44:57', '');

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
  `note_dechargement` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`idDechargement`),
  KEY `idx_date_dechargement` (`date_dechargement`),
  KEY `idx_idEntree` (`idEntree`),
  KEY `idx_idChargement` (`idChargement`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `dechargements_camions`
--

INSERT INTO `dechargements_camions` (`idDechargement`, `idEntree`, `idChargement`, `date_dechargement`, `note_dechargement`) VALUES
(1, 1, 0, '2026-01-15 19:38:18', '');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_timestamp` (`timestamp`),
  KEY `idx_logs_user_id` (`user_id`),
  KEY `idx_logs_action` (`action`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `note` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idMarchandise`),
  KEY `idx_idPesage` (`idPesage`),
  KEY `idx_idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandises_pesage`
--

INSERT INTO `marchandises_pesage` (`idMarchandise`, `idPesage`, `idTypeMarchandise`, `poids`, `note`, `date_ajout`, `created_at`, `updated_at`) VALUES
(4, 1, 3, 500.00, '', '2026-01-14 16:37:39', '2026-01-14 16:37:39', '2026-01-14 16:37:39'),
(3, 1, 2, 500.00, '', '2026-01-14 16:37:39', '2026-01-14 16:37:39', '2026-01-14 16:37:39');

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
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_marchandise_pesage` (`idPesageChargement`,`idTypeMarchandise`),
  KEY `idx_idPesageChargement` (`idPesageChargement`),
  KEY `idx_idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `note` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_bateau_entrant` (`id_bateau_entrant`),
  KEY `id_type_marchandise` (`id_type_marchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_bateau_entrant`
--

INSERT INTO `marchandise_bateau_entrant` (`id`, `id_bateau_entrant`, `id_type_marchandise`, `poids`, `note`, `date_ajout`) VALUES
(7, 11, 3, 35.00, '', '2026-01-16 12:29:36');

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
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_bateau_sortant` (`id_bateau_sortant`),
  KEY `id_type_marchandise` (`id_type_marchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_bateau_sortant`
--

INSERT INTO `marchandise_bateau_sortant` (`id`, `id_bateau_sortant`, `id_type_marchandise`, `poids`, `note`, `date_ajout`) VALUES
(1, 1, 2, 10.00, '', '2026-01-16 14:12:36');

-- --------------------------------------------------------

--
-- Structure de la table `marchandise_chargement_camion`
--

DROP TABLE IF EXISTS `marchandise_chargement_camion`;
CREATE TABLE IF NOT EXISTS `marchandise_chargement_camion` (
  `idMarchandiseChargement` int NOT NULL AUTO_INCREMENT,
  `idChargement` int NOT NULL,
  `idTypeMarchandise` int NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `poids` int NOT NULL,
  PRIMARY KEY (`idMarchandiseChargement`),
  KEY `idChargement` (`idChargement`),
  KEY `idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_chargement_camion`
--

INSERT INTO `marchandise_chargement_camion` (`idMarchandiseChargement`, `idChargement`, `idTypeMarchandise`, `note`, `date_ajout`, `poids`) VALUES
(8, 1, 1, '', '2026-01-15 19:44:57', 0),
(7, 1, 2, '', '2026-01-15 19:44:57', 0);

-- --------------------------------------------------------

--
-- Structure de la table `marchandise_dechargement_camion`
--

DROP TABLE IF EXISTS `marchandise_dechargement_camion`;
CREATE TABLE IF NOT EXISTS `marchandise_dechargement_camion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idDechargement` int NOT NULL,
  `idTypeMarchandise` int NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_idDechargement` (`idDechargement`),
  KEY `idx_idTypeMarchandise` (`idTypeMarchandise`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `marchandise_dechargement_camion`
--

INSERT INTO `marchandise_dechargement_camion` (`id`, `idDechargement`, `idTypeMarchandise`, `note`, `date_ajout`) VALUES
(1, 1, 3, '', '2026-01-15 19:38:18'),
(2, 1, 2, '', '2026-01-15 19:38:18');

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
  `note_surcharge` text COLLATE utf8mb4_unicode_ci,
  `date_pesage` datetime DEFAULT CURRENT_TIMESTAMP,
  `agent_bascule` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPesage`),
  KEY `idx_idEntree` (`idEntree`),
  KEY `idx_date_pesage` (`date_pesage`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `pesages`
--

INSERT INTO `pesages` (`idPesage`, `idEntree`, `ptav`, `ptac`, `ptra`, `charge_essieu`, `poids_total_marchandises`, `surcharge`, `note_surcharge`, `date_pesage`, `agent_bascule`, `created_at`, `updated_at`) VALUES
(1, 1, 1200.00, 1800.00, 2000.00, 3000.00, 1000.00, 1, '', '2026-01-14 16:37:39', 'Patrice TETE', '2026-01-14 15:24:31', '2026-01-14 16:37:39'),
(2, 2, 1500.00, 1800.00, 2300.00, 2500.00, 0.00, 0, '', '2026-01-14 16:35:36', 'Patrice TETE', '2026-01-14 16:35:36', '2026-01-14 16:35:36');

-- --------------------------------------------------------

--
-- Structure de la table `pesage_chargement_camion`
--

DROP TABLE IF EXISTS `pesage_chargement_camion`;
CREATE TABLE IF NOT EXISTS `pesage_chargement_camion` (
  `idPesageChargement` int NOT NULL AUTO_INCREMENT,
  `idChargement` int NOT NULL,
  `date_pesage` datetime NOT NULL,
  `note_pesage` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPesageChargement`),
  KEY `idx_idChargement` (`idChargement`),
  KEY `idx_date_pesage` (`date_pesage`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `port`
--

DROP TABLE IF EXISTS `port`;
CREATE TABLE IF NOT EXISTS `port` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pays` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `port`
--

INSERT INTO `port` (`id`, `nom`, `pays`) VALUES
(1, 'BUJUMBURA', 'BURUNDI');

-- --------------------------------------------------------

--
-- Structure de la table `type_bateau`
--

DROP TABLE IF EXISTS `type_bateau`;
CREATE TABLE IF NOT EXISTS `type_bateau` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `type_bateau`
--

INSERT INTO `type_bateau` (`id`, `nom`) VALUES
(1, 'Bateau cargo'),
(2, 'Bateau RoRO'),
(3, 'Bateau passager'),
(4, 'Bateau conteneur');

-- --------------------------------------------------------

--
-- Structure de la table `type_camion`
--

DROP TABLE IF EXISTS `type_camion`;
CREATE TABLE IF NOT EXISTS `type_camion` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `type_camion`
--

INSERT INTO `type_camion` (`id`, `nom`) VALUES
(1, 'Titan'),
(2, 'DAF'),
(3, 'JEEP');

-- --------------------------------------------------------

--
-- Structure de la table `type_marchandise`
--

DROP TABLE IF EXISTS `type_marchandise`;
CREATE TABLE IF NOT EXISTS `type_marchandise` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `type_marchandise`
--

INSERT INTO `type_marchandise` (`id`, `nom`) VALUES
(1, 'Café'),
(2, 'Cacao'),
(3, 'Farine');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `idUser` smallint NOT NULL AUTO_INCREMENT,
  `nom` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `mdp` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','autorite','enregistreurEntreeCamion','enregistreurSortieCamion','agentBascule','agentEntrepot','enregistreurEntreeBateau','enregistreurSortieBateau','agentDouane') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mcp` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`idUser`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`idUser`, `nom`, `prenom`, `email`, `mdp`, `role`, `mcp`) VALUES
(1, 'Admin', 'System', 'portbujumbura@gmail.com', '$2y$10$pBMXTJSWf8negdQckG6c0.DXC/Mpr4HOJVoC0P5QDVO3OxsYdrNv6', 'admin', 0),
(3, 'AKAKPO', 'Jean', 'jeanakakpo@gmail.com', '$2y$10$mgTfcxIX4VkBiBkZZYmn6uCs2oeNHZMFlJxNG3l2krpdhfoDhivJq', 'agentEntrepot', 0),
(4, 'GOUDO', 'Fabio', 'fabiodab83@gmail.com', '$2y$10$fkHW5kUibm0L6pjXwT.V.uzl.wupuUtFRComJEHgiA/EjFolEMLHO', 'autorite', 1),
(5, 'FOLLY', 'David', 'davidfolly@gmail.com', '$2y$10$KhaetmTWKn4mYvkUawV5zOpHxoL5sbolh632gmrsseUH7MIRQij7q', 'enregistreurEntreeCamion', 0),
(6, 'ATTISSO', 'Elom', 'elomattisso@gmail.com', '$2y$10$7mOkE82NKEb71707X79Coe800aCTpmwzvz6HXUTPwxjqZ3UpNDvPK', 'enregistreurSortieCamion', 0),
(7, 'TETE', 'Patrice', 'patricetete@gmail.com', '$2y$10$6LTa3f7oIH.8ZIlArDmWnuRpgJXJ02CQM2dQtfaf5DBi1Epua8xM2', 'agentBascule', 0),
(8, 'ESIAKU', 'Arnold', 'arnoldesiaku@gmail.com', '$2y$10$aYbvhKGHsf7TWuO291eKkOvL3Eb7UquVI1mJFW.nmBlYL1RF7HXBu', 'enregistreurEntreeBateau', 0),
(9, 'AYITE', 'Patrice', 'patriceayite@gmail.com', '$2y$10$1/p.lzi9HCTTul1GvV86a.vxzWBTYQ4e2RvNpizMiUx0Gd2TxoWNC', 'enregistreurSortieBateau', 0),
(10, 'NAPO', 'Alexandre', 'alexandrenapo@gmail.com', '$2y$10$uoiYaF18B979VjcG8jPWuObI62MrbpfaxOH4p1nVMH0SeZ8FbgjV6', 'agentDouane', 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
