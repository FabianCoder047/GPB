<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentBascule') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les périodes
$periode = $_GET['periode'] ?? 'today'; // today, week, month, year
$date_reference = $_GET['date_reference'] ?? date('Y-m-d');

// Déterminer les dates de début et fin selon la période
$date_debut = '';
$date_fin = '';

switch ($periode) {
    case 'today':
        $date_debut = $date_reference;
        $date_fin = $date_reference;
        break;
    case 'week':
        $date_debut = date('Y-m-d', strtotime('monday this week', strtotime($date_reference)));
        $date_fin = date('Y-m-d', strtotime('sunday this week', strtotime($date_reference)));
        break;
    case 'month':
        $date_debut = date('Y-m-01', strtotime($date_reference));
        $date_fin = date('Y-m-t', strtotime($date_reference));
        break;
    case 'year':
        $date_debut = date('Y-01-01', strtotime($date_reference));
        $date_fin = date('Y-12-31', strtotime($date_reference));
        break;
}

// Récupérer les statistiques générales
$stats = [];
$graph_data = [];

try {
    // Statistiques pour la période sélectionnée
    $query_stats = "
        SELECT 
            COUNT(*) as total_pesages,
            SUM(CASE WHEN ps.surcharge = 1 THEN 1 ELSE 0 END) as total_surcharge,
            SUM(CASE WHEN ps.surcharge = 0 THEN 1 ELSE 0 END) as total_conforme,
            AVG(ps.ptav + ps.poids_total_marchandises) as poids_moyen,
            SUM(ps.ptav + ps.poids_total_marchandises) as poids_total,
            COUNT(DISTINCT ce.idTypeCamion) as types_camions_differents,
            COUNT(DISTINCT ps.agent_bascule) as agents_actifs
        FROM pesages ps
        INNER JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        WHERE DATE(ps.date_pesage) BETWEEN ? AND ?
    ";
    
    $stmt = $conn->prepare($query_stats);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Statistiques par type de camion
    $query_camions = "
        SELECT 
            tc.nom as type_camion,
            COUNT(*) as nombre,
            AVG(ps.ptav + ps.poids_total_marchandises) as poids_moyen,
            SUM(CASE WHEN ps.surcharge = 1 THEN 1 ELSE 0 END) as surcharges
        FROM pesages ps
        INNER JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        INNER JOIN type_camion tc ON ce.idTypeCamion = tc.id
        WHERE DATE(ps.date_pesage) BETWEEN ? AND ?
        GROUP BY tc.id
        ORDER BY nombre DESC
        LIMIT 5
    ";
    
    $stmt = $conn->prepare($query_camions);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats_camions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Statistiques par état (Vide/Chargé)
    $query_etats = "
        SELECT 
            ce.etat,
            COUNT(*) as nombre,
            AVG(ps.poids_total_marchandises) as poids_moyen_marchandises
        FROM pesages ps
        INNER JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        WHERE DATE(ps.date_pesage) BETWEEN ? AND ?
        GROUP BY ce.etat
    ";
    
    $stmt = $conn->prepare($query_etats);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats_etats = $result->fetch_all(MYSQLI_ASSOC);
    
    // Statistiques horaires (pour le graphique)
    $query_horaires = "
        SELECT 
            HOUR(ps.date_pesage) as heure,
            COUNT(*) as nombre_pesages,
            AVG(ps.ptav + ps.poids_total_marchandises) as poids_moyen
        FROM pesages ps
        WHERE DATE(ps.date_pesage) BETWEEN ? AND ?
        GROUP BY HOUR(ps.date_pesage)
        ORDER BY heure
    ";
    
    $stmt = $conn->prepare($query_horaires);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats_horaires = $result->fetch_all(MYSQLI_ASSOC);
    
    // Préparer les données pour le graphique
    $graph_hours = [];
    $graph_counts = [];
    for ($i = 0; $i < 24; $i++) {
        $graph_hours[] = sprintf('%02d:00', $i);
        $graph_counts[] = 0;
    }
    
    foreach ($stats_horaires as $horaire) {
        $hour = (int)$horaire['heure'];
        if ($hour >= 0 && $hour < 24) {
            $graph_counts[$hour] = (int)$horaire['nombre_pesages'];
        }
    }
    
    $graph_data = [
        'labels' => $graph_hours,
        'data' => $graph_counts
    ];
    
    // Derniers pesages (10 derniers)
    $query_last = "
        SELECT 
            ce.immatriculation,
            ce.etat,
            ps.date_pesage,
            (ps.ptav + ps.poids_total_marchandises) as poids_total,
            ps.surcharge,
            tc.nom as type_camion,
            ps.agent_bascule
        FROM pesages ps
        INNER JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        WHERE DATE(ps.date_pesage) BETWEEN ? AND ?
        ORDER BY ps.date_pesage DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query_last);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $last_pesages = $result->fetch_all(MYSQLI_ASSOC);
    
    // Top 5 des camions avec surcharge
    $query_top_surcharge = "
        SELECT 
            ce.immatriculation,
            (ps.ptav + ps.poids_total_marchandises) as poids_total,
            ps.ptac as ptac_max,
            ((ps.ptav + ps.poids_total_marchandises) - ps.ptac) as surcharge_kg,
            ps.date_pesage,
            ps.note_surcharge
        FROM pesages ps
        INNER JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        WHERE ps.surcharge = 1 AND DATE(ps.date_pesage) BETWEEN ? AND ?
        ORDER BY ((ps.ptav + ps.poids_total_marchandises) - ps.ptac) DESC
        LIMIT 5
    ";
    
    $stmt = $conn->prepare($query_top_surcharge);
    $stmt->bind_param("ss", $date_debut, $date_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $top_surcharges = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des statistiques: " . $e->getMessage();
}

