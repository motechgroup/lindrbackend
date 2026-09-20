<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * LINDR SHARED HOSTING BROWSER SETUP SCRIPT
 * Comprehensive diagnostic & migration runner for shared hosting.
 */

// Force display of all errors for browser setup
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$secret = $_GET['secret'] ?? '';
if ($secret !== 'lindr2026') {
    http_response_code(403);
    exit('<h1>Access Denied</h1><p>Please append <code>?secret=lindr2026</code> to the URL to run setup.</p>');
}

// Force APP_DEBUG=true in memory for setup diagnostics
putenv('APP_DEBUG=true');
$_ENV['APP_DEBUG'] = 'true';
$_SERVER['APP_DEBUG'] = 'true';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lindr Shared Hosting Setup & Diagnostics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; line-height: 1.5; }
        .card { background: #1e293b; padding: 1.5rem; border-radius: 12px; border: 1px solid #334155; margin-bottom: 1.5rem; }
        h1 { color: #38bdf8; margin-top: 0; }
        h2 { color: #f43f5e; margin-top: 0; }
        h3 { color: #e2e8f0; margin-top: 0; }
        pre { background: #090d16; padding: 1rem; border-radius: 8px; border: 1px solid #1e293b; overflow-x: auto; color: #38bdf8; font-size: 13px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .ok { background: #059669; color: #fff; }
        .err { background: #dc2626; color: #fff; }
        .warn { background: #d97706; color: #fff; }
        .info { color: #94a3b8; }
        ul { padding-left: 1.2rem; }
        li { margin-bottom: 0.4rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Lindr Server Setup & Pre-Flight Diagnostics</h1>
        <p class="info">PHP Version: <strong><?= PHP_VERSION ?></strong> | Server API: <strong><?= PHP_SAPI ?></strong></p>
    </div>
<?php

$hasErrors = false;

// --- STEP 1: Auto-Fix Permissions & Create Missing Storage Folders ---
$dirsToFix = [
    __DIR__.'/../storage',
    __DIR__.'/../storage/app',
    __DIR__.'/../storage/app/public',
    __DIR__.'/../storage/framework',
    __DIR__.'/../storage/framework/cache',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/logs',
    __DIR__.'/../bootstrap/cache',
];

foreach ($dirsToFix as $d) {
    if (! file_exists($d)) {
        @mkdir($d, 0777, true);
    }
    @chmod($d, 0777);
}

// --- STEP 2: PHP Extension Checks ---
echo '<div class="card">';
echo '<h3>1. PHP Extension Verification</h3>';
$requiredExts = ['pdo', 'openssl', 'mbstring', 'curl', 'fileinfo', 'gd', 'zip', 'xml', 'ctype', 'json', 'tokenizer'];
echo '<ul>';

foreach ($requiredExts as $ext) {
    if (extension_loaded($ext)) {
        echo "<li><span class=\"badge ok\">OK</span> <code>$ext</code> extension is enabled.</li>";
    } else {
        echo "<li><span class=\"badge err\">ERROR</span> <code>$ext</code> extension is MISSING!</li>";
        $hasErrors = true;
    }
}

// Database Driver Extension Check
if (extension_loaded('pdo_mysql')) {
    echo '<li><span class="badge ok">OK</span> <code>pdo_mysql</code> extension is enabled.</li>';
} elseif (extension_loaded('pdo_pgsql')) {
    echo '<li><span class="badge ok">OK</span> <code>pdo_pgsql</code> extension is enabled.</li>';
} else {
    echo '<li><span class="badge err">ERROR</span> Neither <code>pdo_mysql</code> nor <code>pdo_pgsql</code> is enabled in PHP! Enable them in cPanel.</li>';
    $hasErrors = true;
}

echo '</ul>';
echo '</div>';

// --- STEP 3: Directory Write Permission Verification ---
echo '<div class="card">';
echo '<h3>2. Directory Write Permissions</h3>';
$dirsToTest = [
    'storage' => __DIR__.'/../storage',
    'storage/framework' => __DIR__.'/../storage/framework',
    'storage/logs' => __DIR__.'/../storage/logs',
    'bootstrap/cache' => __DIR__.'/../bootstrap/cache',
];
echo '<ul>';
foreach ($dirsToTest as $label => $path) {
    $writable = is_writable($path);
    if (! $writable) {
        $testFile = $path.'/.write_test_'.time();
        if (@file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            $writable = true;
        }
    }

    if ($writable) {
        echo "<li><span class=\"badge ok\">OK</span> <code>$label</code> is writable.</li>";
    } else {
        echo "<li><span class=\"badge err\">ERROR</span> <code>$label</code> is NOT writable! (In cPanel File Manager, right-click <code>$label</code> &gt; Permissions &gt; set to 775 or 777).</li>";
        $hasErrors = true;
    }
}
echo '</ul>';
echo '</div>';

// --- STEP 4: Environment File Check & Direct Database Connection Test ---
echo '<div class="card">';
echo '<h3>3. Environment & Database Connection Test</h3>';

$envPath = __DIR__.'/../.env';
if (! file_exists($envPath)) {
    echo '<p><span class="badge err">ERROR</span> <code>.env</code> file not found in root directory!</p>';
    $hasErrors = true;
} else {
    echo '<p><span class="badge ok">OK</span> <code>.env</code> file exists.</p>';

    // Parse .env manually
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $envVars = [];
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $envVars[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }

    $dbDriver = strtolower($envVars['DB_CONNECTION'] ?? 'mysql');
    $dbHost = $envVars['DB_HOST'] ?? '127.0.0.1';
    $dbPort = $envVars['DB_PORT'] ?? ($dbDriver === 'mysql' ? '3306' : '5432');
    $dbName = $envVars['DB_DATABASE'] ?? '';
    $dbUser = $envVars['DB_USERNAME'] ?? '';
    $dbPass = $envVars['DB_PASSWORD'] ?? '';
    $appKey = $envVars['APP_KEY'] ?? '';

    if (empty($appKey)) {
        echo '<p><span class="badge warn">WARNING</span> <code>APP_KEY</code> is empty in .env! Please set APP_KEY.</p>';
    } else {
        echo '<p><span class="badge ok">OK</span> <code>APP_KEY</code> is configured.</p>';
    }

    if ($dbDriver === 'sqlite') {
        echo '<p><span class="badge err">FAIL</span> <code>DB_CONNECTION=sqlite</code> is set in <code>.env</code>!</p>';
        echo '<p class="info">💡 <strong>Action Required:</strong> Edit your <code>.env</code> file in cPanel File Manager and set your MySQL credentials:<br>'
            .'<code>DB_CONNECTION=mysql</code><br>'
            .'<code>DB_HOST=127.0.0.1</code><br>'
            .'<code>DB_PORT=3306</code><br>'
            .'<code>DB_DATABASE=your_cpanel_mysql_db_name</code><br>'
            .'<code>DB_USERNAME=your_cpanel_mysql_user</code><br>'
            .'<code>DB_PASSWORD=your_cpanel_mysql_password</code></p>';
        $hasErrors = true;
    } else {
        echo "<p>Testing direct PDO connection to database: <code>$dbDriver://$dbUser@$dbHost:$dbPort/$dbName</code>...</p>";

        if ($dbDriver === 'pgsql' && ! extension_loaded('pdo_pgsql')) {
            echo '<p><span class="badge err">FAIL</span> <code>DB_CONNECTION=pgsql</code> is set in .env, but <code>pdo_pgsql</code> extension is NOT enabled in PHP.</p>';
            $hasErrors = true;
        } elseif ($dbDriver === 'mysql' && ! extension_loaded('pdo_mysql')) {
            echo '<p><span class="badge err">FAIL</span> <code>DB_CONNECTION=mysql</code> is set in .env, but <code>pdo_mysql</code> extension is NOT enabled in PHP.</p>';
            $hasErrors = true;
        } else {
            try {
                $dsn = $dbDriver === 'mysql'
                    ? "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4"
                    : "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";

                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                echo '<p><span class="badge ok">SUCCESS</span> Direct PDO database connection successful!</p>';
            } catch (Throwable $pdoEx) {
                echo '<p><span class="badge err">FAIL</span> Database Connection Error: <code>'.htmlspecialchars($pdoEx->getMessage()).'</code></p>';
                echo '<p class="info">Please check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD in your <code>.env</code> file.</p>';
                $hasErrors = true;
            }
        }
    }
}
echo '</div>';

if ($hasErrors && ! isset($_GET['force'])) {
    echo '<div class="card" style="border-color:#dc2626;">';
    echo '<h2>⚠️ Pre-Flight Verification Failed</h2>';
    echo '<p>Please resolve the red errors above, or click below to force attempt setup anyway:</p>';
    echo '<p><a href="?secret=lindr2026&force=1" style="background:#dc2626;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:bold;">Force Execution Anyway</a></p>';
    echo '</div></body></html>';
    exit;
}

// --- STEP 5: Boot Laravel & Run Migrations / Cache Commands ---
echo '<div class="card">';
echo '<h3>4. Executing Laravel Migrations & Optimizations</h3>';
echo '<pre>';

try {
    define('LARAVEL_START', microtime(true));
    require __DIR__.'/../vendor/autoload.php';

    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';

    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    echo "=== 4.1 Running Database Migrations ===\n";
    Schema::disableForeignKeyConstraints();

    try {
        if (isset($_GET['fresh'])) {
            echo "Wiping existing tables clean...\n";
            Artisan::call('db:wipe', ['--force' => true]);
            echo Artisan::output();
        }

        Artisan::call('migrate', ['--force' => true]);
        echo Artisan::output()."\n";
    } catch (Throwable $migErr) {
        echo '⚠️ Notice during migration: '.$migErr->getMessage()."\n";
        echo "Attempting clean database wipe and re-migration...\n";
        Artisan::call('db:wipe', ['--force' => true]);
        echo Artisan::output();

        Artisan::call('migrate', ['--force' => true]);
        echo Artisan::output()."\n";
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    echo "=== 4.2 Seeding Initial Database Records ===\n";
    try {
        Artisan::call('db:seed', ['--force' => true]);
        echo Artisan::output()."\n";
    } catch (Throwable $seedErr) {
        echo 'Seeding Notice: '.$seedErr->getMessage()."\n";
    }

    echo "=== 4.3 Ensuring Administrator User Account ===\n";
    try {
        $admin = User::updateOrCreate(
            ['email' => 'admin@lindr.app'],
            [
                'name' => 'Lindr Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        Wallet::firstOrCreate(['user_id' => $admin->id], ['coin_balance' => 0]);
        echo "✅ Admin Account Ready: admin@lindr.app / password\n\n";
    } catch (Throwable $adminErr) {
        echo 'Admin Setup Notice: '.$adminErr->getMessage()."\n";
    }

    echo "=== 4.4 Building Configuration Caches ===\n";
    Artisan::call('config:cache');
    echo Artisan::output();

    Artisan::call('route:cache');
    echo Artisan::output();

    Artisan::call('view:cache');
    echo Artisan::output()."\n";

    echo "=== 4.5 Creating Storage Symlink ===\n";
    try {
        Artisan::call('storage:link');
        echo Artisan::output()."\n";
    } catch (Throwable $stErr) {
        echo 'Storage Link Notice: '.$stErr->getMessage()."\n";
    }

    echo "=== 🎉 Setup Completed Successfully! ===\n";
    echo "API Health Endpoint: /api/v1/health\n";
    echo "Admin Access Portal: /access\n";
    echo "Filament Admin Panel: /admin\n";
    echo "Default Admin Login: admin@lindr.app / password\n";
} catch (Throwable $e) {
    echo "❌ EXCEPTION THROWN DURING SETUP:\n";
    echo 'Message: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n\n";
    echo "Stack Trace:\n".$e->getTraceAsString()."\n";
}

echo '</pre>';
echo '</div></body></html>';
