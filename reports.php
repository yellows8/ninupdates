<?

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$workdir/weblogs/reportsphp";

dbconnection_start();

db_checkmaintenance(1);

$reportdate = "";
$system = "";
$region = "";
$setver = "";
if(isset($_REQUEST['date']))$reportdate = mysql_real_escape_string($_REQUEST['date']);
if(isset($_REQUEST['sys']))$system = mysql_real_escape_string($_REQUEST['sys']);
if(isset($_REQUEST['reg']))$region = mysql_real_escape_string($_REQUEST['reg']);
if(isset($_REQUEST['setver']))$setver = mysql_real_escape_string($_REQUEST['setver']);
if(isset($_REQUEST['setsysver']))$setsysver = mysql_real_escape_string($_POST['setsysver']);

if(($reportdate!="" && $system=="") || ($system!="" && $reportdate==""))
{
	header("Location: reports.php");

	dbconnection_end();
	return;
}

$sys = getsystem_sysname($system);

$con = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\" dir=\"ltr\">\n";

if($reportdate!="" && $system!="")
{
	if($setver=="1" || $setsysver!="")
	{
		$query="SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
		$result=mysql_query($query);
		$numrows=mysql_numrows($result);
		
		if($numrows==0)
		{
			dbconnection_end();

			header("Location: reports.php");
			writeNormalLog("ROW FOR SETVER NOT FOUND. RESULT: 302");

			return;
		}		

		$row = mysql_fetch_row($result);
		if($row[0]!="N/A")
		{
			dbconnection_end();

			header("Location: reports.php");
			writeNormalLog("UPDATEVERSION ALREADY SET TO ".$row[0].". RESULT: 302");

			return;
		}

		if($setver=="1")
		{
			$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update $sys $reportdate Set System Version</title></head><body>
<form method=\"post\" action=\"reports.php?date=$reportdate&amp;sys=$system\" enctype=\"multipart/form-data\">
  System version: <input type=\"text\" value=\"\" name=\"setsysver\"/><input type=\"submit\" value=\"Submit\"/></form></body></html>";

			dbconnection_end();
			writeNormalLog("RESULT: 200");
			echo $con;

			return;
		}
		else if($setsysver!="")
		{
			$query = "UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.updateversion='".$setsysver."' WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && log='report'";
			$result=mysql_query($query);
			dbconnection_end();

			header("Location: reports.php?date=".$reportdate."&sys=".$system);
			writeNormalLog("CHANGED SYSVER TO $setsysver. RESULT: 302");

			return;
		}
	}
}

$text = "reports";
if($reportdate!="")
{
	$query="SELECT updateversion FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);
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
}

if($region=="")$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update $text</title></head>\n<body>";

if($reportdate=="")
{
	$con.= "<table border=\"1\">
<tr>
  <th>Report date</th>
  <th>Update Version</th>
  <th>System</th>
  <th>UTC datetime</th>
</tr>\n";

	$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system, ninupdates_reports.curdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_consoles.system, ninupdates_reports.curdate";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);
	
	for($i=0; $i<$numrows; $i++)
	{
		$row = mysql_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];
		$curdate = $row[3];

		$sys = getsystem_sysname($system);

		$url = "reports.php?date=$reportdate&amp;sys=$system";

		if($updateversion=="N/A")$updateversion = "<a href=\"$url&setver=1\">N/A</a>";

		$con.= "<tr>\n";

		$con.= "<td><a href=\"".$url."\">$reportdate</a></td>\n";
		$con.= "<td>".$updateversion."</td>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td>".$curdate."</td>\n";

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
	$numrows=mysql_numrows($result);

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysql_fetch_row($result);
		$system = $row[0];

		$sys = getsystem_sysname($system);

		$con.= "<tr>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td><a href=\"titlelist.php?sys=$system\">Titlelist</a></td>\n";
		$con.= "</tr>\n";
	}

	$con.= "</table><br />\n";

	$con.= "RSS feed is available <a href=\"feed.php\">here.</a><br />";
	$con.= "Source code is available <a href=\"https://github.com/yellows8/ninupdates\">here.</a>";

	$con.= "</body></html>";
}
else
{
	$con.= "<table border=\"1\">
<tr>
  <th>Region</th>
  <th>Report</th>
  <th>Titlelist</th>
</tr>\n";

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.reportdaterfc, ninupdates_reports.updateversion FROM ninupdates_reports, ninupdates_consoles WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && log='report'";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);
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
		$con.= "<td><a href=\"$url\">$reportdate</a></td>\n";
		$con.= "<td><a href=\"$url&amp;soap=1\">$reportdate</a></td>\n";

		$region = strtok(",");
	}

	$url = "titlelist.php?date=$reportdate&amp;sys=$system";

	$con.= "<tr>\n";
	$con.= "<td>All</td>\n";
	$con.= "<td><a href=\"$url\">$reportdate</a></td>\n";
	$con.= "<td><a href=\"$url&amp;soap=1\">$reportdate</a></td>\n";

	$con.= "</table><br />\n";
	$con.= "Request timestamp: $reportdaterfc<br /><br />\n";
	if($updateversion=="N/A")$con.= "Set system <a href=\"reports.php?date=$reportdate&sys=$system&setver=1\">version.</a>";
	$con.= "</body></html>";
}

dbconnection_end();

writeNormalLog("RESULT: 200");

echo $con;

?>
