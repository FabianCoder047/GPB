<?php
session_start();
require_once '../../config/db_connect.php';

// Vérifier la session
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurSortieBateau') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Récupérer l'ID du bateau
$bateau_id = $_GET['id'] ?? null;

if (!$bateau_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID du bateau manquant']);
    exit();
}

try {
    // Récupérer les détails du bateau sortant
    $stmt = $conn->prepare("
        SELECT 
            bs.*, 
            tb.nom as type_bateau, 
            p.nom as destination_port_nom,
            p.id as destination_port_id
        FROM bateau_sortant bs
        LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
        LEFT JOIN port p ON bs.id_destination_port = p.id
        WHERE bs.id = ?
    ");
    $stmt->bind_param("i", $bateau_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Bateau non trouvé']);
        exit();
    }
    
    $bateau = $result->fetch_assoc();
    
    // Récupérer les marchandises
    $stmt = $conn->prepare("
        SELECT mbs.*, tm.nom as type_marchandise
        FROM marchandise_bateau_sortant mbs
        LEFT JOIN type_marchandise tm ON mbs.id_type_marchandise = tm.id
        WHERE mbs.id_bateau_sortant = ?
        ORDER BY mbs.date_ajout DESC
    ");
    $stmt->bind_param("i", $bateau_id);
    $stmt->execute();
    $marchandises_result = $stmt->get_result();
    $marchandises = $marchandises_result->fetch_all(MYSQLI_ASSOC);
    
    // Ajouter les marchandises aux données du bateau
    $bateau['marchandises'] = $marchandises;
    
    // Retourner les données en JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($bateau, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>