<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeBateau') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Date actuelle
$aujourdhui = date('Y-m-d');
$mois_courant = date('Y-m');
$semaine_courante = date('Y-W');

// Récupérer les statistiques
$stats = [
    'total_aujourdhui' => 0,
    'charges_aujourdhui' => 0,
    'vides_aujourdhui' => 0,
    'total_semaine' => 0,
    'total_mois' => 0,
    'moyenne_quotidienne' => 0,
    'top_types' => [],
    'activite_recente' => []
];

try {
    // Bateaux d'aujourd'hui
    $query = "SELECT COUNT(*) as total,
                     SUM(CASE WHEN etat = 'chargé' THEN 1 ELSE 0 END) as charges,
                     SUM(CASE WHEN etat = 'vide' THEN 1 ELSE 0 END) as vides
              FROM bateau_entrant 
              WHERE DATE(date_entree) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_aujourdhui'] = $row['total'] ?? 0;
        $stats['charges_aujourdhui'] = $row['charges'] ?? 0;
        $stats['vides_aujourdhui'] = $row['vides'] ?? 0;
    }
    $stmt->close();

    // Total de la semaine
    $query = "SELECT COUNT(*) as total 
              FROM bateau_entrant 
              WHERE YEARWEEK(date_entree, 1) = YEARWEEK(CURDATE(), 1)";
    $result = $conn->query($query);
    if ($row = $result->fetch_assoc()) {
        $stats['total_semaine'] = $row['total'] ?? 0;
    }

    // Total du mois
    $query = "SELECT COUNT(*) as total 
              FROM bateau_entrant 
              WHERE YEAR(date_entree) = YEAR(CURDATE()) 
              AND MONTH(date_entree) = MONTH(CURDATE())";
    $result = $conn->query($query);
    if ($row = $result->fetch_assoc()) {
        $stats['total_mois'] = $row['total'] ?? 0;
    }

    // Moyenne quotidienne sur les 30 derniers jours
    $query = "SELECT COUNT(*) as total, 
                     COUNT(DISTINCT DATE(date_entree)) as jours
              FROM bateau_entrant 
              WHERE date_entree >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $result = $conn->query($query);
    if ($row = $result->fetch_assoc()) {
        $total_30jours = $row['total'] ?? 0;
        $jours_actifs = max($row['jours'] ?? 1, 1);
        $stats['moyenne_quotidienne'] = round($total_30jours / $jours_actifs, 1);
    }

    // Top 5 types de bateaux du mois
    $query = "SELECT tb.nom, COUNT(*) as count
              FROM bateau_entrant be
              JOIN type_bateau tb ON be.id_type_bateau = tb.id
              WHERE YEAR(be.date_entree) = YEAR(CURDATE()) 
              AND MONTH(be.date_entree) = MONTH(CURDATE())
              GROUP BY tb.nom
              ORDER BY count DESC
              LIMIT 5";
    $result = $conn->query($query);
    $stats['top_types'] = $result->fetch_all(MYSQLI_ASSOC);

    // Activité récente (derniers bateaux)
    $query = "SELECT be.*, tb.nom as type_bateau, p.nom as port_nom
              FROM bateau_entrant be
              LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
              LEFT JOIN port p ON be.id_port = p.id
              ORDER BY be.date_entree DESC
              LIMIT 10";
    $result = $conn->query($query);
    $stats['activite_recente'] = $result->fetch_all(MYSQLI_ASSOC);

    // Statistiques pour le graphique (7 derniers jours)
    $query = "SELECT DATE(date_entree) as date, 
                     COUNT(*) as total,
                     SUM(CASE WHEN etat = 'chargé' THEN 1 ELSE 0 END) as charges,
                     SUM(CASE WHEN etat = 'vide' THEN 1 ELSE 0 END) as vides
              FROM bateau_entrant
              WHERE date_entree >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(date_entree)
              ORDER BY date";
    $result = $conn->query($query);
    $graph_data = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = "Erreur lors du chargement des statistiques: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Enregistreur Entrée Bateaux</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            border-top: 4px solid;
            overflow: hidden;
        }
        
        .stat-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        
        .percentage-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .progress-bar {
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .activity-item {
            border-bottom: 1px solid #f3f4f6;
            padding: 0.75rem 0;
            transition: background-color 0.2s;
        }
        
        .activity-item:hover {
            background-color: #f9fafb;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .dot-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        
        .grid-item {
            grid-column: span 1;
        }
        
        @media (min-width: 768px) {
            .grid-item-large {
                grid-column: span 2;
            }
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
        
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo safe_html($error); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="dashboard-grid mb-8">
            <!-- Carte 1: Bateaux aujourd'hui -->
            <div class="dashboard-card stat-card p-6" style="border-top-color: #3b82f6;">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-card-icon" style="background-color: rgba(59, 130, 246, 0.1);">
                            <i class="fas fa-ship text-xl" style="color: #3b82f6;"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Bateaux aujourd'hui
                        </h3>
                        <div class="mt-2 flex items-baseline">
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo $stats['total_aujourdhui']; ?>
                            </p>
                            <?php if ($stats['total_aujourdhui'] > 0): ?>
                                <span class="percentage-badge ml-2 bg-green-100 text-green-800">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    <?php echo min($stats['total_aujourdhui'] * 10, 100); ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800">
                            <?php echo date('d/m'); ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Chargés: <?php echo $stats['charges_aujourdhui']; ?></span>
                        <span>Vides: <?php echo $stats['vides_aujourdhui']; ?></span>
                    </div>
                    <?php if ($stats['total_aujourdhui'] > 0): ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(($stats['charges_aujourdhui'] / $stats['total_aujourdhui']) * 100, 100); ?>%; background-color: #10b981;"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Carte 2: Bateaux cette semaine -->
            <div class="dashboard-card stat-card p-6" style="border-top-color: #8b5cf6;">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-card-icon" style="background-color: rgba(139, 92, 246, 0.1);">
                            <i class="fas fa-calendar-week text-xl" style="color: #8b5cf6;"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Cette semaine
                        </h3>
                        <div class="mt-2">
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo $stats['total_semaine']; ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800">
                            Sem. <?php echo date('W'); ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-chart-line mr-1"></i>
                        <?php 
                        $evolution_semaine = $stats['total_semaine'] > 0 ? 
                            round(($stats['total_semaine'] / max($stats['moyenne_quotidienne'] * 7, 1)) * 100, 0) : 0;
                        echo $evolution_semaine; ?>% de la moyenne
                    </p>
                </div>
            </div>
            
            <!-- Carte 3: Bateaux ce mois -->
            <div class="dashboard-card stat-card p-6" style="border-top-color: #10b981;">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-card-icon" style="background-color: rgba(16, 185, 129, 0.1);">
                            <i class="fas fa-calendar-alt text-xl" style="color: #10b981;"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Ce mois-ci
                        </h3>
                        <div class="mt-2">
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo $stats['total_mois']; ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-green-100 text-green-800">
                            <?php echo date('F'); ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-trending-up mr-1"></i>
                        <?php 
                        $jours_ecoules = date('j');
                        $objectif_mensuel = $stats['moyenne_quotidienne'] * 30;
                        $pourcentage_mois = $objectif_mensuel > 0 ? 
                            round(($stats['total_mois'] / $objectif_mensuel) * 100, 0) : 0;
                        echo $pourcentage_mois; ?>% de l'objectif mensuel
                    </p>
                </div>
            </div>
            
            <!-- Carte 4: Moyenne quotidienne -->
            <div class="dashboard-card stat-card p-6" style="border-top-color: #f59e0b;">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-card-icon" style="background-color: rgba(245, 158, 11, 0.1);">
                            <i class="fas fa-chart-bar text-xl" style="color: #f59e0b;"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Moyenne/jour
                        </h3>
                        <div class="mt-2">
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo $stats['moyenne_quotidienne']; ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800">
                            30 jours
                        </span>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-history mr-1"></i>
                        Sur les 30 derniers jours
                    </p>
                </div>
            </div>
        </div>
        
        
        <!-- Activité récente et Alertes -->
        <div class="grid grid-cols-1 gap-6 mb-8">
            <!-- Activité récente -->
            <div class="lg:col-span-2 dashboard-card p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-history mr-2"></i>Activité récente
                    </h2>
                    <a href="historiques.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-external-link-alt mr-1"></i>Voir tout
                    </a>
                </div>
                
                <div class="overflow-y-auto" style="max-height: 400px;">
                    <?php if (!empty($stats['activite_recente'])): ?>
                        <div class="space-y-0">
                            <?php foreach ($stats['activite_recente'] as $bateau): ?>
                                <div class="activity-item px-2 py-3 rounded-lg hover:bg-gray-50">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center 
                                                    <?php echo $bateau['etat'] == 'chargé' ? 'bg-green-100' : 'bg-gray-100'; ?>">
                                            <i class="fas fa-ship <?php echo $bateau['etat'] == 'chargé' ? 'text-green-600' : 'text-gray-600'; ?>"></i>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <div class="flex justify-between">
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?php echo safe_html($bateau['nom_navire']); ?>
                                                </p>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo date('H:i', strtotime($bateau['date_entree'])); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-500 mt-1">
                                                <span class="mr-3">
                                                    <i class="fas fa-user-tie mr-1"></i>
                                                    <?php echo safe_html($bateau['nom_capitaine']); ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-anchor mr-1"></i>
                                                    <?php echo safe_html($bateau['port_nom'] ?? 'N/A'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-ship text-3xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Aucune activité récente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            
        </div>
    </div>
    
    <script>
            // Actualiser l'heure en temps réel
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('fr-FR');
                const dateString = now.toLocaleDateString('fr-FR');
                document.querySelector('.text-blue-800').textContent = `${dateString} - ${timeString}`;
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