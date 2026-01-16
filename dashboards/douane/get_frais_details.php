<?php
session_start();
require_once '../../config/db_connect.php'; // Ajustez le chemin selon votre structure

// Vérifier l'authentification
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentDouane') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit();
}

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit();
}

$id = intval($_GET['id']);

try {
    $query = "SELECT 
                ft.*,
                CASE 
                    WHEN ft.type_entite = 'camion_entrant' THEN ce.immatriculation
                    WHEN ft.type_entite = 'camion_sortant' THEN cs_entrants.immatriculation
                    WHEN ft.type_entite = 'bateau_entrant' THEN be.nom_navire
                    WHEN ft.type_entite = 'bateau_sortant' THEN bs.nom_navire
                END as entite_nom,
                CASE 
                    WHEN ft.type_entite = 'camion_entrant' THEN CONCAT(ce.nom_chauffeur, ' ', ce.prenom_chauffeur)
                    WHEN ft.type_entite = 'camion_sortant' THEN CONCAT(cs_entrants.nom_chauffeur, ' ', cs_entrants.prenom_chauffeur)
                    WHEN ft.type_entite = 'bateau_entrant' THEN CONCAT(be.nom_capitaine, ' ', be.prenom_capitaine)
                    WHEN ft.type_entite = 'bateau_sortant' THEN CONCAT(bs.nom_capitaine, ' ', bs.prenom_capitaine)
                END as personne,
                CASE 
                    WHEN ft.type_entite = 'camion_entrant' THEN ce.date_entree
                    WHEN ft.type_entite = 'camion_sortant' THEN cs.date_sortie
                    WHEN ft.type_entite = 'bateau_entrant' THEN be.date_entree
                    WHEN ft.type_entite = 'bateau_sortant' THEN bs.date_sortie
                END as date_operation
              FROM frais_transit ft
              LEFT JOIN camions_entrants ce ON ft.type_entite = 'camion_entrant' AND ft.id_entite = ce.idEntree
              LEFT JOIN camions_sortants cs ON ft.type_entite = 'camion_sortant' AND ft.id_entite = cs.idSortie
              LEFT JOIN camions_entrants cs_entrants ON cs.idEntree = cs_entrants.idEntree
              LEFT JOIN bateau_entrant be ON ft.type_entite = 'bateau_entrant' AND ft.id_entite = be.id
              LEFT JOIN bateau_sortant bs ON ft.type_entite = 'bateau_sortant' AND ft.id_entite = bs.id
              WHERE ft.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $frais = $result->fetch_assoc();
        // Convertir les valeurs NULL en 0
        $frais['frais_thc'] = $frais['frais_thc'] ?? 0;
        $frais['frais_magasinage'] = $frais['frais_magasinage'] ?? 0;
        $frais['droits_douane'] = $frais['droits_douane'] ?? 0;
        $frais['surestaries'] = $frais['surestaries'] ?? 0;
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'frais' => $frais]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Frais non trouvé']);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
?>