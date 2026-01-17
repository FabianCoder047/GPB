<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurSortieCamion') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Initialisation des variables de période
$periode = $_GET['periode'] ?? 'today'; // today, week, month, year
$filters = [
    'periode' => $periode,
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? ''
];

// Déterminer les dates selon la période
$now = new DateTime();
$date_debut = '';
$date_fin = '';

switch ($periode) {
    case 'today':
        $date_debut = $now->format('Y-m-d');
        $date_fin = $now->format('Y-m-d');
        break;
    case 'yesterday':
        $yesterday = clone $now;
        $yesterday->modify('-1 day');
        $date_debut = $yesterday->format('Y-m-d');
        $date_fin = $yesterday->format('Y-m-d');
        break;
    case 'week':
        $monday = clone $now;
        $monday->modify('Monday this week');
        $date_debut = $monday->format('Y-m-d');
        $date_fin = $now->format('Y-m-d');
        break;
    case 'month':
        $date_debut = $now->format('Y-m-01');
        $date_fin = $now->format('Y-m-t');
        break;
    case 'year':
        $date_debut = $now->format('Y-01-01');
        $date_fin = $now->format('Y-12-31');
        break;
    case 'custom':
        $date_debut = $filters['date_debut'] ?? '';
        $date_fin = $filters['date_fin'] ?? '';
        break;
    default:
        $date_debut = $now->format('Y-m-d');
        $date_fin = $now->format('Y-m-d');
}

// Statistiques principales
$stats = [
    'total_sorties' => 0,
    'sorties_charge' => 0,
    'sorties_decharge' => 0,
    'total_poids' => 0,
    'moyenne_poids' => 0,
    'total_marchandises' => 0,
    'surcharges' => 0
];

// Statistiques hebdomadaires pour graphique
$stats_semaine = [];
$stats_mois = [];

// Tendances
$tendances = [
    'sorties' => 0, // +10% ou -5%
    'poids' => 0,
    'surcharges' => 0
];

