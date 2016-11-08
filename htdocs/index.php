<?php 
try {
    $dbh = new PDO('mysql:host=magento-db;dbname=magento17', 'root', 'root');
    echo "DB connection ok";
} catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
}

phpinfo();
