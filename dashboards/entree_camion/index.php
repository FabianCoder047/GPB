<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeCamion') {
    header("Location: ../../login.php");
    exit();
}

// Récupérer les statistiques
$stats = [
    'total_aujourdhui' => 0,
    'total_mois' => 0,
    'charge_aujourdhui' => 0,
    'vide_aujourdhui' => 0,
    'pesage_aujourdhui' => 0,
    'en_attente' => 0,
    'total_tous' => 0
];

try {
    // Statistiques pour aujourd'hui
    $date_aujourdhui = date('Y-m-d');
    $date_debut_mois = date('Y-m-01');
    
    // Total des camions aujourd'hui
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM camions_entrants WHERE DATE(date_entree) = ?");
    $stmt->bind_param("s", $date_aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_aujourdhui'] = $result->fetch_assoc()['total'];
    
    // Total des camions ce mois
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM camions_entrants WHERE DATE(date_entree) >= ?");
    $stmt->bind_param("s", $date_debut_mois);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_mois'] = $result->fetch_assoc()['total'];
    
    // Camions chargés aujourd'hui
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM camions_entrants WHERE DATE(date_entree) = ? AND etat = 'Chargé'");
    $stmt->bind_param("s", $date_aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['charge_aujourdhui'] = $result->fetch_assoc()['total'];
    
    // Camions vides aujourd'hui
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM camions_entrants WHERE DATE(date_entree) = ? AND etat = 'Vide'");
    $stmt->bind_param("s", $date_aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['vide_aujourdhui'] = $result->fetch_assoc()['total'];
    
    // Camions pour pesage aujourd'hui
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM camions_entrants WHERE DATE(date_entree) = ? AND raison = 'Pesage'");
    $stmt->bind_param("s", $date_aujourdhui);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pesage_aujourdhui'] = $result->fetch_assoc()['total'];
    
    // Total général
    $result = $conn->query("SELECT COUNT(*) as total FROM camions_entrants");
    $stats['total_tous'] = $result->fetch_assoc()['total'];
    
    // Récupérer les 5 derniers camions enregistrés
    $query = "SELECT ce.*, tc.nom as type_camion, p.nom as port 
              FROM camions_entrants ce
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              LEFT JOIN port p ON ce.idPort = p.id
              ORDER BY ce.date_entree DESC
              LIMIT 5";
    $result = $conn->query($query);
    $derniers_camions = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des statistiques: " . $e->getMessage();
    $derniers_camions = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Entrée Camions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .action-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            transform: scale(1.05);
            border-color: #3b82f6;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="mb-10">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-bar mr-2"></i>Statistiques du Jour
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1: Total aujourd'hui -->
                <div class="stat-card bg-white shadow rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Camions aujourd'hui</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['total_aujourdhui']; ?></p>
                            <div class="mt-2 flex items-center text-sm text-green-600">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span>+<?php echo $stats['total_aujourdhui']; ?> ce jour</span>
                            </div>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-truck text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>Nombre total de camions entrés aujourd'hui
                    </div>
                </div>
                
                <!-- Card 2: Camions chargés -->
                <div class="stat-card bg-white shadow rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Camions chargés</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['charge_aujourdhui']; ?></p>
                            <div class="mt-2">
                                <?php if ($stats['total_aujourdhui'] > 0): ?>
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" 
                                                 style="width: <?php echo ($stats['charge_aujourdhui'] / $stats['total_aujourdhui']) * 100; ?>%">
                                            </div>
                                        </div>
                                        <span class="ml-2 text-sm text-gray-600">
                                            <?php echo $stats['total_aujourdhui'] > 0 ? round(($stats['charge_aujourdhui'] / $stats['total_aujourdhui']) * 100, 1) : 0; ?>%
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-gray-400">Aucun camion aujourd'hui</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-box text-green-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Card 3: Camions vides -->
                <div class="stat-card bg-white shadow rounded-lg p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Camions vides</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['vide_aujourdhui']; ?></p>
                            <div class="mt-2">
                                <?php if ($stats['total_aujourdhui'] > 0): ?>
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-500 h-2 rounded-full" 
                                                 style="width: <?php echo ($stats['vide_aujourdhui'] / $stats['total_aujourdhui']) * 100; ?>%">
                                            </div>
                                        </div>
                                        <span class="ml-2 text-sm text-gray-600">
                                            <?php echo $stats['total_aujourdhui'] > 0 ? round(($stats['vide_aujourdhui'] / $stats['total_aujourdhui']) * 100, 1) : 0; ?>%
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-gray-400">Aucun camion aujourd'hui</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-box-open text-yellow-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Card 4: Pour pesage -->
                <div class="stat-card bg-white shadow rounded-lg p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Pour pesage</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['pesage_aujourdhui']; ?></p>
                            <div class="mt-2 flex items-center text-sm text-purple-600">
                                <i class="fas fa-weight-scale mr-2"></i>
                                <span>Opération de pesage</span>
                            </div>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-weight text-purple-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques supplémentaires -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <!-- Card 5: Total du mois -->
                <div class="stat-card bg-white shadow rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total du mois</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['total_mois']; ?></p>
                            <div class="mt-2 flex items-center text-sm text-indigo-600">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Mois de <?php echo date('F'); ?></span>
                            </div>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-full">
                            <i class="fas fa-chart-line text-indigo-600 text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Moyenne journalière</span>
                            <span class="font-bold">
                                <?php 
                                    $jours_ecoules = date('j');
                                    echo $jours_ecoules > 0 ? round($stats['total_mois'] / $jours_ecoules, 1) : 0;
                                ?> camions/jour
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Card 6: Total général -->
                <div class="stat-card bg-white shadow rounded-lg p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total général</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['total_tous']; ?></p>
                            <div class="mt-2 flex items-center text-sm text-red-600">
                                <i class="fas fa-database mr-2"></i>
                                <span>Tous les enregistrements</span>
                            </div>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-truck-loading text-red-600 text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Dont aujourd'hui</span>
                            <span class="font-bold"><?php echo $stats['total_aujourdhui']; ?> (<?php echo $stats['total_tous'] > 0 ? round(($stats['total_aujourdhui'] / $stats['total_tous']) * 100, 1) : 0; ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mise à jour de l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const timeElement = document.querySelector('.time-display');
            if (timeElement) {
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                timeElement.textContent = `${hours}:${minutes}`;
            }
        }
        
        // Mettre à jour l'heure toutes les minutes
        setInterval(updateTime, 60000);
        
        // Animation pour les cartes statistiques
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
            
            // Initialiser l'heure
            updateTime();
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