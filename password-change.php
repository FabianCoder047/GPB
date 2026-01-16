<?php
session_start();
require_once "config/db_connect.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$message = '';
$error = '';

// Traitement du formulaire de changement de mot de passe
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ancien_mdp = $_POST['ancien_mdp'];
    $nouveau_mdp = $_POST['nouveau_mdp'];
    $confirmer_mdp = $_POST['confirmer_mdp'];
    
    // Validation
    if (empty($ancien_mdp) || empty($nouveau_mdp) || empty($confirmer_mdp)) {
        $error = "Tous les champs sont obligatoires";
    } elseif ($nouveau_mdp !== $confirmer_mdp) {
        $error = "Les nouveaux mots de passe ne correspondent pas";
    } elseif (strlen($nouveau_mdp) < 6) {
        $error = "Le nouveau mot de passe doit contenir au moins 6 caractères";
    } else {
        // Vérifier l'ancien mot de passe
        $stmt = $conn->prepare("SELECT mdp FROM users WHERE idUser = ?");
        $stmt->bind_param("i", $user['idUser']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $db_user = $result->fetch_assoc();
            
            if (password_verify($ancien_mdp, $db_user['mdp'])) {
                // Hasher le nouveau mot de passe
                $hashed_password = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
                
                // Mettre à jour le mot de passe et le flag de changement
                $update_stmt = $conn->prepare("UPDATE users SET mdp = ?, mcp = 0 WHERE idUser = ?");
                $update_stmt->bind_param("si", $hashed_password, $user['idUser']);
                
                if ($update_stmt->execute()) {
                    $message = "Mot de passe changé avec succès !";
                    
                    // Mettre à jour la session
                    $user['mcp'] = 0;
                    $_SESSION['user'] = $user;
                    
                    // Redirection après 2 secondes
                    header("refresh:2;url=" . getDashboardUrl($user['role']));
                } else {
                    $error = "Erreur lors de la mise à jour : " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error = "Ancien mot de passe incorrect";
            }
        } else {
            $error = "Utilisateur non trouvé";
        }
        $stmt->close();
    }
}