try {
    // Statistiques générales pour la période
    $query_stats = "
        SELECT 
            COUNT(*) as total_sorties,
            SUM(CASE WHEN type_sortie = 'charge' THEN 1 ELSE 0 END) as sorties_charge,
            SUM(CASE WHEN type_sortie = 'decharge' THEN 1 ELSE 0 END) as sorties_decharge,
            COUNT(DISTINCT cs.idEntree) as camions_uniques,
            AVG(p.ptav + p.poids_total_marchandises) as moyenne_poids,
            SUM(p.poids_total_marchandises) as total_poids_marchandises,
            SUM(CASE WHEN p.surcharge = 1 THEN 1 ELSE 0 END) as surcharges
        FROM camions_sortants cs
        LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
        LEFT JOIN pesages p ON cs.idEntree = p.idEntree
        WHERE DATE(cs.date_sortie) BETWEEN ? AND ?
    ";
    
    $stmt = $conn->prepare($query_stats);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result_stats = $stmt->get_result();
    
    if ($result_stats->num_rows > 0) {
        $row = $result_stats->fetch_assoc();
        $stats = [
            'total_sorties' => $row['total_sorties'] ?? 0,
            'sorties_charge' => $row['sorties_charge'] ?? 0,
            'sorties_decharge' => $row['sorties_decharge'] ?? 0,
            'camions_uniques' => $row['camions_uniques'] ?? 0,
            'moyenne_poids' => $row['moyenne_poids'] ?? 0,
            'total_poids' => $row['total_poids_marchandises'] ?? 0,
            'surcharges' => $row['surcharges'] ?? 0
        ];
        
        // Calcul du pourcentage de surcharges
        if ($stats['total_sorties'] > 0) {
            $stats['pourcentage_surcharges'] = ($stats['surcharges'] / $stats['total_sorties']) * 100;
        } else {
            $stats['pourcentage_surcharges'] = 0;
        }
        
        // Calcul du ratio charge/décharge
        if ($stats['total_sorties'] > 0) {
            $stats['ratio_charge'] = ($stats['sorties_charge'] / $stats['total_sorties']) * 100;
            $stats['ratio_decharge'] = ($stats['sorties_decharge'] / $stats['total_sorties']) * 100;
        } else {
            $stats['ratio_charge'] = 0;
            $stats['ratio_decharge'] = 0;
        }
    }
    
    // Statistiques par jour de la semaine
    $query_semaine = "
        SELECT 
            DAYNAME(cs.date_sortie) as jour,
            COUNT(*) as nombre,
            SUM(CASE WHEN type_sortie = 'charge' THEN 1 ELSE 0 END) as charge,
            SUM(CASE WHEN type_sortie = 'decharge' THEN 1 ELSE 0 END) as decharge
        FROM camions_sortants cs
        WHERE cs.date_sortie >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DAYNAME(cs.date_sortie), DAYOFWEEK(cs.date_sortie)
        ORDER BY DAYOFWEEK(cs.date_sortie)
    ";
    
    $result_semaine = $conn->query($query_semaine);
    $jours_francais = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche'
    ];
    
    while ($row = $result_semaine->fetch_assoc()) {
        $jour_fr = $jours_francais[$row['jour']] ?? $row['jour'];
        $stats_semaine[] = [
            'jour' => $jour_fr,
            'total' => $row['nombre'],
            'charge' => $row['charge'],
            'decharge' => $row['decharge']
        ];
    }
    
    // Statistiques par mois (derniers 6 mois)
    $query_mois = "
        SELECT 
            DATE_FORMAT(cs.date_sortie, '%Y-%m') as mois,
            DATE_FORMAT(cs.date_sortie, '%M %Y') as mois_nom,
            COUNT(*) as total,
            SUM(CASE WHEN type_sortie = 'charge' THEN 1 ELSE 0 END) as charge,
            SUM(CASE WHEN type_sortie = 'decharge' THEN 1 ELSE 0 END) as decharge,
            SUM(p.poids_total_marchandises) as poids_total
        FROM camions_sortants cs
        LEFT JOIN pesages p ON cs.idEntree = p.idEntree
        WHERE cs.date_sortie >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(cs.date_sortie, '%Y-%m'), DATE_FORMAT(cs.date_sortie, '%M %Y')
        ORDER BY mois DESC
        LIMIT 6
    ";
    
    $result_mois = $conn->query($query_mois);
    $mois_francais = [
        'January' => 'Janvier',
        'February' => 'Février',
        'March' => 'Mars',
        'April' => 'Avril',
        'May' => 'Mai',
        'June' => 'Juin',
        'July' => 'Juillet',
        'August' => 'Août',
        'September' => 'Septembre',
        'October' => 'Octobre',
        'November' => 'Novembre',
        'December' => 'Décembre'
    ];
    
    while ($row = $result_mois->fetch_assoc()) {
        $mois_parts = explode(' ', $row['mois_nom']);
        $mois_en = $mois_parts[0];
        $annee = $mois_parts[1] ?? '';
        $mois_fr = $mois_francais[$mois_en] ?? $mois_en;
        
        $stats_mois[] = [
            'mois' => $row['mois'],
            'mois_nom' => $mois_fr . ' ' . $annee,
            'total' => $row['total'],
            'charge' => $row['charge'],
            'decharge' => $row['decharge'],
            'poids_total' => $row['poids_total'] ?? 0
        ];
    }
    
    // Récupérer les 10 dernières sorties
    $query_recent = "
        SELECT 
            cs.idSortie, cs.idEntree, cs.date_sortie, cs.type_sortie,
            ce.immatriculation, ce.etat,
            tc.nom as type_camion, p.nom as port,
            ps.ptav, ps.ptac, ps.ptra, ps.poids_total_marchandises, ps.surcharge
        FROM camions_sortants cs
        LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON cs.idEntree = ps.idEntree
        ORDER BY cs.date_sortie DESC
        LIMIT 10
    ";
    
    $result_recent = $conn->query($query_recent);
    $sorties_recentes = $result_recent->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les ports les plus actifs
    $query_ports = "
        SELECT 
            p.nom as port,
            COUNT(*) as total_sorties,
            COUNT(DISTINCT ce.idEntree) as camions_uniques
        FROM camions_sortants cs
        LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
        LEFT JOIN port p ON ce.idPort = p.id
        WHERE cs.date_sortie >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND p.nom IS NOT NULL
        GROUP BY p.id, p.nom
        ORDER BY total_sorties DESC
        LIMIT 5
    ";
    
    $result_ports = $conn->query($query_ports);
    $ports_actifs = $result_ports->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les camions les plus fréquents
    $query_camions = "
        SELECT 
            ce.immatriculation,
            COUNT(*) as nombre_sorties,
            GROUP_CONCAT(DISTINCT cs.type_sortie ORDER BY cs.type_sortie) as types
        FROM camions_sortants cs
        LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
        WHERE cs.date_sortie >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY ce.immatriculation
        HAVING COUNT(*) > 1
        ORDER BY nombre_sorties DESC
        LIMIT 5
    ";
    
    $result_camions = $conn->query($query_camions);
    $camions_frequents = $result_camions->fetch_all(MYSQLI_ASSOC);
    
    // Calculer les tendances (comparaison avec période précédente)
    $periode_precedente = '';
    $periode_precedente_debut = '';
    $periode_precedente_fin = '';
    
    switch ($periode) {
        case 'today':
            $periode_precedente_debut = date('Y-m-d', strtotime('-1 day', strtotime($date_debut)));
            $periode_precedente_fin = $periode_precedente_debut;
            break;
        case 'week':
            $periode_precedente_debut = date('Y-m-d', strtotime('-7 days', strtotime($date_debut)));
            $periode_precedente_fin = date('Y-m-d', strtotime('-7 days', strtotime($date_fin)));
            break;
        case 'month':
            $periode_precedente_debut = date('Y-m-01', strtotime('-1 month', strtotime($date_debut)));
            $periode_precedente_fin = date('Y-m-t', strtotime('-1 month', strtotime($date_fin)));
            break;
    }
    
    if ($periode_precedente_debut && $periode_precedente_fin) {
        $query_tendance = "
            SELECT 
                COUNT(*) as total_sorties,
                SUM(p.poids_total_marchandises) as total_poids,
                SUM(CASE WHEN p.surcharge = 1 THEN 1 ELSE 0 END) as surcharges
            FROM camions_sortants cs
            LEFT JOIN pesages p ON cs.idEntree = p.idEntree
            WHERE DATE(cs.date_sortie) BETWEEN ? AND ?
        ";
        
        $stmt_tendance = $conn->prepare($query_tendance);
        $stmt_tendance->bind_param("ss", $periode_precedente_debut, $periode_precedente_fin);
        $stmt_tendance->execute();
        $result_tendance = $stmt_tendance->get_result();
        
        if ($result_tendance->num_rows > 0) {
            $row_tendance = $result_tendance->fetch_assoc();
            $prev_total = $row_tendance['total_sorties'] ?? 0;
            $prev_poids = $row_tendance['total_poids'] ?? 0;
            $prev_surcharges = $row_tendance['surcharges'] ?? 0;
            
            // Calculer les tendances en pourcentage
            if ($prev_total > 0) {
                $tendances['sorties'] = (($stats['total_sorties'] - $prev_total) / $prev_total) * 100;
            }
            if ($prev_poids > 0) {
                $tendances['poids'] = (($stats['total_poids'] - $prev_poids) / $prev_poids) * 100;
            }
            if ($prev_surcharges > 0) {
                $tendances['surcharges'] = (($stats['surcharges'] - $prev_surcharges) / $prev_surcharges) * 100;
            }
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
    error_log("Erreur dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestion des Sorties</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .positive-trend {
            color: #10b981;
        }
        
        .negative-trend {
            color: #ef4444;
        }
        
        .tendance-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .tendance-positive {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .tendance-negative {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .tendance-neutral {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background-color: #e5e7eb;
        }
        
        .progress-bar-fill {
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .period-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid #e5e7eb;
            background: white;
        }
        
        .period-btn:hover {
            background-color: #f9fafb;
        }
        
        .period-btn.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .badge-charge {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-decharge {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-surcharge {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }
        
        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <!-- En-tête du dashboard -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                <div>
                    <p class="text-gray-600">
                        
                        <?php 
                        $periode_labels = [
                            'today' => "Aujourd'hui",
                            'yesterday' => 'Hier',
                            'week' => 'Cette semaine',
                            'month' => 'Ce mois',
                            'year' => 'Cette année',
                            'custom' => 'Période personnalisée'
                        ];
                        
                        ?>
                    </p>
                </div>
                
                <!-- Filtres de période -->
                <div class="mt-4 md:mt-0">
                    <div class="flex flex-wrap gap-2">
                        <a href="index.php?periode=today" 
                           class="period-btn <?php echo $periode == 'today' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day mr-1"></i> Aujourd'hui
                        </a>
                        <a href="index.php?periode=yesterday" 
                           class="period-btn <?php echo $periode == 'yesterday' ? 'active' : ''; ?>">
                            <i class="fas fa-history mr-1"></i> Hier
                        </a>
                        <a href="index.php?periode=week" 
                           class="period-btn <?php echo $periode == 'week' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week mr-1"></i> Semaine
                        </a>
                        <a href="index.php?periode=month" 
                           class="period-btn <?php echo $periode == 'month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar mr-1"></i> Mois
                        </a>
                        <a href="index.php?periode=year" 
                           class="period-btn <?php echo $periode == 'year' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt mr-1"></i> Année
                        </a>
                        
                        <!-- Filtre personnalisé -->
                        <div class="relative">
                            <button id="customPeriodBtn" 
                                    class="period-btn <?php echo $periode == 'custom' ? 'active' : ''; ?>">
                                <i class="fas fa-filter mr-1"></i> Personnalisé
                            </button>
                            
                            <!-- Menu déroulant pour période personnalisée -->
                            <div id="customPeriodMenu" 
                                 class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl z-50 hidden border">
                                <div class="p-4">
                                    <form method="GET" action="index.php" class="space-y-3">
                                        <input type="hidden" name="periode" value="custom">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Date début
                                            </label>
                                            <input type="date" name="date_debut" 
                                                   value="<?php echo safe_html($filters['date_debut']); ?>"
                                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Date fin
                                            </label>
                                            <input type="date" name="date_fin" 
                                                   value="<?php echo safe_html($filters['date_fin']); ?>"
                                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <button type="submit" 
                                                class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">
                                            Appliquer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Avertissement période personnalisée -->
            <?php if ($periode == 'custom' && (empty($date_debut) || empty($date_fin))): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                    <p class="text-yellow-800">
                        Veuillez sélectionner une date de début et une date de fin pour la période personnalisée.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Cartes de statistiques principales -->
        
        
        <!-- Tableaux des dernières sorties et statistiques -->
        <div class="grid grid-cols-1 gap-6 mb-6">
            <!-- Dernières sorties -->
            <div class="glass-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-history mr-2"></i>Dernières sorties
                    </h3>
                    <a href="historiques.php" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Camion</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Poids</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">État</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($sorties_recentes as $sortie): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-xs">
                                    <?php echo date('H:i', strtotime($sortie['date_sortie'])); ?>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fas fa-truck text-gray-400 mr-2"></i>
                                        <span class="text-sm font-medium"><?php echo safe_html($sortie['immatriculation']); ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $sortie['type_sortie'] == 'charge' ? 'badge-charge' : 'badge-decharge'; ?>">
                                        <?php echo $sortie['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé'; ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs">
                                    <?php echo number_format($sortie['poids_total_marchandises'] ?? 0, 0); ?> kg
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <?php if ($sortie['surcharge'] == 1): ?>
                                    <span class="badge-surcharge px-2 py-1 text-xs rounded-full">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Surcharge
                                    </span>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-500">Conforme</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            
        </div>

    </div>
    
    <script>
        // Données pour les graphiques
        const weekData = <?php echo json_encode($stats_semaine); ?>;
        const monthData = <?php echo json_encode(array_reverse($stats_mois)); ?>; // Inverser pour ordre chronologique
        
        // Configuration du graphique par jour de la semaine
        const weekCtx = document.getElementById('weekChart').getContext('2d');
        const weekChart = new Chart(weekCtx, {
            type: 'bar',
            data: {
                labels: weekData.map(item => item.jour),
                datasets: [
                    {
                        label: 'Chargé',
                        data: weekData.map(item => item.charge),
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1
                    },
                    {
                        label: 'Déchargé',
                        data: weekData.map(item => item.decharge),
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Graphique circulaire pour la répartition
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Chargé', 'Déchargé'],
                datasets: [{
                    data: [<?php echo $stats['sorties_charge']; ?>, <?php echo $stats['sorties_decharge']; ?>],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ],
                    borderColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });
        
        // Graphique des statistiques mensuelles
        const monthCtx = document.getElementById('monthChart').getContext('2d');
        const monthChart = new Chart(monthCtx, {
            type: 'line',
            data: {
                labels: monthData.map(item => item.mois_nom),
                datasets: [
                    {
                        label: 'Total des sorties',
                        data: monthData.map(item => item.total),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Poids total (tonnes)',
                        data: monthData.map(item => (item.poids_total / 1000).toFixed(1)),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre de sorties'
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Poids (tonnes)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        
        // Gestion du menu période personnalisée
        document.getElementById('customPeriodBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = document.getElementById('customPeriodMenu');
            menu.classList.toggle('hidden');
        });
        
        // Fermer le menu en cliquant ailleurs
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('customPeriodMenu');
            const btn = document.getElementById('customPeriodBtn');
            
            if (!menu.contains(e.target) && !btn.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
        
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Mettre à jour l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('fr-FR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.innerHTML = `<i class="far fa-clock mr-2"></i>${dateString} - ${timeString}`;
            }
        }
        
        // Créer l'élément d'heure s'il n'existe pas
        const header = document.querySelector('h1');
        if (header) {
            const timeDiv = document.createElement('div');
            timeDiv.id = 'currentTime';
            timeDiv.className = 'text-sm text-gray-500 mt-1';
            header.parentElement.appendChild(timeDiv);
            updateTime();
            setInterval(updateTime, 1000);
        }
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('nav a');
    
    navLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        // Vérifier si le lien correspond à la page actuelle
        if (currentPath.includes(linkPath) && linkPath !== '../../logout.php') {
            link.classList.add('bg-blue-100', 'text-blue-600', 'font-semibold');
            link.classList.remove('hover:bg-gray-100');
        }
    });
});
</script>
</body>
</html>