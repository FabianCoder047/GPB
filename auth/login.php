<?php
session_start();
require_once "../config/db_connect.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);
    $mdp   = $_POST['password'];

    if (empty($email) || empty($mdp)) {
        header("Location: login.php?error=Champs requis");
        exit();
    }

    // Préparation de la requête
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Vérification utilisateur
    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // Vérification mot de passe
        if (password_verify($mdp, $user['mdp'])) {

            // Stockage en session
            $_SESSION['idUser'] = $user['idUser'];
            $_SESSION['nom']    = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['role']   = $user['role'];
            $_SESSION['user']   = $user;

            // Vérifier changement de mot de passe
            if ($user['mcp'] == true) {
                header("Location: ../password-change.php");
                exit();
            }

            // Redirection selon le rôle
            switch ($user['role']) {

                case 'admin':
                    header("Location: ../dashboards/admin/index.php");
                    break;

                case 'autorite':
                    header("Location: ../dashboards/autorite/index.php");
                    break;

                case 'enregistreurEntreeCamion':
                    header("Location: ../dashboards/entree_camion/index.php");
                    break;

                case 'enregistreurSortieCamion':
                    header("Location: ../dashboards/sortie_camion/index.php");
                    break;

                case 'agentBascule':
                    header("Location: ../dashboards/bascule/index.php");
                    break;

                case 'agentEntrepot':
                    header("Location: ../dashboards/entrepot/index.php");
                    break;

                case 'enregistreurEntreeBateau':
                    header("Location: ../dashboards/entree_bateau/index.php");
                    break;

                case 'enregistreurEntreeBateau':
                    header("Location: ../dashboards/sortie_bateau/index.php");
                    break;

                case 'agentDouane':
                    header("Location: ../dashboards/douane/index.php");
                    break;

                default:
                    header("Location: ../login.php?error=Rôle inconnu");
            }

            exit();

        } else {
            header("Location: ../login.php?error=Adresse email ou mot de passe incorrect");
            exit();
        }

    } else {
        header("Location: ../login.php?error=Utilisateur introuvable");
        exit();
    }
}
