<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

if($argc<7)
{
	die("Usage:\nphp manage_consoles.php <system(internal name)> <sysname(display name)> <clientcertfn> <clientprivfn> <nushttpsurl> <platformid> {optional regions}\n");
}

dbconnection_start();

$system = mysqli_real_escape_string($mysqldb, $argv[1]);
$sysname = mysqli_real_escape_string($mysqldb, $argv[2]);
$clientcertfn = mysqli_real_escape_string($mysqldb, $argv[3]);
$clientprivfn = mysqli_real_escape_string($mysqldb, $argv[4]);
$nushttpsurl = mysqli_real_escape_string($mysqldb, $argv[5]);
$platformid = mysqli_real_escape_string($mysqldb, $argv[6]);

if($argc<8)
{
	$regions = "EPJCKAT";
}
else
{
	$regions = mysqli_real_escape_string($mysqldb, $argv[7]);
}

$path = "$sitecfg_workdir/soap$system";

mkdir($path, 0760);

for($i=0; $i<strlen($regions); $i++)
{
	mkdir("$path/" . substr($regions, $i, 1), 0760);
}

$query = "INSERT INTO ninupdates_consoles (system, sysname, clientcertfn, clientprivfn, nushttpsurl, platformid, regions) VALUES ('".$system."','".$sysname."','".$clientcertfn."','".$clientprivfn."','".$nushttpsurl."','".$platformid."','".$regions."')";
$result=mysqli_query($mysqldb, $query);

dbconnection_end();

?>
