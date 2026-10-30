<?php

session_start();
require_once "conexion.php";

if (!isset($_SESSION["id"]) || $_SESSION["tipo_usuario"] != "admin") {

    header("Location: index.php");
    exit();

}

$id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($id <= 0) {

    header("Location: admin.php");
    exit();

}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nombre = trim($_POST["nombre"]);
    $usuario = trim($_POST["usuario"]);
    $correo = trim($_POST["correo"]);
    $tipo = trim($_POST["tipo_usuario"]);
    $nuevaPassword = $_POST["password"];

    if ($nuevaPassword != "") {

        $hash = password_hash(
            $nuevaPassword,
            PASSWORD_DEFAULT
        );

        $sql = "UPDATE usuarios
                SET nombre = ?,
                    usuario = ?,
                    correo = ?,
                    tipo_usuario = ?,
                    password = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sssssi",
            $nombre,
            $usuario,
            $correo,
            $tipo,
            $hash,
            $id
        );

    } else {

        $sql = "UPDATE usuarios
                SET nombre = ?,
                    usuario = ?,
                    correo = ?,
                    tipo_usuario = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssi",
            $nombre,
            $usuario,
            $correo,
            $tipo,
            $id
        );
    }

    if ($stmt->execute()) {

        header("Location: admin.php");
        exit();

    } else {

        $mensaje = "No se pudo actualizar el usuario.";

    }

    $stmt->close();
}

$stmt = $conn->prepare(
    "SELECT * FROM usuarios WHERE id = ?"
);

$stmt->bind_param("i", $id);

