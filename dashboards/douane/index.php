<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentDouane') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Date actuelle
$aujourdhui = date('Y-m-d');
$mois_actuel = date('Y-m');
$semaine_debut = date('Y-m-d', strtotime('monday this week'));
$semaine_fin = date('Y-m-d', strtotime('sunday this week'));
$annee_actuelle = date('Y');

// Initialiser les statistiques
$stats = [
    // Totaux généraux
    'total_camions_entres' => 0,
    'total_camions_sortis' => 0,
    'total_bateaux_entres' => 0,
    'total_bateaux_sortis' => 0,
    
    // Aujourd'hui
    'camions_entres_aujourdhui' => 0,
    'camions_sortis_aujourdhui' => 0,
    'bateaux_entres_aujourdhui' => 0,
    'bateaux_sortis_aujourdhui' => 0,
    
    // Cette semaine
    'camions_entres_semaine' => 0,
    'camions_sortis_semaine' => 0,
    'bateaux_entres_semaine' => 0,
    'bateaux_sortis_semaine' => 0,
    
    // Ce mois
    'camions_entres_mois' => 0,
    'camions_sortis_mois' => 0,
    'bateaux_entres_mois' => 0,
    'bateaux_sortis_mois' => 0,
    
    // Frais - Aujourd'hui
    'frais_thc_aujourdhui' => 0,
    'frais_magasinage_aujourdhui' => 0,
    'frais_douane_aujourdhui' => 0,
    'frais_surestaries_aujourdhui' => 0,
    'total_frais_aujourdhui' => 0,
    
    // Frais - Cette semaine
    'frais_thc_semaine' => 0,
    'frais_magasinage_semaine' => 0,
    'frais_douane_semaine' => 0,
    'frais_surestaries_semaine' => 0,
    'total_frais_semaine' => 0,
    
    // Frais - Ce mois
    'frais_thc_mois' => 0,
    'frais_magasinage_mois' => 0,
    'frais_douane_mois' => 0,
    'frais_surestaries_mois' => 0,
    'total_frais_mois' => 0,
    
    // Frais - Totaux généraux
    'frais_thc_total' => 0,
    'frais_magasinage_total' => 0,
    'frais_douane_total' => 0,
    'frais_surestaries_total' => 0,
    'total_frais_general' => 0,
    
    // Répartition par type d'entité
    'frais_par_type_entite' => [],
    
    // Types de camions
    'camions_par_type' => [],
    
    // Types de bateaux
    'bateaux_par_type' => [],
    
    // Dernières activités
    'derniers_camions_entres' => [],
    'derniers_camions_sortis' => [],
    'derniers_bateaux_entres' => [],
    'derniers_bateaux_sortis' => [],
    'derniers_frais' => []
];

