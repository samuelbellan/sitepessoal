<?php
require_once "conexao.php";

if($_SERVER['REQUEST_METHOD']==='POST'){
    $input = json_decode(file_get_contents('php://input'), true);

    if($input && isset($input['file'])){
        // Inserir arquivo no banco com tags e score
        $stmt = $pdo->prepare("INSERT INTO albuns 
            (nome_arquivo,nome_colecao,caminho,artista,empresa,data_colecao,tags,score)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $input['file'],
            $input['colecao'],
            $input['pasta'],
            $input['artista'],
            $input['empresa'],
            $input['data'] ?? date('Y-m-d'),
            $input['tags'] ?? '',
            (int)($input['score'] ?? 0)
        ]);
        echo json_encode(['status'=>'ok']);
        exit;
    } else {
        // Primeira requisição: listar arquivos da pasta
        $pasta = $_POST['pasta'] ?? '';
        if(!is_dir($pasta)) { echo json_encode(['total'=>0,'files'=>[]]); exit; }
        $arquivos = array_values(array_filter(scandir($pasta), function($f){
            return preg_match('/\.(jpg|jpeg|png|gif)$/i',$f);
        }));
        echo json_encode(['total'=>count($arquivos),'files'=>$arquivos]);
        exit;
    }
}
?>
