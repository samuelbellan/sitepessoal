<?php

// 1. Configurações do Banco de Dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "imageset";

// 2. Diretório de upload
// Crie uma pasta 'uploads' no mesmo diretório deste arquivo
$upload_dir = 'uploads/';

// Mensagem de feedback para o usuário
$message = '';

// Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $colecao = trim($_POST['colecao'] ?? '');
    $artista = trim($_POST['artista'] ?? '');
    $data_colecao = trim($_POST['data_colecao'] ?? '');

    // Validação básica do formulário
    if (empty($colecao) || empty($artista) || empty($data_colecao) || empty($_FILES['imagens']['name'][0])) {
        $message = '<p style="color:red;">Por favor, preencha todos os campos e selecione pelo menos uma imagem.</p>';
    } else {
        // Formata o nome da coleção para ser usado como nome de pasta (sem espaços ou caracteres especiais)
        $dir_colecao = str_replace(' ', '_', $colecao);
        $caminho_colecao = $upload_dir . $dir_colecao . '/';

        // Cria o diretório da coleção se ele não existir
        if (!is_dir($caminho_colecao)) {
            mkdir($caminho_colecao, 0777, true);
        }

        $upload_count = 0;
        $total_files = count($_FILES['imagens']['name']);

        // Loop para processar cada arquivo enviado
        for ($i = 0; $i < $total_files; $i++) {
            $file_name = $_FILES['imagens']['name'][$i];
            $file_tmp = $_FILES['imagens']['tmp_name'][$i];
            $file_size = $_FILES['imagens']['size'][$i];
            $file_error = $_FILES['imagens']['error'][$i];

            // Verifica se não houve erro no upload do arquivo
            if ($file_error === 0) {
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                // Valida o tipo e o tamanho do arquivo
                if (in_array($file_ext, $allowed_ext)) {
                    // Gera um nome de arquivo único para evitar colisões
                    $novo_nome = uniqid('', true) . '.' . $file_ext;
                    $caminho_final = $caminho_colecao . $novo_nome;

                    if (move_uploaded_file($file_tmp, $caminho_final)) {
                        // Prepara e executa a query de inserção
                        // Adicionamos 'data_colecao' à query e '?' aos parâmetros
                        $sql = "INSERT INTO imagens (nome_arquivo, caminho_completo, colecao, artista, data_colecao) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        // Adicionamos 's' e '$data_colecao' ao bind_param
                        $stmt->bind_param("sssss", $novo_nome, $caminho_final, $colecao, $artista, $data_colecao);
                        $stmt->execute();
                        $upload_count++;
                    }
                }
            }
        }
        $message = "<p style='color:green;'>Upload concluído. $upload_count de $total_files imagens enviadas com sucesso.</p>";
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Upload de Coleção de Imagens</title>
</head>
<body>

    <h2>Upload de Coleção de Imagens</h2>

    <?php echo $message; ?>

    <form action="upload_multiplo_com_data.php" method="post" enctype="multipart/form-data">
        <label for="colecao">Nome da Coleção:</label><br>
        <input type="text" id="colecao" name="colecao" required><br><br>

        <label for="artista">Nome do Artista:</label><br>
        <input type="text" id="artista" name="artista" required><br><br>
        
        <label for="data_colecao">Data da Coleção:</label><br>
        <input type="date" id="data_colecao" name="data_colecao" required><br><br>

        <label for="imagens">Selecione as Imagens (Ctrl/Cmd para múltiplas):</label><br>
        <input type="file" id="imagens" name="imagens[]" multiple required><br><br>

        <input type="submit" value="Fazer Upload">
    </form>

</body>
</html>