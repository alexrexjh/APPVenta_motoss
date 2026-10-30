<?php
$password_plano = "123456";
$password_seguro = password_hash($password_plano, PASSWORD_DEFAULT);
echo "Copia este hash para tu base de datos: <br><strong>" . $password_seguro . "</strong>";
?>

