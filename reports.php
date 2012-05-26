<?

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$workdir/weblogs/reportsphp";

dbconnection_start();

db_checkmaintenance(1);

$reportdate = "";
$system = "";
$region = "";
$usesoap = "";
$setver = "";
if($_REQUEST['date']!="")$reportdate = mysql_real_escape_string($_REQUEST['date']);
if($_REQUEST['sys']!="")$system = mysql_real_escape_string($_REQUEST['sys']);
if($_REQUEST['reg']!="")$region = mysql_real_escape_string($_REQUEST['reg']);
if($_REQUEST['soap']!="")$usesoap = mysql_real_escape_string($_REQUEST['soap']);
if($_REQUEST['setver']!="")$setver = mysql_real_escape_string($_REQUEST['setver']);
if($_REQUEST['setsysver']!="")$setsysver = mysql_real_escape_string($_POST['setsysver']);

if(($reportdate!="" && $system=="") || ($system!="" && $reportdate==""))
{
	header("Location: reports.php");

	dbconnection_end();
	return;
}

$sys="";
if($system=="twl")$sys = "DSi";
if($system=="ctr")$sys = "3DS";

if($reportdate!="" && $system!="")
{
	if($setver=="1")
	{
		$con = "<html><head><title>Nintendo System Update $sys $reportdate Set System Version</title></head><body>
<form method=\"post\" action=\"reports.php?date=$reportdate&sys=$system\" enctype=\"multipart/form-data\">
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

$con = "";
if($region=="")$con = "<html><head><title>Nintendo System Update $text</title></head>\n<body>";

if($reportdate=="")
{
	$con.= "<table border=\"1\">
<tr>
  <th>Report date</th>
  <th>Update Version</th>
  <th>System</th>
  <th>Datetime</th>
</tr>\n";

	$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system, ninupdates_reports.reportdaterfc FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_consoles.system, curdate";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);
	
	for($i=0; $i<$numrows; $i++)
	{
		$row = mysql_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];
		$reportdaterfc = $row[3];

		$sys="";
		if($system=="twl")$sys = "DSi";
		if($system=="ctr")$sys = "3DS";

		$url = "$httpbase/reports.php?date=$reportdate&sys=$system";

		if($updateversion=="N/A")$updateversion = "<a href=\"$url&setver=1\">N/A</a>";

		$con.= "<tr>\n";

		$con.= "<td><a href=\"".$url."\">$reportdate</a></td>\n";
		$con.= "<td>".$updateversion."</td>\n";
		$con.= "<td>".$sys."</td>\n";
		$con.= "<td>".$reportdaterfc."</td>\n";

		$con.= "</tr>\n";
	}
	$con.= "</table><br>\n";
	$con.= "RSS feed is available <a href=\"feed.php\">here.</a><br>";
	$con.= "Source code is available <a href=\"https://github.com/yellows8/ninupdates\">here.</a>";

	$con.= "</body></html>";
}
else
{
	if($region=="")
	{
		$con.= "<table border=\"1\">
<tr>
  <th>Region</th>
  <th>Report log</th>
  <th>Titlelist log</th>
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

			$con.= "<tr>\n";
			$con.= "<td>Region $region</td>\n";
			$con.= "<td><a href=\"reports.php?date=".$reportdate."&sys=".$system."&reg=".$region."\">$reportdate</a></td>\n";
			$con.= "<td><a href=\"reports.php?date=".$reportdate."&sys=".$system."&reg=".$region."&soap=1\">$reportdate</a></td>\n";

			$region = strtok(",");
		}

		$con.= "</table><br>\n";
		$con.= "Request timestamp: $reportdaterfc<br><br>\n";
		if($updateversion=="N/A")$con.= "Set system <a href=\"$httpbase/reports.php?date=$reportdate&sys=$system&setver=1>version.</a>";
		$con.= "</body></html>";

		$con.= "</body></html>";
	}
	else
	{
		$query="SELECT ninupdates_reports.regions FROM ninupdates_reports, ninupdates_consoles WHERE reportdate='".$reportdate."' && system='".$system."' && log='report'";
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
		if(!strstr($regions, $region))
		{
			writeNormalLog("REPORT REGION NOT FOUND. RESULT: 302");

			header("Location: reports.php?date=".$reportdate."&sys=".$system);

			dbconnection_end();
			return;
		}

		if($usesoap=="")$con = file_get_contents("$workdir/reports$system/$region/$reportdate.html");
		if($usesoap!="")$con = file_get_contents("$workdir/soap$system/$region/$reportdate.html");
		if($con===FALSE)
		{
			writeNormalLog("REPORT LOG NOT FOUND. RESULT: 302");

			header("Location: reports.php?date=".$reportdate."&sys=".$system);

			dbconnection_end();
			return;
		}
	}
}

dbconnection_end();

writeNormalLog("RESULT: 200");

echo $con;

?>
