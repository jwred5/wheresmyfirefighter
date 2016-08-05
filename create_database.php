<?php
/**
 * Simple example of extending the SQLite3 class and changing the __construct
 * parameters, then using the open method to initialize the DB.
 */
class MyDB extends SQLite3
{
    function __construct()
    {
        $this->open('mysqlitedb.db');
    }
}

$db = new MyDB();
$db -> exec('CREATE TABLE wmf (id int(10), access_token varchar(40), refresh_token varchar(40), access_token_expiration int(10))');
?>