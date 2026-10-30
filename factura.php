<?php
session_start();
require_once "conexion.php";

// Usamos PHPMailer de forma manual
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit();
}

$usuarioId = (int)$_SESSION["id"];
$esAdmin = (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "admin");
$ventaId = (int)($_GET["id"] ?? 0);
$enviarCorreo = isset($_GET["enviar"]) && $_GET["enviar"] == 1;

if ($ventaId <= 0) {
    die("Venta inválida.");
}

/* =========================
   DATOS DE LA VENTA (Permisos Admin / Cliente)
========================= */
if ($esAdmin) {
    // Si es administrador, puede ver cualquier factura sin restricciones de usuario
    $stmt = $conn->prepare(
        "SELECT v.id, v.fecha, v.total, u.nombre, u.usuario, u.correo 
         FROM ventas v 
         INNER JOIN usuarios u ON v.usuario_id = u.id 
         WHERE v.id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $ventaId);
} else {
    // Si es cliente, solo puede ver sus propias facturas
    $stmt = $conn->prepare(
        "SELECT v.id, v.fecha, v.total, u.nombre, u.usuario, u.correo 
         FROM ventas v 
         INNER JOIN usuarios u ON v.usuario_id = u.id 
         WHERE v.id = ? AND v.usuario_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $ventaId, $usuarioId);
}

$stmt->execute();
$resultado = $stmt->get_result();
$venta = $resultado->fetch_assoc();
$stmt->close();

if (!$venta) {
    die("No se encontró la venta.");
}

/* =========================
   DETALLE (Motos)
========================= */
$stmt = $conn->prepare(
    "SELECT dv.id, dv.moto_id AS producto_id, CONCAT(m.marca, ' ', m.modelo) AS producto, dv.precio_unitario AS precio, dv.cantidad, (dv.precio_unitario * dv.cantidad) AS total, m.imagen, 'image/jpeg' AS imagen_tipo 
     FROM detalle_ventas dv 
     LEFT JOIN motos m ON dv.moto_id = m.id 
     WHERE dv.venta_id = ? ORDER BY dv.id ASC"
);
$stmt->bind_param("i", $ventaId);
$stmt->execute();
$detalles = $stmt->get_result();

$items_array = [];
$subtotalGeneral = 0;
while ($row = $detalles->fetch_assoc()) {
    $items_array[] = $row;
    $subtotalGeneral += (float)$row["total"];
}
$stmt->close();

// Cálculos de IVA (16%) y Total
$ivaGeneral = $subtotalGeneral * 0.16;
$totalGeneralCalculado = $subtotalGeneral + $ivaGeneral;

