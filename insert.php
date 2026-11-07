<?php

require 'secret.php';

$host = "localhost";
$dbname = "mails_bd";
$user = "mails_bd";
$pass = "A147z258e369=";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT emails, password FROM emails_list";
    $stmt = $pdo->query($sql);

    foreach ($stmt as $row) {
        $email = $row['emails'];
        $password = $row['password'];

        // Chiffrement AES-256-CBC
        $encrypted = openssl_encrypt($password, "AES-256-CBC", ENCRYPTION_KEY, 0, ENCRYPTION_IV);

        $insert = $pdo->prepare("INSERT INTO emails (emails, password, etat) VALUES (?, ?, 1)");
        $insert->execute([$email, $encrypted]);
    }

    echo "Import terminé avec chiffrement.\n";

} catch (PDOException $e) {
    die("Erreur DB: " . $e->getMessage());
}

?>
