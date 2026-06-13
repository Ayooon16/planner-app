<?php
$db_host = getenv('DB_HOST') ?: 'projektbazasql.mysql.database.azure.com';
$db_user = getenv('DB_USER') ?: 'azureuser';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'tasks';

function getMysqliConnection() {
    global $db_host, $db_user, $db_pass, $db_name;

    $conn = mysqli_init();
    
    $ssl_ca = getenv('SSL_CA') ?: '/home/site/wwwroot/DigiCertGlobalRootCA.crt.pem';
    
    if (file_exists($ssl_ca)) {
        mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL);
    } else {
        mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    }
    if (!mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, 3306, NULL, MYSQLI_CLIENT_SSL)) {
        error_log("Database connection failed: " . mysqli_connect_error());
        die("Błąd połączenia z bazą danych. Spróbuj później.");
    }

    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}
?>