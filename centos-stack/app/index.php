<?php
// Obtener configuración desde variables de entorno
$host = getenv('DB_HOST') ?: 'mariadb';
$user = getenv('DB_USER') ?: 'appuser';  
$pass = getenv('DB_PASSWORD') ?: 'apppassword';
$db   = getenv('DB_NAME') ?: 'appdb';

// Intentar conexión a la base de datos
$mysqli = @new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_errno) {
    $db_status = "Error de conexión";
    $db_error = $mysqli->connect_error;
} else {
    $db_status = "Conectado";
    $version_result = $mysqli->query("SELECT VERSION() as version");
    $version = $version_result->fetch_assoc()['version'];
    $mysqli->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stack LEMP - CentOS Stream 9</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px; 
            margin: 60px auto; 
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 80vh;
        }
        .container {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        h1 {
            margin: 0 0 30px 0;
            font-size: 2.2em;
        }
        .status-grid {
            display: grid;
            gap: 20px;
            margin: 30px 0;
        }
        .status-item {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #4ade80;
        }
        .status-item h3 {
            margin: 0 0 10px 0;
            color: #4ade80;
            font-size: 1.1em;
        }
        .version-info {
            font-size: 0.9em;
            opacity: 0.8;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Stack LEMP</h1>
        <p>CentOS Stream 9 + Nginx + PHP + MariaDB</p>
        
        <div class="status-grid">
            <div class="status-item">
                <h3>🌐 Servidor Web</h3>
                <p>✅ Nginx funcionando</p>
            </div>
            
            <div class="status-item">
                <h3>🐘 PHP-FPM</h3>
                <p>✅ PHP <?= phpversion() ?></p>
            </div>
            
            <div class="status-item">
                <h3>🗃️ Base de Datos</h3>
                <p><?= $db_status ?></p>
                <?php if (isset($version)): ?>
                    <small>MariaDB <?= htmlspecialchars($version) ?></small>
                <?php endif; ?>
                <?php if (isset($db_error)): ?>
                    <small><?= htmlspecialchars($db_error) ?></small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="version-info">
            Sistema: <?= php_uname('s r') ?>
        </div>
    </div>
</body>
</html>
