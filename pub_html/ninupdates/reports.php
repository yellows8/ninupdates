<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/reportsphp";

dbconnection_start();

db_checkmaintenance(1);

$reportdate = "";
$system = "";
$setver = "";
$setsysver = "";
$order = "";
if(isset($_REQUEST['date']))$reportdate = mysql_real_escape_string($_REQUEST['date']);
if(isset($_REQUEST['sys']))$system = mysql_real_escape_string($_REQUEST['sys']);
if(isset($_REQUEST['setver']))$setver = mysql_real_escape_string($_REQUEST['setver']);
if(isset($_REQUEST['setsysver']))$setsysver = mysql_real_escape_string($_POST['setsysver']);
if(isset($_REQUEST['order']))$order = mysql_real_escape_string($_REQUEST['order']);

if(($reportdate!="" && $system=="") || ($system!="" && $reportdate==""))
{
	writeNormalLog("INVALID PARAMS. RESULT: 302");
	header("Location: reports.php");

	dbconnection_end();
	return;
}

if($system!="")$sys = getsystem_sysname($system);

$con = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\" dir=\"ltr\">\n";

if($reportdate!="" && $system!="")
{
	if($setver=="1" || $setsysver!="")
	{
		$query="SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
		$result=mysql_query($query);
		$numrows=mysql_num_rows($result);
		
		if($numrows==0)
		{
			dbconnection_end();

			header("Location: reports.php");
			writeNormalLog("ROW FOR SETVER NOT FOUND. RESULT: 302");

			return;
		}		

		$row = mysql_fetch_row($result);
		/*if($row[0]!="N/A")
		{
			dbconnection_end();

			header("Location: reports.php");
			writeNormalLog("UPDATEVERSION ALREADY SET TO ".$row[0].". RESULT: 302");

			return;
		}*/

		if($setver=="1")
		{
			$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update $sys $reportdate Set System Version</title></head><body>
<form method=\"post\" action=\"reports.php?date=$reportdate&amp;sys=$system\" enctype=\"multipart/form-data\">
  System version: <input type=\"text\" value=\"\" name=\"setsysver\"/><input type=\"submit\" value=\"Submit\"/></form></body></html>";

			dbconnection_end();
			if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");
			echo $con;

			return;
		}
		else if($setsysver!="")
		{
			$setsysver = strip_tags($setsysver);

			while(1)
			{
				$pos = strpos($setsysver, ",");
				if($pos === FALSE)break;
				$setsysver[$pos] = " ";

			}

			if($setsysver!="N/A")
			{
				$query = "SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updateversion='".$setsysver."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
				$result=mysql_query($query);
				$numrows=mysql_num_rows($result);
			
				if($numrows!=0)
				{
					dbconnection_end();

					header("Location: reports.php");
					writeNormalLog("THE SPECIFIED SYSVER ALREADY EXISTS FOR ANOTHER REPORT UNDER THE SPECIFIED SYSTEM. RESULT: 302");

					return;
				}
			}

			$query = "UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.updateversion='".$setsysver."' WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
			$result=mysql_query($query);
			dbconnection_end();

			header("Location: reports.php?date=".$reportdate."&sys=".$system);
			writeNormalLog("CHANGED SYSVER TO $setsysver. RESULT: 302");

			return;
		}
	}
}

$text = "reports";
$report_titletext = "";
if($reportdate!="")
{
	$query="SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);
	if($numrows==0)
	{
		$text = "$sys $reportdate report";
	}
	else
	{
		$row = mysql_fetch_row($result);
		if($row[0]=="N/A")
		{
			$text = "$sys $reportdate report";
		}
		else
		{
			$text = "$sys ".$row[0]." report";
		}
	}

	$report_titletext = $text;
}

$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update $text</title></head>\n<body>";

if($reportdate=="")
{
	$reportdate_columntext = "Report date";
	$system_columntext = "System";

	if($order=="")
	{
		$reportdate_columntext = "<a href=\"reports.php?order=1\">$reportdate_columntext</a>";
	}
	else
	{
		$system_columntext = "<a href=\"reports.php\">$system_columntext</a>";
	}

	$con.= "$sitecfg_homepage_header<table border=\"1\">
<tr>
  <th>$reportdate_columntext</th>
  <th>Update Version</th>
  <th>$system_columntext</th>
  <th>Request timestamp</th>
</tr>\n";
//  <th>UTC datetime</th>

	$orderquery = "";
	if($order=="")
	{
		$orderquery = "ninupdates_consoles.system, ninupdates_reports.curdate";
	}
	else
	{
		$orderquery = "ninupdates_reports.curdate";
	}

	$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system, ninupdates_reports.curdate, ninupdates_reports.reportdaterfc FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY $orderquery";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);
	
	for($i=0; $i<$numrows; $i++)
	{
		$row = mysql_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];
		$curdate = $row[3];
		$reportdaterfc = $row[4];

		$sys = getsystem_sysname($system);

		$url = "reports.php?date=$reportdate&amp;sys=$system";

		if($updateversion=="N/A")$updateversion = "<a href=\"$url&setver=1\">N/A</a>";

		$con.= "<tr>\n";

		$con.= "<td><a href=\"".$url."\">$reportdate</a></td>\n";
		$con.= "<td>".$updateversion."</td>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td>".$reportdaterfc."</td>\n";

		$con.= "</tr>\n";
	}
	$con.= "</table><br />\n";

	$con.= "<table border=\"1\">
<tr>
  <th>System</th>
  <th>Title List</th>
