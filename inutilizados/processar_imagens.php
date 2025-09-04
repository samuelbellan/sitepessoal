<?php
include "auth.php";
include "conexao.php";

$nome_colecao = $_POST['nome_colecao'] ?? '';
$artista = $_POST['artista'] ?? '';
$data_colecao = $_POST['data_colecao'] ?? '';
$caminho = rtrim($_POST['caminho'],'/');
$empresa = $_POST['empresa'] ?? '';
$tags = $_POST['tags'] ?? '';
$score = floatval($_POST['score'] ?? 0);

$caminho_fisico = $_SERVER['DOCUMENT_ROOT'].'/'.str_replace('/','\\',$caminho);

if(!is_dir($caminho_fisico)){
    die("Erro: pasta não existe.");
}

$arquivos = array_values(array_filter(scandir($caminho_fisico), function($f){
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return in_array($ext,['jpg','jpeg','png','gif','webp']);
}));

$total = count($arquivos);
if($total==0) die("Nenhuma imagem encontrada.");

foreach($arquivos as $i=>$arquivo){
    $sql = "INSERT INTO imagens (nome_arquivo,nome_colecao,caminho,artista,data_colecao,empresa,tags,score)
            VALUES ('".$conn->real_escape_string($arquivo)."',
                    '".$conn->real_escape_string($nome_colecao)."',
                    '".$conn->real_escape_string($caminho)."',
                    '".$conn->real_escape_string($artista)."',
                    '".$conn->real_escape_string($data_colecao)."',
                    '".$conn->real_escape_string($empresa)."',
                    '".$conn->real_escape_string($tags)."',
                    $score)";
    $conn->query($sql);

    // criar miniatura
    $thumbDir = $caminho_fisico.'\\thumbs\\';
    if(!is_dir($thumbDir)) mkdir($thumbDir,0777,true);
    $thumbPath = $thumbDir.$arquivo;

    $info = getimagesize($caminho_fisico.'\\'.$arquivo);
    if($info){
        $ratio = min(200/$info[0],200/$info[1]);
        $nw = intval($info[0]*$ratio);
        $nh = intval($info[1]*$ratio);
        switch($info[2]){
            case IMAGETYPE_JPEG: $img = imagecreatefromjpeg($caminho_fisico.'\\'.$arquivo); break;
            case IMAGETYPE_PNG: $img = imagecreatefrompng($caminho_fisico.'\\'.$arquivo); break;
            case IMAGETYPE_GIF: $img = imagecreatefromgif($caminho_fisico.'\\'.$arquivo); break;
            default: $img=false; break;
        }
        if($img){
            $thumb = imagecreatetruecolor($nw,$nh);
            imagecopyresampled($thumb,$img,0,0,0,0,$nw,$nh,$info[0],$info[1]);
            imagejpeg($thumb,$thumbPath,85);
            imagedestroy($img);
            imagedestroy($thumb);
        }
    }

    $percent = intval((($i+1)/$total)*100).'%';
    echo $percent;
    flush();
    usleep(50000); // pequeno delay para visualização do progresso
}
?>
