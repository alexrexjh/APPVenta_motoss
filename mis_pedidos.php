<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION["id"];
$sql = "SELECT * FROM ventas WHERE usuario_id = ? ORDER BY fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Pedidos</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d60000; padding-bottom: 10px; }
        h1 { color: #d60000; }
        .btn-header { background: #d60000; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .card { background: #1d1d1d; padding: 20px; border-radius: 10px; margin-top: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #222; color: #d60000; }
        .btn-factura { background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 14px; font-weight: bold; }
        .btn-factura:hover { background: #0056b3; }
    </style>
</head>
<body>

<header>
    <h1>📦 Historial de Mis Pedidos</h1>
    <div>
        <a href="tienda.php" class="btn-header">⬅️ Volver a la Tienda</a>
    </div>
</header>

<div class="card">
    <?php if ($resultado->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID Venta</th>
                    <th>Fecha y Hora</th>
                    <th>Total Pagado</th>
                    <th>Comprobante</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($venta = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $venta["id"]; ?></td>
                        <td><?php echo $venta["fecha"]; ?></td>
                        <td style="color: #28a745; font-weight: bold;">$<?php echo number_format($venta["total"], 2); ?></td>
                        <td>
                            <a href="factura.php?id=<?php echo $venta["id"]; ?>" target="_blank" class="btn-factura">📄 Ver Comprobante / PDF</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; padding: 20px;">Aún no has realizado ninguna compra.</p>
    <?php endif; ?>
</div>

</body>
</html>

