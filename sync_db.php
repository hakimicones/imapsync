<?php

// Lock file to prevent concurrent executions
$lockFile = '/tmp/imapsync_sync.lock';
if (file_exists($lockFile)) {
    $pid = trim(file_get_contents($lockFile));
    if (posix_kill((int)$pid, 0)) {
        echo "Sync already running (PID $pid). Exiting.\n";
        exit(0);
    }
}
file_put_contents($lockFile, getmypid());
register_shutdown_function(function() use ($lockFile) {
    @unlink($lockFile);
});
 
require '/etc/imapsync/secret.php';

$host = "localhost";
$dbname = "mails_bd";
$user = "mails_bd";
$pass = "A147z258e369=";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion DB: " . $e->getMessage());
}

$sql = "SELECT id, emails, password FROM emails WHERE etat=1";
$stmt = $pdo->query($sql);

foreach ($stmt as $row) {
    $email = $row['emails'];

    // Déchiffrement AES-256-CBC
    $password = openssl_decrypt($row['password'], "AES-256-CBC", ENCRYPTION_KEY, 0, ENCRYPTION_IV);

    // $cmd = "imapsync --automap --host1 mail.gema-group.com --user1 " . escapeshellarg($email) . " --password1 " . escapeshellarg($password) . " " .
    //        "--host2 mail2.gema-group.com --user2 " . escapeshellarg($email) . " --password2 " . escapeshellarg($password) . " " .
    //        "--useuid --nofoldersizes --minsize 1   --exclude 'Spam' --exclude 'Trash' --exclude 'INBOX.Trash'";




    $cmd = "imapsync --automap \
            --host1 mail.gema-group.com --user1 " . escapeshellarg($email) . " --password1 " . escapeshellarg($password) . " \
            --host2 mail2.gema-group.com --user2 " . escapeshellarg($email) . " --password2 " . escapeshellarg($password) . " \
            --useheader 'Message-Id' --delete2duplicates --nofoldersizes --minsize 1 \
            --exclude 'Spam' --exclude 'Trash' --exclude 'INBOX.Trash'";
 

    exec($cmd, $output, $return_var);

    $msg = implode("\n", $output);
    // Truncate message to avoid SQL error (Data too long)
    $msg = substr($msg, -60000);

    $insert = $pdo->prepare("INSERT INTO sync_logs (email, error_code, error_message) VALUES (?, ?, ?)");
    $insert->execute([$email, $return_var, $msg]);

    echo "Compte: $email | Code: $return_var\n";
}
?>


