<?php 
echo "arriba";

try {
    $dbh = new PDO('mysql:host=magento-db;dbname=magento17', 'root', 'root');
} catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
}

var_dump($dbh);
phpinfo();
