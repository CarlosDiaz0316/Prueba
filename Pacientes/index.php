<?php
session_start();
$host = 'localhost'; $db = 'gestion_pacientes'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

// AJAX MUNICIPIOS
if (isset($_GET['get_municipios'])) {
    $stmt = $pdo->prepare("SELECT id, nombre FROM municipios WHERE departamento_id = ?");
    $stmt->execute([$_GET['get_municipios']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// LOGIN
if (isset($_POST['btn_login'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE documento = ? AND clave = ?");
    $stmt->execute([$_POST['doc'], $_POST['pass']]);
    if ($u = $stmt->fetch()) { $_SESSION['user'] = $u['documento']; } 
    else { $login_err = "Credenciales incorrectas"; }
}
if (isset($_GET['salir'])) { session_destroy(); header("Location: index.php"); exit; }

// ELIMINAR
if (isset($_GET['eliminar'])) {
    $stmt = $pdo->prepare("SELECT foto FROM pacientes WHERE id = ?");
    $stmt->execute([$_GET['eliminar']]);
    if ($p = $stmt->fetch()) {
        if (!empty($p['foto']) && file_exists($p['foto'])) unlink($p['foto']);
    }
    $pdo->prepare("DELETE FROM pacientes WHERE id = ?")->execute([$_GET['eliminar']]);
    $_SESSION['msg'] = ['t' => 'success', 'm' => 'Registro eliminado.'];
    header("Location: index.php"); exit;
}

// GUARDAR
if (isset($_POST['btn_guardar'])) {
    $doc = $_POST['numero_documento'];
    $check = $pdo->prepare("SELECT id FROM pacientes WHERE numero_documento = ?");
    $check->execute([$doc]);
    if ($check->fetch()) {
        $_SESSION['msg'] = ['t' => 'danger', 'm' => "El documento $doc ya existe."];
    } else {
        $foto = "";
        if ($_FILES['foto']['name']) {
            if (!is_dir('uploads')) mkdir('uploads');
            $foto = "uploads/" . time() . "_" . $_FILES['foto']['name'];
            move_uploaded_file($_FILES['foto']['tmp_name'], $foto);
        }
        $sql = "INSERT INTO pacientes (tipo_documento_id, numero_documento, nombre1, nombre2, apellido1, apellido2, genero_id, departamento_id, municipio_id, foto) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([
            $_POST['tipo_documento_id'], $doc, $_POST['nombre1'], $_POST['nombre2'], 
            $_POST['apellido1'], $_POST['apellido2'], $_POST['genero_id'], 
            $_POST['departamento_id'], $_POST['municipio_id'], $foto
        ]);
        $_SESSION['msg'] = ['t' => 'success', 'm' => "Paciente registrado correctamente."];
    }
    header("Location: index.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba Tecnica</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0f172a; --accent: #3b82f6; --bg: #f1f5f9; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #1e293b; margin: 0; }
        
        /* LOGIN ANIMADO */
        .auth-screen { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .glass-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); width: 340px; text-align: center; }

        /* HEADER */
        .header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        
        .main { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        
        /* CARD DESIGN */
        .card { background: white; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .card-h { padding: 20px 25px; border-bottom: 1px solid var(--border); font-size: 16px; font-weight: 700; color: var(--primary); display: flex; align-items: center; justify-content: space-between; }

        /* FORMULARIO ORGANIZADO */
        .form-layout { display: flex; gap: 30px; padding: 25px; }
        .photo-sidebar { flex: 0 0 180px; text-align: center; }
        .form-content { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .group { display: flex; flex-direction: column; }
        .group label { font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        input, select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: #fcfcfc; transition: all 0.2s; }
        input:focus { border-color: var(--accent); outline: none; background: white; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

        /* PREVIEW AJUSTADA */
        .preview-container {
            width: 150px; height: 150px; border-radius: 16px; 
            border: 2px dashed #cbd5e1; background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; margin: 0 auto 15px; position: relative;
        }
        #img_p { width: 100%; height: 100%; object-fit: cover; display: none; }
        .placeholder-icon { color: #94a3b8; font-size: 11px; font-weight: 500; }

        .file-custom { 
            font-size: 11px; width: 100%; 
            color: #64748b; cursor: pointer;
        }

        /* BOTONES */
        .btn-main { background: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-main:hover { background: #334155; transform: translateY(-1px); }

        /* TABLA */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 11px; color: #64748b; text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid var(--border); }
        td { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .badge-id { background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-family: monospace; }
        .btn-del { color: #94a3b8; text-decoration: none; font-size: 12px; font-weight: 600; transition: 0.2s; }
        .btn-del:hover { color: #ef4444; }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; border: 1px solid; }
        .success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .danger { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    </style>
</head>
<body class="<?= !isset($_SESSION['user']) ? 'auth-screen' : '' ?>">

<?php if (!isset($_SESSION['user'])): ?>
    <div class="glass-card">
        <h2 style="margin:0 0 10px;"> LOGIN </h2>
        <form method="POST">
            <input type="text" name="doc" placeholder="Usuario" required style="width:100%; box-sizing:border-box; margin-bottom:12px;">
            <input type="password" name="pass" placeholder="Contraseña" required style="width:100%; box-sizing:border-box; margin-bottom:20px;">
            <button type="submit" name="btn_login" class="btn-main" style="width:100%">Iniciar Sesión</button>
        </form>
    </div>
<?php else: ?>
    <div class="header">
        <div style="font-weight: 800; letter-spacing: -0.5px; font-size: 20px;">SGP<span style="color:var(--accent)">+</span></div>
        <div style="font-size: 13px;">
            <span style="color:#64748b">usuario:</span> <b><?= $_SESSION['user'] ?></b> 
            <a href="?salir=1" style="margin-left:20px; color:#ef4444; text-decoration:none; font-weight:600;">Cerrar</a>
        </div>
    </div>

    <div class="main">
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert <?= $_SESSION['msg']['t'] ?>"> <?= $_SESSION['msg']['m']; unset($_SESSION['msg']); ?> </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-h">Nuevo Registro de Paciente</div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-layout">
                    <div class="photo-sidebar">
                        <div class="preview-container">
                            <img id="img_p" src="">
                            <div id="ph" class="placeholder-icon">VISTA PREVIA</div>
                        </div>
                        <input type="file" name="foto" id="f_input" accept="image/*" onchange="preview(this)" style="display:none;">
                        <button type="button" onclick="document.getElementById('f_input').click()" class="btn-main" style="background:#f1f5f9; color:#475569; padding:8px 15px; font-size:11px;">Elegir Foto</button>
                    </div>

                    <div class="form-content">
                        <div class="group"><label>Tipo Documento</label><select name="tipo_documento_id"><?php foreach($pdo->query("SELECT * FROM tipos_documento") as $r) echo "<option value='{$r['id']}'>{$r['nombre']}</option>"; ?></select></div>
                        <div class="group"><label>N° Documento</label><input type="text" name="numero_documento" required></div>
                        <div class="group"><label>Primer Nombre</label><input type="text" name="nombre1" required></div>
                        <div class="group"><label>Segundo Nombre</label><input type="text" name="nombre2"></div>
                        <div class="group"><label>Primer Apellido</label><input type="text" name="apellido1" required></div>
                        <div class="group"><label>Segundo Apellido</label><input type="text" name="apellido2"></div>
                        <div class="group"><label>Género</label><select name="genero_id"><?php foreach($pdo->query("SELECT * FROM generos") as $r) echo "<option value='{$r['id']}'>{$r['nombre']}</option>"; ?></select></div>
                        <div class="group"><label>Departamento</label>
                            <select name="departamento_id" onchange="loadMun(this.value)" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($pdo->query("SELECT * FROM departamentos") as $r) echo "<option value='{$r['id']}'>{$r['nombre']}</option>"; ?>
                            </select>
                        </div>
                        <div class="group"><label>Municipio</label><select name="municipio_id" id="mun" required><option value="">Elegir depto...</option></select></div>
                        <div style="grid-column: span 2; text-align: right; margin-top: 10px;">
                            <button type="submit" name="btn_guardar" class="btn-main">Registrar Informacion</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-h">Lista de Pacientes</div>
            <divspan style="font-weight:400; font-size:12px; color:#64748b;"></span>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Identificación</th>
                            <th>Ubicación</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, m.nombre as muni, d.nombre as dep FROM pacientes p JOIN municipios m ON p.municipio_id = m.id JOIN departamentos d ON p.departamento_id = d.id ORDER BY p.id DESC";
                        foreach($pdo->query($sql) as $p): ?>
                        <tr>
                            <td style="display:flex; align-items:center; gap:12px;">
                                <div style="width:32px; height:32px; border-radius:50%; overflow:hidden; background:#eee; border:1px solid #ddd;">
                                    <img src="<?= $p['foto'] ?: 'https://ui-avatars.com/api/?name='.$p['nombre1'] ?>" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                                <span style="font-weight:600;"><?= $p['nombre1']." ".$p['apellido1'] ?></span>
                            </td>
                            <td><span class="badge-id"><?= $p['numero_documento'] ?></span></td>
                            <td style="color:#64748b; font-size:13px;"><?= $p['muni'] ?> (<?= $p['dep'] ?>)</td>
                            <td style="text-align:right">
                                <a href="?eliminar=<?= $p['id'] ?>" class="btn-del" onclick="return confirm('¿Eliminar registro?')">Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function loadMun(id) {
        fetch('?get_municipios=' + id).then(r => r.json()).then(data => {
            const m = document.getElementById('mun');
            m.innerHTML = '<option value="">Seleccione...</option>';
            data.forEach(x => { m.innerHTML += `<option value="${x.id}">${x.nombre}</option>`; });
        });
    }
    function preview(i) {
        if (i.files && i.files[0]) {
            const r = new FileReader();
            r.onload = e => { 
                const img = document.getElementById('img_p'); 
                const ph = document.getElementById('ph');
                img.src = e.target.result; 
                img.style.display='block'; 
                ph.style.display='none';
            }
            r.readAsDataURL(i.files[0]);
        }
    }
    </script>
<?php endif; ?>
</body>
</html>