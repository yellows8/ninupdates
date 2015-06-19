<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

if($argc<7)
{
	die("Usage:\nphp manage_consoles.php <system(internal name)> <sysname(display name)> <clientcertfn> <clientprivfn> <nushttpsurl> <platformid>\n");
}

dbconnection_start();

$system = mysqli_real_escape_string($mysqldb, $argv[1]);
$sysname = mysqli_real_escape_string($mysqldb, $argv[2]);
$clientcertfn = mysqli_real_escape_string($mysqldb, $argv[3]);
$clientprivfn = mysqli_real_escape_string($mysqldb, $argv[4]);
$nushttpsurl = mysqli_real_escape_string($mysqldb, $argv[5]);
$platformid = mysqli_real_escape_string($mysqldb, $argv[6]);

$path = "$sitecfg_workdir/soap$system";

mkdir($path, 0760);

$regions = "EPJCKAT";

for($i=0; $i<strlen($regions); $i++)
{
	mkdir("$path/" . substr($regions, $i, 1), 0760);
}

$query = "INSERT INTO ninupdates_consoles (system, sysname, clientcertfn, clientprivfn, nushttpsurl, platformid) VALUES ('".$system."','".$sysname."','".$clientcertfn."','".$clientprivfn."','".$nushttpsurl."','".$platformid."')";
$result=mysqli_query($mysqldb, $query);

dbconnection_end();

?>
