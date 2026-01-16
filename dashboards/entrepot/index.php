<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentEntrepot') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les messages
$message = '';
$message_type = '';

// Récupérer les statistiques pour le dashboard
$statistiques = [
    'camions_vides' => 0,
    'camions_charges_a_decharger' => 0,
    'chargements_aujourdhui' => 0,
    'dechargements_aujourdhui' => 0,
    'total_camions_mois' => 0,
    'camions_par_etat' => []
];

try {
    // Nombre de camions vides disponibles
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM camions_entrants ce
        WHERE ce.etat = 'Vide'
        AND ce.idEntree NOT IN (
            SELECT idEntree FROM chargement_camions
        )
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $statistiques['camions_vides'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Nombre de camions chargés à décharger
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM camions_entrants ce
        WHERE ce.etat = 'Chargé'
        AND ce.idEntree NOT IN (
            SELECT idEntree FROM dechargements_camions
        )
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $statistiques['camions_charges_a_decharger'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Chargements aujourd'hui
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM chargement_camions 
        WHERE DATE(date_chargement) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $statistiques['chargements_aujourdhui'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Déchargements aujourd'hui
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM dechargements_camions
        WHERE DATE(date_dechargement) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $statistiques['dechargements_aujourdhui'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Total camions ce mois
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM camions_entrants 
        WHERE MONTH(date_entree) = MONTH(CURDATE())
        AND YEAR(date_entree) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $statistiques['total_camions_mois'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Distribution par état
    $stmt = $conn->prepare("
        SELECT etat, COUNT(*) as count 
        FROM camions_entrants 
        GROUP BY etat
        ORDER BY etat
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $statistiques['camions_par_etat'][] = $row;
    }
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des statistiques: " . $e->getMessage();
    $message_type = "error";
    error_log("Erreur statistiques: " . $e->getMessage());
}

// Récupérer les activités récentes
$activites_recentes = [];
try {
    // Récupérer les 10 dernières activités (chargements et déchargements)
    $stmt = $conn->prepare("
        (SELECT 
            'chargement' as type,
            cc.date_chargement as date,
            ce.immatriculation,
            ce.etat,
            CONCAT('Chargement - ', GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ')) as description,
            cc.note_chargement as note
        FROM chargement_camions cc
        INNER JOIN camions_entrants ce ON cc.idEntree = ce.idEntree
        LEFT JOIN marchandise_chargement_camion mcc ON cc.idChargement = mcc.idChargement
        LEFT JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
        GROUP BY cc.idChargement
        ORDER BY cc.date_chargement DESC
        LIMIT 5)
        
        UNION ALL
        
        (SELECT 
            'dechargement' as type,
            d.date_dechargement as date,
            ce.immatriculation,
            ce.etat,
            CONCAT('Déchargement - ', GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ')) as description,
            d.note_dechargement as note
        FROM dechargements_camions d
        INNER JOIN camions_entrants ce ON d.idEntree = ce.idEntree
        LEFT JOIN marchandise_dechargement_camion mdc ON d.idDechargement = mdc.idDechargement
        LEFT JOIN type_marchandise tm ON mdc.idTypeMarchandise = tm.id
        GROUP BY d.idDechargement
        ORDER BY d.date_dechargement DESC
        LIMIT 5)
        
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $activites_recentes = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des activités récentes: " . $e->getMessage();
    $message_type = "error";
    error_log("Erreur activités récentes: " . $e->getMessage());
}

// Récupérer les camions nécessitant attention
$camions_attention = [];
try {
    // Camions chargés depuis plus de 24h non déchargés
    $stmt = $conn->prepare("
        SELECT 
            ce.idEntree,
            ce.immatriculation,
            ce.etat,
            ce.date_entree,
            cc.date_chargement,
            TIMESTAMPDIFF(HOUR, cc.date_chargement, NOW()) as heures_attente,
            tc.nom as type_camion
        FROM camions_entrants ce
        INNER JOIN chargement_camions cc ON ce.idEntree = cc.idEntree
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        WHERE ce.etat = 'Chargé'
        AND ce.idEntree NOT IN (
            SELECT idEntree FROM dechargements_camions
        )
        AND TIMESTAMPDIFF(HOUR, cc.date_chargement, NOW()) > 24
        ORDER BY cc.date_chargement ASC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_attention = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions nécessitant attention: " . $e->getMessage();
    $message_type = "error";
    error_log("Erreur camions attention: " . $e->getMessage());
}

// Récupérer les statistiques mensuelles pour le graphique
$stats_mensuelles = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(date_chargement, '%Y-%m') as mois,
            COUNT(*) as chargements,
            (SELECT COUNT(*) FROM dechargements_camions d 
             WHERE DATE_FORMAT(d.date_dechargement, '%Y-%m') = DATE_FORMAT(cc.date_chargement, '%Y-%m')) as dechargements
        FROM chargement_camions cc
        WHERE date_chargement >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_chargement, '%Y-%m')
        ORDER BY mois DESC
        LIMIT 6
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats_mensuelles = $result->fetch_all(MYSQLI_ASSOC);
    
    // Réorganiser du plus ancien au plus récent
    $stats_mensuelles = array_reverse($stats_mensuelles);
    
} catch (Exception $e) {
    error_log("Erreur stats mensuelles: " . $e->getMessage());
}

// Récupérer les types de marchandises les plus fréquents
$marchandises_frequentes = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            tm.nom,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM marchandise_chargement_camion), 1) as pourcentage
        FROM marchandise_chargement_camion mcc
        INNER JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
        GROUP BY tm.nom
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $marchandises_frequentes = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Erreur marchandises fréquentes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Agent Entrepôt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .scrollable-section {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-vide {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-charge {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-decharge {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-attente {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .quick-action-btn {
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .activity-item {
            border-left: 4px solid transparent;
            padding-left: 12px;
            margin-bottom: 12px;
        }
        
        .activity-chargement {
            border-left-color: #3b82f6;
        }
        
        .activity-dechargement {
            border-left-color: #10b981;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        
        <?php if ($message): ?>
            <div class="mb-6 animate-fade-in">
                <div class="<?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> 
                            border px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-3"></i>
                        <span><?php echo safe_html($message); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Camions vides -->
            <div class="glass-card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-600">Camions vides</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $statistiques['camions_vides']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-truck text-blue-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Disponibles pour chargement
                </p>
                <a href="chargement.php" class="inline-block mt-4 text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-arrow-right mr-1"></i> Voir tous
                </a>
            </div>
            
            <!-- Camions à décharger -->
            <div class="glass-card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-600">À décharger</p>
                        <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo $statistiques['camions_charges_a_decharger']; ?></p>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-full">
                        <i class="fas fa-truck-loading text-orange-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    En attente de déchargement
                </p>
                <a href="dechargement.php" class="inline-block mt-4 text-orange-600 hover:text-orange-800 text-sm font-medium">
                    <i class="fas fa-arrow-right mr-1"></i> Voir tous
                </a>
            </div>
            
            <!-- Chargements aujourd'hui -->
            <div class="glass-card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-600">Chargements aujourd'hui</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $statistiques['chargements_aujourdhui']; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-upload text-green-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Effectués ce jour
                </p>
                <div class="mt-4">
                    <span class="badge-success px-3 py-1 rounded-full text-xs font-bold">
                        <i class="fas fa-calendar-day mr-1"></i> <?php echo date('d/m/Y'); ?>
                    </span>
                </div>
            </div>
            
            <!-- Déchargements aujourd'hui -->
            <div class="glass-card p-6 stat-card">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-600">Déchargements aujourd'hui</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $statistiques['dechargements_aujourdhui']; ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-download text-purple-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Effectués ce jour
                </p>
                <div class="mt-4">
                    <span class="badge-info px-3 py-1 rounded-full text-xs font-bold">
                        <i class="fas fa-calendar-day mr-1"></i> <?php echo date('d/m/Y'); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Deuxième ligne : Graphiques et activités -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Graphique des activités -->
            <div class="glass-card p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-chart-line mr-2"></i>Activités mensuelles
                </h2>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
            
            <!-- Activités récentes -->
            <div class="glass-card p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-history mr-2"></i>Activités Récentes
                </h2>
                <div class="scrollable-section">
                    <?php if (!empty($activites_recentes)): ?>
                        <div class="space-y-4">
                            <?php foreach ($activites_recentes as $activite): ?>
                            <div class="activity-item <?php echo $activite['type'] === 'chargement' ? 'activity-chargement' : 'activity-dechargement'; ?>">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            <?php echo safe_html($activite['immatriculation']); ?>
                                            <span class="ml-2 text-xs font-bold <?php echo $activite['type'] === 'chargement' ? 'text-blue-600' : 'text-green-600'; ?>">
                                                <?php echo $activite['type'] === 'chargement' ? 'CHARGEMENT' : 'DÉCHARGEMENT'; ?>
                                            </span>
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?php echo safe_html($activite['description']); ?>
                                        </p>
                                        <?php if (!empty($activite['note'])): ?>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-sticky-note mr-1"></i>
                                            <?php echo substr(safe_html($activite['note']), 0, 60); ?>
                                            <?php echo strlen($activite['note']) > 60 ? '...' : ''; ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('H:i', strtotime($activite['date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('d/m', strtotime($activite['date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-4"></i>
                            <p>Aucune activité récente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialisation des graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique des activités mensuelles
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            
            // Données du graphique
            const months = <?php echo json_encode(array_column($stats_mensuelles, 'mois')); ?>;
            const chargements = <?php echo json_encode(array_column($stats_mensuelles, 'chargements')); ?>;
            const dechargements = <?php echo json_encode(array_column($stats_mensuelles, 'dechargements')); ?>;
            
            // Formater les mois
            const formattedMonths = months.map(month => {
                const [year, monthNum] = month.split('-');
                const monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 
                                   'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                return `${monthNames[parseInt(monthNum) - 1]} ${year}`;
            });
            
            // Créer le graphique
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: formattedMonths,
                    datasets: [
                        {
                            label: 'Chargements',
                            data: chargements,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1
                        },
                        {
                            label: 'Déchargements',
                            data: dechargements,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    }
                }
            });
            
            // Animation des cartes de statistiques
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>