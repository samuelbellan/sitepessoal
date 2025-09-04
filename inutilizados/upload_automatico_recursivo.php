<?php

// 1. Configurações do Banco de Dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "imageset";

// 2. Caminho da pasta que contém as coleções de imagens
$pasta_raiz = 'D:/Torrents/Concluidos/Imageset/Femjoy'; // Substitua pelo seu caminho

// 3. Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// 4. Função para percorrer as pastas e inserir no banco
// Adicionamos o parâmetro opcional $colecao_atual
function importar_para_bd($diretorio, $conn, $pasta_raiz, $colecao_atual = null) {
    // Escaneia o diretório e ignora as pastas . e ..
    $arquivos = array_diff(scandir($diretorio), array('.', '..'));

    foreach ($arquivos as $arquivo) {
        $caminho_completo = $diretorio . '/' . $arquivo;
        
        // Verifica se é um diretório
        if (is_dir($caminho_completo)) {
            // Se for o primeiro nível de subpasta, definimos a coleção
            $nova_colecao = $colecao_atual;
            if (is_null($colecao_atual)) {
                 $nova_colecao = basename($caminho_completo);
            }
            
            // Chama a função recursivamente com a nova_colecao
            importar_para_bd($caminho_completo, $conn, $pasta_raiz, $nova_colecao);
        } else {
            // Se for um arquivo, verifica se é uma imagem
            $extensao = strtolower(pathinfo($caminho_completo, PATHINFO_EXTENSION));
            if (in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                
                // Remove o caminho raiz para salvar o caminho relativo no banco
                $caminho_bd = str_replace($pasta_raiz, '', $caminho_completo);
                
                // Prepara a query SQL
                $sql = "INSERT INTO imagens (nome_arquivo, caminho_completo, colecao) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $nome_arquivo, $caminho_bd, $colecao_atual);

                // Define os valores e executa
                $nome_arquivo = basename($caminho_completo);
                $stmt->execute();
            }
        }
    }
}

// 5. Chamada da função para iniciar o processo
importar_para_bd($pasta_raiz, $conn, $pasta_raiz);

echo "Dados de todas as imagens importados com sucesso para o banco de dados!";

$conn->close();

?>