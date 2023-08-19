<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");

dbconnection_start();

db_checkmaintenance(1);

$con = "<!doctype html>\n<html lang=\"en\">";
$con .= "<head><meta charset=\"UTF-8\" /><title>Nintendo System Update Last Scan</title></head><body>\n";

$query="SELECT lastscan FROM ninupdates_management";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

$lastscan = "N/A";
if($numrows>0)
{
	$row = mysqli_fetch_row($result);
	$lastscan = $row[0];
}

$con.= "Last scan datetime: " . $lastscan . "<br />\n";

$con.= "</body></html>";

dbconnection_end();

echo $con;

?>
