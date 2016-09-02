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
if(isset($_REQUEST['date']))$reportdate = mysqli_real_escape_string($mysqldb, $_REQUEST['date']);
if(isset($_REQUEST['sys']))$system = mysqli_real_escape_string($mysqldb, $_REQUEST['sys']);
if(isset($_REQUEST['setver']))$setver = mysqli_real_escape_string($mysqldb, $_REQUEST['setver']);
if(isset($_REQUEST['setsysver']))$setsysver = mysqli_real_escape_string($mysqldb, $_POST['setsysver']);
if(isset($_REQUEST['order']))$order = mysqli_real_escape_string($mysqldb, $_REQUEST['order']);

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
		$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update $sys $reportdate Set System Version</title></head><body>
Manually setting the update-version is disabled since that's supposed to be done automatically, doing it manually would also disable other things which are supposed to be done automatically.</body></html>";

		dbconnection_end();
		if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");
		echo $con;

		return;

		/*$query="SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		
		if($numrows==0)
		{
			dbconnection_end();

			header("Location: reports.php");
			writeNormalLog("ROW FOR SETVER NOT FOUND. RESULT: 302");

			return;
		}		

		$row = mysqli_fetch_row($result);*/
		/*if($row[0]!="N/A")
		{
			dbconnection_end();

			header("Location: reports.php");
			writeNormalLog("UPDATEVERSION ALREADY SET TO ".$row[0].". RESULT: 302");

			return;
		}*/

		/*if($setver=="1")
		{
			$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update $sys $reportdate Set System Version</title></head><body>
<form method=\"post\" action=\"reports.php?date=$reportdate&amp;sys=$system\" enctype=\"multipart/form-data\">
  System version: <input type=\"text\" value=\"\" name=\"setsysver\"/><input type=\"submit\" value=\"Submit\"/></form></body></html>";

			dbconnection_end();
			if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");
			echo $con;

			return;
		}*/
		/*else if($setsysver!="")
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
				$result=mysqli_query($mysqldb, $query);
				$numrows=mysqli_num_rows($result);
			
				if($numrows!=0)
				{
					dbconnection_end();

					header("Location: reports.php");
					writeNormalLog("THE SPECIFIED SYSVER ALREADY EXISTS FOR A REPORT UNDER THE SPECIFIED SYSTEM. RESULT: 302");

					return;
				}
			}

			$query = "UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.updateversion='".$setsysver."', ninupdates_reports.wikipage_exists=0 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
			$result=mysqli_query($mysqldb, $query);
			dbconnection_end();

			header("Location: reports.php?date=".$reportdate."&sys=".$system);
			writeNormalLog("CHANGED SYSVER TO $setsysver. RESULT: 302");

			return;
		}*/
	}
}

