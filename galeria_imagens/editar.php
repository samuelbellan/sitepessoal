<?php
require_once "auth.php";
require_once "conexao.php";

$title = "Editar Álbum";
include "header.php";

// Pegar ID do álbum
$id = $_GET['id'] ?? null;
if(!$id){
    echo "<div class='alert alert-danger'>Álbum não encontrado!</div>";
    include "footer.php";
    exit;
}

// Buscar dados atuais
$stmt = $pdo->prepare("SELECT * FROM imagens WHERE id = ?");
$stmt->execute([$id]);
$album = $stmt->fetch();

if(!$album){
    echo "<div class='alert alert-danger'>Álbum não encontrado!</div>";
    include "footer.php";
    exit;
}

$mensagem = "";

// Atualizar dados
if($_SERVER['REQUEST_METHOD']==='POST'){
    $artista = $_POST['artista'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $data = $_POST['data'] ?? date('Y-m-d');
    $tags = $_POST['tags'] ?? '';
    $score = (int)($_POST['score'] ?? 0);

    $stmt = $pdo->prepare("UPDATE imagens SET artista=?, empresa=?, data_colecao=?, tags=?, score=? WHERE id=?");
    $stmt->execute([$artista, $empresa, $data, $tags, $score, $id]);
    $mensagem = "Álbum atualizado com sucesso!";
    
    // Atualizar dados locais
    $album['artista'] = $artista;
    $album['empresa'] = $empresa;
    $album['data_colecao'] = $data;
    $album['tags'] = $tags;
    $album['score'] = $score;
}
?>

<h1>Editar Álbum: <?=htmlspecialchars($album['nome_colecao'])?></h1>

<?php if($mensagem): ?>
    <div class="alert alert-success"><?=$mensagem?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Artista</label>
        <input type="text" name="artista" class="form-control" value="<?=htmlspecialchars($album['artista'])?>">
    </div>
    <div class="mb-3">
        <label>Empresa</label>
        <input type="text" name="empresa" class="form-control" value="<?=htmlspecialchars($album['empresa'])?>">
    </div>
    <div class="mb-3">
        <label>Data</label>
        <input type="date" name="data" class="form-control" value="<?=htmlspecialchars($album['data_colecao'])?>">
    </div>
    <div class="mb-3">
        <label>Tags (separadas por vírgula)</label>
        <input type="text" name="tags" class="form-control" value="<?=htmlspecialchars($album['tags'])?>">
    </div>
    <div class="mb-3">
        <label>Score</label><br>
        <div class="star-rating">
            <?php for($i=10;$i>=1;$i--): ?>
                <input type="radio" id="star<?=$i?>" name="score" value="<?=$i?>" <?php if($album['score']==$i) echo 'checked'; ?>>
                <label for="star<?=$i?>">★</label>
            <?php endfor; ?>
        </div>
    </div>
    <button type="submit" class="btn btn-success">Salvar Alterações</button>
    <a href="index.php" class="btn btn-secondary">Voltar</a>
</form>

<?php include "footer.php"; ?>