</tr>\n";

	$query="SELECT DISTINCT ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_consoles.system";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysql_fetch_row($result);
		$system = $row[0];

		$sys = getsystem_sysname($system);

		$url = "titlelist.php?sys=$system";

		$con.= "<tr>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td><a href=\"$url\">HTML</a> <a href=\"$url&amp;wiki=1\">Wiki</a> <a href=\"$url&amp;csv=1\">CSV</a></td>\n";
		$con.= "</tr>\n";
	}

	$con.= "</table><br />\n";

	$con.= "<iframe src=\"scanstatus.php\" width=512 height=64></iframe><br /><br />\n";

	$con.= "RSS feed is available <a href=\"feed.php\">here.</a><br />\n";
	$con.= "Source code is available <a href=\"https://github.com/yellows8/ninupdates\">here.</a><br />\n";
	$con.= "Parsed Nintendo Zone Hotspots data is available <a href=\"3ds_nzonehotspots.php\">here.</a><br />\n";

	$con.= "$sitecfg_homepage_footer</body></html>";
}
else
{
	$con.= "$sitecfg_reportupdatepage_header";

	$con.= "$sitecfg_sitenav_header<a href=\"reports.php\">Homepage</a> -> $report_titletext<hr><br/><br/>\n";

	$con.= "<table border=\"1\">
<tr>
  <th>Region</th>
  <th>Report</th>
  <th>Titlelist</th>
</tr>\n";

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.reportdaterfc, ninupdates_reports.updateversion FROM ninupdates_reports, ninupdates_consoles WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && log='report'";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);
	if($numrows==0)
	{
		writeNormalLog("REPORT ROW NOT FOUND. RESULT: 302");

		header("Location: reports.php");

		dbconnection_end();
		return;
	}

	$row = mysql_fetch_row($result);
	$regions = $row[0];
	$reportdaterfc = $row[1];
	$updateversion = $row[2];

	if(strlen($regions)==0)
	{
		writeNormalLog("INVALID ROW REGIONS. RESULT: 302");

		header("Location: reports.php");

		dbconnection_end();
		return;
	}

	$region = strtok($regions, ",");
	while($region!==FALSE)
	{
		if(strlen($region)>1)
		{
			writeNormalLog("INVALID ROW REGIONS FIELD $region. RESULT: 302");

			header("Location: reports.php");

			dbconnection_end();
			return;
		}

		$url = "titlelist.php?date=$reportdate&amp;sys=$system&amp;reg=$region";

		$con.= "<tr>\n";
		$con.= "<td>$region</td>\n";
		$con.= "<td><a href=\"$url\">$reportdate</a> <a href=\"$url&amp;wiki=1\">Wiki</a> <a href=\"$url&amp;csv=1\">CSV</a> <a href=\"$url&amp;gentext=1\">Text</a></td>\n";
		$con.= "<td><a href=\"$url&amp;soap=1\">$reportdate</a> <a href=\"$url&amp;soap=1&amp;wiki=1\">Wiki</a> <a href=\"$url&amp;soap=1&amp;csv=1\">CSV</a> <a href=\"$url&amp;soapreply=1\">Raw SOAP reply</a></td>\n";

		$region = strtok(",");
	}

	$url = "titlelist.php?date=$reportdate&amp;sys=$system";

	$con.= "<tr>\n";
	$con.= "<td>All</td>\n";
	$con.= "<td><a href=\"$url\">$reportdate</a> <a href=\"$url&amp;wiki=1\">Wiki</a> <a href=\"$url&amp;csv=1\">CSV</a> <a href=\"$url&amp;gentext=1\">Text</a></td>\n";
	$con.= "<td><a href=\"$url&amp;soap=1\">$reportdate</a> <a href=\"$url&amp;soap=1&amp;wiki=1\">Wiki</a> <a href=\"$url&amp;soap=1&amp;csv=1\">CSV</a></td>\n";

	$con.= "</table><br />\n";

	$changelog_count = 0;

	$region = strtok($regions, ",");
	while($region!==FALSE)
	{
		if(strlen($region)>1)break;

		$query="SELECT ninupdates_officialchangelog_pages.url FROM ninupdates_officialchangelog_pages, ninupdates_consoles, ninupdates_regions WHERE ninupdates_consoles.system='".$system."' && ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id && ninupdates_officialchangelog_pages.regionid=ninupdates_regions.id && ninupdates_regions.regioncode='".$region."'";
		$result=mysql_query($query);
		$numrows=mysql_num_rows($result);
		if($numrows>0)
		{
			$changelog_count++;

			$row = mysql_fetch_row($result);
			$pageurl = $row[0];

			if($changelog_count==1)
			{
				$con.= "Official changelog(s):<br /><br />\n";
				$con.= "<table border=\"1\">
<tr>
  <th>Region</th>
  <th>Official page URL</th>
</tr>\n";
			}

			$con.= "<tr>\n";
			$con.= "<td>$region</td>\n";
			$con.= "<td><a href=\"$pageurl\">Page link</a></td>\n";
		}

		$region = strtok(",");
	}

	if($changelog_count==0)
	{
		$con.= "Official changelog(s): N/A.<br /><br />\n";
	}
	else
	{
		$con.= "</table><br />\n";
	}

	$con.= "Request timestamp: $reportdaterfc<br /><br />\n";
	if($updateversion=="N/A")$con.= "Set system <a href=\"reports.php?date=$reportdate&sys=$system&setver=1\">version.</a>";
	$con.= "$sitecfg_reportupdatepage_footer";
	$con.= "</body></html>";
}

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");

echo $con;

?>
