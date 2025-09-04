<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/5.3.7/photoswipe.min.css" integrity="sha512-421xV8Uj+e6M+n6xK6/bWzIuF0oB+Wl2hWk0bVbL9g5yR3s6E/cRj5bXoQz5r5x+Q+Q+g8W7d7hHj+Q+A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnify/2.3.3/css/magnify.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/magnify/2.3.3/js/jquery.magnify.min.js"></script>
    <title><?php echo $title; ?></title>

    <style>
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            margin-top: 10px;
            position: relative;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            color: #ccc;
            font-size: 2rem;
            padding: 0 5px;
            cursor: pointer;
            transition: color 0.2s ease-in-out;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107; /* Amarelo */
        }

        .star-rating input:checked ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home.php">Home</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['usuario'])): ?>
                        <li class="nav-item"><span class="nav-link">Olá, <?php echo $_SESSION['usuario']; ?></span></li>
                        <li class="nav-item"><a class="nav-link btn btn-outline-light btn-sm" href="../logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="../login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container">