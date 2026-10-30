<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["id"])) {
    header("Location: index.php");
 exit();
 
}

$mensaje = "";

if (isset($_GET["eliminar"])) {
    $id_eliminar = intval($_GET["eliminar"]);
    unset($_SESSION["carrito"][$id_eliminar]);
    header("Location: carrito.php");
    exit();
}

if (isset($_GET["vaciar"])) {
    unset($_SESSION["carrito"]);
    header("Location: carrito.php");
    exit();
}

// Finalizar Compra y registrar en la Base de Datos
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["finalizar_compra"])) {
    if (!empty($_SESSION["carrito"])) {
        $conn->begin_transaction();
        $error = false;
        $usuario_id = $_SESSION["id"];
        $total_general = 0;

        foreach ($_SESSION["carrito"] as $item) {
            $total_general += ($item["precio"] * $item["cantidad"]);
        }

        // 1. Insertar la venta principal
        $stmtVenta = $conn->prepare("INSERT INTO ventas (usuario_id, total) VALUES (?, ?)");
        $stmtVenta->bind_param("id", $usuario_id, $total_general);
        $stmtVenta->execute();
        $venta_id = $stmtVenta->insert_id;
        $stmtVenta->close();

        // 2. Insertar detalles y descontar stock
        foreach ($_SESSION["carrito"] as $id => $item) {
            $cant = $item["cantidad"];
            $precio = $item["precio"];

            // Descontar stock
            $stmtStock = $conn->prepare("UPDATE motos SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmtStock->bind_param("iii", $cant, $id, $cant);
            $stmtStock->execute();

            if ($stmtStock->affected_rows == 0) {
                $error = true;
            }
            $stmtStock->close();

            // Guardar detalle de venta
            $stmtDetalle = $conn->prepare("INSERT INTO detalle_ventas (venta_id, moto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
            $stmtDetalle->bind_param("iiid", $venta_id, $id, $cant, $precio);
            $stmtDetalle->execute();
            $stmtDetalle->close();
        }

       if (!$error) {
            $conn->commit();
            unset($_SESSION["carrito"]);
            
            // CAMBIA ESTA LÍNEA:
            // header("Location: enviar_factura.php?id=" . $venta_id);
            
            // POR ESTA:
            header("Location: factura.php?id=" . $venta_id);
            exit();
        }
        } else {
            $conn->rollback();
            $mensaje = "❌ Error: Stock insuficiente en algunos productos.";
        }
    }
$total_general = 0;
if (isset($_SESSION["carrito"])) {
    foreach ($_SESSION["carrito"] as $item) {
        $total_general += ($item["precio"] * $item["cantidad"]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito de Compras</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d60000; padding-bottom: 10px; }
        h1 { color: #d60000; }
        .btn-header { background: #d60000; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .card { background: #1d1d1d; padding: 20px; border-radius: 10px; margin-top: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #222; color: #d60000; }
        .img-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; }
        .total-box { font-size: 22px; font-weight: bold; color: #28a745; text-align: right; margin-top: 15px; }
        .btn-acciones { display: flex; justify-content: space-between; margin-top: 20px; }
        .btn-eliminar { color: #ff4d4d; text-decoration: none; font-weight: bold; }
        .btn-comprar { background: #28a745; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; }
        .btn-comprar:hover { background: #218838; }
        .btn-vaciar { background: #555; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; }
        .alert { background: #ff4d4d; color: white; padding: 12px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>

<header>
    <h1>🛒 Carrito de Compras</h1>
    <div>
        <a href="tienda.php" class="btn-header">⬅️ Volver a la Tienda</a>
        <a href="mis_pedidos.php" class="btn-header" style="background: #007bff;">📦 Mis Pedidos</a>
    </div>
</header>

<div class="card">
    <?php if (!empty($mensaje)): ?>
        <div class="alert"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION["carrito"])): ?>
     <table>
            <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Moto / Producto</th>
                    <th>Stock Dispo.</th>
                    <th>Precio Unit.</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                    <th>Código QR</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION["carrito"] as $id => $item): ?>
                    <?php 
                        $subtotal = $item["precio"] * $item["cantidad"]; 
                        
                        // Consultar stock actual desde la base de datos para asegurarlo en el carrito
                        $stmt_stk = $conn->prepare("SELECT stock FROM motos WHERE id = ?");
                        $stmt_stk->bind_param("i", $id);
                        $stmt_stk->execute();
                        $res_stk = $stmt_stk->get_result()->fetch_assoc();
                        $stock_actual = $res_stk['stock'] ?? 0;
                        $stmt_stk->close();

                        // Enlace y API para generar el QR con imagen, stock y precio (vía detalle_qr.php)
                        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $url_qr = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/detalle_qr.php?id=" . $id;
                        $api_qr = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($url_qr);
                    ?>
                    <tr>
                        <td>
                            <img src="<?php echo !empty($item['imagen']) ? htmlspecialchars($item['imagen']) : 'https://via.placeholder.com/60'; ?>" class="img-thumb" onerror="this.src='https://via.placeholder.com/60'">
                        </td>
                        <td><strong><?php echo htmlspecialchars($item["marca"] . " " . $item["modelo"]); ?></strong></td>
                        <td style="color: #17a2b8; text-align: center;"><?php echo $stock_actual; ?></td>
                        <td>$<?php echo number_format($item["precio"], 2); ?></td>
                        <td style="text-align: center;"><?php echo $item["cantidad"]; ?></td>
                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                        <td style="text-align: center;">
                            <a href="<?php echo $url_qr; ?>" target="_blank" title="Escanear QR">
                                <img src="<?php echo $api_qr; ?>" alt="QR Producto" style="width: 65px; height: 65px; background: white; padding: 2px; border-radius: 4px;">
                            </a>
                        </td>
                        <td>
                            <a href="carrito.php?eliminar=<?php echo $id; ?>" class="btn-eliminar">❌ Quitar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-box">
            Total a Pagar: $<?php echo number_format($total_general, 2); ?>
        </div>

        <div class="btn-acciones">
            <a href="carrito.php?vaciar=1" class="btn-vaciar">Vaciar Carrito</a>
            <form method="POST">
                <input type="hidden" name="finalizar_compra" value="1">
                <button type="submit" class="btn-comprar">💳 Finalizar Compra</button>
            </form>
        </div>

    <?php else: ?>
        <p style="text-align: center; padding: 20px;">Tu carrito de compras está vacío.</p>
        <div style="text-align: center;">
            <a href="tienda.php" class="btn-header">Ir a ver motos</a>
            <a href="mis_pedidos.php" class="btn-header" style="background: #007bff;">📦 Ver mis pedidos anteriores</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

