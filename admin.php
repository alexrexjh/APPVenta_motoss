<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["id"]) || $_SESSION["tipo_usuario"] !== "admin") {
    header("Location: index.php");
    exit();
}

$mensaje_moto = "";
$moto_editar = null; 

if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $stmt_edit = $conn->prepare("SELECT * FROM motos WHERE id = ?");
    $stmt_edit->bind_param("i", $id_editar);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if ($res_edit->num_rows == 1) {
        $moto_editar = $res_edit->fetch_assoc();
    }
    $stmt_edit->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["guardar_moto"])) {
    $id_moto = isset($_POST["id_moto"]) ? intval($_POST["id_moto"]) : 0;
    $marca = trim($_POST["marca"]);
    $modelo = trim($_POST["modelo"]);
    $anio = intval($_POST["anio"]);
    $cilindrada = trim($_POST["cilindrada"]);
    $precio = floatval($_POST["precio"]);
    $stock = intval($_POST["stock"]);
    $imagen = trim($_POST["imagen"]);
    $descripcion = trim($_POST["descripcion"]);

    if (!empty($marca) && !empty($modelo) && $precio > 0) {
        if ($id_moto > 0) {
            $sql = "UPDATE motos SET marca=?, modelo=?, anio=?, cilindrada=?, precio=?, stock=?, imagen=?, descripcion=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisdissi", $marca, $modelo, $anio, $cilindrada, $precio, $stock, $imagen, $descripcion, $id_moto);
            if ($stmt->execute()) {
                header("Location: admin.php?msj=" . urlencode("Moto actualizada correctamente."));
                exit();
            }
            $stmt->close();
        } else {
            $sql = "INSERT INTO motos (marca, modelo, anio, cilindrada, precio, stock, imagen, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisdiss", $marca, $modelo, $anio, $cilindrada, $precio, $stock, $imagen, $descripcion);
            if ($stmt->execute()) {
                $mensaje_moto = "Moto registrada con éxito.";
            }
            $stmt->close();
        }
    }
}

// Estadísticas generales acumuladas (históricas)
$resIngresos = $conn->query("SELECT SUM(total) as total_ingresos, COUNT(*) as total_ventas FROM ventas");
$datosReporte = $resIngresos->fetch_assoc();
$ingresosTotales = $datosReporte["total_ingresos"] ?? 0;
$cantidadVentas = $datosReporte["total_ventas"] ?? 0;

// Control de Reportes por Rango Personalizado (De / A) o accesos rápidos
$tipo_reporte = isset($_GET['reporte']) ? $_GET['reporte'] : 'mes';
$fecha_de = isset($_GET['de']) ? $_GET['de'] : date('Y-m-01'); // Inicio de mes por defecto
$fecha_a = isset($_GET['a']) ? $_GET['a'] : date('Y-m-d');     // Fecha actual por defecto

$ingresosFiltrados = 0;
$ventasFiltradasCount = 0;
$tituloReporte = "Personalizado";

if (isset($_GET['de']) && isset($_GET['a'])) {
    $tituloReporte = "Del " . $fecha_de . " al " . $fecha_a;
    $sqlFiltro = "SELECT SUM(total) as ingresos, COUNT(*) as total FROM ventas WHERE DATE(fecha) BETWEEN ? AND ?";
    $stmtFiltro = $conn->prepare($sqlFiltro);
    $stmtFiltro->bind_param("ss", $fecha_de, $fecha_a);
    $stmtFiltro->execute();
    $resFiltro = $stmtFiltro->get_result();
    
    $sqlListaVentas = "SELECT v.id, u.nombre as cliente, v.total, v.fecha FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE DATE(v.fecha) BETWEEN ? AND ? ORDER BY v.fecha DESC";
    $stmtLista = $conn->prepare($sqlListaVentas);
    $stmtLista->bind_param("ss", $fecha_de, $fecha_a);
    $stmtLista->execute();
    $resVentasAdmin = $stmtLista->get_result();
} else {
    // Accesos rápidos por botones
    if ($tipo_reporte == 'dia') {
        $tituloReporte = "Diario (Hoy)";
        $sqlFiltro = "SELECT SUM(total) as ingresos, COUNT(*) as total FROM ventas WHERE DATE(fecha) = CURDATE()";
        $sqlListaVentas = "SELECT v.id, u.nombre as cliente, v.total, v.fecha FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE DATE(v.fecha) = CURDATE() ORDER BY v.fecha DESC";
        $resVentasAdmin = $conn->query($sqlListaVentas);
    } elseif ($tipo_reporte == 'semana') {
        $tituloReporte = "Semanal (Esta semana)";
        $sqlFiltro = "SELECT SUM(total) as ingresos, COUNT(*) as total FROM ventas WHERE YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)";
        $sqlListaVentas = "SELECT v.id, u.nombre as cliente, v.total, v.fecha FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE YEARWEEK(v.fecha, 1) = YEARWEEK(CURDATE(), 1) ORDER BY v.fecha DESC";
        $resVentasAdmin = $conn->query($sqlListaVentas);
    } else { // Mes actual por defecto
        $tituloReporte = "Mensual (Este mes)";
        $sqlFiltro = "SELECT SUM(total) as ingresos, COUNT(*) as total FROM ventas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
        $sqlListaVentas = "SELECT v.id, u.nombre as cliente, v.total, v.fecha FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE MONTH(v.fecha) = MONTH(CURDATE()) AND YEAR(v.fecha) = YEAR(CURDATE()) ORDER BY v.fecha DESC";
        $resVentasAdmin = $conn->query($sqlListaVentas);
    }
    $resFiltro = $conn->query($sqlFiltro);
}

