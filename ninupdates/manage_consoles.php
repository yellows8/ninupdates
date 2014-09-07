<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

if($argc<7)
{
	die("Usage:\nphp manage_consoles.php <system(internal name)> <sysname(display name)> <clientcertfn> <clientprivfn> <nushttpsurl> <platformid>\n");
}

dbconnection_start();

$system = mysql_real_escape_string($argv[1]);
$sysname = mysql_real_escape_string($argv[2]);
$clientcertfn = mysql_real_escape_string($argv[3]);
$clientprivfn = mysql_real_escape_string($argv[4]);
$nushttpsurl = mysql_real_escape_string($argv[5]);
$platformid = mysql_real_escape_string($argv[6]);

$path = "$sitecfg_workdir/soap$system";

mkdir($path, 0760);

$regions = "EPJCKAT";

for($i=0; $i<strlen($regions); $i++)
{
	mkdir("$path/" . substr($regions, $i, 1), 0760);
}

$query = "INSERT INTO ninupdates_consoles (system, sysname, clientcertfn, clientprivfn, nushttpsurl, platformid) VALUES ('".$system."','".$sysname."','".$clientcertfn."','".$clientprivfn."','".$nushttpsurl."','".$platformid."')";
$result=mysql_query($query);

dbconnection_end();

?>
