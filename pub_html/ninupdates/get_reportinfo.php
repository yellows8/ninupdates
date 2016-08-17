<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

dbconnection_start();

db_checkmaintenance(1);

$system = "";
$updateversion = "";
$reportinfo = "";
$output_format = "";
if(isset($_REQUEST['sys']))$system = mysqli_real_escape_string($mysqldb, $_REQUEST['sys']);
if(isset($_REQUEST['updateversion']))$updateversion = mysqli_real_escape_string($mysqldb, $_REQUEST['updateversion']);
if(isset($_REQUEST['info']))$reportinfo = mysqli_real_escape_string($mysqldb, $_REQUEST['info']);
if(isset($_REQUEST['format']))$output_format = mysqli_real_escape_string($mysqldb, $_REQUEST['format']);

if($system=="")
{
	dbconnection_end();
	echo "System not specified.\n";
	return;
}

if($updateversion=="")
{
	dbconnection_end();
	echo "Updateversion not specified.\n";
	return;
}

if($output_format!=="raw")
{
	dbconnection_end();
	echo "Invalid format.\n";
	return;
}

if($reportinfo!=="reportdate")
{
	dbconnection_end();
	echo "Invalid info param.\n";
	return;
}

$sys = getsystem_sysname($system);

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);
$systemid = $row[0];

$query="SELECT reportdate FROM ninupdates_reports WHERE ninupdates_reports.updateversion LIKE '".$updateversion."%' && ninupdates_reports.systemid=$systemid && ninupdates_reports.log='report'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	echo "Invalid updateversion.\n";
	return;
}

$con = "";

for($i=0; $i<$numrows; $i++)
{
	$row = mysqli_fetch_row($result);
	$con.= $row[0] . "\n";
}

dbconnection_end();

echo $con;

?>
