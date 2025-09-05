<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/5.3.7/photoswipe.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnify/2.3.3/css/magnify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/magnify/2.3.3/js/jquery.magnify.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
    .star-rating { display:flex; flex-direction: row-reverse; justify-content: center; margin-top: 10px; position: relative; }
    .star-rating input { display: none; }
    .star-rating label { color: #ccc; font-size: 2rem; padding:0 5px; cursor:pointer; transition: color 0.2s; }
    .star-rating label:hover, .star-rating label:hover ~ label { color: #ffc107; }
    .star-rating input:checked ~ label { color: #ffc107; }
    </style>
</head>
<body>
<!-- Navbar Modernizada -->
<nav class="navbar navbar-expand-lg navbar-dark bg-gradient bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="../home.php">
            <i class="fa fa-home me-2"></i> Meu Financeiro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavBar" aria-controls="mainNavBar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavBar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="lancamentos.php"><i class="fa fa-exchange-alt"></i> Lançamentos</a></li>
                <li class="nav-item"><a class="nav-link" href="orcamento.php"><i class="fa fa-bullseye"></i> Orçamento</a></li>
                <li class="nav-item"><a class="nav-link" href="categorias.php"><i class="fa fa-tags"></i> Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="cartoes.php"><i class="fa fa-credit-card"></i> Cartões</a></li>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if(isset($_SESSION['usuario'])): ?>
                    <li class="nav-item d-flex align-items-center me-2">
                        <span class="nav-link opacity-75">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['usuario']) ?>&background=204080&color=fff&rounded=true&size=32" alt="avatar" class="rounded-circle me-1" style="width:32px;height:32px;">
                            Olá, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger btn-sm px-3 ms-1 text-light" href="../logout.php">
                            <i class="fa fa-sign-out-alt"></i> Sair
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="../login.php"><i class="fa fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-3">
