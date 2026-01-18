<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php';
    
// Décommenter les imports nécessaires
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et est autorité
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'autorite') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les filtres
$filtre_type = $_GET['type'] ?? 'camions_entrants'; // camions_entrants, camions_sortis, bateaux_entrants, bateaux_sortis, marchandises_entrees, marchandises_sorties
$filtre_date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$filtre_date_fin = $_GET['date_fin'] ?? date('Y-m-t');
$filtre_immatriculation = $_GET['immatriculation'] ?? '';
$filtre_port = $_GET['port'] ?? '';
$filtre_etat = $_GET['etat'] ?? '';
$filtre_chauffeur = $_GET['chauffeur'] ?? '';
$filtre_marchandise = $_GET['marchandise'] ?? '';
$filtre_origine = $_GET['origine'] ?? ''; // camion, bateau

// Récupérer les listes pour les filtres
$ports = [];
$result_ports = $conn->query("SELECT id, nom FROM port ORDER BY nom");
if ($result_ports && $result_ports->num_rows > 0) {
    while ($row = $result_ports->fetch_assoc()) {
        $ports[] = $row;
    }
}

$types_marchandises = [];
$result_types = $conn->query("SELECT id, nom FROM type_marchandise ORDER BY nom");
if ($result_types && $result_types->num_rows > 0) {
    while ($row = $result_types->fetch_assoc()) {
        $types_marchandises[] = $row;
    }
}

// Récupérer les données selon le filtre
$resultats = [];
$stats = [
    'total' => 0,
    'surcharges' => 0,
    'poids_total_kg' => 0,
    'poids_total_t' => 0,
    'moyenne_poids_kg' => 0,
    'moyenne_poids_t' => 0,
    'camions' => 0,
    'bateaux' => 0
];

