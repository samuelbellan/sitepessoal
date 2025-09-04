<?php
require_once "auth.php";
$title = "Home";
include "header.php";
?>

<h1>Dashboard</h1>
<p>Bem-vindo, <?php echo $_SESSION['usuario']; ?>!</p>

<div class="row g-4 mt-3">
    <!-- Coleções de Álbuns -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <img src="https://icons.getbootstrap.com/assets/icons/images.svg" class="card-img-top p-4" style="height:200px; object-fit:contain;" alt="Ícone de um álbum de fotos">
            <div class="card-body">
                <h5 class="card-title">Coleções de Álbuns</h5>
                <p class="card-text">Gerencie suas coleções de imagens, visualize álbuns, adicione tags e avaliações.</p>
                <a href="galeria_imagens/index.php" class="btn btn-primary">Acessar</a>
            </div>
        </div>
    </div>

    <!-- Sistema de Vídeos -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <img src="https://icons.getbootstrap.com/assets/icons/camera-video.svg" class="card-img-top p-4" style="height:200px; object-fit:contain;" alt="Ícone de um player de vídeo">
            <div class="card-body">
                <h5 class="card-title">Sistema de Vídeos</h5>
                <p class="card-text">Em desenvolvimento.</p>
                <a href="#" class="btn btn-secondary disabled">Em breve</a>
            </div>
        </div>
    </div>

    <!-- Controle Financeiro -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <img src="https://icons.getbootstrap.com/assets/icons/currency-dollar.svg" class="card-img-top p-4" style="height:200px; object-fit:contain;" alt="Ícone de um gráfico financeiro">
            <div class="card-body">
                <h5 class="card-title">Controle Financeiro</h5>
                <p class="card-text">Gerencie suas receitas, despesas e acompanhe o saldo.</p>
                <a href="financeiro/index.php" class="btn btn-success">Acessar</a>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