// Formater les statistiques pour l'affichage
$stats['total_pesages'] = $stats['total_pesages'] ?? 0;
$stats['total_surcharge'] = $stats['total_surcharge'] ?? 0;
$stats['total_conforme'] = $stats['total_conforme'] ?? 0;
$stats['poids_moyen'] = $stats['poids_moyen'] ?? 0;
$stats['poids_total'] = $stats['poids_total'] ?? 0;
$stats['types_camions_differents'] = $stats['types_camions_differents'] ?? 0;
$stats['agents_actifs'] = $stats['agents_actifs'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Agent Bascule</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .stat-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <!-- En-tête du tableau de bord -->
        <div class="mb-8">
        
            <!-- Indicateurs de période -->
            <div class="flex flex-wrap gap-2 mt-6">
                <a href="index.php?periode=today" 
                   class="px-4 py-2 rounded-full text-sm font-medium <?php echo $periode == 'today' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                    Aujourd'hui
                </a>
                <a href="index.php?periode=week" 
                   class="px-4 py-2 rounded-full text-sm font-medium <?php echo $periode == 'week' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                    7 derniers jours
                </a>
                <a href="index.php?periode=month" 
                   class="px-4 py-2 rounded-full text-sm font-medium <?php echo $periode == 'month' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                    Ce mois
                </a>
                <a href="index.php?periode=year" 
                   class="px-4 py-2 rounded-full text-sm font-medium <?php echo $periode == 'year' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                    Cette année
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?php echo safe_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Carte 1: Total Pesages -->
            <div class="glass-card card-hover p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Total Pesages</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo number_format($stats['total_pesages']); ?></p>
                    </div>
                    <div class="stat-card-icon bg-blue-100 text-blue-600">
                        <i class="fas fa-weight-scale"></i>
                    </div>
                </div>
                <div class="progress-bar bg-blue-100">
                    <div class="bg-blue-500 h-full" style="width: <?php echo min(100, $stats['total_pesages']); ?>%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <?php echo $periode == 'today' ? 'Aujourd\'hui' : 'Période sélectionnée'; ?>
                </p>
            </div>
            
            <!-- Carte 2: Conformes -->
            <div class="glass-card card-hover p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Camions Conformes</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($stats['total_conforme']); ?></p>
                    </div>
                    <div class="stat-card-icon bg-green-100 text-green-600">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="progress-bar bg-green-100">
                    <?php if ($stats['total_pesages'] > 0): ?>
                    <div class="bg-green-500 h-full" style="width: <?php echo ($stats['total_conforme'] / $stats['total_pesages']) * 100; ?>%"></div>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    <?php if ($stats['total_pesages'] > 0): ?>
                    <?php echo round(($stats['total_conforme'] / $stats['total_pesages']) * 100, 1); ?>% du total
                    <?php else: ?>
                    Aucun pesage
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Carte 3: Surcharges -->
            <div class="glass-card card-hover p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Surcharges</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?php echo number_format($stats['total_surcharge']); ?></p>
                    </div>
                    <div class="stat-card-icon bg-red-100 text-red-600">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="progress-bar bg-red-100">
                    <?php if ($stats['total_pesages'] > 0): ?>
                    <div class="bg-red-500 h-full" style="width: <?php echo ($stats['total_surcharge'] / $stats['total_pesages']) * 100; ?>%"></div>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    <?php if ($stats['total_pesages'] > 0): ?>
                    <?php echo round(($stats['total_surcharge'] / $stats['total_pesages']) * 100, 1); ?>% du total
                    <?php else: ?>
                    Aucun pesage
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Carte 4: Poids Moyen -->
            <div class="glass-card card-hover p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Poids Moyen</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo number_format($stats['poids_moyen'] ?? 0, 2); ?> kg</p>
                    </div>
                    <div class="stat-card-icon bg-purple-100 text-purple-600">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <div class="text-left">
                        <p class="text-xs text-gray-500">Poids Total</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo number_format($stats['poids_total'] ?? 0, 0); ?> kg</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Types Camions</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo $stats['types_camions_differents']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deuxième ligne : Derniers pesages et Top surcharges -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Derniers pesages -->
            <div class="glass-card card-hover p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-history mr-2"></i>Derniers Pesages
                    </h2>
                    <a href="historiques.php" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>Voir tout
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Camion</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Heure</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($last_pesages as $pesage): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-truck text-blue-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($pesage['immatriculation']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($pesage['type_camion'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('H:i', strtotime($pesage['date_pesage'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('d/m', strtotime($pesage['date_pesage'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($pesage['poids_total'], 2); ?> kg
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo safe_html($pesage['etat']); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $pesage['surcharge'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo $pesage['surcharge'] ? 'Surcharge' : 'Conforme'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($last_pesages)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                    Aucun pesage récent
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Top surcharges -->
            <div class="glass-card card-hover p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Top 5 Surcharges
                    </h2>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-weight-scale mr-1"></i>Plus importantes
                    </div>
                </div>
                
                <div class="space-y-4">
                    <?php foreach ($top_surcharges as $index => $surcharge): ?>
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="flex items-center">
                                    <span class="w-6 h-6 flex items-center justify-center bg-red-100 text-red-800 rounded-full text-xs font-bold mr-3">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <span class="font-bold text-gray-800"><?php echo safe_html($surcharge['immatriculation']); ?></span>
                                </div>
                                <p class="text-xs text-gray-600 ml-9 mt-1">
                                    <?php echo date('d/m/Y H:i', strtotime($surcharge['date_pesage'])); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-bold rounded">
                                +<?php echo number_format($surcharge['surcharge_kg'], 2); ?> kg
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mt-3">
                            <div>
                                <p class="text-xs text-gray-500">Poids total</p>
                                <p class="text-sm font-bold"><?php echo number_format($surcharge['poids_total'], 2); ?> kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">PTAC max</p>
                                <p class="text-sm font-bold"><?php echo number_format($surcharge['ptac_max'], 2); ?> kg</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($surcharge['note_surcharge'])): ?>
                        <div class="mt-3 pt-3 border-t border-red-200">
                            <p class="text-xs text-gray-600">
                                <i class="fas fa-sticky-note mr-1"></i>
                                <?php echo substr(safe_html($surcharge['note_surcharge']), 0, 100); ?>
                                <?php echo strlen($surcharge['note_surcharge']) > 100 ? '...' : ''; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($top_surcharges)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-check-circle text-3xl text-green-500 mb-3"></i>
                        <p>Aucune surcharge enregistrée</p>
                        <p class="text-sm mt-2">Tous les camions sont conformes !</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour changer la période
        function changePeriode(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('periode', value);
            window.location.href = url.toString();
        }
        
        // Initialiser le graphique Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            
            const activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($graph_data['labels']); ?>,
                    datasets: [{
                        label: 'Nombre de Pesages',
                        data: <?php echo json_encode($graph_data['data']); ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Nombre de pesages'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            title: {
                                display: true,
                                text: 'Heures de la journée'
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'nearest'
                    }
                }
            });
            
            // Mettre à jour automatiquement le tableau de bord toutes les 5 minutes
            setInterval(() => {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        // Pour une mise à jour complète, on recharge la page
                        // Dans une version plus avancée, on pourrait faire des requêtes AJAX
                        const currentTime = new Date().toLocaleTimeString();
                        console.log(`Dashboard mis à jour à ${currentTime}`);
                    })
                    .catch(error => console.error('Erreur de mise à jour:', error));
            }, 300000); // 5 minutes
            
            // Animation pour les cartes au chargement
            const cards = document.querySelectorAll('.card-hover');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Rafraîchir les données en cliquant sur les indicateurs
        document.querySelectorAll('.glass-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.tagName === 'A' || e.target.closest('a')) return;
                
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
            });
        });
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