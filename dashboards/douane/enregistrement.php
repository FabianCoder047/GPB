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

// Déterminer l'onglet actif
$active_tab = $_GET['tab'] ?? 'camions_entres';

// Variables pour les filtres
$search = $_GET['search'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Initialiser les données pour chaque onglet
$camions_entres = [];
$camions_sortis = [];
$bateaux_entres = [];
$bateaux_sortis = [];

// Compter les éléments pour chaque onglet
$counts = [
    'camions_entres' => 0,
    'camions_sortis' => 0,
    'bateaux_entres' => 0,
    'bateaux_sortis' => 0
];

try {
    // Récupérer le nombre pour chaque onglet
    $result = $conn->query("SELECT COUNT(*) as count FROM camions_entrants");
    $counts['camions_entres'] = $result->fetch_assoc()['count'] ?? 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM camions_sortants");
    $counts['camions_sortis'] = $result->fetch_assoc()['count'] ?? 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM bateau_entrant");
    $counts['bateaux_entres'] = $result->fetch_assoc()['count'] ?? 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM bateau_sortant");
    $counts['bateaux_sortis'] = $result->fetch_assoc()['count'] ?? 0;

    // Récupérer les données en fonction de l'onglet actif
    switch ($active_tab) {
        case 'camions_entres':
            $query = "SELECT ce.*, tc.nom as type_camion
                      FROM camions_entrants ce
                      LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                      WHERE 1=1";
            
            if (!empty($search)) {
                $query .= " AND (ce.immatriculation LIKE ? OR ce.nom_chauffeur LIKE ? OR ce.prenom_chauffeur LIKE ?)";
                $search_param = "%$search%";
            }
            
            if (!empty($date_debut)) {
                $query .= " AND DATE(ce.date_entree) >= ?";
            }
            
            if (!empty($date_fin)) {
                $query .= " AND DATE(ce.date_entree) <= ?";
            }
            
            $query .= " ORDER BY ce.date_entree DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if (!empty($search)) {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_fin);
                } else {
                    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
                }
            } else {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("ss", $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("s", $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("s", $date_fin);
                }
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $camions_entres = $result->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'camions_sortis':
            $query = "SELECT cs.*, ce.immatriculation, ce.nom_chauffeur, ce.prenom_chauffeur, 
                            tc.nom as type_camion
                      FROM camions_sortants cs
                      LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
                      LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                      WHERE 1=1";
            
            if (!empty($search)) {
                $query .= " AND (ce.immatriculation LIKE ? OR ce.nom_chauffeur LIKE ? OR ce.prenom_chauffeur LIKE ?)";
                $search_param = "%$search%";
            }
            
            if (!empty($date_debut)) {
                $query .= " AND DATE(cs.date_sortie) >= ?";
            }
            
            if (!empty($date_fin)) {
                $query .= " AND DATE(cs.date_sortie) <= ?";
            }

            $query .= " ORDER BY cs.date_sortie DESC LIMIT 100";

            $stmt = $conn->prepare($query);
            if (!empty($search)) {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_fin);
                } else {
                    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
                }
            } else {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("ss", $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("s", $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("s", $date_fin);
                }
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $camions_sortis = $result->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'bateaux_entres':
            $query = "SELECT be.*, tb.nom as type_bateau, p.nom as port_nom
                      FROM bateau_entrant be
                      LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
                      LEFT JOIN port p ON be.id_port = p.id
                      WHERE 1=1";
            
            if (!empty($search)) {
                $query .= " AND (be.nom_navire LIKE ? OR be.immatriculation LIKE ? OR be.nom_capitaine LIKE ?)";
                $search_param = "%$search%";
            }
            
            if (!empty($date_debut)) {
                $query .= " AND DATE(be.date_entree) >= ?";
            }
            
            if (!empty($date_fin)) {
                $query .= " AND DATE(be.date_entree) <= ?";
            }
            
            $query .= " ORDER BY be.date_entree DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if (!empty($search)) {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_fin);
                } else {
                    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
                }
            } else {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("ss", $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("s", $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("s", $date_fin);
                }
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $bateaux_entres = $result->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'bateaux_sortis':
            $query = "SELECT bs.*, tb.nom as type_bateau, p.nom as destination_port_nom
                      FROM bateau_sortant bs
                      LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
                      LEFT JOIN port p ON bs.id_destination_port = p.id
                      WHERE 1=1";
            
            if (!empty($search)) {
                $query .= " AND (bs.nom_navire LIKE ? OR bs.immatriculation LIKE ? OR bs.nom_capitaine LIKE ?)";
                $search_param = "%$search%";
            }
            
            if (!empty($date_debut)) {
                $query .= " AND DATE(bs.date_sortie) >= ?";
            }
            
            if (!empty($date_fin)) {
                $query .= " AND DATE(bs.date_sortie) <= ?";
            }
            
            $query .= " ORDER BY bs.date_sortie DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if (!empty($search)) {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $date_fin);
                } else {
                    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
                }
            } else {
                if (!empty($date_debut) && !empty($date_fin)) {
                    $stmt->bind_param("ss", $date_debut, $date_fin);
                } elseif (!empty($date_debut)) {
                    $stmt->bind_param("s", $date_debut);
                } elseif (!empty($date_fin)) {
                    $stmt->bind_param("s", $date_fin);
                }
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $bateaux_sortis = $result->fetch_all(MYSQLI_ASSOC);
            break;
    }

} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrôle Douanier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .nav-tab {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .nav-tab.active {
            color: #3b82f6;
            font-weight: 600;
        }
        
        .nav-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #3b82f6;
            border-radius: 3px 3px 0 0;
        }
        
        .count-badge {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 9999px;
        }
        
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #ffffff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .data-row {
            transition: all 0.2s ease;
        }
        
        .data-row:hover {
            background-color: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
        }
        
        .frais-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .frais-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            background-color: #8b5cf6;
            color: white;
            margin-left: 0.5rem;
        }
        
        .has-frais {
            position: relative;
        }
        
        .has-frais::after {
            content: '';
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
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
        
        <!-- Barre de navigation des onglets -->
        <div class="bg-white rounded-xl shadow mb-6">
            <div class="border-b">
                <nav class="flex flex-wrap -mb-px">
                    <!-- Onglet Camions Entrés -->
                    <button onclick="switchTab('camions_entres')" 
                            class="nav-tab flex items-center px-6 py-4 text-sm font-medium <?php echo $active_tab == 'camions_entres' ? 'active' : 'text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-truck-moving mr-2"></i>
                        Camions Entrés
                        <span class="count-badge ml-2 <?php echo $active_tab == 'camions_entres' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $counts['camions_entres']; ?>
                        </span>
                    </button>
                    
                    <!-- Onglet Camions Sortis -->
                    <button onclick="switchTab('camions_sortis')" 
                            class="nav-tab flex items-center px-6 py-4 text-sm font-medium <?php echo $active_tab == 'camions_sortis' ? 'active' : 'text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-truck-loading mr-2"></i>
                        Camions Sortis
                        <span class="count-badge ml-2 <?php echo $active_tab == 'camions_sortis' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $counts['camions_sortis']; ?>
                        </span>
                    </button>
                    
                    <!-- Onglet Bateaux Entrés -->
                    <button onclick="switchTab('bateaux_entres')" 
                            class="nav-tab flex items-center px-6 py-4 text-sm font-medium <?php echo $active_tab == 'bateaux_entres' ? 'active' : 'text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-ship mr-2"></i>
                        Bateaux Entrés
                        <span class="count-badge ml-2 <?php echo $active_tab == 'bateaux_entres' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $counts['bateaux_entres']; ?>
                        </span>
                    </button>
                    
                    <!-- Onglet Bateaux Sortis -->
                    <button onclick="switchTab('bateaux_sortis')" 
                            class="nav-tab flex items-center px-6 py-4 text-sm font-medium <?php echo $active_tab == 'bateaux_sortis' ? 'active' : 'text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas fa-anchor mr-2"></i>
                        Bateaux Sortis
                        <span class="count-badge ml-2 <?php echo $active_tab == 'bateaux_sortis' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $counts['bateaux_sortis']; ?>
                        </span>
                    </button>
                </nav>
            </div>
            
            <!-- Contenu des onglets -->
            <div class="p-6">
                <!-- Formulaire de recherche -->
                <div class="mb-6 bg-gray-50 rounded-lg p-4">
                    <form method="GET" class="space-y-4">
                        <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="search">
                                    <i class="fas fa-search mr-1"></i>Recherche
                                </label>
                                <input type="text" id="search" name="search" 
                                       value="<?php echo safe_html($search); ?>"
                                       placeholder="<?php 
                                           echo $active_tab == 'camions_entres' || $active_tab == 'camions_sortis' 
                                               ? 'Immatriculation, conducteur...' 
                                               : 'Nom navire, immatriculation, capitaine...';
                                       ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="date_debut">
                                    <i class="fas fa-calendar-alt mr-1"></i>Date début
                                </label>
                                <input type="date" id="date_debut" name="date_debut"
                                       value="<?php echo safe_html($date_debut); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="date_fin">
                                    <i class="fas fa-calendar-alt mr-1"></i>Date fin
                                </label>
                                <input type="date" id="date_fin" name="date_fin"
                                       value="<?php echo safe_html($date_fin); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200 flex items-center">
                                <i class="fas fa-search mr-2"></i>Rechercher
                            </button>
                            
                            <?php if (!empty($search) || !empty($date_debut) || !empty($date_fin)): ?>
                                <a href="?tab=<?php echo $active_tab; ?>" 
                                   class="text-gray-600 hover:text-gray-800 text-sm font-medium flex items-center">
                                    <i class="fas fa-times mr-1"></i>Effacer les filtres
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Contenu de l'onglet Camions Entrés -->
                <div id="camions_entres" class="tab-content <?php echo $active_tab == 'camions_entres' ? 'active' : ''; ?>">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky-header bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Camion</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conducteur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Etat Entrée</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($camions_entres)): ?>
                                    <?php foreach ($camions_entres as $camion): ?>
                                    <?php
                                    // Vérifier si des frais existent pour ce camion
                                    $has_frais = false;
                                    try {
                                        $frais_query = $conn->prepare("SELECT COUNT(*) as count FROM frais_transit WHERE type_entite = 'camion_entrant' AND id_entite = ?");
                                        $frais_query->bind_param("i", $camion['idEntree']);
                                        $frais_query->execute();
                                        $frais_result = $frais_query->get_result();
                                        $has_frais = $frais_result->fetch_assoc()['count'] > 0;
                                    } catch (Exception $e) {
                                        $has_frais = false;
                                    }
                                    ?>
                                    <tr class="data-row">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                    <i class="fas fa-truck text-blue-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900">
                                                        <?php echo safe_html($camion['immatriculation']); ?>
                                                        
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo safe_html($camion['type_camion'] ?? 'N/A'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($camion['nom_chauffeur'] . ' ' . $camion['prenom_chauffeur']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($camion['tel_chauffeur'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($camion['date_entree'] ?? '')); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($camion['date_entree'] ?? '')); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 uppercase">
                                            <?php
                                                $etat = $camion['etat'] ?? 'Inconnu';
                                                $badge_color = 'bg-gray-100 text-gray-800';
                                                if ($etat === 'Vide') {
                                                    $badge_color = 'bg-green-100 text-green-800';
                                                } elseif ($etat === 'Chargé') {
                                                    $badge_color = 'bg-yellow-100 text-yellow-800';
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $badge_color; ?>">
                                                <?php echo safe_html($etat); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button onclick="openDetails('camion_entrant', <?php echo $camion['idEntree']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition duration-200">
                                                <i class="fas fa-eye mr-1.5"></i>Détails
                                            </button>
                                            <button onclick="openFraisModal('camion_entrant', <?php echo $camion['idEntree']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-sm font-medium transition duration-200 ml-2 <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                <i class="fas fa-money-bill-wave mr-1.5"></i>Frais
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="text-gray-400">
                                                <i class="fas fa-truck text-4xl mb-3"></i>
                                                <p class="text-lg font-medium text-gray-500">Aucun camion trouvé</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Contenu de l'onglet Camions Sortis -->
                <div id="camions_sortis" class="tab-content <?php echo $active_tab == 'camions_sortis' ? 'active' : ''; ?>">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky-header bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Camion</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conducteur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sortie</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Etat Sortie</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($camions_sortis)): ?>
                                    <?php foreach ($camions_sortis as $camion): ?>
                                    <?php
                                    // Vérifier si des frais existent pour ce camion
                                    $has_frais = false;
                                    try {
                                        $frais_query = $conn->prepare("SELECT COUNT(*) as count FROM frais_transit WHERE type_entite = 'camion_sortant' AND id_entite = ?");
                                        $frais_query->bind_param("i", $camion['idSortie']);
                                        $frais_query->execute();
                                        $frais_result = $frais_query->get_result();
                                        $has_frais = $frais_result->fetch_assoc()['count'] > 0;
                                    } catch (Exception $e) {
                                        $has_frais = false;
                                    }
                                    ?>
                                    <tr class="data-row">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                    <i class="fas fa-truck-loading text-green-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900">
                                                        <?php echo safe_html($camion['immatriculation']); ?>
                                                        
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo safe_html($camion['type_camion'] ?? 'N/A'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($camion['nom_chauffeur'] . ' ' . $camion['prenom_chauffeur']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($camion['tel_chauffeur'] ?? ''); ?>
                                            </div>
                                        </td>
                                        
                                        
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($camion['date_sortie'] ?? '')); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($camion['date_sortie'] ?? '')); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 uppercase">
                                            <?php
                                                $etat = $camion['type_sortie'] ?? 'Inconnu';
                                                $badge_color = 'bg-gray-100 text-gray-800';
                                                if ($etat === 'charge') {
                                                    $badge_color = 'bg-green-100 text-green-800';
                                                } elseif ($etat === 'decharge') {
                                                    $badge_color = 'bg-yellow-100 text-yellow-800';
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $badge_color; ?>">
                                                <?php echo safe_html($etat); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button onclick="openDetails('camion_sortant', <?php echo $camion['idSortie']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-green-50 hover:bg-green-100 text-green-700 rounded-lg text-sm font-medium transition duration-200">
                                                <i class="fas fa-eye mr-1.5"></i>Détails
                                            </button>
                                            <button onclick="openFraisModal('camion_sortant', <?php echo $camion['idSortie']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-sm font-medium transition duration-200 ml-2 <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                <i class="fas fa-money-bill-wave mr-1.5"></i>Frais
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="text-gray-400">
                                                <i class="fas fa-truck-loading text-4xl mb-3"></i>
                                                <p class="text-lg font-medium text-gray-500">Aucun camion déchargé</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Contenu de l'onglet Bateaux Entrés -->
                <div id="bateaux_entres" class="tab-content <?php echo $active_tab == 'bateaux_entres' ? 'active' : ''; ?>">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky-header bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bateau</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capitaine</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type / État</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port / Agence</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($bateaux_entres)): ?>
                                    <?php foreach ($bateaux_entres as $bateau): ?>
                                    <?php
                                    // Vérifier si des frais existent pour ce bateau
                                    $has_frais = false;
                                    try {
                                        $frais_query = $conn->prepare("SELECT COUNT(*) as count FROM frais_transit WHERE type_entite = 'bateau_entrant' AND id_entite = ?");
                                        $frais_query->bind_param("i", $bateau['id']);
                                        $frais_query->execute();
                                        $frais_result = $frais_query->get_result();
                                        $has_frais = $frais_result->fetch_assoc()['count'] > 0;
                                    } catch (Exception $e) {
                                        $has_frais = false;
                                    }
                                    ?>
                                    <tr class="data-row">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                    <i class="fas fa-ship text-blue-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900">
                                                        <?php echo safe_html($bateau['nom_navire']); ?>
                                                        
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo safe_html($bateau['immatriculation']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($bateau['nom_capitaine'] . ' ' . $bateau['prenom_capitaine']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($bateau['tel_capitaine'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($bateau['type_bateau'] ?? 'N/A'); ?>
                                            </div>
                                            <div>
                                                <span class="status-badge <?php echo $bateau['etat'] == 'chargé' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo safe_html($bateau['etat']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($bateau['port_nom'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($bateau['agence'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($bateau['date_entree'] ?? '')); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($bateau['date_entree'] ?? '')); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button onclick="openDetails('bateau_entrant', <?php echo $bateau['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition duration-200">
                                                <i class="fas fa-eye mr-1.5"></i>Détails
                                            </button>
                                            <button onclick="openFraisModal('bateau_entrant', <?php echo $bateau['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-sm font-medium transition duration-200 ml-2 <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                <i class="fas fa-money-bill-wave mr-1.5"></i>Frais
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="text-gray-400">
                                                <i class="fas fa-ship text-4xl mb-3"></i>
                                                <p class="text-lg font-medium text-gray-500">Aucun bateau entré</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Contenu de l'onglet Bateaux Sortis -->
                <div id="bateaux_sortis" class="tab-content <?php echo $active_tab == 'bateaux_sortis' ? 'active' : ''; ?>">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky-header bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bateau</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capitaine</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type / État</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination / Agence</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sortie</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($bateaux_sortis)): ?>
                                    <?php foreach ($bateaux_sortis as $bateau): ?>
                                    <?php
                                    // Vérifier si des frais existent pour ce bateau
                                    $has_frais = false;
                                    try {
                                        $frais_query = $conn->prepare("SELECT COUNT(*) as count FROM frais_transit WHERE type_entite = 'bateau_sortant' AND id_entite = ?");
                                        $frais_query->bind_param("i", $bateau['id']);
                                        $frais_query->execute();
                                        $frais_result = $frais_query->get_result();
                                        $has_frais = $frais_result->fetch_assoc()['count'] > 0;
                                    } catch (Exception $e) {
                                        $has_frais = false;
                                    }
                                    ?>
                                    <tr class="data-row">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                    <i class="fas fa-anchor text-green-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900">
                                                        <?php echo safe_html($bateau['nom_navire']); ?>
                                                        
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo safe_html($bateau['immatriculation']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($bateau['nom_capitaine'] . ' ' . $bateau['prenom_capitaine']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($bateau['tel_capitaine'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($bateau['type_bateau'] ?? 'N/A'); ?>
                                            </div>
                                            <div>
                                                <span class="status-badge <?php echo $bateau['etat'] == 'chargé' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo safe_html($bateau['etat']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($bateau['destination_port_nom'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($bateau['agence'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($bateau['date_sortie'] ?? '')); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($bateau['date_sortie'] ?? '')); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button onclick="openDetails('bateau_sortant', <?php echo $bateau['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-green-50 hover:bg-green-100 text-green-700 rounded-lg text-sm font-medium transition duration-200">
                                                <i class="fas fa-eye mr-1.5"></i>Détails
                                            </button>
                                            <button onclick="openFraisModal('bateau_sortant', <?php echo $bateau['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-sm font-medium transition duration-200 ml-2 <?php echo $has_frais ? 'has-frais' : ''; ?>">
                                                <i class="fas fa-money-bill-wave mr-1.5"></i>Frais
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="text-gray-400">
                                                <i class="fas fa-anchor text-4xl mb-3"></i>
                                                <p class="text-lg font-medium text-gray-500">Aucun bateau sorti</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modale pour afficher les détails -->
    <div id="modalOverlay" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-4/5 md:w-3/5 lg:w-2/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800"></h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent" class="overflow-y-auto max-h-[70vh]">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
    
    <!-- Modale pour les frais -->
    <div id="fraisModalOverlay" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-4/5 md:w-2/5 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 id="fraisModalTitle" class="text-xl font-bold text-gray-800">Ajouter les frais de transit</h3>
                <button onclick="closeFraisModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="fraisForm" class="space-y-4">
                <input type="hidden" id="fraisTypeEntite" name="type_entite">
                <input type="hidden" id="fraisIdEntite" name="id_entite">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="frais_thc">
                            <i class="fas fa-dolly mr-1"></i>Frais THC (F CFA)
                        </label>
                        <input type="number" id="frais_thc" name="frais_thc" min="0" step="0.01"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent frais-input"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="frais_magasinage">
                            <i class="fas fa-warehouse mr-1"></i>Magasinage (F CFA)
                        </label>
                        <input type="number" id="frais_magasinage" name="frais_magasinage" min="0" step="0.01"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent frais-input"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="droits_douane">
                            <i class="fas fa-file-invoice-dollar mr-1"></i>Droits de douane (F CFA)
                        </label>
                        <input type="number" id="droits_douane" name="droits_douane" min="0" step="0.01"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent frais-input"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="surestaries">
                            <i class="fas fa-clock mr-1"></i>Surestaries (F CFA)
                        </label>
                        <input type="number" id="surestaries" name="surestaries" min="0" step="0.01"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent frais-input"
                               placeholder="0.00">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="commentaire">
                        <i class="fas fa-comment mr-1"></i>Commentaire
                    </label>
                    <textarea id="commentaire" name="commentaire" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent frais-input"
                              placeholder="Notes supplémentaires..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeFraisModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-200">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-200 font-medium">
                        <i class="fas fa-save mr-2"></i>Enregistrer les frais
                    </button>
                </div>
                
                <div id="fraisMessage" class="mt-4"></div>
            </form>
        </div>
    </div>
    
    <script>
        // Fonction pour changer d'onglet
        function switchTab(tabName) {
            // Mettre à jour l'URL avec le nouvel onglet
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.location.href = url.toString();
        }
        
        // Fonction pour ouvrir la modale de détails
        async function openDetails(type, id) {
            try {
                // Afficher le loader
                document.getElementById('modalContent').innerHTML = `
                    <div class="p-8 text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="mt-4 text-gray-600">Chargement des détails...</p>
                    </div>
                `;
                
                // Afficher la modale
                document.getElementById('modalOverlay').classList.remove('hidden');
                
                // Déterminer le titre en fonction du type
                let title = '';
                switch(type) {
                    case 'camion_entrant': title = 'Détails Camion Entrant'; break;
                    case 'camion_sortant': title = 'Détails Camion Sortant'; break;
                    case 'bateau_entrant': title = 'Détails Bateau Entrant'; break;
                    case 'bateau_sortant': title = 'Détails Bateau Sortant'; break;
                }
                document.getElementById('modalTitle').textContent = title;
                
                // Récupérer les détails via AJAX
                const response = await fetch(`get_douane_details.php?type=${type}&id=${id}`);
                const data = await response.json();
                
                // Construire le contenu de la modale
                let content = '';
                
                if (type === 'camion_entrant') {
                    content = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-truck mr-2"></i>Informations Camion</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Immatriculation:</span>
                                        <span class="font-medium">${data.immatriculation || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type de camion:</span>
                                        <span class="font-medium">${data.type_camion || 'N/A'}</span>
                                    </div>
                                
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-user-tie mr-2"></i>Conducteur</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Nom:</span>
                                        <span class="font-medium">${data.nom_chauffeur || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Prénom:</span>
                                        <span class="font-medium">${data.prenom_chauffeur || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Téléphone:</span>
                                        <span class="font-medium">${data.telephone_chauffeur || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-box mr-2"></i>Marchandise</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type:</span>
                                        <span class="font-medium">${data.type_marchandise || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Poids brut:</span>
                                        <span class="font-medium">${data.poids_brut ? data.poids_brut + ' t' : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Destination:</span>
                                        <span class="font-medium">${data.destination || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2"></i>Dates</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date entrée:</span>
                                        <span class="font-medium">${data.date_entree ? new Date(data.date_entree).toLocaleDateString('fr-FR') : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Heure entrée:</span>
                                        <span class="font-medium">${data.date_entree ? new Date(data.date_entree).toLocaleTimeString('fr-FR') : 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${data.note ? `
                            <div class="mb-6">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-sticky-note mr-2"></i>Notes</h4>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-gray-700">${data.note}</p>
                                </div>
                            </div>
                        ` : ''}
                    `;
                }else if (type === 'camion_sortant') {
                    content = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-truck mr-2"></i>Informations Camion</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Immatriculation:</span>
                                        <span class="font-medium">${data.immatriculation || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type de camion:</span>
                                        <span class="font-medium">${data.type_camion || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Client:</span>
                                        <span class="font-medium">${data.client || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-user-tie mr-2"></i>Conducteur</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Nom:</span>
                                        <span class="font-medium">${data.nom_chauffeur || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Prénom:</span>
                                        <span class="font-medium">${data.prenom_chauffeur || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Téléphone:</span>
                                        <span class="font-medium">${data.telephone_chauffeur || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-box mr-2"></i>Marchandise</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type:</span>
                                        <span class="font-medium">${data.type_marchandise || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Poids brut:</span>
                                        <span class="font-medium">${data.poids_brut ? data.poids_brut + ' t' : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Destination:</span>
                                        <span class="font-medium">${data.destination || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2"></i>Dates</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date entrée:</span>
                                        <span class="font-medium">${data.date_entree ? new Date(data.date_entree).toLocaleDateString('fr-FR') : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Heure entrée:</span>
                                        <span class="font-medium">${data.date_entree ? new Date(data.date_entree).toLocaleTimeString('fr-FR') : 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${data.note ? `
                            <div class="mb-6">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-sticky-note mr-2"></i>Notes</h4>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-gray-700">${data.note}</p>
                                </div>
                            </div>
                        ` : ''}
                    `;
                }if (type === 'bateau_entrant') {
                    content = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-truck mr-2"></i>Informations Camion</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Immatriculation:</span>
                                        <span class="font-medium">${data.immatriculation || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type de bateau:</span>
                                        <span class="font-medium">${data.type_bateau || 'N/A'}</span>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-user-tie mr-2"></i>Conducteur</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Nom:</span>
                                        <span class="font-medium">${data.nom_capitaine || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Prénom:</span>
                                        <span class="font-medium">${data.prenom_capitaine || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Téléphone:</span>
                                        <span class="font-medium">${data.tel_capitaine || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-box mr-2"></i>Marchandise</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type:</span>
                                        <span class="font-medium">${data.type_marchandise || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Poids brut:</span>
                                        <span class="font-medium">${data.poids_brut ? data.poids_brut + ' t' : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Destination:</span>
                                        <span class="font-medium">${data.destination || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2"></i>Dates</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date entrée:</span>
                                        <span class="font-medium">${data.date_entree ? new Date(data.date_entree).toLocaleDateString('fr-FR') : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Heure entrée:</span>
                                        <span class="font-medium">${data.date_entree ? new Date(data.date_entree).toLocaleTimeString('fr-FR') : 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${data.note ? `
                            <div class="mb-6">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-sticky-note mr-2"></i>Notes</h4>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-gray-700">${data.note}</p>
                                </div>
                            </div>
                        ` : ''}
                    `;
                }if (type === 'bateau_sortant') {
                    content = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-truck mr-2"></i>Informations Camion</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Immatriculation:</span>
                                        <span class="font-medium">${data.immatriculation || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type de bateau:</span>
                                        <span class="font-medium">${data.type_bateau || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-user-tie mr-2"></i>Conducteur</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Nom:</span>
                                        <span class="font-medium">${data.nom_capitaine || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Prénom:</span>
                                        <span class="font-medium">${data.prenom_capitaine || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Téléphone:</span>
                                        <span class="font-medium">${data.tel_capitaine || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-box mr-2"></i>Marchandise</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Type:</span>
                                        <span class="font-medium">${data.type_marchandise || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Poids brut:</span>
                                        <span class="font-medium">${data.poids_brut ? data.poids_brut + ' t' : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Destination:</span>
                                        <span class="font-medium">${data.destination || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-calendar-alt mr-2"></i>Dates</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date entrée:</span>
                                        <span class="font-medium">${data.date_sortie ? new Date(data.date_sortie).toLocaleDateString('fr-FR') : 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Heure entrée:</span>
                                        <span class="font-medium">${data.date_sortie ? new Date(data.date_sortie).toLocaleTimeString('fr-FR') : 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${data.note ? `
                            <div class="mb-6">
                                <h4 class="font-bold text-gray-700 mb-2"><i class="fas fa-sticky-note mr-2"></i>Notes</h4>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-gray-700">${data.note}</p>
                                </div>
                            </div>
                        ` : ''}
                    `;
                }
                
                document.getElementById('modalContent').innerHTML = content;
                
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('modalContent').innerHTML = `
                    <div class="p-8 text-center">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-3"></i>
                        <p class="text-lg font-medium text-gray-700">Erreur de chargement</p>
                        <p class="text-sm text-gray-500 mt-2">Impossible de charger les détails.</p>
                    </div>
                `;
            }
        }
        
        // Fonction pour fermer la modale
        function closeModal() {
            document.getElementById('modalOverlay').classList.add('hidden');
        }
        
        // Fermer la modale avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeFraisModal();
            }
        });
        
        // Fermer la modale en cliquant en dehors
        document.getElementById('modalOverlay').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('fraisModalOverlay').addEventListener('click', function(event) {
            if (event.target === this) {
                closeFraisModal();
            }
        });
        
        // Variables pour stocker les données actuelles des frais
        let currentFraisData = null;
        
        // Fonction pour ouvrir la modale des frais
        async function openFraisModal(type, id) {
            try {
                // Afficher le loader
                document.getElementById('fraisMessage').innerHTML = `
                    <div class="text-center py-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-purple-600 mx-auto"></div>
                    </div>
                `;
                
                // Remplir les champs cachés
                document.getElementById('fraisTypeEntite').value = type;
                document.getElementById('fraisIdEntite').value = id;
                
                // Déterminer le titre
                let title = 'Ajouter les frais de transit';
                switch(type) {
                    case 'camion_entrant': title = 'Frais pour camion entrant'; break;
                    case 'camion_sortant': title = 'Frais pour camion sortant'; break;
                    case 'bateau_entrant': title = 'Frais pour bateau entrant'; break;
                    case 'bateau_sortant': title = 'Frais pour bateau sortant'; break;
                }
                document.getElementById('fraisModalTitle').textContent = title;
                
                // Récupérer les frais existants s'il y en a
                const response = await fetch(`get_frais.php?type=${type}&id=${id}`);
                const data = await response.json();
                
                // Remplir le formulaire
                document.getElementById('frais_thc').value = data.frais_thc || '';
                document.getElementById('frais_magasinage').value = data.frais_magasinage || '';
                document.getElementById('droits_douane').value = data.droits_douane || '';
                document.getElementById('surestaries').value = data.surestaries || '';
                document.getElementById('commentaire').value = data.commentaire || '';
                
                // Stocker les données pour référence
                currentFraisData = data;
                
                // Vider le message
                document.getElementById('fraisMessage').innerHTML = '';
                
                // Afficher la modale
                document.getElementById('fraisModalOverlay').classList.remove('hidden');
                
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('fraisMessage').innerHTML = `
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        Erreur de chargement des frais existants.
                    </div>
                `;
                document.getElementById('fraisModalOverlay').classList.remove('hidden');
            }
        }
        
        // Fonction pour fermer la modale des frais
        function closeFraisModal() {
            document.getElementById('fraisModalOverlay').classList.add('hidden');
            document.getElementById('fraisForm').reset();
            currentFraisData = null;
            document.getElementById('fraisMessage').innerHTML = '';
        }
        
        // Gérer la soumission du formulaire des frais
        document.getElementById('fraisForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Afficher le loader
            document.getElementById('fraisMessage').innerHTML = `
                <div class="text-center py-2">
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-purple-600 mx-auto"></div>
                    <p class="text-sm text-gray-600 mt-1">Enregistrement en cours...</p>
                </div>
            `;
            
            // Récupérer les données du formulaire
            const formData = {
                type_entite: document.getElementById('fraisTypeEntite').value,
                id_entite: document.getElementById('fraisIdEntite').value,
                frais_thc: document.getElementById('frais_thc').value,
                frais_magasinage: document.getElementById('frais_magasinage').value,
                droits_douane: document.getElementById('droits_douane').value,
                surestaries: document.getElementById('surestaries').value,
                commentaire: document.getElementById('commentaire').value
            };
            
            try {
                // Envoyer les données au serveur
                const response = await fetch('save_frais.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Afficher le message de succès
                    document.getElementById('fraisMessage').innerHTML = `
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            <i class="fas fa-check-circle mr-2"></i>${result.message}
                        </div>
                    `;
                    
                    // Fermer la modale après 2 secondes
                    setTimeout(() => {
                        closeFraisModal();
                        // Recharger la page pour afficher le badge
                        location.reload();
                    }, 2000);
                    
                } else {
                    throw new Error(result.error || 'Erreur inconnue');
                }
                
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('fraisMessage').innerHTML = `
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>${error.message}
                    </div>
                `;
            }
        });
        
        // Initialiser les dates par défaut
        document.addEventListener('DOMContentLoaded', function() {
            const dateDebut = document.getElementById('date_debut');
            const dateFin = document.getElementById('date_fin');
            
            // Si les dates sont vides, mettre les valeurs par défaut (7 derniers jours)
            if (!dateDebut.value) {
                const oneWeekAgo = new Date();
                oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
                dateDebut.valueAsDate = oneWeekAgo;
            }
            
            if (!dateFin.value) {
                dateFin.valueAsDate = new Date();
            }
            
            // Valider les dates
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value) {
                    e.preventDefault();
                    alert('La date de début doit être antérieure à la date de fin');
                    dateDebut.focus();
                }
            });
        });
    </script>
</body>
</html>