<?php
session_start();
require_once '../../config/db_connect.php';

// Vérifier la session
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentDouane') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Récupérer les paramètres
$type = $_GET['type'] ?? null;
$id = $_GET['id'] ?? null;

if (!$type || !$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

try {
    $data = [];
    
    switch ($type) {
        case 'camion_entrant':
            $query = "SELECT ce.*, tc.nom as type_camion
                      FROM camions_entrants ce
                      LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                    --   LEFT JOIN type_marchandise tm ON ce.id_type_marchandise = tm.id
                      WHERE ce.idEntree = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            break;
            
        case 'camion_sortant':
            $query = "SELECT cs.*, ce.immatriculation, ce.nom_conducteur, ce.prenom_conducteur, 
                             ce.telephone_conducteur, tc.nom as type_camion
                      FROM camions_sortants cs
                      LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
                    --   LEFT JOIN type_marchandise tm ON ce.id_type_marchandise = tm.id
                      LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                      WHERE cs.idSortie = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            break;
            
        case 'bateau_entrant':
            $query = "SELECT be.*, tb.nom as type_bateau, p.nom as port_nom
                      FROM bateau_entrant be
                      LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
                      LEFT JOIN port p ON be.id_port = p.id
                      WHERE be.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            break;
            
        case 'bateau_sortant':
            $query = "SELECT bs.*, tb.nom as type_bateau, p.nom as destination_port_nom
                      FROM bateau_sortant bs
                      LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
                      LEFT JOIN port p ON bs.id_destination_port = p.id
                      WHERE bs.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            break;
    }
    
    if (empty($data)) {
        http_response_code(404);
        echo json_encode(['error' => 'Données non trouvées']);
        exit();
    }
    
    // Retourner les données en JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>