$text = "reports";
$report_titletext = "";
if($reportdate!="")
{
	$query="SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows==0)
	{
		$text = "$sys $reportdate report";
	}
	else
	{
		$row = mysqli_fetch_row($result);
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

	$con.= "$sitecfg_homepage_header <h3><a name=Reports href=#Reports>Reports</a></h3><table border=\"1\">
<tr>
  <th>$reportdate_columntext</th>
  <th>Update Version</th>
  <th>$system_columntext</th>
  <th>Request timestamp</th>
  <th>Previous report</th>
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
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	
	$prev_curdate = "";
	$prev_system = "";

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysqli_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];
		$curdate = $row[3];
		$reportdaterfc = $row[4];

		$sys = getsystem_sysname($system);

		$url = "reports.php?date=$reportdate&amp;sys=$system";

		//if($updateversion=="N/A")$updateversion = "<a href=\"$url&setver=1\">N/A</a>";

		$con.= "<tr>\n";

		$query="SELECT TIMESTAMPDIFF(DAY,'".$prev_curdate."','".$curdate."'), TIMESTAMPDIFF(WEEK,'".$prev_curdate."','".$curdate."'), TIMESTAMPDIFF(MONTH,'".$prev_curdate."','".$curdate."'), TIMESTAMPDIFF(MINUTE,'".$prev_curdate."','".$curdate."'), TIMESTAMPDIFF(HOUR,'".$prev_curdate."','".$curdate."')";
		$result_new=mysqli_query($mysqldb, $query);
		$row_new = mysqli_fetch_row($result_new);

		$timediff0 = $row_new[0];
		$timediff1 = $row_new[1];
		$timediff2 = $row_new[2];
		$timediff3 = $row_new[3] % 60;
		$timediff4 = $row_new[4] % 24;

		$lastreport_text = "";
		if($i>0 && $prev_system===$system)$lastreport_text = "$timediff0 day(s) / $timediff1 week(s) / $timediff2 month(s) and $timediff4 hours $timediff3 minutes ago.";

		$con.= "<td><a href=\"".$url."\">$reportdate</a></td>\n";
		$con.= "<td>".$updateversion."</td>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td>".$reportdaterfc."</td>\n";
		$con.= "<td>".$lastreport_text."</td>\n";

		$con.= "</tr>\n";

		$prev_curdate = $curdate;
		$prev_system = $system;
	}
	$con.= "</table><br />\n";

	$con.= "<h3><a name=Systems href=#Systems>Systems</a></h3><table border=\"1\">
<tr>
  <th>System</th>
  <th>Title List</th>
  <th>Last report</th>
