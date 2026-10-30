<?php
session_start();
require_once "conexion.php";

// Verificar sesión
if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit();
}

$mensaje_tienda = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["agregar_al_carrito"])) {
    $id_moto = intval($_POST["id_moto"]);
    $cantidad = intval($_POST["cantidad"]);

    // Obtener datos de la moto
    $stmt = $conn->prepare("SELECT * FROM motos WHERE id = ?");
    $stmt->bind_param("i", $id_moto);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 1) {
        $moto = $res->fetch_assoc();

        // Inicializar carrito si no existe
        if (!isset($_SESSION["carrito"])) {
            $_SESSION["carrito"] = [];
        }

        // Si ya está en el carrito, sumar cantidad
        if (isset($_SESSION["carrito"][$id_moto])) {
            $_SESSION["carrito"][$id_moto]["cantidad"] += $cantidad;
        } else {
            $_SESSION["carrito"][$id_moto] = [
                "id" => $moto["id"],
                "marca" => $moto["marca"],
                "modelo" => $moto["modelo"],
                "precio" => $moto["precio"],
                "imagen" => $moto["imagen"],
                "cantidad" => $cantidad
            ];
        }

        $mensaje_tienda = "¡Moto agregada al carrito!";
    }
    $stmt->close();
}

// Obtener motos disponibles
$sql = "SELECT * FROM motos WHERE stock > 0 ORDER BY id DESC";
$resultado = $conn->query($sql);

// Contar ítems en carrito para el badge
$total_items = 0;
if (isset($_SESSION["carrito"])) {
    foreach ($_SESSION["carrito"] as $item) {
        $total_items += $item["cantidad"];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda de Motos</title>
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: white; margin: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d60000; padding-bottom: 10px; }
        h1 { color: #d60000; }
        .btn-header { background: #d60000; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-left: 10px; }
        .btn-carrito { background: #28a745; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-top: 20px; }
        .moto-card { background: #1d1d1d; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.5); display: flex; flex-direction: column; justify-content: space-between; }
        .moto-card h3 { color: #d60000; margin-bottom: 5px; }
        .moto-img { width: 100%; height: 180px; object-fit: cover; border-radius: 8px; margin: 10px 0; background: #222; }
        .precio { font-size: 20px; font-weight: bold; color: #28a745; margin: 10px 0; }
        .btn-comprar { background: #d60000; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-comprar:hover { background: #b00000; }
        .input-cant { width: 60px; padding: 5px; text-align: center; border-radius: 5px; border: 1px solid #333; background: #222; color: white; }
        .alert { background: #28a745; color: white; padding: 10px; border-radius: 5px; margin-top: 15px; text-align: center; }
    </style>
</head>
<body>

<header>
    <h1>🏎️ Catálogo de Motocicletas</h1>
    <div>
        <span>Hola, <strong><?php echo htmlspecialchars($_SESSION["nombre"]); ?></strong></span> | 
        <a href="carrito.php" class="btn-header btn-carrito">🛒 Carrito (<?php echo $total_items; ?>)</a>
        <a href="logout.php" class="btn-header">Cerrar Sesión</a>
    </div>
</header>

<?php if (!empty($mensaje_tienda)): ?>
    <div class="alert"><?php echo htmlspecialchars($mensaje_tienda); ?></div>
<?php endif; ?>

<div class="grid">
    <?php if ($resultado->num_rows > 0): ?>
        <?php while ($moto = $resultado->fetch_assoc()): ?>
            <div class="moto-card">
                <div>
                    <h3><?php echo htmlspecialchars($moto["marca"] . " " . $moto["modelo"]); ?></h3>
                    
                    <?php 
                        $url_imagen = !empty($moto["imagen"]) ? htmlspecialchars($moto["imagen"]) : "https://via.placeholder.com/300x180?text=Sin+Imagen";
                    ?>
                    <img src="<?php echo $url_imagen; ?>" alt="<?php echo htmlspecialchars($moto["modelo"]); ?>" class="moto-img" onerror="this.src='https://via.placeholder.com/300x180?text=Imagen+No+Disponible'">

                    <p>Año: <?php echo $moto["anio"]; ?> | CC: <?php echo htmlspecialchars($moto["cilindrada"]); ?></p>
                    <p>Stock disponible: <strong><?php echo $moto["stock"]; ?></strong></p>
                    <p><?php echo htmlspecialchars($moto["descripcion"]); ?></p>
                </div>

                <div>
                    <div class="precio">$<?php echo number_format($moto["precio"], 2); ?></div>
                    
                    <form method="POST">
                        <input type="hidden" name="agregar_al_carrito" value="1">
                        <input type="hidden" name="id_moto" value="<?php echo $moto["id"]; ?>">
                        <label>Cant: </label>
                        <input type="number" name="cantidad" value="1" min="1" max="<?php echo $moto["stock"]; ?>" class="input-cant">
                        <button type="submit" class="btn-comprar" style="margin-top: 10px;">🛒 Agregar al Carrito</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No hay motocicletas disponibles en este momento.</p>
    <?php endif; ?>
</div>

</body>
</html>