/* =========================
   PROCESAR ENVÍO DE CORREO
========================= */
if ($enviarCorreo) {
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

        // Generar enlace absoluto y QR optimizado para correo
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $url_qr_destino = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/detalle_qr.php?id=" . $item["producto_id"];
        $api_qr_url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($url_qr_destino) . "&size=150x150";
        $qrTag = "<img src='" . $api_qr_url . "' width='65' height='65' style='display:block; margin:auto; background:#fff; padding:3px; border:1px solid #ddd;'>";

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

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = "smtp.gmail.com";
        $mail->SMTPAuth   = true;
        $mail->Username   = "alumnolex@gmail.com";
        $mail->Password   = "ocdb skoi lqgw rfvr";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = "UTF-8";

        // Parche SSL para XAMPP
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Incrustar imágenes de las motos en el correo
        foreach ($items_array as $item) {
            if (!empty($item["imagen"])) {
                if (file_exists($item["imagen"])) {
                    $mail->addEmbeddedImage($item["imagen"], "moto_" . $item["producto_id"]);
                } elseif (filter_var($item["imagen"], FILTER_VALIDATE_URL)) {
                    $mail->addStringEmbeddedImage(@file_get_contents($item["imagen"]), "moto_" . $item["producto_id"], "moto.jpg");
                }
            }
        }

        $mail->setFrom("alumnolex@gmail.com", "MOTOS");
        $mail->addAddress($venta["correo"], $venta["nombre"]);

        $mail->isHTML(true);
        $mail->Subject = "Factura MOTOS #" . $ventaId;
        $mail->Body    = $cuerpoHtml;

        $mail->send();
        echo "<div style='font-family:Arial; text-align:center; margin-top:80px;'><h2 style='color:#0879e8;'>✓ Correo enviado con éxito a " . htmlspecialchars($venta["correo"]) . "</h2><br><a href='factura.php?id=" . $ventaId . "' style='background:#0879e8; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; font-weight:bold;'>Regresar a la factura</a></div>";
        exit();
    } catch (Exception $e) {
        echo "<div style='font-family:Arial; text-align:center; margin-top:80px;'><h2 style='color:#b42318;'>✕ Error al enviar el correo</h2><p>" . htmlspecialchars($mail->ErrorInfo) . "</p><br><a href='factura.php?id=" . $ventaId . "'>Regresar</a></div>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura #<?= $ventaId ?> | MOTOS</title>
<style>
body { margin: 0; background: #eef2f7; font-family: Arial, sans-serif; color: #172033; }
.factura-container { width: 900px; max-width: calc(100% - 30px); margin: 40px auto; }
.factura { background: white; padding: 45px; border-radius: 18px; box-shadow: 0 10px 35px rgba(0,0,0,.08); }
.encabezado { display: flex; justify-content: space-between; gap: 25px; padding-bottom: 25px; border-bottom: 2px solid #0879e8; }
.logo { display: flex; align-items: center; gap: 12px; }
.logo-icon { width: 55px; height: 55px; border-radius: 12px; background: #0879e8; color: white; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 20px; }
.logo h1 { margin: 0; font-size: 22px; }
.logo small { color: #7b8494; }
.factura-info { text-align: right; }
.factura-info h2 { margin: 0; color: #0879e8; }
.cliente { margin-top: 25px; background: #f7f9fc; padding: 18px; border-radius: 12px; }
.tabla { width: 100%; border-collapse: collapse; margin-top: 30px; }
.tabla th { background: #f7f9fc; padding: 13px; text-align: left; font-size: 12px; color: #687384; }
.tabla td { padding: 14px 10px; border-bottom: 1px solid #edf0f4; }
.producto-imagen { width: 65px; height: 65px; object-fit: contain; border-radius: 9px; background: #f5f7fa; padding: 4px; }
.sin-imagen { width: 65px; height: 65px; background: #f5f7fa; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 9px; color: #98a2b3; }
.totales { width: 320px; margin-left: auto; margin-top: 25px; text-align: right; }
.linea-total { display: flex; justify-content: space-between; padding: 6px 0; font-size: 15px; }
.total-final { border-top: 2px solid #172033; margin-top: 8px; padding-top: 12px; font-size: 20px; font-weight: 900; }
.total-final strong { color: #0879e8; }
.acciones { display: flex; justify-content: center; gap: 12px; margin: 25px auto; }
.btn { padding: 13px 18px; border-radius: 9px; text-decoration: none; font-weight: 800; border: none; cursor: pointer; }
.btn-pdf { background: #0879e8; color: white; }
.btn-correo { background: #172033; color: white; }
.btn-inicio { background: white; color: #0879e8; }
@media print { 
    .acciones { display: none; } 
    body { background: white; }
    .factura { box-shadow: none; padding: 0; }
}
</style>
</head>
<body>

<div class="factura-container">
    <div class="acciones">
        <button onclick="window.print()" class="btn btn-pdf">📄 Descargar / Guardar PDF</button>
        <a href="factura.php?id=<?= $ventaId ?>&enviar=1" class="btn btn-correo">✉️ Enviar por correo</a>
        <a href="productos.php" class="btn btn-inicio">Productos</a>
    </div>

    <div class="factura">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-icon">MT</div>
                <div>
                    <h1>MOTOS</h1>
                    <small>SISTEMA DE VENTAS</small>
                </div>
            </div>
            <div class="factura-info">
                <h2>FACTURA</h2>
                <strong>#<?= $ventaId ?></strong><br>
                <?= htmlspecialchars($venta["fecha"]) ?>
            </div>
        </div>

        <div class="cliente">
            <strong>Cliente:</strong> <?= htmlspecialchars($venta["nombre"]) ?><br>
            Usuario: <?= htmlspecialchars($venta["usuario"]) ?><br>
            Correo: <?= htmlspecialchars($venta["correo"]) ?>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>IMAGEN</th>
                    <th>MOTO</th>
                    <th>PRECIO</th>
                    <th>CANTIDAD</th>
                    <th>TOTAL</th>
                    <th>CÓDIGO QR</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items_array as $item): ?>
                <tr>
                    <td>
                        <?php if (!empty($item["imagen"])): ?>
                            <img class="producto-imagen" src="<?= htmlspecialchars($item["imagen"]) ?>">
                        <?php else: ?>
                            <div class="sin-imagen">Sin imagen</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($item["producto"]) ?></strong></td>
                    <td>$<?= number_format((float)$item["precio"], 2) ?></td>
                    <td><?= (int)$item["cantidad"] ?></td>
                    <td><strong>$<?= number_format((float)$item["total"], 2) ?></strong></td>
                    <td style="text-align: center;">
                        <?php 
                            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                            $url_qr = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/detalle_qr.php?id=" . $item["producto_id"];
                            $api_qr = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($url_qr);
                        ?>
                        <img src="<?= $api_qr ?>" alt="QR" style="width: 60px; height: 60px; background: white; padding: 2px; border-radius: 4px;">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales">
            <div class="linea-total">
                <span>Subtotal:</span>
                <strong>$<?= number_format($subtotalGeneral, 2) ?></strong>
            </div>
            <div class="linea-total">
                <span>IVA (16%):</span>
                <strong>$<?= number_format($ivaGeneral, 2) ?></strong>
            </div>
            <div class="linea-total total-final">
                <span>TOTAL:</span>
                <strong>$<?= number_format($totalGeneralCalculado, 2) ?></strong>
            </div>
        </div>
    </div>
</div>

</body>
</html>