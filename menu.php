<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="home.php">Painel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
            data-bs-target="#navbarNav" aria-controls="navbarNav" 
            aria-expanded="false" aria-label="Alternar navegação">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="../home.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php">Coleções de Álbuns</a></li>
      </ul>

      <ul class="navbar-nav ms-auto">
        <?php if(isset($_SESSION['usuario'])): ?>
            <li class="nav-item">
                <span class="navbar-text me-3">👤 <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            </li>
            <li class="nav-item">
                <a class="btn btn-outline-light" href="logout.php">Logout</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="btn btn-outline-light" href="login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
