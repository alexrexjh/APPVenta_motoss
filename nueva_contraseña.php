<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["recuperar_id"])) {
    header("Location: recuperar.php");
    exit();
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST["password"];
    $confirmar = $_POST["confirmar"];

    if (strlen($password) < 6) {
        $mensaje = "La contraseña debe tener mínimo 6 caracteres.";
    } elseif ($password != $confirmar) {
        $mensaje = "Las contraseñas no coinciden.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = $_SESSION["recuperar_id"];

        $sql = "UPDATE usuarios SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hash, $id);

        if ($stmt->execute()) {
            unset($_SESSION["recuperar_id"]);
            header("Location: index.php");
            exit();
        } else {
            $mensaje = "No se pudo cambiar la contraseña.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1d1d1d; padding: 30px; border-radius: 12px; width: 350px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        h2 { color: #d60000; text-align: center; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border-radius: 5px; border: 1px solid #333; background: #222; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #d60000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        button:hover { background: #b00000; }
        .mensaje { background: #d60000; color: white; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="card">
    <h2>🔒 Nueva Contraseña</h2>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="password" name="password" placeholder="Nueva contraseña (mín. 6 caracteres)" required>
        <input type="password" name="confirmar" placeholder="Confirmar contraseña" required>
        <button type="submit">Actualizar Contraseña</button>
    </form>
</div>

</body>
</html>