// Construire la requête en fonction du type
try {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    // Conditions de date communes
    if ($filtre_date_debut) {
        switch ($filtre_type) {
            case 'camions_entrants':
                $where_conditions[] = "ce.date_entree >= ?";
                break;
            case 'camions_sortis':
                $where_conditions[] = "cs.date_sortie >= ?";
                break;
            case 'bateaux_entrants':
                $where_conditions[] = "be.date_entree >= ?";
                break;
            case 'bateaux_sortis':
                $where_conditions[] = "bs.date_sortie >= ?";
                break;
            case 'marchandises_entrees':
                $where_conditions[] = "date_operation >= ?";
                break;
            case 'marchandises_sorties':
                $where_conditions[] = "date_operation >= ?";
                break;
        }
        $params[] = $filtre_date_debut;
        $types .= 's';
    }
    
    if ($filtre_date_fin) {
        switch ($filtre_type) {
            case 'camions_entrants':
                $where_conditions[] = "ce.date_entree <= ?";
                break;
            case 'camions_sortis':
                $where_conditions[] = "cs.date_sortie <= ?";
                break;
            case 'bateaux_entrants':
                $where_conditions[] = "be.date_entree <= ?";
                break;
            case 'bateaux_sortis':
                $where_conditions[] = "bs.date_sortie <= ?";
                break;
            case 'marchandises_entrees':
                $where_conditions[] = "date_operation <= ?";
                break;
            case 'marchandises_sorties':
                $where_conditions[] = "date_operation <= ?";
                break;
        }
        $params[] = $filtre_date_fin . ' 23:59:59';
        $types .= 's';
    }
    
    // Conditions spécifiques
    if ($filtre_immatriculation && in_array($filtre_type, ['camions_entrants', 'camions_sortis', 'marchandises_entrees', 'marchandises_sorties'])) {
        if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
            $where_conditions[] = "immatriculation LIKE ?";
            $params[] = '%' . $filtre_immatriculation . '%';
            $types .= 's';
        } else {
            $where_conditions[] = "(ce.immatriculation LIKE ? OR be.immatriculation LIKE ? OR bs.immatriculation LIKE ?)";
            $params[] = '%' . $filtre_immatriculation . '%';
            $params[] = '%' . $filtre_immatriculation . '%';
            $params[] = '%' . $filtre_immatriculation . '%';
            $types .= 'sss';
        }
    }
    
    if ($filtre_port && in_array($filtre_type, ['camions_entrants', 'camions_sortis', 'bateaux_entrants', 'bateaux_sortis', 'marchandises_entrees', 'marchandises_sorties'])) {
        if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
            // Pour les marchandises, nous filtrons par le nom du port
            $port_result = $conn->query("SELECT nom FROM port WHERE id = " . intval($filtre_port));
            if ($port_result && $port_result->num_rows > 0) {
                $port_row = $port_result->fetch_assoc();
                $where_conditions[] = "port_nom = ?";
                $params[] = $port_row['nom'];
                $types .= 's';
            }
        } elseif (in_array($filtre_type, ['camions_entrants', 'camions_sortis'])) {
            $where_conditions[] = "ce.idPort = ?";
            $params[] = $filtre_port;
            $types .= 'i';
        } elseif (in_array($filtre_type, ['bateaux_entrants'])) {
            $where_conditions[] = "be.id_port = ?";
            $params[] = $filtre_port;
            $types .= 'i';
        } elseif (in_array($filtre_type, ['bateaux_sortis'])) {
            $where_conditions[] = "bs.id_destination_port = ?";
            $params[] = $filtre_port;
            $types .= 'i';
        }
    }
    
    if ($filtre_etat && in_array($filtre_type, ['camions_entrants', 'camions_sortis'])) {
        $where_conditions[] = "ce.etat = ?";
        $params[] = $filtre_etat;
        $types .= 's';
    }
    
    if ($filtre_chauffeur && in_array($filtre_type, ['camions_entrants', 'camions_sortis', 'marchandises_entrees', 'marchandises_sorties'])) {
        if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
            $where_conditions[] = "operateur LIKE ?";
            $params[] = '%' . $filtre_chauffeur . '%';
            $types .= 's';
        } else {
            $where_conditions[] = "(ce.nom_chauffeur LIKE ? OR ce.prenom_chauffeur LIKE ? OR be.nom_capitaine LIKE ? OR be.prenom_capitaine LIKE ? OR bs.nom_capitaine LIKE ? OR bs.prenom_capitaine LIKE ?)";
            $params[] = '%' . $filtre_chauffeur . '%';
            $params[] = '%' . $filtre_chauffeur . '%';
            $params[] = '%' . $filtre_chauffeur . '%';
            $params[] = '%' . $filtre_chauffeur . '%';
            $params[] = '%' . $filtre_chauffeur . '%';
            $params[] = '%' . $filtre_chauffeur . '%';
            $types .= 'ssssss';
        }
    }
    
    if ($filtre_marchandise && in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
        $where_conditions[] = "type_marchandise_id = ?";
        $params[] = $filtre_marchandise;
        $types .= 'i';
    }
    
    if ($filtre_origine && in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
        $where_conditions[] = "origine = ?";
        $params[] = $filtre_origine;
        $types .= 's';
    }
    
    // Construire la requête selon le type
    switch ($filtre_type) {
        case 'camions_entrants':
            $query = "
                SELECT ce.*, p.nom as port_nom,
                       ps.poids_total_marchandises, ps.ptav, ps.ptac, ps.ptra, ps.date_pesage,
                       COUNT(mp.idMarchandise) as nb_marchandises,
                       (ps.ptav + ps.poids_total_marchandises) as poids_total_camion
                FROM camions_entrants ce
                LEFT JOIN port p ON ce.idPort = p.id
                LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
                LEFT JOIN marchandises_pesage mp ON ps.idPesage = mp.idPesage
            ";
            
            if (!empty($where_conditions)) {
                $query .= " WHERE " . implode(" AND ", $where_conditions);
            }
            
            $query .= " GROUP BY ce.idEntree ORDER BY ce.date_entree DESC";
            break;
            
        case 'camions_sortis':
            $query = "
                SELECT ce.*, p.nom as port_nom,
                       cs.date_sortie, cs.type_sortie,
                       ps.poids_total_marchandises, ps.ptav, ps.ptac, ps.ptra, ps.date_pesage,
                       ps.note_surcharge,
                       COUNT(mp.idMarchandise) as nb_marchandises,
                       (ps.ptav + ps.poids_total_marchandises) as poids_total_camion
                FROM camions_sortants cs
                INNER JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
                LEFT JOIN port p ON ce.idPort = p.id
                LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
                LEFT JOIN marchandises_pesage mp ON ps.idPesage = mp.idPesage
                WHERE cs.date_sortie IS NOT NULL
            ";
            
            if (!empty($where_conditions)) {
                $query .= " AND " . implode(" AND ", $where_conditions);
            }
            
            $query .= " GROUP BY cs.idSortie ORDER BY cs.date_sortie DESC";
            break;
            
        case 'bateaux_entrants':
            $query = "
                SELECT be.*, tb.nom as type_bateau, p.nom as port_nom,
                       COUNT(mbe.id) as nb_marchandises
                FROM bateau_entrant be
                LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
                LEFT JOIN port p ON be.id_port = p.id
                LEFT JOIN marchandise_bateau_entrant mbe ON be.id = mbe.id_bateau_entrant
            ";
            
            if (!empty($where_conditions)) {
                $query .= " WHERE " . implode(" AND ", $where_conditions);
            }
            
            $query .= " GROUP BY be.id ORDER BY be.date_entree DESC";
            break;
            
        case 'bateaux_sortis':
            $query = "
                SELECT bs.*, tb.nom as type_bateau, p.nom as port_nom,
                       COUNT(mbs.id) as nb_marchandises
                FROM bateau_sortant bs
                LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
                LEFT JOIN port p ON bs.id_destination_port = p.id
                LEFT JOIN marchandise_bateau_sortant mbs ON bs.id = mbs.id_bateau_sortant
                WHERE bs.date_sortie IS NOT NULL
            ";
            
            if (!empty($where_conditions)) {
                $query .= " AND " . implode(" AND ", $where_conditions);
            }
            
            $query .= " GROUP BY bs.id ORDER BY bs.date_sortie DESC";
            break;
            
        case 'marchandises_entrees':
            // Marchandises entrées: bateaux entrants + camions entrants
            $query = "
                SELECT 
                    'bateau' as origine,
                    mbe.id,
                    mbe.poids * 1000 as poids,  -- Convertir tonnes en kg
                    mbe.note,
                    mbe.date_ajout,
                    mbe.id_type_marchandise as type_marchandise_id,
                    tm.nom as type_marchandise,
                    be.nom_navire,
                    be.immatriculation,
                    be.date_entree as date_operation,
                    CONCAT(be.prenom_capitaine, ' ', be.nom_capitaine) as operateur,
                    p.nom as port_nom,
                    NULL as surcharge
                FROM marchandise_bateau_entrant mbe
                LEFT JOIN type_marchandise tm ON mbe.id_type_marchandise = tm.id
                LEFT JOIN bateau_entrant be ON mbe.id_bateau_entrant = be.id
                LEFT JOIN port p ON be.id_port = p.id
                WHERE mbe.poids > 0
                
                UNION ALL
                
                SELECT 
                    'camion' as origine,
                    mp.idMarchandise as id,
                    mp.poids,  -- Déjà en kg
                    mp.note,
                    mp.date_ajout,
                    mp.idTypeMarchandise as type_marchandise_id,
                    tm.nom as type_marchandise,
                    ce.immatriculation as nom_navire,
                    ce.immatriculation,
                    ce.date_entree as date_operation,
                    CONCAT(ce.prenom_chauffeur, ' ', ce.nom_chauffeur) as operateur,
                    p.nom as port_nom,
                    ps.surcharge
                FROM marchandises_pesage mp
                LEFT JOIN type_marchandise tm ON mp.idTypeMarchandise = tm.id
                LEFT JOIN pesages ps ON mp.idPesage = ps.idPesage
                LEFT JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
                LEFT JOIN port p ON ce.idPort = p.id
                WHERE mp.poids > 0 AND ce.date_entree IS NOT NULL
            ";
            
            if (!empty($where_conditions)) {
                $query = "SELECT * FROM ($query) as marchandises WHERE " . implode(" AND ", $where_conditions);
            }
            
            $query .= " ORDER BY date_operation DESC";
            break;
            
        case 'marchandises_sorties':
            // Marchandises sorties: bateaux sortants + camions sortants
            $query = "
                SELECT 
                    'bateau' as origine,
                    mbs.id,
                    mbs.poids * 1000 as poids,  -- Convertir tonnes en kg
                    mbs.note,
                    mbs.date_ajout,
                    mbs.id_type_marchandise as type_marchandise_id,
                    tm.nom as type_marchandise,
                    bs.nom_navire,
                    bs.immatriculation,
                    bs.date_sortie as date_operation,
                    CONCAT(bs.prenom_capitaine, ' ', bs.nom_capitaine) as operateur,
                    p.nom as port_nom,
                    NULL as surcharge
                FROM marchandise_bateau_sortant mbs
                LEFT JOIN type_marchandise tm ON mbs.id_type_marchandise = tm.id
                LEFT JOIN bateau_sortant bs ON mbs.id_bateau_sortant = bs.id
                LEFT JOIN port p ON bs.id_destination_port = p.id
                WHERE mbs.poids > 0 AND bs.date_sortie IS NOT NULL
                
                UNION ALL
                
                SELECT 
                    'camion' as origine,
                    mp.idMarchandise as id,
                    mp.poids,  -- Déjà en kg
                    mp.note,
                    mp.date_ajout,
                    mp.idTypeMarchandise as type_marchandise_id,
                    tm.nom as type_marchandise,
                    ce.immatriculation as nom_navire,
                    ce.immatriculation,
                    cs.date_sortie as date_operation,
                    CONCAT(ce.prenom_chauffeur, ' ', ce.nom_chauffeur) as operateur,
                    p.nom as port_nom,
                    ps.surcharge
                FROM marchandises_pesage mp
                LEFT JOIN type_marchandise tm ON mp.idTypeMarchandise = tm.id
                LEFT JOIN pesages ps ON mp.idPesage = ps.idPesage
                LEFT JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
                LEFT JOIN camions_sortants cs ON ce.idEntree = cs.idEntree
                LEFT JOIN port p ON ce.idPort = p.id
                WHERE mp.poids > 0 AND cs.date_sortie IS NOT NULL
            ";
            
            if (!empty($where_conditions)) {
                $query = "SELECT * FROM ($query) as marchandises WHERE " . implode(" AND ", $where_conditions);
            }
            
            $query .= " ORDER BY date_operation DESC";
            break;
    }
    
    // Exécuter la requête
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Erreur de préparation: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
        if ($result === false) {
            throw new Exception("Erreur d'exécution: " . $conn->error);
        }
    }
    
    if ($result) {
        $resultats = $result->fetch_all(MYSQLI_ASSOC);
        $stats['total'] = count($resultats);
        
        // Calculer les statistiques pour les marchandises
        if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
            $poids_total_kg = 0;
            $camions = 0;
            $bateaux = 0;
            
            foreach ($resultats as $row) {
                $poids_total_kg += $row['poids'];
                if ($row['origine'] === 'camion') {
                    $camions++;
                } else {
                    $bateaux++;
                }
            }
            
            $stats['poids_total_kg'] = $poids_total_kg;
            $stats['poids_total_t'] = $poids_total_kg / 1000;
            $stats['camions'] = $camions;
            $stats['bateaux'] = $bateaux;
            $stats['moyenne_poids_kg'] = $stats['total'] > 0 ? $poids_total_kg / $stats['total'] : 0;
            $stats['moyenne_poids_t'] = $stats['total'] > 0 ? ($poids_total_kg / 1000) / $stats['total'] : 0;
        }
        
        // Calculer les statistiques pour les camions (entrants et sortis)
        if (in_array($filtre_type, ['camions_entrants', 'camions_sortis'])) {
            $surcharges = 0;
            $poids_total = 0;
            
            foreach ($resultats as $row) {
                if (isset($row['poids_total_camion'])) {
                    $poids_total += $row['poids_total_camion'];
                }
            }
            
            $stats['surcharges'] = $surcharges;
            $stats['poids_total_kg'] = $poids_total;
            $stats['poids_total_t'] = $poids_total / 1000;
            $stats['moyenne_poids_kg'] = $stats['total'] > 0 ? $poids_total / $stats['total'] : 0;
            $stats['moyenne_poids_t'] = $stats['total'] > 0 ? ($poids_total / 1000) / $stats['total'] : 0;
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des données: " . $e->getMessage();
}

