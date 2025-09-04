<?php
require_once "auth.php";
require_once "conexao.php";

$title = "Remover Álbum";
include "header.php";

$id = $_GET['id'] ?? null;

if(!$id){
    echo "<div class='alert alert-danger'>Álbum não encontrado!</div>";
    include "footer.php";
    exit;
}

// Buscar dados da coleção
$stmt = $pdo->prepare("SELECT * FROM imagens WHERE id = ?");
$stmt->execute([$id]);
$album = $stmt->fetch();

if(!$album){
    echo "<div class='alert alert-danger'>Álbum não encontrado!</div>";
    include "footer.php";
    exit;
}

$mensagem = "";

// Remover após confirmação
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['confirm']) && $_POST['confirm']==='sim'){
        // Apagar todos os registros da mesma coleção
        $stmtDel = $pdo->prepare("DELETE FROM imagens WHERE nome_colecao = ? AND caminho = ?");
        $stmtDel->execute([$album['nome_colecao'], $album['caminho']]);
        $mensagem = "Coleção '".htmlspecialchars($album['nome_colecao'])."' removida com sucesso!";
    } else {
        header("Location: index.php");
        exit;
    }
}
?>

<h1>Remover Álbum: <?=htmlspecialchars($album['nome_colecao'])?></h1>

<?php if($mensagem): ?>
    <div class="alert alert-success"><?=$mensagem?></div>
    <a href="index.php" class="btn btn-primary mt-3">Voltar para Coleções</a>
<?php else: ?>
    <div class="alert alert-warning">
        Tem certeza que deseja <strong>remover toda a coleção</strong> "<?=htmlspecialchars($album['nome_colecao'])?>" do banco de dados?
    </div>
    <form method="POST">
        <button type="submit" name="confirm" value="sim" class="btn btn-danger">Sim, remover</button>
        <button type="submit" name="confirm" value="nao" class="btn btn-secondary">Cancelar</button>
    </form>
<?php endif; ?>

<?php include "footer.php"; ?>
