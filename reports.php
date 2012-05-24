<?

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$workdir/weblogs/reportsphp";

dbconnection_start();

$reportdate = "";
$system = "";
$region = "";
$usesoap = "";
if($_REQUEST['date']!="")$reportdate = mysql_real_escape_string($_REQUEST['date']);
if($_REQUEST['sys']!="")$system = mysql_real_escape_string($_REQUEST['sys']);
if($_REQUEST['reg']!="")$region = mysql_real_escape_string($_REQUEST['reg']);
if($_REQUEST['soap']!="")$usesoap = mysql_real_escape_string($_REQUEST['soap']);

if(($reportdate!="" && $system=="") || ($system!="" && $reportdate==""))
{
	header("Location: reports.php");

	dbconnection_end();
	return;
}

$sys="";
if($system=="twl")$sys = "DSi";
if($system=="ctr")$sys = "3DS";

$text = "reports";
if($reportdate!="")
{
	$query="SELECT updateversion FROM ninupdates_reports WHERE reportdate='".$reportdate."' && system='".$system."' && log='report'";
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
if($region=="")$con = "<html><head><title>Nintendo system update $text</title></head>\n<body>";

if($reportdate=="")
{
	$con.= "<table border=\"1\">
<tr>
  <th>Report date</th>
  <th>Update Version</th>
  <th>System</th>
</tr>\n";

	$query="SELECT reportdate, updateversion, system FROM ninupdates_reports WHERE log='report' ORDER BY system, curdate";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);
	
	for($i=0; $i<$numrows; $i++)
	{
		$row = mysql_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];

		$sys="";
		if($system=="twl")$sys = "DSi";
		if($system=="ctr")$sys = "3DS";

		$url = "$httpbase/reports.php?date=".$reportdate."&sys=".$system;

		$con.= "<tr>\n";

		$con.= "<td><a href=\"".$url."\">$reportdate</a></td>\n";
		$con.= "<td>".$updateversion."</td>\n";
		$con.= "<td>".$sys."</td>\n";

		$con.= "</tr>\n";
	}
	$con.= "</table><br>\n";
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

		$query="SELECT regions, reportdaterfc FROM ninupdates_reports WHERE reportdate='".$reportdate."' && system='".$system."' && log='report'";
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
		$con.= "Request timestamp: $reportdaterfc<br>\n";
		$con.= "</body></html>";

		$con.= "</body></html>";
	}
	else
	{
		$query="SELECT regions FROM ninupdates_reports WHERE reportdate='".$reportdate."' && system='".$system."' && log='report'";
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
