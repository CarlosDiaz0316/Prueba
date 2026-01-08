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
if (isset($_GET['confirmar_eliminar'])) {
    $stmt = $pdo->prepare("SELECT foto FROM pacientes WHERE id = ?");
    $stmt->execute([$_GET['confirmar_eliminar']]);
    if ($p = $stmt->fetch()) {
        if (!empty($p['foto']) && file_exists($p['foto'])) unlink($p['foto']);
    }
    $pdo->prepare("DELETE FROM pacientes WHERE id = ?")->execute([$_GET['confirmar_eliminar']]);
    $_SESSION['msg'] = ['t' => 'success', 'm' => 'Registro eliminado con éxito.'];
    header("Location: index.php"); exit;
}

// --- LÓGICA DE EDICIÓN (CARGAR DATOS) ---
$edit_p = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $edit_p = $stmt->fetch();
}

// GUARDAR
if (isset($_POST['btn_guardar'])) {
    $doc = $_POST['numero_documento'];
    $id = $_POST['paciente_id'];
    
    if (empty($id)) {
        $check = $pdo->prepare("SELECT id FROM pacientes WHERE numero_documento = ?");
        $check->execute([$doc]);
        if ($check->fetch()) {
            $_SESSION['msg'] = ['t' => 'danger', 'm' => "El documento $doc ya existe."];
            header("Location: index.php"); exit;
        }
    }

    $foto = $_POST['foto_actual'];
    if ($_FILES['foto']['name']) {
        if (!empty($foto) && file_exists($foto)) unlink($foto);
        if (!is_dir('uploads')) mkdir('uploads');
        $foto = "uploads/" . time() . "_" . $_FILES['foto']['name'];
        move_uploaded_file($_FILES['foto']['tmp_name'], $foto);
    }

    if (!empty($id)) {
        $sql = "UPDATE pacientes SET tipo_documento_id=?, numero_documento=?, nombre1=?, nombre2=?, apellido1=?, apellido2=?, genero_id=?, departamento_id=?, municipio_id=?, foto=? WHERE id=?";
        $pdo->prepare($sql)->execute([
            $_POST['tipo_documento_id'], $doc, $_POST['nombre1'], $_POST['nombre2'], 
            $_POST['apellido1'], $_POST['apellido2'], $_POST['genero_id'], 
            $_POST['departamento_id'], $_POST['municipio_id'], $foto, $id
        ]);
        $_SESSION['msg'] = ['t' => 'success', 'm' => "Paciente actualizado correctamente."];
    } else {
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
    <title>Prueba tecnica</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0f172a; --accent: #3b82f6; --bg: #f1f5f9; --border: #e2e8f0; --danger: #ef4444; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #1e293b; margin: 0; }
        
        /* LOGIN */
        .auth-screen { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .glass-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); width: 340px; text-align: center; }

        .header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .main { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; }
        .card-h { padding: 20px 25px; border-bottom: 1px solid var(--border); font-size: 16px; font-weight: 700; color: var(--primary); display: flex; align-items: center; justify-content: space-between; }

        /* FORM */
        .form-layout { display: flex; gap: 30px; padding: 25px; }
        .photo-sidebar { flex: 0 0 180px; text-align: center; }
        .form-content { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .group { display: flex; flex-direction: column; }
        .group label { font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase; }
        input, select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; }

        .preview-container { width: 150px; height: 150px; border-radius: 16px; border: 2px dashed #cbd5e1; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 0 auto 15px; }
        #img_p { width: 100%; height: 100%; object-fit: cover; }

        .btn-main { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-main.edit-mode { background: #059669; }

        /* TABLA */
        .search-box { padding: 15px 25px; border-bottom: 1px solid var(--border); }
        .search-box input { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 11px; color: #64748b; text-transform: uppercase; background: #f8fafc; }
        td { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge-gen { padding: 3px 10px; border-radius: 12px; font-size: 11px; background: #e0f2fe; color: #0369a1; font-weight: 600; }

        /* MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 30px; border-radius: 16px; width: 350px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); }
        .modal h3 { margin: 0 0 10px; color: var(--primary); }
        .modal p { color: #64748b; font-size: 14px; margin-bottom: 25px; }
        .modal-btns { display: flex; gap: 10px; justify-content: center; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-confirm { background: var(--danger); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; border: 1px solid; }
        .success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .danger { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    </style>
</head>
<body class="<?= !isset($_SESSION['user']) ? 'auth-screen' : '' ?>">

<div class="modal-overlay" id="modalDel">
    <div class="modal">
        <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
        <h3>¿Estás seguro?</h3>
        <p>Esta acción no se puede deshacer. El registro del paciente será eliminado permanentemente.</p>
        <div class="modal-btns">
            <button class="btn-cancel" onclick="closeModal()">Cancelar</button>
            <a href="#" id="linkDelete" class="btn-confirm">Eliminar ahora</a>
        </div>
    </div>
</div>

<?php if (!isset($_SESSION['user'])): ?>
    <div class="glass-card">
        <h2>LOGIN</h2>
        <form method="POST">
            <input type="text" name="doc" placeholder="Usuario" required style="width:100%; margin-bottom:12px;">
            <input type="password" name="pass" placeholder="Clave" required style="width:100%; margin-bottom:20px;">
            <button type="submit" name="btn_login" class="btn-main" style="width:100%">Entrar</button>
        </form>
    </div>
<?php else: ?>
    <div class="header">
        <div style="font-weight: 800; font-size: 20px;">SGP<span style="color:var(--accent)">+</span></div>
        <div style="font-size: 13px;"><b><?= $_SESSION['user'] ?></b> | <a href="?salir=1" style="color:var(--danger); text-decoration:none;">Cerrar</a></div>
    </div>

    <div class="main">
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert <?= $_SESSION['msg']['t'] ?>"> <?= $_SESSION['msg']['m']; unset($_SESSION['msg']); ?> </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-h">
                <?= $edit_p ? "Editar Información" : "Registro de informacion" ?>
                <?php if($edit_p): ?><a href="index.php" style="font-size:11px; color:var(--accent); text-decoration:none;">[CANCELAR]</a><?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="paciente_id" value="<?= $edit_p['id'] ?? '' ?>">
                <input type="hidden" name="foto_actual" value="<?= $edit_p['foto'] ?? '' ?>">
                <div class="form-layout">
                    <div class="photo-sidebar">
                        <div class="preview-container">
                            <img id="img_p" src="<?= ($edit_p && $edit_p['foto']) ? $edit_p['foto'] : '' ?>" style="<?= ($edit_p && $edit_p['foto']) ? 'display:block' : 'display:none' ?>">
                            <div id="ph" style="<?= ($edit_p && $edit_p['foto']) ? 'display:none' : 'display:block' ?>; color:#94a3b8; font-size:10px;">FOTO</div>
                        </div>
                        <input type="file" name="foto" id="f_input" accept="image/*" onchange="preview(this)" style="display:none;">
                        <button type="button" onclick="document.getElementById('f_input').click()" style="font-size:11px; cursor:pointer; background:none; border:1px solid #ccc; padding:5px; border-radius:5px;">Elegir Imagen</button>
                    </div>
                    <div class="form-content">
                        <div class="group"><label>Tipo Documento</label><select name="tipo_documento_id"><?php foreach($pdo->query("SELECT * FROM tipos_documento") as $r) echo "<option value='{$r['id']}' ".($edit_p && $edit_p['tipo_documento_id']==$r['id']?'selected':'').">{$r['nombre']}</option>"; ?></select></div>
                        <div class="group"><label>Documento</label><input type="text" name="numero_documento" value="<?= $edit_p['numero_documento'] ?? '' ?>" required></div>
                        <div class="group"><label>Primer Nombre</label><input type="text" name="nombre1" value="<?= $edit_p['nombre1'] ?? '' ?>" required></div>
                        <div class="group"><label>Segundo Nombre</label><input type="text" name="nombre2" value="<?= $edit_p['nombre2'] ?? '' ?>"></div>
                        <div class="group"><label>Primer Apellido</label><input type="text" name="apellido1" value="<?= $edit_p['apellido1'] ?? '' ?>" required></div>
                        <div class="group"><label>Segundo Apellido</label><input type="text" name="apellido2" value="<?= $edit_p['apellido2'] ?? '' ?>"></div>
                        <div class="group"><label>Género</label><select name="genero_id"><?php foreach($pdo->query("SELECT * FROM generos") as $r) echo "<option value='{$r['id']}' ".($edit_p && $edit_p['genero_id']==$r['id']?'selected':'').">{$r['nombre']}</option>"; ?></select></div>
                        <div class="group"><label>Departamento</label><select name="departamento_id" onchange="loadMun(this.value)" required><option value="">Seleccione...</option><?php foreach($pdo->query("SELECT * FROM departamentos") as $r) echo "<option value='{$r['id']}' ".($edit_p && $edit_p['departamento_id']==$r['id']?'selected':'').">{$r['nombre']}</option>"; ?></select></div>
                        <div class="group"><label>Municipio</label><select name="municipio_id" id="mun" required><option>Elegir...</option></select></div>
                        <div style="grid-column: span 2; text-align: right;"><button type="submit" name="btn_guardar" class="btn-main <?= $edit_p ? 'edit-mode' : '' ?>"><?= $edit_p ? "Actualizar" : "registar paciente" ?></button></div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="search-box"><input type="text" id="q" onkeyup="search()" placeholder="Buscar paciente..."></div>
            <table id="tbl">
                <thead><tr><th>Paciente</th><th>Documento</th><th>Género</th><th>Ubicación</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT p.*, m.nombre as muni, d.nombre as dep, g.nombre as gen FROM pacientes p JOIN municipios m ON p.municipio_id=m.id JOIN departamentos d ON p.departamento_id=d.id JOIN generos g ON p.genero_id=g.id ORDER BY p.id DESC";
                    foreach($pdo->query($sql) as $p): ?>
                    <tr>
                        <td style="display:flex; align-items:center; gap:10px;"><img src="<?= $p['foto'] ?: 'https://ui-avatars.com/api/?name='.$p['nombre1'] ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover;"> <b><?= $p['nombre1']." ".$p['apellido1'] ?></b></td>
                        <td><span class="badge-id"><?= $p['numero_documento'] ?></span></td>
                        <td><span class="badge-gen"><?= $p['gen'] ?></span></td>
                        <td style="font-size:12px;"><?= $p['muni'] ?> (<?= $p['dep'] ?>)</td>
                        <td style="text-align:right">
                            <a href="?editar=<?= $p['id'] ?>" style="color:var(--accent); text-decoration:none; font-size:12px; margin-right:10px;">Editar</a>
                            <a href="javascript:void(0)" onclick="openModal(<?= $p['id'] ?>)" style="color:#94a3b8; text-decoration:none; font-size:12px;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById('modalDel').style.display = 'flex';
            document.getElementById('linkDelete').href = '?confirmar_eliminar=' + id;
        }
        function closeModal() { document.getElementById('modalDel').style.display = 'none'; }
        
        function search() {
            let q = document.getElementById("q").value.toLowerCase();
            let rows = document.getElementById("tbl").getElementsByTagName("tr");
            for (let i = 1; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(q) ? "" : "none";
            }
        }

        function loadMun(id, selId = null) {
            fetch('?get_municipios=' + id).then(r => r.json()).then(data => {
                let m = document.getElementById('mun');
                m.innerHTML = '<option value="">Seleccione...</option>';
                data.forEach(x => { m.innerHTML += `<option value="${x.id}" ${selId && x.id == selId ? 'selected' : ''}>${x.nombre}</option>`; });
            });
        }

        function preview(i) {
            if (i.files && i.files[0]) {
                let r = new FileReader();
                r.onload = e => { 
                    document.getElementById('img_p').src = e.target.result; 
                    document.getElementById('img_p').style.display='block';
                    document.getElementById('ph').style.display='none';
                };
                r.readAsDataURL(i.files[0]);
            }
        }
        <?php if($edit_p): ?> window.onload = () => loadMun(<?= $edit_p['departamento_id'] ?>, <?= $edit_p['municipio_id'] ?>); <?php endif; ?>
    </script>
<?php endif; ?>
</body>
</html>