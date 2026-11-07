<?php

$lockFile = '/tmp/imapsync_sync.lock';
$lockMaxAge = 86400;  // 24h — expire un lock orphelin

if (file_exists($lockFile)) {
    $lockData = json_decode(@file_get_contents($lockFile), true);
    if (is_array($lockData)
        && isset($lockData['pid'], $lockData['time'])
        && posix_kill((int)$lockData['pid'], 0)
        && (time() - $lockData['time']) < $lockMaxAge) {
        echo "Sync already running (PID {$lockData['pid']}). Exiting.\n";
        exit(0);
    }
    @unlink($lockFile);  // lock orphelin
}
file_put_contents($lockFile, json_encode([
    'pid'  => getmypid(),
    'time' => time(),
]));
register_shutdown_function(function() use ($lockFile) { @unlink($lockFile); });

require '/etc/imapsync/secret.php';

$host = "localhost"; $dbname = "mails_bd"; $user = "mails_bd";
$pass = "A147z258e369=";  // à déplacer dans secret.php à terme

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion DB: " . $e->getMessage());
}

$sql = "SELECT id, emails, password FROM emails WHERE etat=1";
$stmt = $pdo->query($sql);

$timeoutPerAccount = 3600;  // 1h max par compte — adaptez à la taille des boîtes

foreach ($stmt as $row) {
    $email = $row['emails'];
    $password = openssl_decrypt($row['password'], "AES-256-CBC", ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    if ($password === false) {
        echo "Compte: $email | Erreur déchiffrement, skip\n";
        continue;
    }

    // Mot de passe via fichier temporaire protégé (invisible dans ps)
    $tmpPass = tempnam(sys_get_temp_dir(), 'imapsync_');
    chmod($tmpPass, 0600);
    file_put_contents($tmpPass, $password);

    try {
        $cmd = "timeout --signal=TERM --kill-after=60 {$timeoutPerAccount} " .
               "imapsync --automap " .
               "--host1 mail.gema-group.com --user1 " . escapeshellarg($email) .
               " --passfile1 " . escapeshellarg($tmpPass) . " " .
               "--host2 mail2.gema-group.com --user2 " . escapeshellarg($email) .
               " --passfile2 " . escapeshellarg($tmpPass) . " " .
               "--timeout1 120 --timeout2 120 " .
               "--useuid --nofoldersizes --minsize 1 " .
               "--exclude 'Spam' --exclude 'Trash' --exclude 'INBOX.Trash' 2>&1";
        exec($cmd, $output, $return_var);
    } finally {
        @unlink($tmpPass);
    }

    $msg = substr(implode("\n", $output), -60000);

    $insert = $pdo->prepare("INSERT INTO sync_logs (email, error_code, error_message) VALUES (?, ?, ?)");
    $insert->execute([$email, $return_var, $msg]);

    echo "Compte: $email | Code: $return_var\n";
}