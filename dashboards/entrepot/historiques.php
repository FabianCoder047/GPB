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

// Récupérer les paramètres de recherche
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, charges, decharges

// Construire la requête pour les camions chargés
$query_charges = "
    SELECT 
        cc.idChargement,
        ce.idEntree,
        ce.immatriculation,
        tc.nom as type_camion,
        p.nom as port,
        cc.date_chargement,
        cc.note_chargement,
        ps.ptav,
        ps.ptac,
        ps.ptra,
        ps.charge_essieu,
        GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ') as marchandises_chargees
    FROM chargement_camions cc
    INNER JOIN camions_entrants ce ON cc.idEntree = ce.idEntree
    LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
    LEFT JOIN port p ON ce.idPort = p.id
    LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
    LEFT JOIN marchandise_chargement_camion mcc ON cc.idChargement = mcc.idChargement
    LEFT JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
    WHERE 1=1
";

// Ajouter les filtres de recherche
if (!empty($search_term)) {
    $search_term_escaped = $conn->real_escape_string($search_term);
    $query_charges .= " AND (
        ce.immatriculation LIKE '%$search_term_escaped%' 
        OR tc.nom LIKE '%$search_term_escaped%'
        OR p.nom LIKE '%$search_term_escaped%'
        OR tm.nom LIKE '%$search_term_escaped%'
    )";
}

$query_charges .= " GROUP BY cc.idChargement
    ORDER BY cc.date_chargement DESC";

$result_charges = $conn->query($query_charges);
$camions_charges = [];
if ($result_charges) {
    while ($row = $result_charges->fetch_assoc()) {
        $camions_charges[] = $row;
    }
}

// Construire la requête pour les camions déchargés
$query_decharges = "
    SELECT 
        d.idDechargement,
        ce.idEntree,
        ce.immatriculation,
        tc.nom as type_camion,
        p.nom as port,
        d.date_dechargement,
        d.note_dechargement,
        ps.ptav,
        ps.ptac,
        ps.ptra,
        ps.charge_essieu,
        GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ') as marchandises_dechargees
    FROM dechargements_camions d
    INNER JOIN camions_entrants ce ON d.idEntree = ce.idEntree
    LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
    LEFT JOIN port p ON ce.idPort = p.id
    LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
    LEFT JOIN marchandise_dechargement_camion mdc ON d.idDechargement = mdc.idDechargement
    LEFT JOIN type_marchandise tm ON mdc.idTypeMarchandise = tm.id
    WHERE 1=1
";

// Ajouter les filtres de recherche
if (!empty($search_term)) {
    $search_term_escaped = $conn->real_escape_string($search_term);
    $query_decharges .= " AND (
        ce.immatriculation LIKE '%$search_term_escaped%' 
        OR tc.nom LIKE '%$search_term_escaped%'
        OR p.nom LIKE '%$search_term_escaped%'
        OR tm.nom LIKE '%$search_term_escaped%'
    )";
}

$query_decharges .= " GROUP BY d.idDechargement
    ORDER BY d.date_dechargement DESC";

