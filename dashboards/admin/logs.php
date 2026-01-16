<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier que l'utilisateur est admin
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$user_filter = isset($_GET['user_filter']) ? (int)$_GET['user_filter'] : 0;

// Construire la requête de base avec jointure
$query = "SELECT l.*, u.nom, u.prenom, u.role as user_role 
          FROM logs l 
          LEFT JOIN users u ON l.user_id = u.idUser 
          WHERE 1=1";

$params = [];
$types = '';

// Ajouter les filtres si présents
if (!empty($search)) {
    $query .= " AND (l.action LIKE ? OR l.details LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

if (!empty($date_filter)) {
    $query .= " AND DATE(l.timestamp) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if ($user_filter > 0) {
    $query .= " AND l.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

// Récupérer la liste des utilisateurs pour le filtre
$users_list = [];
$users_result = $conn->query("SELECT idUser, nom, prenom, role FROM users ORDER BY nom");
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users_list[] = $row;
    }
    $users_result->free();
}

// Pagination
$logsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $logsPerPage;

// Compter le nombre total de logs avec filtres
$count_query = "SELECT COUNT(*) as total FROM logs l LEFT JOIN users u ON l.user_id = u.idUser WHERE 1=1";
$count_params = [];
$count_types = '';

// Dupliquer les conditions de filtrage pour le comptage
if (!empty($search)) {
    $count_query .= " AND (l.action LIKE ? OR l.details LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $search_term = "%$search%";
    $count_params = [$search_term, $search_term, $search_term, $search_term];
    $count_types = 'ssss';
}

if (!empty($date_filter)) {
    $count_query .= " AND DATE(l.timestamp) = ?";
    $count_params[] = $date_filter;
    $count_types .= 's';
}

if ($user_filter > 0) {
    $count_query .= " AND l.user_id = ?";
    $count_params[] = $user_filter;
    $count_types .= 'i';
}

// Exécuter la requête de comptage
$count_stmt = $conn->prepare($count_query);
if (!empty($count_types)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_logs = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Calculer le nombre total de pages
$totalPages = ceil($total_logs / $logsPerPage);

// Ajouter le tri et la pagination à la requête principale
$query .= " ORDER BY l.timestamp DESC LIMIT ? OFFSET ?";
$params[] = $logsPerPage;
$params[] = $offset;
$types .= 'ii';

// Exécuter la requête principale
$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $result->free();
}
$stmt->close();

// Récupérer les statistiques
$stats = [];
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        DATE(timestamp) as log_date,
        COUNT(*) as daily_count
    FROM logs 
    WHERE timestamp >= CURDATE() - INTERVAL 7 DAY
    GROUP BY DATE(timestamp)
    ORDER BY log_date DESC
");

$daily_stats = [];
if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $daily_stats[] = $row;
    }
    $stats_result->free();
}

