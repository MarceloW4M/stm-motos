<?php
require __DIR__ . '/../includes/config.php';
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='must_change_password'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['c'] == 0) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        echo "col_added\n";
    } else {
        echo "exists\n";
    }
    $db->exec("UPDATE usuarios SET must_change_password=1 WHERE id=1");
    echo "flag_set\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
