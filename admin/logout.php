<?php
require_once __DIR__ . '/../includes/auth.php';

// Exige o token mesmo sendo um link (GET), para que nenhum site externo consiga
// derrubar a sessão do administrador com um simples <img src="...logout.php">.
csrfValidar(true);

logout();
header('Location: login.php');
exit;
