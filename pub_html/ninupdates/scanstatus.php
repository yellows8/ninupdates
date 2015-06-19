<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");

dbconnection_start();

db_checkmaintenance(1);

$con = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\" dir=\"ltr\">";
$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update Last Scan</title></head><body>\n";

$query="SELECT lastscan, lastreqstatus FROM ninupdates_management";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);

$status = $row[1];
if($status=="")$status = "OK";

$con.= "Last scan datetime: " . $row[0] . "<br />\n";
$con.= "Last request status: " . $status;

$con.= "</body></html>";

dbconnection_end();

echo $con;

?>
