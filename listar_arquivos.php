<?php
include "auth.php";

header('Content-Type: application/json');

$caminho = $_POST['caminho'] ?? '';
if (!$caminho) {
    echo json_encode(["success"=>false, "message"=>"Caminho não informado"]); exit;
}

$fsPath = $caminho; // relativo ao htdocs (ex.: imageset/MinhaColecao)
if (!is_dir($fsPath)) {
    echo json_encode(["success"=>false, "message"=>"Pasta inválida: ".$fsPath]); exit;
}

$extensoes = ["jpg","jpeg","png","gif","webp"];
$arquivos = [];
foreach (scandir($fsPath) as $arq) {
    if ($arq === '.' || $arq === '..') continue;
    $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
    if (in_array($ext, $extensoes)) $arquivos[] = $arq;
}

echo json_encode(["success"=>true, "arquivos"=>$arquivos]);