// Export en Excel (PhpSpreadsheet)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Titres des colonnes selon le type
    $headers = [];
    switch ($filtre_type) {
        case 'camions_entrants':
            $headers = ['Immatriculation', 'Chauffeur', 'Téléphone', 'NIF', 'Date Entrée', 'Provenance', 'PTAV', 'PTAC', 'PTRA', 'Poids Total (kg)', 'NB Marchandises'];
            break;
        case 'camions_sortis':
            $headers = ['Immatriculation', 'Chauffeur', 'Téléphone', 'NIF', 'Date Sortie', 'Type Sortie', 'Destination', 'PTAV', 'PTAC', 'PTRA', 'Poids Total (kg)', 'NB Marchandises'];
            break;
        case 'bateaux_entrants':
            $headers = ['Nom Navire', 'Immatriculation', 'Capitaine', 'Date Entrée', 'Provenance', 'Type Bateau', 'État', 'NB Marchandises'];
            break;
        case 'bateaux_sortis':
            $headers = ['Nom Navire', 'Immatriculation', 'Capitaine', 'Date Sortie', 'Port Destination', 'Type Bateau', 'État', 'NB Marchandises'];
            break;
        case 'marchandises_entrees':
            $headers = ['Type Marchandise', 'Poids (kg)', 'Poids (t)', 'Engin', 'Nom Navire/Camion', 'Immatriculation', 'Opérateur', 'Date Entrée', 'Port', 'Note'];
            break;
        case 'marchandises_sorties':
            $headers = ['Type Marchandise', 'Poids (kg)', 'Poids (t)', 'Engin', 'Nom Navire/Camion', 'Immatriculation', 'Opérateur', 'Date Sortie', 'Port', 'Surcharge', 'Note'];
            break;
    }
    
    // Titre du document
    $titres = [
        'camions_entrants' => 'Rapport des Camions Entrés',
        'camions_sortis' => 'Rapport des Camions Sortis',
        'bateaux_entrants' => 'Rapport des Bateaux Entrés',
        'bateaux_sortis' => 'Rapport des Bateaux Sortis',
        'marchandises_entrees' => 'Rapport des Marchandises Entrées',
        'marchandises_sorties' => 'Rapport des Marchandises Sorties'
    ];
    
    $sheet->setCellValue('A1', $titres[$filtre_type]);
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Sous-titre (période)
    $sheet->setCellValue('A2', 'Période: ' . date('d/m/Y', strtotime($filtre_date_debut)) . ' - ' . date('d/m/Y', strtotime($filtre_date_fin)));
    $sheet->mergeCells('A2:' . Coordinate::stringFromColumnIndex(count($headers)) . '2');
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Ajouter les filtres appliqués (uniquement pour les marchandises)
    if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
        $filtres_text = 'Filtres appliqués: ';
        $filtres_array = [];
        
        if ($filtre_marchandise) {
            $stmt_march = $conn->prepare("SELECT nom FROM type_marchandise WHERE id = ?");
            $stmt_march->bind_param("i", $filtre_marchandise);
            $stmt_march->execute();
            $result_march = $stmt_march->get_result();
            if ($row_march = $result_march->fetch_assoc()) {
                $filtres_array[] = 'Type: ' . $row_march['nom'];
            }
            $stmt_march->close();
        }
        
        if ($filtre_origine) {
            $origine_text = ($filtre_origine == 'camion') ? 'Camion' : 'Bateau';
            $filtres_array[] = 'Moyen de transport: ' . $origine_text;
        }
        
        if ($filtre_port) {
            $stmt_port = $conn->prepare("SELECT nom FROM port WHERE id = ?");
            $stmt_port->bind_param("i", $filtre_port);
            $stmt_port->execute();
            $result_port = $stmt_port->get_result();
            if ($row_port = $result_port->fetch_assoc()) {
                $filtres_array[] = 'Port: ' . $row_port['nom'];
            }
            $stmt_port->close();
        }
        
        if ($filtre_immatriculation) {
            $filtres_array[] = 'Immatriculation: ' . $filtre_immatriculation;
        }
        
        if ($filtre_chauffeur) {
            $filtres_array[] = 'Opérateur: ' . $filtre_chauffeur;
        }
        
        if (!empty($filtres_array)) {
            $filtres_text .= implode(', ', $filtres_array);
            $sheet->setCellValue('A3', $filtres_text);
            $sheet->mergeCells('A3:' . Coordinate::stringFromColumnIndex(count($headers)) . '3');
            $sheet->getStyle('A3')->getFont()->setItalic(true);
            $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $startRow = 4; // Commencer les en-têtes à la ligne 4
        } else {
            $startRow = 3; // Commencer les en-têtes à la ligne 3
        }
    } else {
        $startRow = 3;
    }
    
    // En-têtes
    $colIndex = 1;
    foreach ($headers as $header) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($colLetter . $startRow, $header);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        $colIndex++;
    }
    
    // Style des en-têtes
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    
    $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A' . $startRow . ':' . $lastColLetter . $startRow)->applyFromArray($headerStyle);
    
    // Remplir les données
    $row = $startRow + 1;
    foreach ($resultats as $data) {
        $colIndex = 1;
        
        switch ($filtre_type) {
            case 'camions_entrants':
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['immatriculation']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['prenom_chauffeur'] . ' ' . $data['nom_chauffeur']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['telephone_chauffeur'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nif_chauffeur'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, date('d/m/Y H:i', strtotime($data['date_entree'])));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['port_nom'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['ptav'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['ptac'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['ptra'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['poids_total_camion'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nb_marchandises'] ?? 0);
                break;
                
            case 'camions_sortis':
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['immatriculation']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['prenom_chauffeur'] . ' ' . $data['nom_chauffeur']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['telephone_chauffeur'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nif_chauffeur'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, date('d/m/Y H:i', strtotime($data['date_sortie'])));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['port_nom'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['ptav'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['ptac'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['ptra'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['poids_total_camion'] ?? 0);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nb_marchandises'] ?? 0);
                break;
                
            case 'bateaux_entrants':
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nom_navire']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['immatriculation']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['prenom_capitaine'] . ' ' . $data['nom_capitaine']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, date('d/m/Y H:i', strtotime($data['date_entree'])));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['port_nom'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['type_bateau'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['etat']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nb_marchandises'] ?? 0);
                break;
                
            case 'bateaux_sortis':
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nom_navire']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['immatriculation']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['prenom_capitaine'] . ' ' . $data['nom_capitaine']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, date('d/m/Y H:i', strtotime($data['date_sortie'])));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['port_nom'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['type_bateau'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['etat']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nb_marchandises'] ?? 0);
                break;
                
            case 'marchandises_entrees':
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['type_marchandise']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, number_format($data['poids'], 2));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, number_format($data['poids'] / 1000, 2));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['origine'] == 'camion' ? 'Camion' : 'Bateau');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nom_navire']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['immatriculation']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['operateur']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, date('d/m/Y H:i', strtotime($data['date_operation'])));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['port_nom'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['note'] ?? '');
                break;
                
            case 'marchandises_sorties':
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['type_marchandise']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, number_format($data['poids'], 2));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, number_format($data['poids'] / 1000, 2));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['origine'] == 'camion' ? 'Camion' : 'Bateau');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['nom_navire']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['immatriculation']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['operateur']);
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, date('d/m/Y H:i', strtotime($data['date_operation'])));
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['port_nom'] ?? '');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, isset($data['surcharge']) && $data['surcharge'] ? 'Oui' : 'Non');
                
                $colLetter = Coordinate::stringFromColumnIndex($colIndex++);
                $sheet->setCellValue($colLetter . $row, $data['note'] ?? '');
                break;
        }
        $row++;
    }
    
    // Style des bordures pour toutes les données
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];
    
    $lastDataRow = $row - 1;
    $sheet->getStyle('A' . $startRow . ':' . $lastColLetter . $lastDataRow)->applyFromArray($dataStyle);
    
    // Ajouter les statistiques en bas
    $sheet->setCellValue('A' . ($row+1), 'Statistiques:');
    $sheet->getStyle('A' . ($row+1))->getFont()->setBold(true);
    $sheet->setCellValue('A' . ($row+2), 'Total: ' . $stats['total']);
    
    if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties', 'camions_entrants', 'camions_sortis'])) {
        $sheet->setCellValue('A' . ($row+3), 'Poids total: ' . number_format($stats['poids_total_kg'], 2) . ' kg (' . number_format($stats['poids_total_t'], 2) . ' t)');
    }
    
    // Sauvegarder le fichier
    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="rapport_' . $filtre_type . '_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
}

