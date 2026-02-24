<?php
// TEMPORARY diagnostic file - DELETE after use!
$tests = [
    'tcp+charset'  => 'mysql:host=127.0.0.1;port=3306;dbname=eliskapp;charset=utf8mb4',
    'tcp+nochars'  => 'mysql:host=127.0.0.1;port=3306;dbname=eliskapp',
    'localhost'    => 'mysql:host=localhost;dbname=eliskapp;charset=utf8mb4',
];

$user = 'eliska';
$pass = 'Kolodej.pro100R.dole';

foreach ($tests as $label => $dsn) {
    echo "<b>$label</b>: ";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        echo '<span style="color:green">OK – connected (server: '
            . $pdo->query('SELECT VERSION()')->fetchColumn() . ')</span>';
        $pdo = null;
    } catch (PDOException $e) {
        echo '<span style="color:red">FAIL: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
    echo '<br>';
}
echo '<br>PHP: ' . PHP_VERSION . ' | mysqlnd: ' . phpversion('mysqlnd');