// Fonction pour obtenir l'URL du dashboard selon le rôle
function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
            return "dashboards/admin/index.php";
        case 'autorite':
            return "dashboards/autorite/index.php";
        case 'enregistreurEntreeCamion':
            return "dashboards/entree_camion/index.php";
        case 'enregistreurSortieCamion':
            return "dashboards/sortie_camion/index.php";
        case 'agentBascule':
            return "dashboards/bascule/index.php";
        case 'agentEntrepot':
            return "dashboards/entrepot/index.php";
        case 'enregistreurEntreeBateau':
            return "dashboards/entree_bateau/index.php";
        case 'enregistreurSortieBateau':
            return "dashboards/sortie_bateau/index.php";
        case 'agentDouane':
            return "dashboards/douane/index.php";
        default:
            return "login.php";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changement de mot de passe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .password-container {
            max-width: 1000px;
            width: 100%;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .compact-form {
            transform: scale(0.95);
        }
    </style>
</head>
<body>
    <div class="password-container">
        <!-- Carte de changement de mot de passe -->
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
            <!-- Bannière d'en-tête -->
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4 text-white">
                <div class="flex items-center">
                    <div class="bg-white/20 p-2 rounded-full">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h2 class="text-lg font-bold">Bonjour <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?> !</h2>
                        <p class="text-sm text-blue-100 mt-1">
                            Pour des raisons de sécurité, vous devez changer votre mot de passe.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Contenu du formulaire -->
            <div class="p-6 compact-form">
                <!-- Messages d'alerte -->
                <?php if ($message): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 animate-pulse">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <div class="text-sm">
                            <span class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
                            <p class="text-green-700 text-xs mt-1">Redirection dans 2 secondes...</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                        <span class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulaire compact -->
                <form id="passwordForm" method="POST" action="">
                    <div class="space-y-4">
                        <!-- Ancien mot de passe -->
                        <div>
                            <label for="ancien_mdp" class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-lock mr-1"></i>Ancien mot de passe
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="ancien_mdp" 
                                       name="ancien_mdp" 
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pl-10"
                                       placeholder="Votre ancien mot de passe"
                                       required>
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-key text-gray-400 text-sm"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Nouveau mot de passe -->
                        <div>
                            <label for="nouveau_mdp" class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-lock mr-1"></i>Nouveau mot de passe
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="nouveau_mdp" 
                                       name="nouveau_mdp" 
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pl-10"
                                       placeholder="Minimum 6 caractères"
                                       required>
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-lock text-gray-400 text-sm"></i>
                                </div>
                                <button type="button" 
                                        onclick="togglePassword('nouveau_mdp')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye text-sm"></i>
                                </button>
                            </div>
                            
                            <!-- Indicateur de force du mot de passe -->
                            <div class="mt-2">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Force du mot de passe</span>
                                    <span id="passwordStrengthText">Faible</span>
                                </div>
                                <div class="flex space-x-1">
                                    <div id="strength1" class="password-strength flex-1 bg-gray-200"></div>
                                    <div id="strength2" class="password-strength flex-1 bg-gray-200"></div>
                                    <div id="strength3" class="password-strength flex-1 bg-gray-200"></div>
                                    <div id="strength4" class="password-strength flex-1 bg-gray-200"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Confirmer le nouveau mot de passe -->
                        <div>
                            <label for="confirmer_mdp" class="block text-xs font-medium text-gray-700 mb-1">
                                <i class="fas fa-lock mr-1"></i>Confirmer le mot de passe
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="confirmer_mdp" 
                                       name="confirmer_mdp" 
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pl-10"
                                       placeholder="Confirmer le nouveau mot de passe"
                                       required>
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-lock text-gray-400 text-sm"></i>
                                </div>
                                <button type="button" 
                                        onclick="togglePassword('confirmer_mdp')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye text-sm"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="text-xs mt-1 hidden">
                                <i class="fas fa-check-circle mr-1 text-green-500"></i>
                                <span class="text-green-600">Les mots de passe correspondent</span>
                            </div>
                            <div id="passwordMismatch" class="text-xs mt-1 hidden">
                                <i class="fas fa-times-circle mr-1 text-red-500"></i>
                                <span class="text-red-600">Les mots de passe ne correspondent pas</span>
                            </div>
                        </div>

                        <!-- Bouton de soumission -->
                        <div class="pt-3">
                            <button type="submit" 
                                    id="submitBtn"
                                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white text-sm font-medium py-2.5 px-4 rounded-lg hover:opacity-90 transition duration-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-save mr-2"></i>Changer le mot de passe
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Conseils de sécurité compacts -->
                <div class="mt-5 pt-4 border-t border-gray-200">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 text-blue-500 text-sm">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="ml-2">
                            <h3 class="text-xs font-medium text-gray-700">Conseils de sécurité :</h3>
                            <p class="text-xs text-gray-600 mt-1">
                                Utilisez au moins 6 caractères, combinez lettres et chiffres, évitez les mots de passe courants.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher/masquer le mot de passe
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('button i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Vérification de la force du mot de passe
        function checkPasswordStrength(password) {
            let strength = 0;
            const bars = [
                document.getElementById('strength1'),
                document.getElementById('strength2'),
                document.getElementById('strength3'),
                document.getElementById('strength4')
            ];
            const text = document.getElementById('passwordStrengthText');
            
            // Réinitialiser les barres
            bars.forEach(bar => {
                bar.style.backgroundColor = '#e5e7eb';
            });
            
            if (!password) {
                text.textContent = 'Faible';
                return;
            }
            
            // Tests de complexité
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Limiter à 4 barres
            strength = Math.min(strength, 4);
            
            // Mettre à jour l'affichage
            for (let i = 0; i < strength; i++) {
                if (strength === 1) {
                    bars[i].style.backgroundColor = '#ef4444';
                } else if (strength === 2) {
                    bars[i].style.backgroundColor = '#f59e0b';
                } else if (strength === 3) {
                    bars[i].style.backgroundColor = '#10b981';
                } else if (strength >= 4) {
                    bars[i].style.backgroundColor = '#3b82f6';
                }
            }
            
            // Texte de la force
            if (strength === 1) {
                text.textContent = 'Faible';
                text.className = 'text-xs text-red-500';
            } else if (strength === 2) {
                text.textContent = 'Moyen';
                text.className = 'text-xs text-yellow-500';
            } else if (strength === 3) {
                text.textContent = 'Bon';
                text.className = 'text-xs text-green-500';
            } else if (strength >= 4) {
                text.textContent = 'Fort';
                text.className = 'text-xs text-blue-500';
            }
        }

        // Vérification de la correspondance des mots de passe
        function checkPasswordMatch() {
            const newPassword = document.getElementById('nouveau_mdp').value;
            const confirmPassword = document.getElementById('confirmer_mdp').value;
            const matchDiv = document.getElementById('passwordMatch');
            const mismatchDiv = document.getElementById('passwordMismatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (!confirmPassword) {
                matchDiv.classList.add('hidden');
                mismatchDiv.classList.add('hidden');
                submitBtn.disabled = true;
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchDiv.classList.remove('hidden');
                mismatchDiv.classList.add('hidden');
                submitBtn.disabled = false;
            } else {
                matchDiv.classList.add('hidden');
                mismatchDiv.classList.remove('hidden');
                submitBtn.disabled = true;
            }
        }

        // Validation du formulaire côté client
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const ancienMdp = document.getElementById('ancien_mdp').value;
            const nouveauMdp = document.getElementById('nouveau_mdp').value;
            const confirmerMdp = document.getElementById('confirmer_mdp').value;
            
            if (!ancienMdp || !nouveauMdp || !confirmerMdp) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs');
                return false;
            }
            
            if (nouveauMdp !== confirmerMdp) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas');
                return false;
            }
            
            if (nouveauMdp.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères');
                return false;
            }
            
            return true;
        });

        // Écouteurs d'événements
        document.getElementById('nouveau_mdp').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirmer_mdp').addEventListener('input', checkPasswordMatch);

        // Désactiver le bouton au chargement
        document.getElementById('submitBtn').disabled = true;

        // Focus sur le premier champ
        document.getElementById('ancien_mdp').focus();
    </script>
</body>
</html>