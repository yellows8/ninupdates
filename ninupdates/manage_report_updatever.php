<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

if($argc<3)
{
	die("Get/set the updatever, etc.\nUsage:\nphp manage_report_updatever.php <system(internal name)> <reportdate> [optional updatever when setting updatever] [optional value for updatever_autoset] [optional value for wikibot_runfinished]\n");
}

dbconnection_start();

$system = mysqli_real_escape_string($mysqldb, $argv[1]);
$reportdate = mysqli_real_escape_string($mysqldb, $argv[2]);

$updatever = "";
if($argc > 3) $updatever = mysqli_real_escape_string($mysqldb, $argv[3]);

$updatever_autoset = "";
if($argc > 4) $updatever_autoset = mysqli_real_escape_string($mysqldb, $argv[4]);

$wikibot_runfinished = "";
if($argc > 5) $wikibot_runfinished = mysqli_real_escape_string($mysqldb, $argv[5]);

$query="SELECT id FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to find the specified system.\n");
}

$row = mysqli_fetch_row($result);
$systemid = $row[0];

$query="SELECT ninupdates_reports.id, ninupdates_reports.updateversion FROM ninupdates_reports WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_reports.systemid=$systemid";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to find the specified report with the input system.\n");
}

$row = mysqli_fetch_row($result);
$reportid = $row[0];
$report_updatever = $row[1];

if($updatever==="")echo($report_updatever);

if($updatever!=="")
{
	$logmsg = "manage_report_updatever: CHANGED REPORT $reportdate-$system: updatever = \"$updatever\"";
	$query = "UPDATE ninupdates_reports SET updateversion='".$updatever."'";
	if($updatever_autoset!=="")
	{
		$query.= ", updatever_autoset=".$updatever_autoset."";
		$logmsg.= ", updatever_autoset=".$updatever_autoset;
	}
	if($wikibot_runfinished!=="")
	{
		$query.= ", wikibot_runfinished=".$wikibot_runfinished."";
		$logmsg.= ", wikibot_runfinished=".$wikibot_runfinished;
	}
	$query.= " WHERE id=$reportid";
	$result=mysqli_query($mysqldb, $query);

	writeNormalLog($logmsg);
}

dbconnection_end();

?>
