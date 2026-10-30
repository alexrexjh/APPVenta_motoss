<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["id"]) || !isset($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$venta_id = intval($_GET["id"]);
$usuario_id = $_SESSION["id"];
$es_admin = ($_SESSION["tipo_usuario"] === "admin");

// Obtener datos de la venta (si es admin puede ver cualquiera, si es cliente solo las suyas)
if ($es_admin) {
    $stmt = $conn->prepare("SELECT v.*, u.nombre, u.correo FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE v.id = ?");
    $stmt->bind_param("i", $venta_id);
} else {
    $stmt = $conn->prepare("SELECT v.*, u.nombre, u.correo FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE v.id = ? AND v.usuario_id = ?");
    $stmt->bind_param("ii", $venta_id, $usuario_id);
}

$stmt->execute();
$res_venta = $stmt->get_result();
if ($res_venta->num_rows == 0) {
    die("Comprobante no encontrado o sin permisos.");
}
$venta = $res_venta->fetch_assoc();
$stmt->close();

// Obtener detalles de la venta
$stmt_det = $conn->prepare("SELECT dv.*, m.marca, m.modelo FROM detalle_ventas dv JOIN motos m ON dv.moto_id = m.id WHERE dv.venta_id = ?");
$stmt_det->bind_param("i", $venta_id);
$stmt_det->execute();
$detalles = $stmt_det->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Venta #<?php echo $venta_id; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; color: #333; margin: 30px; }
        .factura-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header-factura { display: flex; justify-content: space-between; border-bottom: 2px solid #d60000; padding-bottom: 15px; margin-bottom: 20px; }
        h2 { color: #d60000; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; color: #333; }
        .total { text-align: right; font-size: 20px; font-weight: bold; margin-top: 20px; color: #28a745; }
        .btn-print { background: #d60000; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 20px; }
        @media print {
            .btn-print, .no-print { display: none; }
            body { margin: 0; }
            .factura-box { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="factura-box">
    <div class="header-factura">
        <div>
            <h2>🏍️ Venta de Motos</h2>
            <p>Comprobante de Compra</p>
        </div>
        <div style="text-align: right;">
            <p><strong>Factura #:</strong> <?php echo $venta["id"]; ?></p>
            <p><strong>Fecha:</strong> <?php echo $venta["fecha"]; ?></p>
        </div>
    </div>

    <div>
        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($venta["nombre"]); ?></p>
        <p><strong>Correo:</strong> <?php echo htmlspecialchars($venta["correo"]); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Moto</th>
                <th>Precio Unitario</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $detalles->fetch_assoc()): ?>
                <?php $sub = $item["precio_unitario"] * $item["cantidad"]; ?>
                <tr>
                    <td><?php echo htmlspecialchars($item["marca"] . " " . $item["modelo"]); ?></td>
                    <td>$<?php echo number_format($item["precio_unitario"], 2); ?></td>
                    <td><?php echo $item["cantidad"]; ?></td>
                    <td>$<?php echo number_format($sub, 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="total">
        Total Pagado: $<?php echo number_format($venta["total"], 2); ?>
    </div>

    <div style="text-align: center;">
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir / Guardar como PDF</button>
        <br><br>
        <a href="javascript:window.close();" class="no-print" style="color: #555; text-decoration: none;">Cerrar ventana</a>
    </div>
</div>
$cuerpoHtml = "
    <h2>MOTOS - Factura #" . $ventaId . "</h2>
    <p>Cliente: <strong>" . htmlspecialchars($venta["nombre"]) . "</strong></p>
    <p>Fecha: " . htmlspecialchars($venta["fecha"]) . "</p>
    <table border='1' cellspacing='0' cellpadding='6' style='width:100%; border-collapse:collapse;'>
    <tr style='background:#f1f4f8;'><th>Imagen Moto</th><th>Producto</th><th>Precio</th><th>Cant</th><th>Total</th><th>Código QR</th></tr>";
    
    foreach ($items_array as $item) {
        $imgTag = "Sin imagen";
        if (!empty($item["imagen"])) {
            $imgTag = "<img src='cid:moto_" . $item["producto_id"] . "' style='width:55px; height:55px; object-fit:contain;'>";
        }

        // Generar el código QR incrustado para el correo
        $qrTag = "<span style='color:#777;'>Sin QR</span>";
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $url_qr_destino = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/detalle_qr.php?id=" . $item["producto_id"];
        $api_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($url_qr_destino);
        
        $qrTag = "<img src='cid:qr_" . $item["producto_id"] . "' style='width:65px; height:65px;'>";

        $cuerpoHtml .= "<tr>
            <td style='text-align:center;'>" . $imgTag . "</td>
            <td>" . htmlspecialchars($item["producto"]) . "</td>
            <td>$" . number_format((float)$item["precio"], 2) . "</td>
            <td style='text-align:center;'>" . (int)$item["cantidad"] . "</td>
            <td>$" . number_format((float)$item["total"], 2) . "</td>
            <td style='text-align:center;'>" . $qrTag . "</td>
        </tr>";
    }
    
    $cuerpoHtml .= "
    </table>
    <div style='text-align:right; margin-top:15px; font-size:16px;'>
        <p>Subtotal: <strong>$" . number_format($subtotalGeneral, 2) . "</strong></p>
        <p>IVA (16%): <strong>$" . number_format($ivaGeneral, 2) . "</strong></p>
        <h3 style='color:#28a745;'>TOTAL: $" . number_format($totalGeneralCalculado, 2) . "</h3>
    </div>";
    
</body>
</html>