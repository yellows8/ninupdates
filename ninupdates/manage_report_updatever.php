<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

if($argc<3)
{
	die("Get/set the updatever, etc.\nUsage:\nphp manage_report_updatever.php <system(internal name)> <reportdate> [Options]\nOptions:\n--updatever=<ver>\n--updatever_autoset=<val>\n--wikibot_runfinished=<val>\n--wikipage_exists=<val>\n");
}

dbconnection_start();

$system = mysqli_real_escape_string($mysqldb, $argv[1]);
$reportdate = mysqli_real_escape_string($mysqldb, $argv[2]);

$updatever = "";
$updatever_autoset = "";
$wikibot_runfinished = "";
$wikipage_exists = "";

if($argc > 3)
{
	for($i=3; $i<$argc; $i++)
	{
		$argend = strpos($argv[$i], "=");
		if($argend!==FALSE)
		{
			$argval = mysqli_real_escape_string($mysqldb, substr($argv[$i], $argend+1));
			if(substr($argv[$i], 0, $argend) ===  "--updatever")
			{
				$updatever = $argval;
			}
			else if(substr($argv[$i], 0, $argend) ===  "--updatever_autoset")
			{
				$updatever_autoset = $argval;
			}
			else if(substr($argv[$i], 0, $argend) ===  "--wikibot_runfinished")
			{
				$wikibot_runfinished = $argval;
			}
			else if(substr($argv[$i], 0, $argend) ===  "--wikipage_exists")
			{
				$wikipage_exists = $argval;
			}
		}
	}
}

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

if($argc == 3)
{
	echo($report_updatever);
}
else
{
	$logmsg = "manage_report_updatever: CHANGED REPORT $reportdate-$system: ";
	$query = "UPDATE ninupdates_reports SET ";

	$cnt=0;
	if($updatever!=="")
	{
		$query.= "updateversion='".$updatever."'";
		$logmsg.= "updatever = \"$updatever\"";
		$cnt++;
	}

	$concatstr = "";
	if($updatever_autoset!=="")
	{
		if($cnt>0)
		{
			$concatstr = ", ";
		}
		$query.= $concatstr . "updatever_autoset=".$updatever_autoset."";
		$logmsg.= $concatstr . "updatever_autoset=".$updatever_autoset;
		$cnt++;
	}
	if($wikibot_runfinished!=="")
	{
		if($cnt>0)
		{
			$concatstr = ", ";
		}
		$query.= $concatstr . "wikibot_runfinished=".$wikibot_runfinished."";
		$logmsg.= $concatstr . "wikibot_runfinished=".$wikibot_runfinished;
		$cnt++;
	}
	if($wikipage_exists!=="")
	{
		if($cnt>0)
		{
			$concatstr = ", ";
		}
		$query.= $concatstr . "wikipage_exists=".$wikipage_exists."";
		$logmsg.= $concatstr . "wikipage_exists=".$wikipage_exists;
		$cnt++;
	}

	if($cnt==0) die("Unrecognized args.\n");

	$query.= " WHERE id=$reportid";
	$result=mysqli_query($mysqldb, $query);

	writeNormalLog($logmsg);
}

dbconnection_end();

?>
