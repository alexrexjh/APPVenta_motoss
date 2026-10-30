<?php
session_start();
require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $password = $_POST["password"];

    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    if (empty($usuario) || empty($password)) {
        $mensaje = "Completa todos los campos.";
        
        $intento = "Inicio de sesión fallido";
        $stmtBitacora = $conn->prepare("INSERT INTO bitacora (usr, fecha, hora, intento) VALUES (?, ?, ?, ?)");
        $stmtBitacora->bind_param("ssss", $usuario, $fecha, $hora, $intento);
        $stmtBitacora->execute();
        $stmtBitacora->close();
    } else {
        $sql = "SELECT id, nombre, usuario, correo, password, tipo_usuario FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usuario, $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows == 1) {
            $datos = $resultado->fetch_assoc();

            if (password_verify($password, $datos["password"])) {
                $_SESSION["id"] = $datos["id"];
                $_SESSION["nombre"] = $datos["nombre"];
                $_SESSION["usuario"] = $datos["usuario"];
                $_SESSION["correo"] = $datos["correo"];
                $_SESSION["tipo_usuario"] = $datos["tipo_usuario"];

                $intento = "Inicio de sesión exitoso";
                $stmtBitacora = $conn->prepare("INSERT INTO bitacora (usr, fecha, hora, intento) VALUES (?, ?, ?, ?)");
                $stmtBitacora->bind_param("ssss", $datos["usuario"], $fecha, $hora, $intento);
                $stmtBitacora->execute();
                $stmtBitacora->close();

                if ($datos["tipo_usuario"] == "admin") {
                    header("Location: admin.php");
                } else {
                    header("Location: tienda.php");
                }
                exit();
            } else {
                $mensaje = "Contraseña incorrecta.";
                $intento = "Inicio de sesión fallido";
                $stmtBitacora = $conn->prepare("INSERT INTO bitacora (usr, fecha, hora, intento) VALUES (?, ?, ?, ?)");
                $stmtBitacora->bind_param("ssss", $usuario, $fecha, $hora, $intento);
                $stmtBitacora->execute();
                $stmtBitacora->close();
            }
        } else {
            $mensaje = "Usuario o correo no encontrado.";
            $intento = "Usuario no encontrado";
            $stmtBitacora = $conn->prepare("INSERT INTO bitacora (usr, fecha, hora, intento) VALUES (?, ?, ?, ?)");
            $stmtBitacora->bind_param("ssss", $usuario, $fecha, $hora, $intento);
            $stmtBitacora->execute();
            $stmtBitacora->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1d1d1d; padding: 30px; border-radius: 12px; width: 350px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        h2 { text-align: center; margin-bottom: 20px; color: #d60000; }
        input { width: 100%; padding: 10px; margin: 8px 0; border-radius: 5px; border: 1px solid #333; box-sizing: border-box; background: #222; color: white; }
        button { width: 100%; padding: 10px; background: #d60000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        button:hover { background: #b00000; }
        .mensaje { background: #d60000; color: white; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; }
        .links { margin-top: 15px; text-align: center; font-size: 14px; }
        .links a { color: #d60000; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <h2>🏍️ Iniciar Sesión</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="usuario" placeholder="Usuario o Correo" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Ingresar</button>
    </form>

    <div class="links">
        <p><a href="recuperar.php">¿Olvidaste tu contraseña?</a></p>
        <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
    </div>
</div>

</body>
</html>
