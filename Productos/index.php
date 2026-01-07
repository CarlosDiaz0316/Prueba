<?php
// 1. CONEXIÓN A LA BASE DE DATOS
$host = 'localhost'; $db = 'prueba_tecnica'; $user = 'root'; $pass = ''; 
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

// 2. LÓGICA DE ACCIONES (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
        $stmt = $pdo->prepare("UPDATE productos SET existencias = existencias + :c WHERE id_producto = :id");
        $stmt->execute(['c' => (int)$_POST['cantidad'], 'id' => $_POST['id_producto']]);
    }
    if (isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
        $sql = ($_POST['tipo_borrado'] == 'producto') ? "DELETE FROM productos WHERE id_producto = :v" : "DELETE FROM productos WHERE id_fabricante = :v";
        $pdo->prepare($sql)->execute(['v' => $_POST['valor_borrado']]);
    }
    header("Location: index.php"); exit;
}

// 3. LÓGICA DEL BUSCADOR
$busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Inventario - Prueba Técnica</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1100px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h1 { color: #1a73e8; border-bottom: 2px solid #e8eaed; padding-bottom: 15px; }
        h2 { color: #2c3e50; margin-top: 20px; }
        
        /* BUSCADOR*/
        .search-inner { display: flex; justify-content: flex-start; gap: 5px; margin-bottom: 15px; }
        .input-search { padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 250px; }
        
        /* BOTONES AGREGAR Y ELIMINAR  */
        .footer-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; }
        .btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.3s; color: white; }
        .btn-add { background: #28a745; }
        .btn-delete { background: #dc3545; }
        .btn-gray { background: #6c757d; }
        
        /* TABLAS */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        th, td { padding: 14px; border: 1px solid #edf2f7; text-align: left; }
        th { background: #1a73e8; color: white; text-transform: uppercase; font-size: 0.85rem; }
        tr:nth-child(even) { background: #f8faff; }
        .text-blue { color: #1a73e8; font-weight: bold; }

        /* PUNTO 4: DESTACADO */
        .card-destacada { background: #e6eceaff; border-left: 5px solid #1a73e8; padding: 20px; margin-top: 30px; border-radius: 4px; }

        /* MODALES */
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 25px; width: 380px; border-radius: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="container">
    <h1>Prueba Tecnica</h1>

    <div style="background: #ffffff; border: 1px solid #eee; padding: 20px; border-radius: 8px;">
        <h2>1. Lista General (Precios con I.V.A.)</h2>
        
        <form method="GET" class="search-inner">
            <input type="text" name="buscar" class="input-search" placeholder="Buscar producto..." value="<?= htmlspecialchars($busqueda) ?>">
            <button type="submit" class="btn btn-gray">🔍 Buscar</button>
            <a href="index.php" style="text-decoration:none; padding:10px; color:#666; font-size: 14px;">Limpiar</a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Fabricante</th><th>Código</th><th>Descripción</th><th>Precio Base</th><th>Precio + IVA</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->prepare("SELECT *, (precio * 1.10) as p_iva FROM productos WHERE descripcion LIKE ?");
                $stmt->execute(["%$busqueda%"]);
                $all_prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($all_prods as $row): ?>
                    <tr>
                        <td><?= $row['id_fabricante'] ?></td>
                        <td><?= $row['id_producto'] ?></td>
                        <td><?= $row['descripcion'] ?></td>
                        <td>$<?= number_format($row['precio'], 3) ?></td>
                        <td class="text-blue">$<?= number_format($row['p_iva'], 3) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer-actions">
            <button class="btn btn-add" onclick="openModal('modalAdd')">📦 Agregar Pedido</button>
            <button class="btn btn-delete" onclick="openModal('modalDel')">🗑️ Eliminar Registro</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
        <div>
            <h3>2. Existencias De Productos</h3>
            <table>
                <tr><th>Producto</th><th>Total</th></tr>
                <?php
                foreach($pdo->query("SELECT descripcion, SUM(existencias) as t FROM productos GROUP BY descripcion") as $r)
                    echo "<tr><td>{$r['descripcion']}</td><td>{$r['t']}</td></tr>";
                ?>
            </table>
        </div>
        <div>
            <h3>3. Promedio De Precio por Fabricante</h3>
            <table>
                <tr><th>Fabricante</th><th>Promedio</th></tr>
                <?php
                foreach($pdo->query("SELECT id_fabricante, AVG(precio) as p FROM productos GROUP BY id_fabricante") as $r)
                    echo "<tr><td>{$r['id_fabricante']}</td><td>\$" . number_format($r['p'], 3) . "</td></tr>";
                ?>
            </table>
        </div>
    </div>

    <?php
    $caro = $pdo->query("SELECT * FROM productos ORDER BY precio DESC LIMIT 1")->fetch();
    ?>
    <div class="card-destacada">
        <h3>4. Producto más costoso</h3>
        <p>El producto con el precio base más alto es: <strong><?= $caro['descripcion'] ?></strong> de <strong><?= $caro['id_fabricante'] ?></strong> con un valor de <strong>$<?= number_format($caro['precio'], 3) ?></strong>.</p>
    </div>
</div>

<div id="modalAdd" class="modal"><div class="modal-content">
    <h3>Actualizar Inventario</h3>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar">
        <div class="form-group"><label>Producto:</label>
        <select name="id_producto" required><?php foreach($all_prods as $p) echo "<option value='{$p['id_producto']}'>{$p['descripcion']}</option>"; ?></select></div>
        <div class="form-group"><label>Cantidad:</label><input type="number" name="cantidad" min="1" required></div>
        <button type="submit" class="btn btn-add">Confirmar</button>
        <button type="button" class="btn btn-gray" onclick="closeModal('modalAdd')">Cerrar</button>
    </form>
</div></div>

<div id="modalDel" class="modal"><div class="modal-content">
    <h3>Eliminar Registro</h3>
    <form method="POST">
        <input type="hidden" name="accion" value="eliminar">
        <div class="form-group"><label>Tipo:</label>
        <select id="tipo_b" name="tipo_borrado" onchange="updateDel()" required><option value="producto">Producto</option><option value="fabricante">Fabricante</option></select></div>
        <div class="form-group"><label>Seleccionar:</label>
        <select name="valor_borrado" id="val_b">
            <?php foreach($all_prods as $p) echo "<option value='{$p['id_producto']}' class='opt-p'>{$p['descripcion']}</option>"; ?>
            <?php $f_list = $pdo->query("SELECT DISTINCT id_fabricante FROM productos")->fetchAll();
            foreach($f_list as $f) echo "<option value='{$f['id_fabricante']}' class='opt-f' style='display:none'>{$f['id_fabricante']}</option>"; ?>
        </select></div>
        <button type="submit" class="btn btn-delete" onclick="return confirm('¿Confirmar?')">Eliminar</button>
        <button type="button" class="btn btn-gray" onclick="closeModal('modalDel')">Cerrar</button>
    </form>
</div></div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function updateDel() {
    const t = document.getElementById('tipo_b').value;
    document.querySelectorAll('.opt-p').forEach(o => o.style.display = (t === 'producto' ? 'block' : 'none'));
    document.querySelectorAll('.opt-f').forEach(o => o.style.display = (t === 'fabricante' ? 'block' : 'none'));
    document.getElementById('val_b').value = "";
}
</script>
</body>
</html>