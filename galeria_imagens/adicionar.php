<?php
require_once "../auth.php";
require_once "../conexao.php";
require_once "funcoes.php"; // Inclua o novo arquivo aqui
$title = "Adicionar Álbum";
include "header.php";

$mensagem = "";
$processando = false;

if($_SERVER['REQUEST_METHOD']==='POST'){
    $processando = true;
    $pasta = $_POST['pasta'];
    $colecao = $_POST['colecao'];
    $artista = $_POST['artista'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $data = $_POST['data'] ?? date('Y-m-d');
    $tags = $_POST['tags'] ?? '';
    $score = (int)($_POST['score'] ?? 0);

    $arquivos = array_diff(scandir($pasta), ['.', '..']);
    $total = 0;
    foreach($arquivos as $arquivo){
        $caminhoImagemOriginal = $pasta . '/' . $arquivo; // Caminho completo para a imagem

        if(preg_match('/\.(jpg|jpeg|png|gif)$/i',$arquivo) && is_file($caminhoImagemOriginal)){
            // Insere no banco de dados
            $stmt = $pdo->prepare("INSERT INTO imagens (nome_arquivo,nome_colecao,caminho,artista,empresa,data_colecao,tags,score) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$arquivo,$colecao,$pasta,$artista,$empresa,$data,$tags,$score]);
            
            // Chama a função para criar a miniatura
            criarThumbnail($caminhoImagemOriginal);

            $total++;
        }
    }
    $mensagem = "Banco populado com $total arquivos! Miniaturas criadas.";
}
?>

<h1>Adicionar Álbum</h1>
<form method="POST">
    <div class="mb-3"><label>Pasta</label><input type="text" name="pasta" class="form-control" placeholder="imageset/Colecao1" required></div>
    <div class="mb-3"><label>Nome da Coleção</label><input type="text" name="colecao" class="form-control" required></div>
    <div class="mb-3"><label>Artista</label><input type="text" name="artista" class="form-control"></div>
    <div class="mb-3"><label>Empresa</label><input type="text" name="empresa" class="form-control"></div>
    <div class="mb-3"><label>Data</label><input type="date" name="data" class="form-control"></div>
    <div class="mb-3"><label>Tags</label><input type="text" name="tags" class="form-control" placeholder="tag1,tag2"></div>
    <div class="mb-3"><label>Score</label><br>
        <div class="star-rating">
            <?php for($i=10;$i>=1;$i--): ?>
                <input type="radio" id="star<?=$i?>" name="score" value="<?=$i?>"><label for="star<?=$i?>">★</label>
            <?php endfor; ?>
        </div>
    </div>
    <button type="submit" class="btn btn-success">Popular Banco</button>
</form>

<?php if($processando): ?>
    <div class="d-flex justify-content-center mt-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Processando...</span>
        </div>
    </div>
<?php endif; ?>

<?php if($mensagem): ?>
    <div class="alert alert-success mt-3"><?php echo $mensagem; ?></div>
<?php endif; ?>

<?php include "footer.php"; ?>