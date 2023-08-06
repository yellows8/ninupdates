<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");

dbconnection_start();

db_checkmaintenance(1);

$con = "<!doctype html>\n<html lang=\"en\">";
$con .= "<head><meta charset=\"UTF-8\" /><title>Nintendo System Update Last Scan</title></head><body>\n";

$query="SELECT lastscan FROM ninupdates_management";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);

$con.= "Last scan datetime: " . $row[0] . "<br />\n";

$con.= "</body></html>";

dbconnection_end();

echo $con;

?>