try {
    // Totaux généraux
    $query = "SELECT COUNT(*) as count FROM camions_entrants";
    $result = $conn->query($query);
    $stats['total_camions_entres'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM camions_sortants";
    $result = $conn->query($query);
    $stats['total_camions_sortis'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_entrant";
    $result = $conn->query($query);
    $stats['total_bateaux_entres'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_sortant";
    $result = $conn->query($query);
    $stats['total_bateaux_sortis'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Statistiques pour aujourd'hui
    $query = "SELECT COUNT(*) as count FROM camions_entrants WHERE DATE(date_entree) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['camions_entres_aujourdhui'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM camions_sortants WHERE DATE(date_sortie) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['camions_sortis_aujourdhui'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_entrant WHERE DATE(date_entree) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['bateaux_entres_aujourdhui'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_sortant WHERE DATE(date_sortie) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['bateaux_sortis_aujourdhui'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Statistiques pour cette semaine
    $query = "SELECT COUNT(*) as count FROM camions_entrants WHERE DATE(date_entree) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $semaine_debut, $semaine_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['camions_entres_semaine'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM camions_sortants WHERE DATE(date_sortie) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $semaine_debut, $semaine_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['camions_sortis_semaine'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_entrant WHERE DATE(date_entree) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $semaine_debut, $semaine_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['bateaux_entres_semaine'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_sortant WHERE DATE(date_sortie) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $semaine_debut, $semaine_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['bateaux_sortis_semaine'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Statistiques pour ce mois
    $query = "SELECT COUNT(*) as count FROM camions_entrants WHERE DATE_FORMAT(date_entree, '%Y-%m') = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $mois_actuel);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['camions_entres_mois'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM camions_sortants WHERE DATE_FORMAT(date_sortie, '%Y-%m') = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $mois_actuel);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['camions_sortis_mois'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_entrant WHERE DATE_FORMAT(date_entree, '%Y-%m') = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $mois_actuel);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['bateaux_entres_mois'] = $result->fetch_assoc()['count'] ?? 0;
    
    $query = "SELECT COUNT(*) as count FROM bateau_sortant WHERE DATE_FORMAT(date_sortie, '%Y-%m') = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $mois_actuel);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['bateaux_sortis_mois'] = $result->fetch_assoc()['count'] ?? 0;
    
    // FRAIS - Aujourd'hui
    $query = "SELECT 
                SUM(COALESCE(frais_thc, 0)) as thc,
                SUM(COALESCE(frais_magasinage, 0)) as magasinage,
                SUM(COALESCE(droits_douane, 0)) as douane,
                SUM(COALESCE(surestaries, 0)) as surestaries
              FROM frais_transit 
              WHERE DATE(date_ajout) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $frais_aujourdhui = $result->fetch_assoc();
    $stats['frais_thc_aujourdhui'] = $frais_aujourdhui['thc'] ?? 0;
    $stats['frais_magasinage_aujourdhui'] = $frais_aujourdhui['magasinage'] ?? 0;
    $stats['frais_douane_aujourdhui'] = $frais_aujourdhui['douane'] ?? 0;
    $stats['frais_surestaries_aujourdhui'] = $frais_aujourdhui['surestaries'] ?? 0;
    $stats['total_frais_aujourdhui'] = $stats['frais_thc_aujourdhui'] + $stats['frais_magasinage_aujourdhui'] + 
                                       $stats['frais_douane_aujourdhui'] + $stats['frais_surestaries_aujourdhui'];
    
    // FRAIS - Cette semaine
    $query = "SELECT 
                SUM(COALESCE(frais_thc, 0)) as thc,
                SUM(COALESCE(frais_magasinage, 0)) as magasinage,
                SUM(COALESCE(droits_douane, 0)) as douane,
                SUM(COALESCE(surestaries, 0)) as surestaries
              FROM frais_transit 
              WHERE DATE(date_ajout) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $semaine_debut, $semaine_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $frais_semaine = $result->fetch_assoc();
    $stats['frais_thc_semaine'] = $frais_semaine['thc'] ?? 0;
    $stats['frais_magasinage_semaine'] = $frais_semaine['magasinage'] ?? 0;
    $stats['frais_douane_semaine'] = $frais_semaine['douane'] ?? 0;
    $stats['frais_surestaries_semaine'] = $frais_semaine['surestaries'] ?? 0;
    $stats['total_frais_semaine'] = $stats['frais_thc_semaine'] + $stats['frais_magasinage_semaine'] + 
                                    $stats['frais_douane_semaine'] + $stats['frais_surestaries_semaine'];
    
    // FRAIS - Ce mois
    $query = "SELECT 
                SUM(COALESCE(frais_thc, 0)) as thc,
                SUM(COALESCE(frais_magasinage, 0)) as magasinage,
                SUM(COALESCE(droits_douane, 0)) as douane,
                SUM(COALESCE(surestaries, 0)) as surestaries
              FROM frais_transit 
              WHERE DATE_FORMAT(date_ajout, '%Y-%m') = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $mois_actuel);
    $stmt->execute();
    $result = $stmt->get_result();
    $frais_mois = $result->fetch_assoc();
    $stats['frais_thc_mois'] = $frais_mois['thc'] ?? 0;
    $stats['frais_magasinage_mois'] = $frais_mois['magasinage'] ?? 0;
    $stats['frais_douane_mois'] = $frais_mois['douane'] ?? 0;
    $stats['frais_surestaries_mois'] = $frais_mois['surestaries'] ?? 0;
    $stats['total_frais_mois'] = $stats['frais_thc_mois'] + $stats['frais_magasinage_mois'] + 
                                 $stats['frais_douane_mois'] + $stats['frais_surestaries_mois'];
    
    // FRAIS - Totaux généraux
    $query = "SELECT 
                SUM(COALESCE(frais_thc, 0)) as thc,
                SUM(COALESCE(frais_magasinage, 0)) as magasinage,
                SUM(COALESCE(droits_douane, 0)) as douane,
                SUM(COALESCE(surestaries, 0)) as surestaries
              FROM frais_transit";
    $result = $conn->query($query);
    $frais_total = $result->fetch_assoc();
    $stats['frais_thc_total'] = $frais_total['thc'] ?? 0;
    $stats['frais_magasinage_total'] = $frais_total['magasinage'] ?? 0;
    $stats['frais_douane_total'] = $frais_total['douane'] ?? 0;
    $stats['frais_surestaries_total'] = $frais_total['surestaries'] ?? 0;
    $stats['total_frais_general'] = $stats['frais_thc_total'] + $stats['frais_magasinage_total'] + 
                                    $stats['frais_douane_total'] + $stats['frais_surestaries_total'];
    
    // Répartition des frais par type d'entité
    $query = "SELECT 
                type_entite,
                SUM(COALESCE(frais_thc, 0)) as thc,
                SUM(COALESCE(frais_magasinage, 0)) as magasinage,
                SUM(COALESCE(droits_douane, 0)) as douane,
                SUM(COALESCE(surestaries, 0)) as surestaries,
                SUM(COALESCE(frais_thc, 0) + COALESCE(frais_magasinage, 0) + 
                    COALESCE(droits_douane, 0) + COALESCE(surestaries, 0)) as total
              FROM frais_transit
              GROUP BY type_entite
              ORDER BY total DESC";
    $result = $conn->query($query);
    $stats['frais_par_type_entite'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Répartition des camions par type
    $query = "SELECT 
                tc.nom as type_camion, 
                COUNT(ce.idEntree) as count
              FROM camions_entrants ce
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              GROUP BY ce.idTypeCamion, tc.nom
              ORDER BY count DESC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $stats['camions_par_type'][] = $row;
    }
    
    // Répartition des bateaux par type
    $query = "SELECT 
                tb.nom as type_bateau, 
                COUNT(be.id) as count
              FROM bateau_entrant be
              LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
              GROUP BY be.id_type_bateau, tb.nom
              ORDER BY count DESC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $stats['bateaux_par_type'][] = $row;
    }
    
    // Derniers camions entrés
    $query = "SELECT ce.*, tc.nom as type_camion 
              FROM camions_entrants ce
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              ORDER BY ce.date_entree DESC LIMIT 5";
    $result = $conn->query($query);
    $stats['derniers_camions_entres'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Derniers camions sortis
    $query = "SELECT cs.*, ce.immatriculation, ce.nom_chauffeur, ce.prenom_chauffeur, 
                     tc.nom as type_camion
              FROM camions_sortants cs
              LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              ORDER BY cs.date_sortie DESC LIMIT 5";
    $result = $conn->query($query);
    $stats['derniers_camions_sortis'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Derniers bateaux entrés
    $query = "SELECT be.*, tb.nom as type_bateau, p.nom as port_nom
              FROM bateau_entrant be
              LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
              LEFT JOIN port p ON be.id_port = p.id
              ORDER BY be.date_entree DESC LIMIT 5";
    $result = $conn->query($query);
    $stats['derniers_bateaux_entres'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Derniers bateaux sortis
    $query = "SELECT bs.*, tb.nom as type_bateau, p.nom as destination_port_nom
              FROM bateau_sortant bs
              LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
              LEFT JOIN port p ON bs.id_destination_port = p.id
              ORDER BY bs.date_sortie DESC LIMIT 5";
    $result = $conn->query($query);
    $stats['derniers_bateaux_sortis'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Derniers frais enregistrés
    $query = "SELECT ft.*,
                     CASE 
                        WHEN ft.type_entite = 'camion_entrant' THEN ce.immatriculation
                        WHEN ft.type_entite = 'camion_sortant' THEN cs_entrants.immatriculation
                        WHEN ft.type_entite = 'bateau_entrant' THEN be.nom_navire
                        WHEN ft.type_entite = 'bateau_sortant' THEN bs.nom_navire
                     END as entite_nom,
                     ft.frais_thc,
                     ft.frais_magasinage,
                     ft.droits_douane,
                     ft.surestaries,
                     (COALESCE(ft.frais_thc, 0) + COALESCE(ft.frais_magasinage, 0) + 
                      COALESCE(ft.droits_douane, 0) + COALESCE(ft.surestaries, 0)) as total
              FROM frais_transit ft
              LEFT JOIN camions_entrants ce ON ft.type_entite = 'camion_entrant' AND ft.id_entite = ce.idEntree
              LEFT JOIN camions_sortants cs ON ft.type_entite = 'camion_sortant' AND ft.id_entite = cs.idSortie
              LEFT JOIN camions_entrants cs_entrants ON cs.idEntree = cs_entrants.idEntree
              LEFT JOIN bateau_entrant be ON ft.type_entite = 'bateau_entrant' AND ft.id_entite = be.id
              LEFT JOIN bateau_sortant bs ON ft.type_entite = 'bateau_sortant' AND ft.id_entite = bs.id
              ORDER BY ft.date_ajout DESC LIMIT 10";
    $result = $conn->query($query);
    $stats['derniers_frais'] = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Contrôle Douanier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .dashboard-section {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .activity-item {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .activity-item:hover {
            border-left-color: #8b5cf6;
            background-color: #f8fafc;
            transform: translateX(5px);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        .stagger-delay-1 { animation-delay: 0.1s; }
        .stagger-delay-2 { animation-delay: 0.2s; }
        .stagger-delay-3 { animation-delay: 0.3s; }
        .stagger-delay-4 { animation-delay: 0.4s; }
        .stagger-delay-5 { animation-delay: 0.5s; }
        
        .glow {
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
        }
        
        .floating-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .floating-card:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        .frais-detail-card {
            background: linear-gradient(145deg, #ffffff, #f7f7f7);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
        }
        
        .frais-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .table-frais {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table-frais th {
            background-color: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .table-frais td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .table-frais tr:hover td {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-6">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6 dashboard-section">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <?php echo safe_html($error); ?>
                </div>
            </div>
        <?php endif; ?>
        
       
        
        <!-- Cartes de statistiques principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Carte Camions Entrés -->
            <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 dashboard-section stagger-delay-1 floating-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-blue-100 p-3 rounded-full glow">
                        <i class="fas fa-truck-moving text-blue-600 text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                            Aujourd'hui: +<?php echo $stats['camions_entres_aujourdhui']; ?>
                        </span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_camions_entres']); ?></h3>
                <p class="text-gray-600 mb-4">Camions Entrés</p>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600">
                        <i class="fas fa-arrow-up mr-1"></i> 
                        +<?php echo $stats['camions_entres_semaine']; ?> cette semaine
                    </span>
                    <span class="text-gray-500"><?php echo $stats['camions_entres_mois']; ?> ce mois</span>
                </div>
            </div>
            
            <!-- Carte Camions Sortis -->
            <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 dashboard-section stagger-delay-2 floating-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-green-100 p-3 rounded-full glow">
                        <i class="fas fa-truck-loading text-green-600 text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-green-600 bg-green-50 px-3 py-1 rounded-full">
                            Aujourd'hui: +<?php echo $stats['camions_sortis_aujourdhui']; ?>
                        </span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_camions_sortis']); ?></h3>
                <p class="text-gray-600 mb-4">Camions Sortis</p>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600">
                        <i class="fas fa-arrow-up mr-1"></i> 
                        +<?php echo $stats['camions_sortis_semaine']; ?> cette semaine
                    </span>
                    <span class="text-gray-500"><?php echo $stats['camions_sortis_mois']; ?> ce mois</span>
                </div>
            </div>
            
            <!-- Carte Bateaux Entrés -->
            <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-500 dashboard-section stagger-delay-3 floating-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-indigo-100 p-3 rounded-full glow">
                        <i class="fas fa-ship text-indigo-600 text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">
                            Aujourd'hui: +<?php echo $stats['bateaux_entres_aujourdhui']; ?>
                        </span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_bateaux_entres']); ?></h3>
                <p class="text-gray-600 mb-4">Bateaux Entrés</p>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600">
                        <i class="fas fa-arrow-up mr-1"></i> 
                        +<?php echo $stats['bateaux_entres_semaine']; ?> cette semaine
                    </span>
                    <span class="text-gray-500"><?php echo $stats['bateaux_entres_mois']; ?> ce mois</span>
                </div>
            </div>
            
            <!-- Carte Frais Totaux -->
            <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500 dashboard-section stagger-delay-4 floating-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-purple-100 p-3 rounded-full glow">
                        <i class="fas fa-money-bill-wave text-purple-600 text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">
                            <?php echo number_format($stats['total_frais_aujourdhui'], 2, ',', ' '); ?> F CFA
                        </span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_frais_general'], 2, ',', ' '); ?> F CFA</h3>
                <p class="text-gray-600 mb-4">Frais Collectés Totaux</p>
                <div class="flex justify-between text-sm">
                    <span class="text-green-600">
                        <i class="fas fa-chart-line mr-1"></i> 
                        <?php echo number_format($stats['total_frais_semaine'], 2, ',', ' '); ?> F CFA cette semaine
                    </span>
                    <span class="text-gray-500"><?php echo number_format($stats['total_frais_mois'], 2, ',', ' '); ?> F CFA ce mois</span>
                </div>
            </div>
        </div>
        
        <!-- Statistiques détaillées des frais -->
        <div class="mb-8 dashboard-section">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-file-invoice-dollar text-purple-600 mr-2"></i>Statistiques Détaillées des Frais
                    </h2>
                    <span class="text-sm text-gray-500"><?php echo date('F Y'); ?></span>
                </div>
                
                <!-- Badges récapitulatifs -->
                <div class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div class="frais-badge bg-blue-100 text-blue-800">
                            <i class="fas fa-dolly mr-2"></i>THC: <?php echo number_format($stats['frais_thc_total'], 2, ',', ' '); ?> F CFA
                        </div>
                        <div class="frais-badge bg-green-100 text-green-800">
                            <i class="fas fa-warehouse mr-2"></i>Magasinage: <?php echo number_format($stats['frais_magasinage_total'], 2, ',', ' '); ?> F CFA
                        </div>
                        <div class="frais-badge bg-yellow-100 text-yellow-800">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>Douane: <?php echo number_format($stats['frais_douane_total'], 2, ',', ' '); ?> F CFA
                        </div>
                        <div class="frais-badge bg-red-100 text-red-800">
                            <i class="fas fa-clock mr-2"></i>Surestaries: <?php echo number_format($stats['frais_surestaries_total'], 2, ',', ' '); ?> F CFA
                        </div>
                    </div>
                </div>
                
                <!-- Tableau des frais par période -->
                <div class="overflow-x-auto">
                    <table class="table-frais">
                        <thead>
                            <tr>
                                <th class="rounded-tl-lg">Période</th>
                                <th>Frais THC (F CFA)</th>
                                <th>Magasinage (F CFA)</th>
                                <th>Droits de Douane (F CFA)</th>
                                <th>Surestaries (F CFA)</th>
                                <th class="rounded-tr-lg">Total (F CFA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Aujourd'hui -->
                            <tr>
                                <td class="font-semibold">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-sun text-yellow-500 mr-2"></i>Aujourd'hui
                                    </span>
                                </td>
                                <td class="text-blue-600 font-medium"><?php echo number_format($stats['frais_thc_aujourdhui'], 2, ',', ' '); ?></td>
                                <td class="text-green-600 font-medium"><?php echo number_format($stats['frais_magasinage_aujourdhui'], 2, ',', ' '); ?></td>
                                <td class="text-yellow-600 font-medium"><?php echo number_format($stats['frais_douane_aujourdhui'], 2, ',', ' '); ?></td>
                                <td class="text-red-600 font-medium"><?php echo number_format($stats['frais_surestaries_aujourdhui'], 2, ',', ' '); ?></td>
                                <td class="font-bold text-gray-800"><?php echo number_format($stats['total_frais_aujourdhui'], 2, ',', ' '); ?></td>
                            </tr>
                            
                            <!-- Cette semaine -->
                            <tr>
                                <td class="font-semibold">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-calendar-week text-blue-500 mr-2"></i>Cette semaine
                                    </span>
                                </td>
                                <td class="text-blue-600 font-medium"><?php echo number_format($stats['frais_thc_semaine'], 2, ',', ' '); ?></td>
                                <td class="text-green-600 font-medium"><?php echo number_format($stats['frais_magasinage_semaine'], 2, ',', ' '); ?></td>
                                <td class="text-yellow-600 font-medium"><?php echo number_format($stats['frais_douane_semaine'], 2, ',', ' '); ?></td>
                                <td class="text-red-600 font-medium"><?php echo number_format($stats['frais_surestaries_semaine'], 2, ',', ' '); ?></td>
                                <td class="font-bold text-gray-800"><?php echo number_format($stats['total_frais_semaine'], 2, ',', ' '); ?></td>
                            </tr>
                            
                            <!-- Ce mois -->
                            <tr>
                                <td class="font-semibold">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-calendar-alt text-purple-500 mr-2"></i>Ce mois
                                    </span>
                                </td>
                                <td class="text-blue-600 font-medium"><?php echo number_format($stats['frais_thc_mois'], 2, ',', ' '); ?></td>
                                <td class="text-green-600 font-medium"><?php echo number_format($stats['frais_magasinage_mois'], 2, ',', ' '); ?></td>
                                <td class="text-yellow-600 font-medium"><?php echo number_format($stats['frais_douane_mois'], 2, ',', ' '); ?></td>
                                <td class="text-red-600 font-medium"><?php echo number_format($stats['frais_surestaries_mois'], 2, ',', ' '); ?></td>
                                <td class="font-bold text-gray-800"><?php echo number_format($stats['total_frais_mois'], 2, ',', ' '); ?></td>
                            </tr>
                            
                            <!-- Total général -->
                            <tr class="bg-gray-50 font-bold">
                                <td class="rounded-bl-lg">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-chart-bar text-gray-700 mr-2"></i>Total Général
                                    </span>
                                </td>
                                <td class="text-blue-700"><?php echo number_format($stats['frais_thc_total'], 2, ',', ' '); ?></td>
                                <td class="text-green-700"><?php echo number_format($stats['frais_magasinage_total'], 2, ',', ' '); ?></td>
                                <td class="text-yellow-700"><?php echo number_format($stats['frais_douane_total'], 2, ',', ' '); ?></td>
                                <td class="text-red-700"><?php echo number_format($stats['frais_surestaries_total'], 2, ',', ' '); ?></td>
                                <td class="text-gray-900 rounded-br-lg"><?php echo number_format($stats['total_frais_general'], 2, ',', ' '); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Répartition par type d'entité -->
                <?php if (!empty($stats['frais_par_type_entite'])): ?>
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie text-gray-600 mr-2"></i>Répartition par Type d'Entité
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php foreach ($stats['frais_par_type_entite'] as $type): 
                            $type_label = '';
                            $type_icon = '';
                            $type_color = '';
                            
                            switch ($type['type_entite']) {
                                case 'camion_entrant':
                                    $type_label = 'Camions Entrants';
                                    $type_icon = 'truck-moving';
                                    $type_color = 'bg-blue-50 text-blue-700';
                                    break;
                                case 'camion_sortant':
                                    $type_label = 'Camions Sortants';
                                    $type_icon = 'truck-loading';
                                    $type_color = 'bg-green-50 text-green-700';
                                    break;
                                case 'bateau_entrant':
                                    $type_label = 'Bateaux Entrants';
                                    $type_icon = 'ship';
                                    $type_color = 'bg-indigo-50 text-indigo-700';
                                    break;
                                case 'bateau_sortant':
                                    $type_label = 'Bateaux Sortants';
                                    $type_icon = 'anchor';
                                    $type_color = 'bg-purple-50 text-purple-700';
                                    break;
                            }
                        ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center mb-3">
                                <div class="h-10 w-10 <?php echo str_replace('text-', 'bg-', $type_color); ?> rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-<?php echo $type_icon; ?> <?php echo $type_color; ?>"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo $type_label; ?></p>
                                    <p class="text-sm text-gray-500">Total: <?php echo number_format($type['total'], 2, ',', ' '); ?> F CFA</p>
                                </div>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">THC:</span>
                                    <span class="font-medium"><?php echo number_format($type['thc'], 2, ',', ' '); ?> F CFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Magasinage:</span>
                                    <span class="font-medium"><?php echo number_format($type['magasinage'], 2, ',', ' '); ?> F CFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Douane:</span>
                                    <span class="font-medium"><?php echo number_format($type['douane'], 2, ',', ' '); ?> F CFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Surestaries:</span>
                                    <span class="font-medium"><?php echo number_format($type['surestaries'], 2, ',', ' '); ?> F CFA</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Dernières activités -->
        <div class="grid grid-cols-1 gap-8 mb-8">
            <!-- Derniers frais -->
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-section">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-history text-purple-600 mr-2"></i>Derniers Frais Enregistrés
                    </h2>
                    <a href="historique_frais.php" class="text-sm text-purple-600 hover:text-purple-800 font-semibold">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="space-y-4">
                    <?php if (!empty($stats['derniers_frais'])): ?>
                        <?php foreach ($stats['derniers_frais'] as $frais): ?>
                            <?php
                            $type_icon = '';
                            $type_color = '';
                            $type_label = '';
                            
                            switch ($frais['type_entite']) {
                                case 'camion_entrant':
                                    $type_icon = 'truck';
                                    $type_color = 'text-blue-500';
                                    $type_label = 'Camion Entrant';
                                    break;
                                case 'camion_sortant':
                                    $type_icon = 'truck-loading';
                                    $type_color = 'text-green-500';
                                    $type_label = 'Camion Sortant';
                                    break;
                                case 'bateau_entrant':
                                    $type_icon = 'ship';
                                    $type_color = 'text-indigo-500';
                                    $type_label = 'Bateau Entrant';
                                    break;
                                case 'bateau_sortant':
                                    $type_icon = 'anchor';
                                    $type_color = 'text-purple-500';
                                    $type_label = 'Bateau Sortant';
                                    break;
                            }
                            
                            $total = $frais['total'] ?? 0;
                            $heure = date('H:i', strtotime($frais['date_ajout']));
                            ?>
                            <div class="activity-item p-4 bg-gray-50 rounded-lg hover:shadow-md">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-gradient-to-r from-purple-100 to-indigo-100 rounded-full flex items-center justify-center mr-4">
                                            <i class="fas fa-<?php echo $type_icon; ?> <?php echo $type_color; ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo safe_html($frais['entite_nom'] ?? 'N/A'); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo $type_label; ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-gray-800"><?php echo number_format($total, 2, ',', ' '); ?> F CFA</p>
                                        <p class="text-xs text-gray-500"><?php echo $heure; ?></p>
                                    </div>
                                </div>
                                <div class="mt-2 grid grid-cols-4 gap-2 text-xs font-bold">
                                    <?php if ($frais['frais_thc'] > 0): ?>
                                    <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-center">
                                        THC: <?php echo number_format($frais['frais_thc'], 2, ',', ' '); ?> F CFA
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($frais['frais_magasinage'] > 0): ?>
                                    <span class="bg-green-50 text-green-700 px-2 py-1 rounded text-center">
                                        Mag: <?php echo number_format($frais['frais_magasinage'], 2, ',', ' '); ?> F CFA
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($frais['droits_douane'] > 0): ?>
                                    <span class="bg-yellow-50 text-yellow-700 px-2 py-1 rounded text-center">
                                        Douane: <?php echo number_format($frais['droits_douane'], 2, ',', ' '); ?> F CFA
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($frais['surestaries'] > 0): ?>
                                    <span class="bg-red-50 text-red-700 px-2 py-1 rounded text-center">
                                        Surest: <?php echo number_format($frais['surestaries'], 2, ',', ' '); ?> F CFA
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <i class="fas fa-file-invoice-dollar text-4xl mb-3"></i>
                            <p>Aucun frais enregistré récemment</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            
        </div>
        
        
        
        
    </div>
    
    <!-- Scripts -->
    <script>
        // Mettre à jour l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const dateTimeString = now.toLocaleDateString('fr-FR', options);
            document.querySelector('.text-gray-600').innerHTML = 
                `<i class="fas fa-calendar-alt mr-2"></i>${dateTimeString}`;
        }
        
        // Mettre à jour l'heure toutes les secondes
        setInterval(updateTime, 1000);
        
        // Animation des cartes au survol
        const cards = document.querySelectorAll('.floating-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.classList.add('glow');
            });
            card.addEventListener('mouseleave', () => {
                card.classList.remove('glow');
            });
        });
        
        // Fonction pour actualiser le dashboard
        function refreshDashboard() {
            const refreshBtn = event.target.closest('a') || event.target;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mb-2 block text-xl"></i>Actualisation...';
            refreshBtn.disabled = true;
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        // Fonction pour afficher une notification
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-100 border-green-400 text-green-700',
                error: 'bg-red-100 border-red-400 text-red-700',
                info: 'bg-blue-100 border-blue-400 text-blue-700',
                warning: 'bg-yellow-100 border-yellow-400 text-yellow-700'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${colors[type]} px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-3"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 10);
            
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Vérifier s'il y a de nouvelles activités
        function checkNewActivities() {
            // Cette fonction pourrait être implémentée avec WebSockets ou AJAX polling
            // Pour l'instant, nous allons simplement vérifier périodiquement
            setTimeout(() => {
                fetch('check_activities.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.new_activities > 0) {
                            showNotification(`${data.new_activities} nouvelles activités détectées`, 'info');
                        }
                        checkNewActivities();
                    })
                    .catch(error => {
                        console.error('Error checking activities:', error);
                        checkNewActivities();
                    });
            }, 30000); // Vérifier toutes les 30 secondes
        }
        
        // Démarrer la vérification des activités
        checkNewActivities();
    </script>
</body>
</html>