<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "✓ Connection OK - Password kosong berhasil\n";
    
    // Cek database sistem_plp
    $result = $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='sistem_plp'");
    $row = $result->fetch();
    if ($row[0] > 0) {
        echo "✓ Database 'sistem_plp' ada\n";
    } else {
        echo "✗ Database 'sistem_plp' tidak ada\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
