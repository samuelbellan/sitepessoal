<?php
session_start();
require_once "conexao.php";
$error = "";

if($_SERVER['REQUEST_METHOD']==='POST'){
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $usuarios_validos = [
        "admin" => "123456",
        "user1" => "senha123"
    ];

    if(isset($usuarios_validos[$usuario]) && $usuarios_validos[$usuario]==$senha){
        $_SESSION['usuario'] = $usuario;
        header("Location: home.php");
        exit;
    } else {
        $error = "Usuário ou senha inválidos!";
    }
}

include "header.php";
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card p-4">
            <h3 class="mb-3 text-center">Login</h3>
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label>Usuário</label>
                    <input type="text" name="usuario" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