</tr>\n";

	$query = "SELECT now()";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);
	$dbcurdate = $row[0];

	$query="SELECT DISTINCT ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_consoles.system";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysqli_fetch_row($result);
		$system = $row[0];

		$sys = getsystem_sysname($system);

		$lastreport_text = "";

		$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_reports.curdate FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
		$result_new=mysqli_query($mysqldb, $query);
		$numrows_new=mysqli_num_rows($result_new);
		if($numrows_new>0)
		{
			$row_new = mysqli_fetch_row($result_new);

			$reportdate = $row_new[0];
			$updateversion = $row_new[1];
			$report_curdate = $row_new[2];

			$url = "reports.php?date=$reportdate&amp;sys=$system";

			$query="SELECT TIMESTAMPDIFF(DAY,'".$report_curdate."','".$dbcurdate."'), TIMESTAMPDIFF(WEEK,'".$report_curdate."','".$dbcurdate."'), TIMESTAMPDIFF(MONTH,'".$report_curdate."','".$dbcurdate."'), TIMESTAMPDIFF(MINUTE,'".$report_curdate."','".$dbcurdate."'), TIMESTAMPDIFF(HOUR,'".$report_curdate."','".$dbcurdate."')";
			$result_new=mysqli_query($mysqldb, $query);
			$row_new = mysqli_fetch_row($result_new);

			$timediff0 = $row_new[0];
			$timediff1 = $row_new[1];
			$timediff2 = $row_new[2];
			$timediff3 = $row_new[3] % 60;
			$timediff4 = $row_new[4] % 24;

			$lastreport_text = "<a href=\"".$url."\">$reportdate($updateversion)</a>, $timediff0 day(s) / $timediff1 week(s) / $timediff2 month(s) and $timediff4 hours $timediff3 minutes ago.";
		}

		$url = "titlelist.php?sys=$system";

		$con.= "<tr>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td><a href=\"$url\">HTML</a> <a href=\"$url&amp;wiki=1\">Wiki</a> <a href=\"$url&amp;csv=1\">CSV</a></td>\n";
		$con.= "<td>".$lastreport_text."</td>\n";
		$con.= "</tr>\n";
	}

	$con.= "</table><br />\n";

	$con.= "<h3><a name=ScanStatus href=#ScanStatus>Scan Status</a></h3><iframe src=\"scanstatus.php\" width=512 height=64></iframe><br /><br />\n";

	$con.= "<h3><a name=Other href=#Other>Other</a></h3>";

	$con.= "RSS feed is available <a href=\"feed.php\">here.</a><br />\n";
	$con.= "Source code is available <a href=\"https://github.com/yellows8/ninupdates\">here.</a><br />\n";
	$con.= "Parsed Nintendo Zone Hotspots data is available <a href=\"3ds_nzonehotspots.php\">here.</a><br />\n";
	$con.= "Scanning involving eShop can be found <a href=\"eshop/\">here.</a><br />\n";
	$con.= "Page scanning is also available: <a href=\"browserupdate/\">browser-version-check</a> and <a href=\"ninoss/\">Nintendo OSS</a>.<br />\n";

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

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.reportdaterfc, ninupdates_reports.updateversion, ninupdates_reports.id, ninupdates_reports.wikipage_exists FROM ninupdates_reports, ninupdates_consoles WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && log='report'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows==0)
	{
		writeNormalLog("REPORT ROW NOT FOUND. RESULT: 302");

		header("Location: reports.php");

		dbconnection_end();
		return;
	}

	$row = mysqli_fetch_row($result);
	$regions = $row[0];
	$reportdaterfc = $row[1];
	$updateversion = $row[2];
	$reportid = $row[3];
	$wikipage_exists = $row[4];

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

		$query="SELECT ninupdates_officialchangelog_pages.url, ninupdates_officialchangelog_pages.id FROM ninupdates_officialchangelog_pages, ninupdates_consoles, ninupdates_regions WHERE ninupdates_consoles.system='".$system."' && ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id && ninupdates_officialchangelog_pages.regionid=ninupdates_regions.id && ninupdates_regions.regioncode='".$region."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		if($numrows>0)
		{
			$changelog_count++;

			$row = mysqli_fetch_row($result);
			$pageurl = $row[0];
			$pageid = $row[1];

			if($changelog_count==1)
			{
				$con.= "Official changelog(s):<br /><br />\n";
				$con.= "<table border=\"1\">
<tr>
  <th>Region</th>
  <th>Page URL</th>
  <th>Changelog text</th>
</tr>\n";
			}

			$display_html = "";

			$query = "SELECT display_html FROM ninupdates_officialchangelogs WHERE pageid=$pageid && reportid=$reportid";
			$result=mysqli_query($mysqldb, $query);
			$numrows=mysqli_num_rows($result);
			if($numrows>0)
			{
				$row = mysqli_fetch_row($result);
				$display_html = $row[0];
			}

			if($display_html===FALSE)$display_html = "";
			if($display_html=="")$display_html = "N/A";

			$con.= "<tr>\n";
			$con.= "<td>$region</td>\n";
			$con.= "<td><a href=\"$pageurl\">Page link</a></td>\n";
			$con.= "<td>$display_html</td>\n";
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

	if(file_exists("$sitecfg_workdir/updatedetails/$system/$reportdate")===TRUE)
	{
		$con.= "Update details are available <a href=\"updatedetails.php?date=$reportdate&sys=$system\">here</a>.<br/>\n<br/>\n";
	}

	if($wikipage_exists==="1")
	{
		$query="SELECT ninupdates_wikiconfig.serverbaseurl FROM ninupdates_wikiconfig, ninupdates_consoles WHERE ninupdates_wikiconfig.id=ninupdates_consoles.wikicfgid && ninupdates_consoles.system='".$system."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$wiki_serverbaseurl = $row[0];

			$con.= "The wiki page is available <a href=\"".$wiki_serverbaseurl."wiki/$updateversion\">here</a>.<br/>\n<br/>\n";
		}
	}

	$con.= "Request timestamp: $reportdaterfc<br /><br />\n";
	//if($updateversion=="N/A")$con.= "Set system <a href=\"reports.php?date=$reportdate&sys=$system&setver=1\">version.</a>";
	$con.= "$sitecfg_reportupdatepage_footer";
	$con.= "</body></html>";
}

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");

echo $con;

?>
