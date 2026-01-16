<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - Port de Bujumbura</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-6xl bg-white shadow-lg rounded-lg overflow-hidden flex flex-col md:flex-row">

    <!-- Image à gauche -->
    <div class="md:w-1/2 hidden md:block">
      <img src="images/login-img.png" 
           alt="Port de Bujumbura" 
           class="w-full h-full object-cover">
    </div>

    <!-- Formulaire à droite -->
    <div class="md:w-1/2 flex flex-col justify-center px-8 py-10">
      <div class="max-w-md w-full mx-auto">

        <div class="text-center mb-6">
          <a href="index.php">
            <i class="fa-brands fa-laravel text-red-500 text-5xl mb-4"></i>
          </a>
          <h1 class="text-2xl font-bold text-gray-900">
            Veuillez vous connecter à votre compte
          </h1>
          <p class="text-gray-500 mt-2">
            Veuillez entrer votre email et mot de passe ci-dessous
          </p>
        </div>

        <!-- MESSAGE D'ERREUR -->
        <?php if (isset($_GET['error'])): ?>
          <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
            <i class="fa-solid fa-circle-exclamation mt-1 text-red-500"></i>
            <span class="text-sm">
              <?= htmlspecialchars($_GET['error']) ?>
              <?php unset($_GET['error']); ?>
            </span>
          </div>
        <?php endif; ?>

        <form action="auth/login.php" method="POST" class="space-y-5">

          <!-- Email -->
          <div>
            <label for="email" class="sr-only">Adresse email</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fa-solid fa-envelope"></i>
              </span>
              <input type="email" id="email" name="email" required
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-md
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Adresse email">
            </div>
          </div>

          <!-- Mot de passe -->
          <div>
            <label for="password" class="sr-only">Mot de passe</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fa-solid fa-lock"></i>
              </span>
              <input type="password" id="password" name="password" required
                class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-md
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Mot de passe">
              <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600"
                    id="togglePassword">
                <i class="fa-solid fa-eye"></i>
              </span>
            </div>
          </div>

          <button type="submit" 
            class="w-full flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-md shadow transition-all">
            <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i>
            Se connecter
          </button>
        </form>

      </div>
    </div>
  </div>

  <script>
    // Afficher / masquer mot de passe
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', () => {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;
      togglePassword.innerHTML =
        type === 'password'
          ? '<i class="fa-solid fa-eye"></i>'
          : '<i class="fa-solid fa-eye-slash"></i>';
    });
  </script>
</body>
</html>