// Export en PDF (TCPDF)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Créer une nouvelle instance de TCPDF
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator('Port Management System');
    $pdf->SetAuthor('Port Management System');
    $pdf->SetTitle('Rapport ' . $filtre_type);
    $pdf->SetSubject('Rapport d\'activité');
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Titres
    $titres = [
        'camions_entrants' => 'Rapport des Camions Entrés',
        'camions_sortis' => 'Rapport des Camions Sortis',
        'bateaux_entrants' => 'Rapport des Bateaux Entrés',
        'bateaux_sortis' => 'Rapport des Bateaux Sortis',
        'marchandises_entrees' => 'Rapport des Marchandises Entrées',
        'marchandises_sorties' => 'Rapport des Marchandises Sorties'
    ];
    
    // Construire le titre avec les filtres pour les marchandises
    $titre_complet = $titres[$filtre_type];
    
    // Ajouter les détails des filtres appliqués pour les marchandises
    if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])) {
        $filtres_details = [];
        
        if ($filtre_marchandise) {
            $stmt_march = $conn->prepare("SELECT nom FROM type_marchandise WHERE id = ?");
            $stmt_march->bind_param("i", $filtre_marchandise);
            $stmt_march->execute();
            $result_march = $stmt_march->get_result();
            if ($row_march = $result_march->fetch_assoc()) {
                $filtres_details[] = 'Type ' . $row_march['nom'];
            }
            $stmt_march->close();
        }
        
        if ($filtre_origine) {
            $origine_text = ($filtre_origine == 'camion') ? 'Camion' : 'Bateau';
            $filtres_details[] = 'Transport ' . $origine_text;
        }
        
        if ($filtre_port) {
            $stmt_port = $conn->prepare("SELECT nom FROM port WHERE id = ?");
            $stmt_port->bind_param("i", $filtre_port);
            $stmt_port->execute();
            $result_port = $stmt_port->get_result();
            if ($row_port = $result_port->fetch_assoc()) {
                $filtres_details[] = 'Port ' . $row_port['nom'];
            }
            $stmt_port->close();
        }
        
        if ($filtre_immatriculation) {
            $filtres_details[] = 'Immat. ' . $filtre_immatriculation;
        }
        
        if ($filtre_chauffeur) {
            $filtres_details[] = 'Opérateur ' . $filtre_chauffeur;
        }
        
        if (!empty($filtres_details)) {
            $titre_complet .= ' - ' . implode(', ', $filtres_details);
        }
    }
    
    // Titre principal
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, "PORT DE BUJUMBURA", 0, 1, 'L');
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 10, $titre_complet, 0, 1, 'L');
    
    // Sous-titre (période)
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 10, 'Période: ' . date('d/m/Y', strtotime($filtre_date_debut)) . ' - ' . date('d/m/Y', strtotime($filtre_date_fin)), 0, 1, 'L', 0, '', true, 0, false, 'T', 'M');
    $pdf->SetTextColor(0, 0, 0);
    
    
    $pdf->Ln(2);
    
    // En-têtes de tableau
    $headers = [];
    $colWidths = [];
    
    switch ($filtre_type) {
        case 'camions_entrants':
            $headers = ['Immatriculation', 'Chauffeur', 'Téléphone', 'NIF', 'Date Entrée', 'Provenance', 'PTAV', 'PTAC', 'PTRA', 'Poids Total', 'Nb March.'];
            $colWidths = [25, 30, 22, 18, 22, 25, 18, 18, 18, 22, 18];
            break;
        case 'camions_sortis':
            $headers = ['Immatriculation', 'Chauffeur', 'Téléphone', 'NIF', 'Date Sortie', 'Type Sortie', 'Destination', 'PTAV', 'PTAC', 'PTRA', 'Poids Total', 'Nb March.'];
            $colWidths = [23, 28, 20, 16, 20, 18, 23, 16, 16, 16, 20, 16];
            break;
        case 'bateaux_entrants':
            $headers = ['Nom Navire', 'Immatriculation', 'Capitaine', 'Date Entrée', 'Provenance', 'Type', 'État', 'Nb March.'];
            $colWidths = [35, 30, 35, 25, 25, 22, 18, 18];
            break;
        case 'bateaux_sortis':
            $headers = ['Nom Navire', 'Immatriculation', 'Capitaine', 'Date Sortie', 'Destination', 'Type', 'État', 'Nb March.'];
            $colWidths = [35, 30, 35, 25, 25, 22, 18, 18];
            break;
        case 'marchandises_entrees':
            $headers = ['Type March.', 'Poids (kg)', 'Poids (t)', 'Engin', 'Nom', 'Immatriculation', 'Opérateur', 'Date', 'Port', 'Note'];
            $colWidths = [30, 22, 22, 18, 30, 25, 30, 22, 22, 25];
            break;
        case 'marchandises_sorties':
            $headers = ['Type March.', 'Poids (kg)', 'Poids (t)', 'Engin', 'Nom', 'Immatriculation', 'Opérateur', 'Date', 'Port', 'Surcharge', 'Note'];
            $colWidths = [28, 20, 20, 16, 28, 25, 28, 20, 20, 18, 22];
            break;
    }
    
    // Dessiner l'en-tête du tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 9);
    
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    
    foreach ($headers as $i => $header) {
        $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
    }
    
    $pdf->Ln();
    
    // Données
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 8);
    
    $fill = false;
    $fillColor = array(245, 245, 245);
    
    foreach ($resultats as $row) {
        // Alterner les couleurs de fond
        if ($fill) {
            $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $colIndex = 0;
        
        switch ($filtre_type) {
            case 'camions_entrants':
                $pdf->Cell($colWidths[$colIndex++], 6, $row['immatriculation'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['prenom_chauffeur'] . ' ' . $row['nom_chauffeur'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['telephone_chauffeur'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nif_chauffeur'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, date('d/m/Y H:i', strtotime($row['date_entree'])), 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['port_nom'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['ptav'] ?? 0, 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['ptac'] ?? 0, 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['ptra'] ?? 0, 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, number_format($row['poids_total_camion'] ?? 0, 2), 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nb_marchandises'] ?? 0, 1, 0, 'C', $fill);
                break;
            case 'camions_sortis':
                $pdf->Cell($colWidths[$colIndex++], 6, $row['immatriculation'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['prenom_chauffeur'] . ' ' . $row['nom_chauffeur'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['telephone_chauffeur'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nif_chauffeur'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, date('d/m/Y H:i', strtotime($row['date_sortie'])), 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé', 1, 0, 'C', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['port_nom'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['ptav'] ?? 0, 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['ptac'] ?? 0, 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['ptra'] ?? 0, 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, number_format($row['poids_total_camion'] ?? 0, 2), 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nb_marchandises'] ?? 0, 1, 0, 'C', $fill);
                break;
            case 'bateaux_entrants':
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nom_navire'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['immatriculation'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['prenom_capitaine'] . ' ' . $row['nom_capitaine'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, date('d/m/Y H:i', strtotime($row['date_entree'])), 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['port_nom'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['type_bateau'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['etat'], 1, 0, 'C', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nb_marchandises'] ?? 0, 1, 0, 'C', $fill);
                break;
            case 'bateaux_sortis':
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nom_navire'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['immatriculation'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['prenom_capitaine'] . ' ' . $row['nom_capitaine'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, date('d/m/Y H:i', strtotime($row['date_sortie'])), 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['port_nom'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['type_bateau'] ?? '', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['etat'], 1, 0, 'C', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nb_marchandises'] ?? 0, 1, 0, 'C', $fill);
                break;
            case 'marchandises_entrees':
                $pdf->Cell($colWidths[$colIndex++], 6, $row['type_marchandise'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, number_format($row['poids'], 2), 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, number_format($row['poids'] / 1000, 2), 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['origine'] == 'camion' ? 'Camion' : 'Bateau', 1, 0, 'C', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nom_navire'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['immatriculation'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['operateur'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, date('d/m/Y H:i', strtotime($row['date_operation'])), 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['port_nom'] ?? 'N/A', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['note'] ?? '', 1, 0, 'L', $fill);
                break;
            case 'marchandises_sorties':
                $pdf->Cell($colWidths[$colIndex++], 6, $row['type_marchandise'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, number_format($row['poids'], 2), 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, number_format($row['poids'] / 1000, 2), 1, 0, 'R', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['origine'] == 'camion' ? 'Camion' : 'Bateau', 1, 0, 'C', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['nom_navire'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['immatriculation'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['operateur'], 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, date('d/m/Y H:i', strtotime($row['date_operation'])), 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['port_nom'] ?? 'N/A', 1, 0, 'L', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, isset($row['surcharge']) && $row['surcharge'] ? 'Oui' : 'Non', 1, 0, 'C', $fill);
                $pdf->Cell($colWidths[$colIndex++], 6, $row['note'] ?? '', 1, 0, 'L', $fill);
                break;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        
        // Vérifier si on a besoin d'une nouvelle page
        if ($pdf->GetY() > 190) {
            $pdf->AddPage();
            // Redessiner les en-têtes
            $pdf->SetFillColor(44, 62, 80);
            $pdf->SetTextColor(255);
            $pdf->SetFont('helvetica', 'B', 9);
            
            foreach ($headers as $i => $header) {
                $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
            }
            
            $pdf->Ln();
            $pdf->SetTextColor(0);
            $pdf->SetFont('helvetica', '', 8);
            $fill = false;
        }
    }
    
    // Générer le PDF
    $pdf->Output('Rapport_' . $filtre_type . '_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Historique et Rapports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .scrollable-table {
            max-height: 65vh;
            overflow-y: auto;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            background: none;
            cursor: pointer;
            position: relative;
        }
        
        .tab-button.active {
            color: #3b82f6;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo safe_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Onglets et filtres -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="flex border-b overflow-x-auto">
                <button type="button" 
                        data-tab="camions_entrants" 
                        class="tab-button <?php echo $filtre_type === 'camions_entrants' ? 'active' : ''; ?> whitespace-nowrap">
                    <i class="fas fa-truck-moving mr-2"></i>Camions Entrés
                </button>
                <button type="button" 
                        data-tab="camions_sortis" 
                        class="tab-button <?php echo $filtre_type === 'camions_sortis' ? 'active' : ''; ?> whitespace-nowrap">
                    <i class="fas fa-truck-loading mr-2"></i>Camions Sortis
                </button>
                <button type="button" 
                        data-tab="bateaux_entrants" 
                        class="tab-button <?php echo $filtre_type === 'bateaux_entrants' ? 'active' : ''; ?> whitespace-nowrap">
                    <i class="fas fa-ship mr-2"></i>Bateaux Entrés
                </button>
                <button type="button" 
                        data-tab="bateaux_sortis" 
                        class="tab-button <?php echo $filtre_type === 'bateaux_sortis' ? 'active' : ''; ?> whitespace-nowrap">
                    <i class="fas fa-anchor mr-2"></i>Bateaux Sortis
                </button>
                <button type="button" 
                        data-tab="marchandises_entrees" 
                        class="tab-button <?php echo $filtre_type === 'marchandises_entrees' ? 'active' : ''; ?> whitespace-nowrap">
                    <i class="fas fa-box-import mr-2"></i>Marchandises Entrées
                </button>
                <button type="button" 
                        data-tab="marchandises_sorties" 
                        class="tab-button <?php echo $filtre_type === 'marchandises_sorties' ? 'active' : ''; ?> whitespace-nowrap">
                    <i class="fas fa-box-export mr-2"></i>Marchandises Sorties
                </button>
            </div>
            
            <div class="p-4">
                <form method="GET" id="filtresForm" class="space-y-4">
                    <input type="hidden" name="type" id="type_input" value="<?php echo $filtre_type; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Période -->
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="date_debut">
                                Date début
                            </label>
                            <input type="date" id="date_debut" name="date_debut" 
                                   value="<?php echo safe_html($filtre_date_debut); ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="date_fin">
                                Date fin
                            </label>
                            <input type="date" id="date_fin" name="date_fin" 
                                   value="<?php echo safe_html($filtre_date_fin); ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Filtres spécifiques -->
                        <?php if (in_array($filtre_type, ['camions_entrants', 'camions_sortis', 'marchandises_entrees', 'marchandises_sorties'])): ?>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-1" for="immatriculation">
                                    Immatriculation/Nom
                                </label>
                                <input type="text" id="immatriculation" name="immatriculation" 
                                       value="<?php echo safe_html($filtre_immatriculation); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="BUR-XXXX ou Nom navire">
                            </div>
                            
                            <?php if (in_array($filtre_type, ['camions_entrants', 'camions_sortis'])): ?>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="chauffeur">
                                        Chauffeur
                                    </label>
                                    <input type="text" id="chauffeur" name="chauffeur" 
                                           value="<?php echo safe_html($filtre_chauffeur); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Nom du chauffeur">
                                </div>
                            <?php else: ?>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="chauffeur">
                                        Opérateur
                                    </label>
                                    <input type="text" id="chauffeur" name="chauffeur" 
                                           value="<?php echo safe_html($filtre_chauffeur); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Nom du chauffeur/capitaine">
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if (in_array($filtre_type, ['camions_entrants', 'camions_sortis', 'bateaux_entrants', 'bateaux_sortis', 'marchandises_entrees', 'marchandises_sorties'])): ?>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-1" for="port">
                                    Provenance/Destination
                                </label>
                                <select id="port" name="port" 
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Tous les ports</option>
                                    <?php foreach ($ports as $port): ?>
                                        <option value="<?php echo $port['id']; ?>" <?php echo $filtre_port == $port['id'] ? 'selected' : ''; ?>>
                                            <?php echo safe_html($port['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (in_array($filtre_type, ['camions_entrants', 'camions_sortis'])): ?>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="etat">
                                        État du camion
                                    </label>
                                    <select id="etat" name="etat" 
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Tous les états</option>
                                        <option value="Chargé" <?php echo $filtre_etat == 'Chargé' ? 'selected' : ''; ?>>Chargé</option>
                                        <option value="Vide" <?php echo $filtre_etat == 'Vide' ? 'selected' : ''; ?>>Vide</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (in_array($filtre_type, ['marchandises_entrees', 'marchandises_sorties'])): ?>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-1" for="marchandise">
                                    Type de marchandise
                                </label>
                                <select id="marchandise" name="marchandise" 
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Tous les types</option>
                                    <?php foreach ($types_marchandises as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" <?php echo $filtre_marchandise == $type['id'] ? 'selected' : ''; ?>>
                                            <?php echo safe_html($type['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-1" for="origine">
                                    Moyen de transport
                                </label>
                                <select id="origine" name="origine" 
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Toutes origines</option>
                                    <option value="camion" <?php echo $filtre_origine == 'camion' ? 'selected' : ''; ?>>Camion</option>
                                    <option value="bateau" <?php echo $filtre_origine == 'bateau' ? 'selected' : ''; ?>>Bateau</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <div>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg">
                                <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                            </button>
                            <a href="rapports.php" class="ml-2 text-gray-600 hover:text-gray-800 font-medium py-2 px-4 rounded-lg">
                                <i class="fas fa-times mr-2"></i>Réinitialiser
                            </a>
                        </div>
                        
                        <?php if (!empty($resultats)): ?>
                            <div class="flex space-x-2">
                                <a href="rapports.php?<?php echo http_build_query($_GET); ?>&export=excel" 
                                   class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-lg">
                                    <i class="fas fa-file-excel mr-2"></i>Exporter Excel
                                </a>
                                <a href="rapports.php?<?php echo http_build_query($_GET); ?>&export=pdf" 
                                   class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg">
                                    <i class="fas fa-file-pdf mr-2"></i>Exporter PDF
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tableau des résultats -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">
                        <?php 
                        $titres = [
                            'camions_entrants' => 'Camions Entrés',
                            'camions_sortis' => 'Camions Sortis',
                            'bateaux_entrants' => 'Bateaux Entrés',
                            'bateaux_sortis' => 'Bateaux Sortis',
                            'marchandises_entrees' => 'Marchandises Entrées',
                            'marchandises_sorties' => 'Marchandises Sorties'
                        ];
                        echo $titres[$filtre_type];
                        ?>
                    </h2>
                    <p class="text-sm text-gray-600"><?php echo $stats['total']; ?> résultat(s) trouvé(s)</p>
                </div>
                
                <div class="text-sm text-gray-500">
                    <?php if (!empty($resultats)): ?>
                        <span class="font-medium">Dernière mise à jour: <?php echo date('d/m/Y H:i'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="scrollable-table">
                <?php if (empty($resultats)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Aucun résultat trouvé avec les filtres actuels</p>
                        <p class="text-sm text-gray-400 mt-2">Essayez de modifier vos critères de recherche</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="sticky-header bg-gray-50">
                            <tr>
                                <?php 
                                // En-têtes selon le type
                                switch ($filtre_type) {
                                    case 'camions_entrants':
                                        echo '
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIF</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provenance</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAV</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAC</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTRA</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marchandises</th>
                                        ';
                                        break;
                                    
                                    case 'camions_sortis':
                                        echo '
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIF</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sortie</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Sortie</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAV</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAC</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTRA</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marchandises</th>
                                        ';
                                        break;
                                    
                                    case 'bateaux_entrants':
                                        echo '
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom Navire</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capitaine</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marchandises</th>
                                        ';
                                        break;
                                    
                                    case 'bateaux_sortis':
                                        echo '
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom Navire</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capitaine</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sortie</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port Destination</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marchandises</th>
                                        ';
                                        break;
                                    
                                    case 'marchandises_entrees':
                                        echo '
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Marchandise</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyen de transport</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom Navire/Camion</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opérateur</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provenance/Destination</th>
                                        ';
                                        break;
                                    
                                    case 'marchandises_sorties':
                                        echo '
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Marchandise</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyen de transport</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom Navire/Camion</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opérateur</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sortie</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provenance/Destination</th>
                                        ';
                                        break;
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($resultats as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <?php 
                                    switch ($filtre_type) {
                                        case 'camions_entrants':
                                            echo '
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                            <i class="fas fa-truck text-blue-600 text-sm"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">' . safe_html($row['immatriculation']) . '</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['prenom_chauffeur'] . ' ' . $row['nom_chauffeur']) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['telephone_chauffeur'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['nif_chauffeur'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . date('d/m/Y H:i', strtotime($row['date_entree'])) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['port_nom'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . number_format($row['ptav'] ?? 0, 2) . ' kg
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . number_format($row['ptac'] ?? 0, 2) . ' kg
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . number_format($row['ptra'] ?? 0, 2) . ' kg
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="font-medium">' . number_format($row['poids_total_camion'] ?? 0, 2) . ' kg</div>
                                                    <div class="text-xs text-gray-400">' . number_format(($row['poids_total_camion'] ?? 0) / 1000, 2) . ' t</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . ($row['nb_marchandises'] ?? 0) . '
                                                </td>
                                            ';
                                            break;
                                        
                                        case 'camions_sortis':
                                            $poids_total = isset($row['poids_total_camion']) ? $row['poids_total_camion'] : 0;
                                            $type_sortie = isset($row['type_sortie']) ? ($row['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé') : '';
                                            echo '
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
                                                            <i class="fas fa-truck-loading text-green-600 text-sm"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">' . safe_html($row['immatriculation']) . '</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['prenom_chauffeur'] . ' ' . $row['nom_chauffeur']) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['telephone_chauffeur'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['nif_chauffeur'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . date('d/m/Y H:i', strtotime($row['date_sortie'])) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . 
                                                    ($row['type_sortie'] == 'charge' ? 'bg-yellow-100 text-yellow-800' : 'bg-purple-100 text-purple-800') . '">
                                                        ' . $type_sortie . '
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['port_nom'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . number_format($row['ptav'] ?? 0, 2) . ' kg
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . number_format($row['ptac'] ?? 0, 2) . ' kg
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . number_format($row['ptra'] ?? 0, 2) . ' kg
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="font-medium">' . number_format($poids_total, 2) . ' kg</div>
                                                    <div class="text-xs text-gray-400">' . number_format($poids_total / 1000, 2) . ' t</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . ($row['nb_marchandises'] ?? 0) . '
                                                </td>
                                            ';
                                            break;
                                        
                                        case 'bateaux_entrants':
                                            echo '
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                            <i class="fas fa-ship text-blue-600 text-sm"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">' . safe_html($row['nom_navire']) . '</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['immatriculation'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['prenom_capitaine'] . ' ' . $row['nom_capitaine']) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . date('d/m/Y H:i', strtotime($row['date_entree'])) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['port_nom'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . 
                                                    ($row['etat'] == 'vide' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') . '">
                                                        ' . ucfirst(safe_html($row['etat'])) . '
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . ($row['nb_marchandises'] ?? 0) . '
                                                </td>
                                            ';
                                            break;
                                        
                                        case 'bateaux_sortis':
                                            echo '
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
                                                            <i class="fas fa-ship text-green-600 text-sm"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">' . safe_html($row['nom_navire']) . '</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['immatriculation'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['prenom_capitaine'] . ' ' . $row['nom_capitaine']) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . date('d/m/Y H:i', strtotime($row['date_sortie'])) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['port_nom'] ?? '') . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . 
                                                    ($row['etat'] == 'vide' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') . '">
                                                        ' . ucfirst(safe_html($row['etat'])) . '
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . ($row['nb_marchandises'] ?? 0) . '
                                                </td>
                                            ';
                                            break;
                                        
                                        case 'marchandises_entrees':
                                        case 'marchandises_sorties':
                                            $origine_icon = $row['origine'] === 'camion' ? 'fa-truck' : 'fa-ship';
                                            $origine_color = $row['origine'] === 'camion' ? 'bg-yellow-100 text-yellow-800' : 'bg-purple-100 text-purple-800';
                                            $origine_text = $row['origine'] === 'camion' ? 'Camion' : 'Bateau';
                                            echo '
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-medium text-gray-900">' . safe_html($row['type_marchandise']) . '</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="font-medium">' . number_format($row['poids'], 2) . ' kg</div>
                                                    <div class="text-xs text-gray-400">' . number_format($row['poids'] / 1000, 2) . ' t</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $origine_color . '">
                                                        <i class="fas ' . $origine_icon . ' mr-1"></i>' . $origine_text . '
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    ' . safe_html($row['nom_navire']) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['operateur']) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . date('d/m/Y H:i', strtotime($row['date_operation'])) . '
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ' . safe_html($row['port_nom'] ?? 'N/A') . '
                                                </td>
                                            ';
                                            break;
                                    }
                                    ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($resultats)): ?>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Affichage de <span class="font-medium">1</span> à 
                            <span class="font-medium"><?php echo min(count($resultats), count($resultats)); ?></span> sur 
                            <span class="font-medium"><?php echo $stats['total']; ?></span> résultats
                        </div>
                        <div class="text-sm text-gray-700">
                            <span class="font-medium">Période :</span> 
                            <?php echo date('d/m/Y', strtotime($filtre_date_debut)); ?> - 
                            <?php echo date('d/m/Y', strtotime($filtre_date_fin)); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Fonction pour changer d'onglet
        function changerOnglet(type) {
            document.getElementById('type_input').value = type;
            
            // Soumettre le formulaire
            document.getElementById('filtresForm').submit();
        }
        
        // Événements
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des onglets
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', function() {
                    const type = this.getAttribute('data-tab');
                    changerOnglet(type);
                });
            });
            
            // Configuration des dates par défaut
            const aujourdhui = new Date();
            const premierDuMois = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth(), 1);
            const dernierDuMois = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth() + 1, 0);
            
            // Formater les dates pour les inputs
            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            // Si les dates sont vides, les remplir avec le mois en cours
            const dateDebut = document.getElementById('date_debut');
            const dateFin = document.getElementById('date_fin');
            
            if (dateDebut && !dateDebut.value) {
                dateDebut.value = formatDate(premierDuMois);
            }
            if (dateFin && !dateFin.value) {
                dateFin.value = formatDate(dernierDuMois);
            }
            
            // Validation : date fin ne peut pas être avant date début
            if (dateDebut) {
                dateDebut.addEventListener('change', function() {
                    if (dateFin && dateFin.value && dateFin.value < this.value) {
                        dateFin.value = this.value;
                    }
                });
            }
            
            if (dateFin) {
                dateFin.addEventListener('change', function() {
                    if (dateDebut && dateDebut.value && dateDebut.value > this.value) {
                        dateDebut.value = this.value;
                    }
                });
            }
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