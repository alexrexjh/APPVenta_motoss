<?php
require_once "conexion.php";

$id = intval($_GET["id"] ?? 0);
$stmt = $conn->prepare("SELECT * FROM motos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$moto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$moto) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>Producto no encontrado.</h2>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle: <?= htmlspecialchars($moto['marca'] . ' ' . $moto['modelo']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1d1d1d; padding: 25px; border-radius: 12px; width: 320px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.6); border: 2px solid #d60000; }
        img { width: 100%; height: 180px; object-fit: contain; border-radius: 8px; background: #222; margin-bottom: 15px; padding: 5px; box-sizing: border-box; }
        h2 { color: #d60000; margin: 10px 0; font-size: 20px; }
        p { font-size: 16px; margin: 10px 0; }
        .precio { color: #28a745; font-weight: bold; font-size: 22px; }
        .stock { color: #17a2b8; font-weight: bold; }
    </style>
</head>
<body>

<div class="card">
    <img src="<?= htmlspecialchars($moto['imagen']) ?>" onerror="this.src='https://via.placeholder.com/200?text=Sin+Imagen'">
    <h2><?= htmlspecialchars($moto['marca'] . ' ' . $moto['modelo']) ?></h2>
    <p>Stock disponible: <span class="stock"><?= $moto['stock'] ?> unidades</span></p>
    <p>Precio: <span class="precio">$<?= number_format($moto['precio'], 2) ?></span></p>
</div>

</body>
</html>