<?php
// On gère le compteur avant d'afficher quoi que ce soit
$file = 'clicks.txt';
if (isset($_GET['click'])) {
    $c = file_exists($file) ? (int)file_get_contents($file) : 0;
    $c++;
    file_put_contents($file, (string)$c);
    echo $c; 
    exit;
}
if (isset($_GET['get'])) {
    echo file_exists($file) ? file_get_contents($file) : '0';
    exit;
}
?>
