<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

if($argc<5)
{
	die("Usage:\nphp parsesoap.php <soaprelydata path> <system(internal name)> <reportdate> <region>\n");
}

dbconnection_start();

$soapdata = file_get_contents($argv[1]);
$system = mysqli_real_escape_string($mysqldb, $argv[2]);
$reportdate = mysqli_real_escape_string($mysqldb, $argv[3]);
$region = mysqli_real_escape_string($mysqldb, $argv[4]);

if($soapdata===FALSE)
{
	dbconnection_end();
	echo "Failed to read the soapdata.\n";
	exit;
}

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysqli_query($mysqldb, $query);

$numrows=mysqli_num_rows($result);
if($numrows==0)
{
	dbconnection_end();
	echo "The specified system is invalid.\n";
	exit;
}

$row = mysql_fetch_row($result);
$systemid = $row[0];

$query="SELECT id, curdate FROM ninupdates_reports WHERE systemid='".$systemid."' && reportdate='".$reportdate."'";
$result=mysqli_query($mysqldb, $query);

$numrows=mysqli_num_rows($result);
if($numrows==0)
{
	dbconnection_end();
	echo "Failed to find the specified report.\n";
	exit;
}

$row = mysqli_fetch_row($result);
$reportid = $row[0];
$dbcurdate = $row[1];

$sysupdate_systitlehashes = array();

init_titlelistarray();
parse_soapresp($soapdata, 0);

$tmpval = titlelist_dbupdate();
echo "Total titles added by titlelist_dbupdate(): $tmpval\n";

if($tmpval)
{
	$query="UPDATE ninupdates_titles SET reportid=$reportid WHERE curdate='".$dbcurdate."' && reportid=0 && systemid=$systemid && region='".$region."'";
	$result=mysqli_query($mysqldb, $query);
}

dbconnection_end();

?>