// Récupérer les actions les plus courantes
$actions_result = $conn->query("
    SELECT action, COUNT(*) as count 
    FROM logs 
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 5
");

$top_actions = [];
if ($actions_result) {
    while ($row = $actions_result->fetch_assoc()) {
        $top_actions[] = $row;
    }
    $actions_result->free();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'Activités</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>

    <div class="flex-1 overflow-auto">
        <div class="container mx-auto px-4 py-6">
            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtrer les logs</h2>
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Action, détails, utilisateur...">
                    </div>
                    
                    <div>
                        <label for="date_filter" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="date_filter" name="date_filter" 
                               value="<?php echo htmlspecialchars($date_filter); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    
                    <div>
                        <label for="user_filter" class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                        <select id="user_filter" name="user_filter" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="0">Tous les utilisateurs</option>
                            <?php foreach ($users_list as $usr): ?>
                            <option value="<?php echo $usr['idUser']; ?>" 
                                <?php echo $user_filter == $usr['idUser'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usr['prenom'] . ' ' . $usr['nom']); ?> (<?php echo $usr['role']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200 text-sm">
                            <i class="fas fa-filter mr-2"></i>Filtrer
                        </button>
                        <?php if ($search || $date_filter || $user_filter): ?>
                        <a href="logs.php" class="ml-2 text-gray-600 hover:text-gray-800 py-2">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-history text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Logs totaux</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo number_format($total_logs); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-users text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Utilisateurs uniques</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo count($users_list); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 p-3 rounded-lg">
                            <i class="fas fa-calendar-day text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Aujourd'hui</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php 
                                $today = date('Y-m-d');
                                $today_count = 0;
                                foreach ($daily_stats as $stat) {
                                    if ($stat['log_date'] == $today) {
                                        $today_count = $stat['daily_count'];
                                        break;
                                    }
                                }
                                echo $today_count;
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-fire text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Top action</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php 
                                if (!empty($top_actions)) {
                                    echo htmlspecialchars($top_actions[0]['action']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des logs -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-800">Historique des activités</h2>
                        <span class="text-sm text-gray-500">
                            Page <?php echo $currentPage; ?> sur <?php echo $totalPages; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (empty($logs)): ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-search text-4xl mb-4 text-gray-300"></i>
                        <p class="text-lg">Aucun log trouvé</p>
                        <p class="text-sm mt-2">Aucune activité n'a été enregistrée avec les critères sélectionnés.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="text-left py-3 px-4 text-xs font-medium text-gray-700 uppercase tracking-wider">Date/Heure</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-gray-700 uppercase tracking-wider">Utilisateur</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-gray-700 uppercase tracking-wider">Action</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-gray-700 uppercase tracking-wider">Détails</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-gray-700 uppercase tracking-wider">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            $timestamp = strtotime($log['timestamp']);
                                            echo date('d/m/Y H:i:s', $timestamp);
                                            ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($log['user_id']): ?>
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 font-medium text-sm">
                                                    <?php echo strtoupper(substr($log['prenom'], 0, 1) . substr($log['nom'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($log['prenom'] . ' ' . $log['nom']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo htmlspecialchars($log['user_role']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-sm text-gray-500">Système</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php 
                                        $action_colors = [
                                            'CREATE' => 'bg-green-100 text-green-800',
                                            'UPDATE' => 'bg-yellow-100 text-yellow-800',
                                            'DELETE' => 'bg-red-100 text-red-800',
                                            'LOGIN' => 'bg-blue-100 text-blue-800',
                                            'LOGOUT' => 'bg-gray-100 text-gray-800',
                                            'ERROR' => 'bg-red-100 text-red-800'
                                        ];
                                        $color_class = $action_colors[$log['action']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 text-xs font-medium rounded-full <?php echo $color_class; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($log['details']); ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-500 font-mono">
                                            <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-4 py-3 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Affichage <?php echo min(($currentPage - 1) * $logsPerPage + 1, $total_logs); ?>-<?php echo min($currentPage * $logsPerPage, $total_logs); ?> sur <?php echo $total_logs; ?> logs
                            </div>
                            <div class="flex space-x-1">
                                <?php if ($currentPage > 1): ?>
                                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $date_filter ? '&date_filter=' . $date_filter : ''; ?><?php echo $user_filter ? '&user_filter=' . $user_filter : ''; ?>" 
                                       class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                        <i class="fas fa-chevron-left mr-1"></i>Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php 
                                // Afficher les numéros de page
                                $start_page = max(1, $currentPage - 2);
                                $end_page = min($totalPages, $start_page + 4);
                                
                                if ($start_page > 1) {
                                    echo '<a href="?page=1' . ($search ? '&search=' . urlencode($search) : '') . ($date_filter ? '&date_filter=' . $date_filter : '') . ($user_filter ? '&user_filter=' . $user_filter : '') . '" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">1</a>';
                                    if ($start_page > 2) echo '<span class="px-3 py-2 text-sm text-gray-500">...</span>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="px-3 py-2 text-sm bg-blue-600 text-white rounded"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $date_filter ? '&date_filter=' . $date_filter : ''; ?><?php echo $user_filter ? '&user_filter=' . $user_filter : ''; ?>" 
                                           class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $totalPages) {
                                    if ($end_page < $totalPages - 1) echo '<span class="px-3 py-2 text-sm text-gray-500">...</span>';
                                    echo '<a href="?page=' . $totalPages . ($search ? '&search=' . urlencode($search) : '') . ($date_filter ? '&date_filter=' . $date_filter : '') . ($user_filter ? '&user_filter=' . $user_filter : '') . '" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">' . $totalPages . '</a>';
                                }
                                
                                if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $date_filter ? '&date_filter=' . $date_filter : ''; ?><?php echo $user_filter ? '&user_filter=' . $user_filter : ''; ?>" 
                                       class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                        Suivant<i class="fas fa-chevron-right ml-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Statistiques supplémentaires -->
            <?php if (!empty($daily_stats) || !empty($top_actions)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <!-- Actions les plus courantes -->
                <?php if (!empty($top_actions)): ?>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Actions les plus courantes</h3>
                    <div class="space-y-3">
                        <?php foreach ($top_actions as $action): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($action['action']); ?></span>
                            </div>
                            <span class="text-sm font-medium text-gray-900"><?php echo $action['count']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Activité des 7 derniers jours -->
                <?php if (!empty($daily_stats)): ?>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Activité des 7 derniers jours</h3>
                    <div class="space-y-3">
                        <?php foreach ($daily_stats as $stat): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm text-gray-700">
                                    <?php 
                                    $date = DateTime::createFromFormat('Y-m-d', $stat['log_date']);
                                    echo $date->format('d/m/Y');
                                    ?>
                                </span>
                            </div>
                            <span class="text-sm font-medium text-gray-900"><?php echo $stat['daily_count']; ?> logs</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // Confirmation pour effacer les filtres
        document.addEventListener('DOMContentLoaded', function() {
            const clearBtn = document.querySelector('a[href="logs.php"]');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    if (confirm('Voulez-vous effacer tous les filtres ?')) {
                        window.location.href = 'logs.php';
                    }
                    e.preventDefault();
                });
            }
        });
    </script>
</body>
</html>