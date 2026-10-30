<?php

session_start();

require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $correo = trim($_POST["correo"]);

    if (empty($correo)) {

        $mensaje = "Escribe tu correo.";

    } else {

        $sql = "SELECT id FROM usuarios WHERE correo = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s", $correo);

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows == 1) {

            $usuario = $resultado->fetch_assoc();

            $_SESSION["recuperar_id"] = $usuario["id"];

            header("Location: nueva_password.php");

            exit();

        } else {

            $mensaje = "No existe una cuenta con ese correo.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Recuperar contraseña</title>

<style>

body {
    font-family: Arial;
    background: #111;
}

.card {
    background: white;
    width: 400px;
    margin: 100px auto;
    padding: 30px;
    border-radius: 15px;
}

input {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    box-sizing: border-box;
}

button {
    width: 100%;
    padding: 12px;
    background: #d60000;
    color: white;
    border: none;
}

</style>

</head>

<body>

<div class="card">

