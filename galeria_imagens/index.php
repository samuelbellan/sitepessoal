<?php
require_once "../auth.php";
require_once "../conexao.php";
require_once "funcoes.php"; // Incluir a função para criar thumbnails

$title = "Coleções de Álbuns";
include "header.php";

$artista = $_GET['artista'] ?? '';
$empresa = $_GET['empresa'] ?? '';
$tags = $_GET['tags'] ?? '';

$sql = "SELECT * FROM imagens WHERE 1=1";
$params = [];
if($artista){ $sql .= " AND artista LIKE ?"; $params[]="%$artista%";}
if($empresa){ $sql .= " AND empresa LIKE ?"; $params[]="%$empresa%";}
if($tags){ $sql .= " AND tags LIKE ?"; $params[]="%$tags%";}
$sql .= " GROUP BY nome_colecao ORDER BY data_colecao DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$albuns = $stmt->fetchAll();

function renderStars($score){
    $html = "";
    for($i=1;$i<=10;$i++)
        $html .= $i<=$score ? "<span style='color:gold;'>★</span>" : "<span style='color:gray;'>★</span>";
    return $html;
}
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
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Coleções de Álbuns</h1>
    <a href="adicionar.php" class="btn btn-success">Adicionar Álbum</a>
</div>

<form method="GET" class="mb-4 d-flex gap-2">
    <input type="text" name="artista" class="form-control" placeholder="Artista" value="<?=htmlspecialchars($artista)?>">
    <input type="text" name="empresa" class="form-control" placeholder="Empresa" value="<?=htmlspecialchars($empresa)?>">
    <input type="text" name="tags" class="form-control" placeholder="Tags" value="<?=htmlspecialchars($tags)?>">
    <button class="btn btn-primary">Filtrar</button>
    <a href="index.php" class="btn btn-secondary">Limpar</a>
</form>

<div class="row g-4">
<?php if(count($albuns)>0): ?>
    <?php foreach($albuns as $album): ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card h-100">
                <?php 
                    $imgPath = $album['caminho'].'/'.$album['nome_arquivo'];
                    $thumbPath = $album['caminho'].'/thumbs/'.$album['nome_arquivo'];

                    if (!file_exists($thumbPath)) {
                        criarThumbnail($imgPath);
                    }
                ?>
                <a href="ver_colecao.php?id=<?=$album['id']?>">
                    <div class="card-img-213-320">
                        <img src="<?=htmlspecialchars($thumbPath)?>" alt="Capa da Coleção <?=htmlspecialchars($album['nome_colecao'])?>">
                    </div>
                </a>
                <div class="card-body">
                    <h5 class="card-title"><?=htmlspecialchars($album['nome_colecao'])?></h5>
                    <p class="card-text small">
                        <strong>Artista:</strong> <?=htmlspecialchars($album['artista'])?><br>
                        <strong>Empresa:</strong> <?=htmlspecialchars($album['empresa'])?><br>
                        <strong>Tags:</strong> <?=htmlspecialchars($album['tags'])?><br>
                        <strong>Score:</strong> <?=renderStars($album['score'])?>
                    </p>
                    <div class="d-flex justify-content-between">
                        <a href="ver_colecao.php?id=<?=$album['id']?>" class="btn btn-primary btn-sm">Ver</a>
                        <a href="editar.php?id=<?=$album['id']?>" class="btn btn-warning btn-sm">Editar</a>
                        <a href="remover.php?id=<?=$album['id']?>" class="btn btn-danger btn-sm">Remover</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="col-12"><div class="alert alert-info">Nenhum álbum encontrado.</div></div>
<?php endif; ?>
</div>

<?php include "footer.php"; ?>