$result_decharges = $conn->query($query_decharges);
$camions_decharges = [];
if ($result_decharges) {
    while ($row = $result_decharges->fetch_assoc()) {
        $camions_decharges[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historiques - Entrepôt</title>
    <link href="https://cdn.tailwindcss.com/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            max-height: calc(100vh - 300px);
            overflow-y: auto;
        }

        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-badge.active {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .badge-green {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-blue {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .badge-orange {
            background-color: #fed7aa;
            color: #92400e;
            border: 1px solid #fdba74;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        table tbody tr {
            transition: background-color 0.2s ease;
        }

        table tbody tr:hover {
            background-color: #f9fafb;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <!-- Titre et barre de recherche -->
        <div class="glass-card p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">
                    <i class="fas fa-history mr-2"></i>Historiques des Camions
                </h1>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="space-y-4">
                <!-- Champ de recherche -->
                <div class="relative">
                    <input 
                        type="text" 
                        id="search-input"
                        placeholder="Rechercher par immatriculation, type, port ou marchandise..."
                        class="search-input w-full px-4 py-3 pl-10 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition duration-300"
                        value="<?php echo safe_html($search_term); ?>"
                    >
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-lg"></i>
                </div>

                <!-- Bouton de recherche -->
                <div class="flex gap-2">
                    <button onclick="performSearch()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                        <i class="fas fa-search mr-2"></i>Rechercher
                    </button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg transition duration-300">
                        <i class="fas fa-redo mr-2"></i>Réinitialiser
                    </a>
                </div>

                <!-- Afficher les statistiques de recherche -->
                <?php if (!empty($search_term)): ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-sm text-gray-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong><?php echo count($camions_charges) + count($camions_decharges); ?> résultat(s)</strong> 
                        trouvé(s) pour "<strong><?php echo safe_html($search_term); ?></strong>"
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deux sections côte à côte -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Section 1: Camions Chargés -->
            <div class="glass-card p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-4 border-b flex items-center">
                    <i class="fas fa-upload text-green-600 mr-3"></i>Camions Chargés
                    <span class="ml-auto text-lg bg-green-100 text-green-800 px-3 py-1 rounded-full">
                        <?php echo count($camions_charges); ?>
                    </span>
                </h2>

                <?php if (!empty($camions_charges)): ?>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-green-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Immatriculation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Port</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Marchandises</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($camions_charges as $camion): ?>
                                <tr class="hover:bg-green-50 transition duration-200 cursor-pointer">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-truck text-green-600 text-sm"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-bold text-gray-900"><?php echo safe_html($camion['immatriculation']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo safe_html($camion['type_camion'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo safe_html($camion['port'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" title="<?php echo safe_html($camion['marchandises_chargees'] ?? ''); ?>">
                                        <?php echo safe_html($camion['marchandises_chargees'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <div><?php echo date('d/m/Y', strtotime($camion['date_chargement'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($camion['date_chargement'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" title="<?php echo safe_html($camion['note_chargement'] ?? ''); ?>">
                                        <?php echo !empty($camion['note_chargement']) ? safe_html(substr($camion['note_chargement'], 0, 50)) . (strlen($camion['note_chargement']) > 50 ? '...' : '') : '-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <p class="text-lg text-gray-500 font-medium">Aucun camion chargé</p>
                        <p class="text-sm text-gray-400 mt-2">Aucun enregistrement correspondant à votre recherche</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 2: Camions Déchargés -->
            <div class="glass-card p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-4 border-b flex items-center">
                    <i class="fas fa-download text-blue-600 mr-3"></i>Camions Déchargés
                    <span class="ml-auto text-lg bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                        <?php echo count($camions_decharges); ?>
                    </span>
                </h2>

                <?php if (!empty($camions_decharges)): ?>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-blue-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Immatriculation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Port</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Marchandises</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($camions_decharges as $camion): ?>
                                <tr class="hover:bg-blue-50 transition duration-200 cursor-pointer">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-truck text-blue-600 text-sm"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-bold text-gray-900"><?php echo safe_html($camion['immatriculation']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo safe_html($camion['type_camion'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <?php echo safe_html($camion['port'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" title="<?php echo safe_html($camion['marchandises_dechargees'] ?? ''); ?>">
                                        <?php echo safe_html($camion['marchandises_dechargees'] ?? '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <div><?php echo date('d/m/Y', strtotime($camion['date_dechargement'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($camion['date_dechargement'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" title="<?php echo safe_html($camion['note_dechargement'] ?? ''); ?>">
                                        <?php echo !empty($camion['note_dechargement']) ? safe_html(substr($camion['note_dechargement'], 0, 50)) . (strlen($camion['note_dechargement']) > 50 ? '...' : '') : '-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <p class="text-lg text-gray-500 font-medium">Aucun camion déchargé</p>
                        <p class="text-sm text-gray-400 mt-2">Aucun enregistrement correspondant à votre recherche</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Gestion de la recherche avec Enter
        document.getElementById('search-input').addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                performSearch();
            }
        });

        // Fonction pour effectuer la recherche
        function performSearch() {
            const searchTerm = document.getElementById('search-input').value.trim();
            if (searchTerm.length > 0) {
                window.location.href = `${window.location.pathname}?search=${encodeURIComponent(searchTerm)}`;
            }
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
