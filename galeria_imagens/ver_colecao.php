<?php
require_once "../auth.php";
require_once "../conexao.php";
require_once "funcoes.php"; // Incluir a função para criar thumbnails

$title = "Ver Coleção";
include "../header.php";

// Pegar ID da coleção
$id = $_GET['id'] ?? null;
if(!$id){
    echo "<div class='alert alert-danger'>Coleção não encontrada!</div>";
    include "footer.php";
    exit;
}

// Buscar dados da coleção
$stmt = $pdo->prepare("SELECT * FROM imagens WHERE id = ?");
$stmt->execute([$id]);
$album = $stmt->fetch();

if(!$album){
    echo "<div class='alert alert-danger'>Coleção não encontrada!</div>";
    include "footer.php";
    exit;
}

// Pegar todas as imagens da mesma coleção, ordenadas para o carrossel
$stmt2 = $pdo->prepare("SELECT * FROM imagens WHERE nome_colecao = ? AND caminho = ? ORDER BY id ASC");
$stmt2->execute([$album['nome_colecao'], $album['caminho']]);
$imagens = $stmt2->fetchAll();
?>

<style>
/* CSS para manter a proporção de 213:320 (vertical) */
.card-img-213-320 {
    position: relative;
    padding-bottom: 150.23%; /* 320 / 213 = 1.5023 */
    overflow: hidden;
}

.card-img-213-320 img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Estilo para a imagem no modal */
.modal-body img {
    display: block;
    margin: auto;
    max-width: 100%;
    max-height: 100vh;
    object-fit: contain;
    cursor: zoom-in;
    transition: max-width 0.3s ease, max-height 0.3s ease;
}

/* Nova classe para exibir a imagem em tamanho original (sem restrições) */
.modal-body img.zoom-active {
    max-width: none !important;
    max-height: none !important;
    cursor: grab;
    transition: none; /* Remove a transição suave para não interferir no arrasto */
}

/* Muda o cursor quando a imagem está sendo arrastada */
.modal-body img.dragging {
    cursor: grabbing;
}

.gallery-item img {
    transition: transform 0.3s ease-in-out;
}

.gallery-item img:hover {
    transform: scale(1.05);
}
</style>

<h1><?=htmlspecialchars($album['nome_colecao'])?></h1>
<p>
    <strong>Artista:</strong> <?=htmlspecialchars($album['artista'])?><br>
    <strong>Empresa:</strong> <?=htmlspecialchars($album['empresa'])?><br>
    <strong>Tags:</strong> <?=htmlspecialchars($album['tags'])?><br>
    <strong>Score:</strong> <?=str_repeat("★",$album['score']).str_repeat("☆",10-$album['score'])?>
</p>

<div class="row g-3">
<?php foreach($imagens as $index => $img): ?>
    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
        <?php
            // Constrói o caminho para a miniatura e imagem original
            $caminhoOriginal = $img['caminho'] . '/' . $img['nome_arquivo'];
            $caminhoMiniatura = $img['caminho'] . '/thumbs/' . $img['nome_arquivo'];

            // Garante que a miniatura existe
            if (!file_exists($caminhoMiniatura)) {
                criarThumbnail($caminhoOriginal); // Tenta criar se não existir
            }
        ?>
        <div class="card h-100 shadow-sm border-0 gallery-item">
            <a href="#" data-bs-toggle="modal" data-bs-target="#galleryModal" data-img-src="<?=htmlspecialchars($caminhoOriginal)?>">
                <div class="card-img-213-320">
                    <img src="<?=htmlspecialchars($caminhoMiniatura)?>"
                         class="img-fluid rounded lazy-load"
                         alt="<?=htmlspecialchars($img['nome_arquivo'])?>">
                </div>
            </a>
            <div class="card-body text-center p-2">
                <small class="text-muted">Imagem <?=($index + 1)?></small>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="modal fade" id="galleryModal" tabindex="-1" aria-labelledby="galleryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="galleryModalLabel">Visualizar Imagem</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body p-0 d-flex align-items-center justify-content-center">
        <img src="" class="img-fluid" id="modalImage" alt="Imagem em tela cheia">
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const galleryModal = document.getElementById('galleryModal');
    const modalImage = document.getElementById('modalImage');

    galleryModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const imgSrc = trigger.getAttribute('data-img-src');
        modalImage.src = imgSrc;
    });

    // Variáveis para controlar o arrasto da imagem
    let isDragging = false;
    let startX, startY;
    let scrollLeft, scrollTop;

    // Adiciona o evento de clique para a funcionalidade de zoom
    modalImage.addEventListener('click', function() {
        this.classList.toggle('zoom-active');
        // Define o estado de arrastar
        if(this.classList.contains('zoom-active')) {
            this.style.cursor = 'grab';
        } else {
            // Volta ao estado inicial
            this.style.transform = 'translate(0, 0)';
            this.style.cursor = 'zoom-in';
        }
    });

    // Inicia o arrasto
    modalImage.addEventListener('mousedown', (e) => {
        if (!modalImage.classList.contains('zoom-active')) return;
        isDragging = true;
        modalImage.classList.add('dragging');
        startX = e.pageX - modalImage.offsetLeft;
        startY = e.pageY - modalImage.offsetTop;
        scrollLeft = modalImage.scrollLeft;
        scrollTop = modalImage.scrollTop;
    });

    // Para o arrasto
    modalImage.addEventListener('mouseup', () => {
        isDragging = false;
        modalImage.classList.remove('dragging');
    });

    // Move a imagem
    modalImage.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.pageX - modalImage.offsetLeft;
        const y = e.pageY - modalImage.offsetTop;
        const walkX = (x - startX) * 2; // Multiplica por 2 para um movimento mais rápido
        const walkY = (y - startY) * 2;
        modalImage.scrollLeft = scrollLeft - walkX;
        modalImage.scrollTop = scrollTop - walkY;
    });

    // Reseta o zoom e o arrasto ao fechar o modal
    galleryModal.addEventListener('hide.bs.modal', function() {
        modalImage.classList.remove('zoom-active');
        modalImage.classList.remove('dragging');
        modalImage.style.transform = 'translate(0, 0)';
    });
});
</script>

<?php include "../footer.php"; ?>