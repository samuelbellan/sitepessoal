<?php

/**
 * Cria uma miniatura de uma imagem e a salva em uma subpasta 'thumbs'.
 * @param string $caminhoCompleto Original Caminho completo para o arquivo de imagem original.
 * @param int $larguraDesejada A largura da miniatura em pixels.
 * @return bool Retorna true se a miniatura foi criada com sucesso, false caso contrário.
 */
function criarThumbnail($caminhoCompleto, $larguraDesejada = 300) {
    // Verifica se o arquivo original existe
    if (!file_exists($caminhoCompleto)) {
        return false;
    }

    // Obtém o diretório e o nome do arquivo
    $dir = pathinfo($caminhoCompleto, PATHINFO_DIRNAME);
    $nomeArquivo = pathinfo($caminhoCompleto, PATHINFO_BASENAME);
    $extensao = strtolower(pathinfo($caminhoCompleto, PATHINFO_EXTENSION));

    // Define o diretório para as miniaturas
    $dirMiniaturas = $dir . '/thumbs';
    if (!is_dir($dirMiniaturas)) {
        mkdir($dirMiniaturas, 0777, true);
    }
    
    $caminhoMiniatura = $dirMiniaturas . '/' . $nomeArquivo;

    // Carrega a imagem dependendo do tipo
    switch ($extensao) {
        case 'jpg':
        case 'jpeg':
            $imagemOriginal = imagecreatefromjpeg($caminhoCompleto);
            break;
        case 'png':
            $imagemOriginal = imagecreatefrompng($caminhoCompleto);
            break;
        case 'gif':
            $imagemOriginal = imagecreatefromgif($caminhoCompleto);
            break;
        default:
            return false; // Tipo de arquivo não suportado
    }

    if (!$imagemOriginal) {
        return false;
    }

    // Obtém as dimensões da imagem original
    $larguraOriginal = imagesx($imagemOriginal);
    $alturaOriginal = imagesy($imagemOriginal);
    $alturaDesejada = ($alturaOriginal / $larguraOriginal) * $larguraDesejada;

    // Cria uma nova imagem com as dimensões da miniatura
    $imagemMiniatura = imagecreatetruecolor($larguraDesejada, $alturaDesejada);

    // Redimensiona e copia a imagem original para a miniatura
    imagecopyresampled(
        $imagemMiniatura, 
        $imagemOriginal, 
        0, 0, 0, 0, 
        $larguraDesejada, 
        $alturaDesejada, 
        $larguraOriginal, 
        $alturaOriginal
    );

    // Salva a miniatura na pasta thumbs
    switch ($extensao) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($imagemMiniatura, $caminhoMiniatura, 90);
            break;
        case 'png':
            imagepng($imagemMiniatura, $caminhoMiniatura);
            break;
        case 'gif':
            imagegif($imagemMiniatura, $caminhoMiniatura);
            break;
    }

    // Libera a memória
    imagedestroy($imagemOriginal);
    imagedestroy($imagemMiniatura);

    return true;
}