if ($resFiltro) {
    $rowF = $resFiltro->fetch_assoc();
    $ingresosFiltrados = $rowF["ingresos"] ?? 0;
    $ventasFiltradasCount = $rowF["total"] ?? 0;
}

// Consulta segura para el Top de Modelos
$labelsTopMotos = [];
$dataTopMotos = [];
$queryTopMotos = "SELECT CONCAT(m.marca, ' ', m.modelo) as moto, SUM(dv.cantidad) as total_vendidas
                 FROM detalle_ventas dv
                 JOIN motos m ON dv.moto_id = m.id
                 GROUP BY m.id
                 ORDER BY total_vendidas DESC
                 LIMIT 5";
$resTopMotos = $conn->query($queryTopMotos);
if ($resTopMotos) {
    while ($row = $resTopMotos->fetch_assoc()) {
        $labelsTopMotos[] = $row['moto'];
        $dataTopMotos[] = (int)$row['total_vendidas'];
    }
}

if (isset($_GET['msj'])) {
    $mensaje_moto = $_GET['msj'];
}

$queryBitacora = "SELECT usr, fecha, hora, intento FROM bitacora ORDER BY id DESC LIMIT 10";
$resBitacora = $conn->query($queryBitacora);

$queryMotos = "SELECT * FROM motos ORDER BY id DESC";
$resMotos = $conn->query($queryMotos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - Reportes</title>
    <!-- Incluimos Chart.js desde CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d60000; padding-bottom: 10px; }
        h1, h2 { color: #d60000; }
        a.btn-logout { background: #d60000; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .container { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; }
        .card { background: #1d1d1d; padding: 20px; border-radius: 10px; flex: 1; min-width: 300px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        input, textarea, button, select { width: 100%; padding: 8px; margin: 6px 0; border-radius: 5px; border: 1px solid #333; background: #222; color: white; box-sizing: border-box; }
        button { background: #d60000; cursor: pointer; font-weight: bold; border: none; padding: 10px; }
        button:hover { background: #b00000; }
        a.btn-cancelar { display: block; text-align: center; background: #555; color: white; padding: 8px; border-radius: 5px; text-decoration: none; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; vertical-align: middle; }
        th { background: #222; color: #d60000; }
        .alert { background: #4bb543; color: white; padding: 8px; border-radius: 5px; margin-bottom: 10px; }
        .img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid #444; }
        .btn-editar { background: #ff9800; color: black; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-weight: bold; font-size: 12px; }
        .stats-box { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { background: #222; padding: 15px; border-radius: 8px; flex: 1; min-width: 250px; border-left: 4px solid #28a745; }
        .stat-card h3 { margin: 0 0 5px 0; font-size: 16px; color: #aaa; }
        .stat-card p { margin: 0; font-size: 22px; font-weight: bold; color: #28a745; }
        .filtro-botones { margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-filtro { background: #333; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; border: 1px solid #444; }
        .btn-filtro.active { background: #d60000; border-color: #d60000; }
        .form-rango { display: flex; gap: 10px; align-items: flex-end; background: #222; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .form-rango div { flex: 1; }
        .form-rango label { font-size: 13px; color: #aaa; display: block; margin-bottom: 3px; }
    </style>
</head>
<body>

<header>
    <h1>🏍️ Panel Administrador</h1>
    <div>
        <span>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION["nombre"]); ?></strong></span> | 
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<!-- Resumen de Estadísticas Globales -->
<div class="stats-box" style="margin-top: 20px;">
    <div class="stat-card">
        <h3>Ingresos Totales (Histórico)</h3>
        <p>$<?php echo number_format($ingresosTotales, 2); ?></p>
    </div>
    <div class="stat-card" style="border-left-color: #007bff;">
        <h3>Total de Ventas (Histórico)</h3>
        <p style="color: #007bff;"><?php echo $cantidadVentas; ?> pedidos</p>
    </div>
</div>

<!-- SECCIÓN DE REPORTES (Día, Semana, Mes y Rango Personalizado De / A) -->
<div class="card" style="margin-top: 20px;">
    <h2>📈 Generador de Reportes de Ventas</h2>
    
    <!-- Botones de Acceso Rápido -->
    <div class="filtro-botones">
        <a href="admin.php?reporte=dia" class="btn-filtro <?php echo (!isset($_GET['de']) && $tipo_reporte == 'dia') ? 'active' : ''; ?>">📅 Hoy (Día)</a>
        <a href="admin.php?reporte=semana" class="btn-filtro <?php echo (!isset($_GET['de']) && $tipo_reporte == 'semana') ? 'active' : ''; ?>">📊 Esta Semana</a>
        <a href="admin.php?reporte=mes" class="btn-filtro <?php echo (!isset($_GET['de']) && $tipo_reporte == 'mes') ? 'active' : ''; ?>">🗓️ Este Mes</a>
    </div>

    <!-- Formulario Personalizado por Rango (De / A) estilo Pizarrón -->
    <form method="GET" action="admin.php" class="form-rango">
        <div>
            <label>De (Fecha Inicio):</label>
            <input type="date" name="de" value="<?php echo htmlspecialchars($fecha_de); ?>" required>
        </div>
        <div>
            <label>A (Fecha Fin):</label>
            <input type="date" name="a" value="<?php echo htmlspecialchars($fecha_a); ?>" required>
        </div>
        <div>
            <button type="submit" style="margin: 0; height: 38px;">Generar Reporte</button>
        </div>
    </form>

    <!-- Tarjetas de Resumen del Reporte Seleccionado -->
    <div class="stats-box">
        <div class="stat-card" style="border-left-color: #ff9800;">
            <h3>Ingresos [<?php echo $tituloReporte; ?>]</h3>
            <p style="color: #ff9800;">$<?php echo number_format($ingresosFiltrados, 2); ?></p>
        </div>
        <div class="stat-card" style="border-left-color: #17a2b8;">
            <h3>Pedidos [<?php echo $tituloReporte; ?>]</h3>
            <p style="color: #17a2b8;"><?php echo $ventasFiltradasCount; ?> ventas</p>
        </div>
    </div>

    <!-- Tabla Detallada del Reporte -->
    <h3 style="color: #aaa; margin-top: 20px;">Detalle de Ventas - <?php echo $tituloReporte; ?></h3>
    <table>
        <thead>
            <tr>
                <th>ID Venta</th>
                <th>Cliente</th>
                <th>Total</th>
                <th>Fecha y Hora</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resVentasAdmin->num_rows > 0): ?>
                <?php while ($v = $resVentasAdmin->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $v["id"]; ?></td>
                    <td><?php echo htmlspecialchars($v["cliente"]); ?></td>
                    <td style="color: #28a745; font-weight: bold;">$<?php echo number_format($v["total"], 2); ?></td>
                    <td><?php echo $v["fecha"]; ?></td>
                    <td>
                        <a href="factura.php?id=<?php echo $v["id"]; ?>" target="_blank" class="btn-editar" style="background: #007bff; color: white;">Ver Recibo</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #777; padding: 15px;">No hay registros de ventas para este periodo.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="container">
    <!-- Formulario para agregar / actualizar motos -->
    <div class="card">
        <h2><?php echo $moto_editar ? "✏️ Editar Moto #".$moto_editar['id'] : "➕ Registrar Nueva Moto"; ?></h2>
        
        <?php if (!empty($mensaje_moto)): ?>
            <div class="alert"><?php echo htmlspecialchars($mensaje_moto); ?></div>
        <?php endif; ?>

        <form method="POST" action="admin.php">
            <input type="hidden" name="guardar_moto" value="1">
            <input type="hidden" name="id_moto" value="<?php echo $moto_editar ? $moto_editar['id'] : 0; ?>">

            <input type="text" name="marca" placeholder="Marca (ej. Yamaha)" value="<?php echo $moto_editar ? htmlspecialchars($moto_editar['marca']) : ''; ?>" required>
            <input type="text" name="modelo" placeholder="Modelo (ej. R3)" value="<?php echo $moto_editar ? htmlspecialchars($moto_editar['modelo']) : ''; ?>" required>
            <input type="number" name="anio" placeholder="Año" value="<?php echo $moto_editar ? $moto_editar['anio'] : date('Y'); ?>" required>
            <input type="text" name="cilindrada" placeholder="Cilindrada (ej. 321cc)" value="<?php echo $moto_editar ? htmlspecialchars($moto_editar['cilindrada']) : ''; ?>" required>
            <input type="number" step="0.01" name="precio" placeholder="Precio ($)" value="<?php echo $moto_editar ? $moto_editar['precio'] : ''; ?>" required>
            <input type="number" name="stock" placeholder="Cantidad en Stock" value="<?php echo $moto_editar ? $moto_editar['stock'] : 1; ?>" required>
            <input type="url" name="imagen" placeholder="URL de la imagen" value="<?php echo $moto_editar ? htmlspecialchars($moto_editar['imagen']) : ''; ?>">
            <textarea name="descripcion" placeholder="Descripción"><?php echo $moto_editar ? htmlspecialchars($moto_editar['descripcion']) : ''; ?></textarea>

            <button type="submit"><?php echo $moto_editar ? "Guardar Cambios" : "Guardar Moto"; ?></button>
            <?php if ($moto_editar): ?>
                <a href="admin.php" class="btn-cancelar">Cancelar Edición</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bitácora de accesos -->
    <div class="card">
        <h2>Últimos Accesos (Bitácora)</h2>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resBitacora->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row["usr"]); ?></td>
                    <td><?php echo $row["fecha"]; ?></td>
                    <td><?php echo $row["hora"]; ?></td>
                    <td><?php echo htmlspecialchars($row["intento"]); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h2>🏆 Top 5 Modelos de Motos Más Vendidos</h2>
    <canvas id="chartTopMotos"></canvas>
</div>

<!-- Catálogo registrado -->
<div class="card" style="margin-top: 20px;">
    <h2>Motos Registradas en Inventario</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Imagen</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Año</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($moto = $resMotos->fetch_assoc()): ?>
            <tr>
                <td><?php echo $moto["id"]; ?></td>
                <td>
                    <?php if (!empty($moto["imagen"])): ?>
                        <img src="<?php echo htmlspecialchars($moto["imagen"]); ?>" class="img-preview" onerror="this.src='https://via.placeholder.com/50?text=Sin+Img'">
                    <?php else: ?>
                        <span style="color: #777;">Sin imagen</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($moto["marca"]); ?></td>
                <td><?php echo htmlspecialchars($moto["modelo"]); ?></td>
                <td><?php echo $moto["anio"]; ?></td>
                <td>$<?php echo number_format($moto["precio"], 2); ?></td>
                <td><?php echo $moto["stock"]; ?></td>
                <td>
                    <a href="admin.php?editar=<?php echo $moto["id"]; ?>" class="btn-editar">Editar</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<!-- Catálogo registrado con Código QR -->
<div class="card" style="margin-top: 20px;">
    <h2>Motos Registradas en Inventario (Con Código QR)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Imagen</th>
                <th>Marca y Modelo</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Código QR</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Reiniciamos el puntero de la consulta de motos por seguridad
            $resMotos = $conn->query("SELECT * FROM motos ORDER BY id DESC");
            while ($moto = $resMotos->fetch_assoc()): 
                // Generar URL absoluta para el QR apuntando a detalle_qr.php con el ID de la moto
                $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $url_qr = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/detalle_qr.php?id=" . $moto['id'];
                $api_qr = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($url_qr);
            ?>
            <tr>
                <td><?php echo $moto["id"]; ?></td>
                <td>
                    <?php if (!empty($moto["imagen"])): ?>
                        <img src="<?php echo htmlspecialchars($moto["imagen"]); ?>" class="img-preview" onerror="this.src='https://via.placeholder.com/50?text=Sin+Img'">
                    <?php else: ?>
                        <span style="color: #777;">Sin imagen</span>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo htmlspecialchars($moto["marca"] . " " . $moto["modelo"]); ?></strong></td>
                <td style="color: #28a745;">$<?php echo number_format($moto["precio"], 2); ?></td>
                <td><?php echo $moto["stock"]; ?></td>
                <td style="text-align: center;">
                    <!-- Muestra el código QR generado por API -->
                    <a href="<?php echo $url_qr; ?>" target="_blank" title="Haz clic para probar">
                        <img src="<?php echo $api_qr; ?>" alt="QR Moto" style="width: 70px; height: 70px; border-radius: 4px; background: white; padding: 2px;">
                    </a>
                </td>
                <td>
                    <a href="admin.php?editar=<?php echo $moto["id"]; ?>" class="btn-editar">Editar</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- SCRIPT DE CHART.JS -->
<script>
    const ctxTopMotos = document.getElementById('chartTopMotos').getContext('2d');
    new Chart(ctxTopMotos, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labelsTopMotos); ?>,
            datasets: [{
                label: 'Unidades Vendidas',
                data: <?php echo json_encode($dataTopMotos); ?>,
                backgroundColor: '#ff9800',
                borderColor: '#e68a00',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: { ticks: { color: 'white' } },
                y: { ticks: { color: 'white' }, beginAtZero: true }
            },
            plugins: { legend: { labels: { color: 'white' } } }
        }
    });
</script>

</body>
</html>