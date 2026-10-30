<?php
session_start();
require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"]);
    $usuario = trim($_POST["usuario"]);
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];

    if (!empty($nombre) && !empty($usuario) && !empty($correo) && !empty($password)) {

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (nombre, usuario, correo, password, tipo_usuario) VALUES (?, ?, ?, ?, 'cliente')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $nombre, $usuario, $correo, $passwordHash);

        if ($stmt->execute()) {
            $mensaje = "¡Registro exitoso! Ya puedes iniciar sesión.";
        } else {
            $mensaje = "Error: El usuario o correo ya existe.";
        }
        $stmt->close();
    } else {
        $mensaje = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1d1d1d; padding: 30px; border-radius: 12px; width: 350px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        h2 { text-align: center; color: #d60000; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border-radius: 5px; border: 1px solid #333; background: #222; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #d60000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        button:hover { background: #b00000; }
        .mensaje { background: #d60000; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; }
        .links { margin-top: 15px; text-align: center; font-size: 14px; }
        .links a { color: #d60000; text-decoration: none; }
    </style>
</head>
<body>

<div class="card">
    <h2>📝 Crear Cuenta</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="nombre" placeholder="Nombre completo" required>
        <input type="text" name="usuario" placeholder="Nombre de usuario" required>
        <input type="email" name="correo" placeholder="Correo electrónico" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Registrarse</button>
    </form>

    <div class="links">
        <p>¿Ya tienes cuenta? <a href="index.php">Inicia sesión aquí</a></p>
    </div>
</div>

</body>
</html>

