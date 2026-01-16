<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php';

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeCamion') {
    header("Location: ../../login.php");
    exit();
}

// Fonction pour nettoyer les données
function cleanData($data) {
    return htmlspecialchars($data ?? '-');
}

// Récupérer les filtres depuis l'URL
$filters = [
    'etat' => $_GET['etat'] ?? '',
    'raison' => $_GET['raison'] ?? '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? '',
    'immatriculation' => $_GET['immatriculation'] ?? '',
    'chauffeur' => $_GET['chauffeur'] ?? '',
    'port' => $_GET['port'] ?? ''
];

// Construction de la requête SQL avec filtres
$where_conditions = [];
$params = [];
$types = '';

// Filtre par état
if (!empty($filters['etat'])) {
    $where_conditions[] = "ce.etat = ?";
    $params[] = $filters['etat'];
    $types .= 's';
}

// Filtre par raison
if (!empty($filters['raison'])) {
    $where_conditions[] = "ce.raison = ?";
    $params[] = $filters['raison'];
    $types .= 's';
}

// Filtre par période de date
if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(ce.date_entree) BETWEEN ? AND ?";
    $params[] = $filters['date_debut'];
    $params[] = $filters['date_fin'];
    $types .= 'ss';
} elseif (!empty($filters['date_debut'])) {
    $where_conditions[] = "DATE(ce.date_entree) >= ?";
    $params[] = $filters['date_debut'];
    $types .= 's';
} elseif (!empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(ce.date_entree) <= ?";
    $params[] = $filters['date_fin'];
    $types .= 's';
}

// Filtre par immatriculation (recherche partielle)
if (!empty($filters['immatriculation'])) {
    $where_conditions[] = "ce.immatriculation LIKE ?";
    $params[] = '%' . $filters['immatriculation'] . '%';
    $types .= 's';
}

// Filtre par chauffeur (recherche partielle)
if (!empty($filters['chauffeur'])) {
    $where_conditions[] = "(ce.nom_chauffeur LIKE ? OR ce.prenom_chauffeur LIKE ?)";
    $params[] = '%' . $filters['chauffeur'] . '%';
    $params[] = '%' . $filters['chauffeur'] . '%';
    $types .= 'ss';
}

// Filtre par port
if (!empty($filters['port']) && $filters['port'] !== 'all') {
    $where_conditions[] = "ce.idPort = ?";
    $params[] = $filters['port'];
    $types .= 'i';
}

// Construction de la clause WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Récupérer toutes les données (sans pagination pour l'export)
$query = "SELECT ce.*, tc.nom as type_camion, p.nom as port
          FROM camions_entrants ce
          LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
          LEFT JOIN port p ON ce.idPort = p.id
          $where_sql
          ORDER BY ce.date_entree DESC";

try {
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    $camions = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("Erreur lors de la récupération des données: " . $e->getMessage());
}

// Vérifier s'il y a des données
if (empty($camions)) {
    die("Aucune donnée à exporter avec les filtres actuels.");
}

// Créer le PDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Informations du document
$pdf->SetCreator('Système GPB');
$pdf->SetAuthor('Système GPB');
$pdf->SetTitle('Rapport des Camions Entrants');
$pdf->SetSubject('Export PDF des camions entrants');

// En-têtes
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Police par défaut
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Marges
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);

// Ajouter une page
$pdf->AddPage();

// Titre
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Rapport des Camions Entrants', 0, 1, 'C');
$pdf->Ln(5);

// Informations sur les filtres
$pdf->SetFont('helvetica', '', 10);
$filter_text = 'Filtres appliqués: ';
$active_filters = [];

if (!empty($filters['etat'])) $active_filters[] = "État: " . $filters['etat'];
if (!empty($filters['raison'])) $active_filters[] = "Raison: " . $filters['raison'];
if (!empty($filters['date_debut'])) $active_filters[] = "Du: " . $filters['date_debut'];
if (!empty($filters['date_fin'])) $active_filters[] = "Au: " . $filters['date_fin'];
if (!empty($filters['immatriculation'])) $active_filters[] = "Immatriculation: " . $filters['immatriculation'];
if (!empty($filters['chauffeur'])) $active_filters[] = "Chauffeur: " . $filters['chauffeur'];
if (!empty($filters['port']) && $filters['port'] !== 'all') {
    // Récupérer le nom du port
    $port_query = $conn->prepare("SELECT nom FROM port WHERE id = ?");
    $port_query->bind_param("i", $filters['port']);
    $port_query->execute();
    $port_result = $port_query->get_result();
    if ($port_row = $port_result->fetch_assoc()) {
        $active_filters[] = "Port: " . $port_row['nom'];
    }
}

if (empty($active_filters)) {
    $filter_text .= 'Aucun filtre';
} else {
    $filter_text .= implode(' | ', $active_filters);
}

$pdf->MultiCell(0, 5, $filter_text, 0, 'L');
$pdf->Ln(5);

// Statistiques
$total_camions = count($camions);
$stats_text = "Nombre total de camions: $total_camions";
$pdf->Cell(0, 5, $stats_text, 0, 1, 'L');
$pdf->Ln(5);

// Créer le tableau
$pdf->SetFont('helvetica', 'B', 8);

// En-têtes du tableau
$headers = ['Date Entrée', 'Immatriculation', 'Marque', 'Type', 'Chauffeur', 'Téléphone', 'État', 'Raison', 'Poids', 'Provenance', 'Agence', 'NIF', 'Destinataire'];
$widths = [25, 25, 20, 20, 30, 25, 15, 30, 20, 25, 20, 20, 25];

// Dessiner les en-têtes
foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C');
}
$pdf->Ln();

// Données du tableau
$pdf->SetFont('helvetica', '', 7);

foreach ($camions as $camion) {
    $data = [
        date('d/m/Y H:i', strtotime($camion['date_entree'])),
        cleanData($camion['immatriculation']),
        cleanData($camion['marque']),
        cleanData($camion['type_camion']),
        cleanData(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')),
        cleanData($camion['telephone_chauffeur']),
        cleanData($camion['etat']),
        cleanData($camion['raison']),
        $camion['poids'] ? number_format($camion['poids'], 2) . ' kg' : '-',
        cleanData($camion['port']),
        cleanData($camion['agence']),
        cleanData($camion['nif']),
        cleanData($camion['destinataire'])
    ];

    $max_lines = 1;
    foreach ($data as $i => $value) {
        $lines = $pdf->getNumLines($value, $widths[$i]);
        $max_lines = max($max_lines, $lines);
    }

    $height = 5 * $max_lines;

    foreach ($data as $i => $value) {
        $pdf->MultiCell($widths[$i], $height / $max_lines, $value, 1, 'L', false, 0);
    }
    $pdf->Ln();
}

// Date de génération
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Rapport généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');

// Sortie du PDF
$filename = 'rapport_camions_entrants_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'D');
?>