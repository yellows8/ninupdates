<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

if($argc<5)
{
	die("Usage:\nphp parsesoap.php <soaprelydata path> <system(internal name)> <reportdate> <region>\n");
}

dbconnection_start();

$soapdata = file_get_contents($argv[1]);
$system = mysql_real_escape_string($argv[2]);
$reportdate = mysql_real_escape_string($argv[3]);
$region = mysql_real_escape_string($argv[4]);

if($soapdata===FALSE)
{
	dbconnection_end();
	echo "Failed to read the soapdata.\n";
	exit;
}

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysql_query($query);

$numrows=mysql_num_rows($result);
if($numrows==0)
{
	dbconnection_end();
	echo "The specified system is invalid.\n";
	exit;
}

$row = mysql_fetch_row($result);
$systemid = $row[0];

$query="SELECT id, curdate FROM ninupdates_reports WHERE systemid='".$systemid."' && reportdate='".$reportdate."'";
$result=mysql_query($query);

$numrows=mysql_num_rows($result);
if($numrows==0)
{
	dbconnection_end();
	echo "Failed to find the specified report.\n";
	exit;
}

$row = mysql_fetch_row($result);
$reportid = $row[0];
$dbcurdate = $row[1];

$sysupdate_systitlehashes = array();

init_titlelistarray();
parse_soapresp($soapdata);

$tmpval = titlelist_dbupdate();
echo "Total titles added by titlelist_dbupdate(): $tmpval\n";

if($tmpval)
{
	$query="UPDATE ninupdates_titles SET reportid=$reportid WHERE curdate='".$dbcurdate."' && reportid=0 && systemid=$systemid && region='".$region."'";
	$result=mysql_query($query);
}

dbconnection_end();

?>
