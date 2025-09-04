<?php
// Inicia a sessão apenas se ela ainda não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Protege todas as páginas exceto login
if(!isset($_SESSION['usuario']) && basename($_SERVER['PHP_SELF']) != "login.php"){
    header("Location: login.php");
    exit;
}
?>