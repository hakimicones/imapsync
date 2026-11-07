<?php
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
    $password = openssl_decrypt($row['password'], "AES-256-CBC", ENCRYPTION_KEY, 0, ENCRYPTION_IV);

    // Commande imapdedup
    $cmd = "imapdedup --host localhost --port 993 --ssl1 " .
           "--user " . escapeshellarg($email) . " " .
           "--password " . escapeshellarg($password) . " " .
           "--folder ALL --delete";

    exec($cmd, $output, $return_var);

    $msg = implode("\n", $output);
    $msg = substr($msg, -60000);

    $insert = $pdo->prepare("INSERT INTO dedup_logs (email, error_code, error_message) VALUES (?, ?, ?)");
    $insert->execute([$email, $return_var, $msg]);

    echo "Compte: $email | Code: $return_var\n";
}
?>
