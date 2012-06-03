<?

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$workdir/weblogs/titlelistphp";

dbconnection_start();

db_checkmaintenance(1);

$reportdate = "";
$system = "";
$region = "";
$usesoap = "";
$genwiki = "";
if(isset($_REQUEST['date']))$reportdate = mysql_real_escape_string($_REQUEST['date']);
if(isset($_REQUEST['sys']))$system = mysql_real_escape_string($_REQUEST['sys']);
if(isset($_REQUEST['reg']))$region = mysql_real_escape_string($_REQUEST['reg']);
if(isset($_REQUEST['soap']))$usesoap = mysql_real_escape_string($_REQUEST['soap']);
if(isset($_REQUEST['wiki']))$genwiki = mysql_real_escape_string($_REQUEST['wiki']);

if($system=="")
{
	dbconnection_end();
	writeNormalLog("SYSTEM NOT SPECIFIED. RESULT: 200");
	echo "System not specified.\n";
	return;
}

$sys = getsystem_sysname($system);

if($genwiki=="")
{
	$con = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\" dir=\"ltr\">\n";
}

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysql_query($query);
$row = mysql_fetch_row($result);
$systemid = $row[0];

$reportid = 0;
$curdate = "";

$text = "";

if($reportdate!="")
{
	$query="SELECT id, updateversion, curdate FROM ninupdates_reports WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_reports.systemid=$systemid && ninupdates_reports.log='report'";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);

	if($numrows==0)
	{
		dbconnection_end();
		writeNormalLog("REPORT ROW NOT FOUND. RESULT: 200");
		echo "Invalid reportdate.\n";
		return;
	}
	else
	{
		$row = mysql_fetch_row($result);
		if($row[0]=="N/A")
		{
			$text = "$sys $reportdate";
		}
		else
		{
			$text = "$sys ".$row[1];
			$reportid = $row[0];
			$curdate = $row[2];
		}
	}
	if($region!="")$text.= " $region";
}
else
{
	$text = $sys;
}

if($genwiki=="")
{
	$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update Titlelist $text</title></head>\n<body>";

	$con.= "<table border=\"1\">
<tr>
  <th>TitleID</th>
  <th>Region</th>
  <th>Title versions</th>
  <th>Update versions</th>
</tr>\n";
}
else
{
	header("Content-Type: text/plain");

	$con.= "=== $text ===\n";
	$con.= "{| class=\"wikitable\" border=\"1\"\n";
	$con.= "|-
!  TitleID
!  Region
!  Versions\n";
}

$numrows = 0;

$reportquery = "";
$soapquery = "";
if($reportdate!="")
{
	if($usesoap=="")$reportquery = " && ninupdates_titles.reportid=$reportid && ninupdates_reports.id=$reportid";
	if($usesoap!="")$soapquery = " && ninupdates_titles.curdate<='".$curdate."' && ninupdates_reports.curdate<='".$curdate."'";
}

$regionquery = "";
if($region!="")$regionquery = " && ninupdates_titles.region='".$region."'";

$versionquery = "ninupdates_titles.version";
if($soapquery!="" || $reportdate=="")$versionquery = "GROUP_CONCAT(DISTINCT ninupdates_titles.version ORDER BY ninupdates_titles.version SEPARATOR ', ')";

$query = "SELECT ninupdates_titleids.titleid, $versionquery, ninupdates_titles.region, ninupdates_regions.regionid, GROUP_CONCAT(DISTINCT ninupdates_reports.reportdate ORDER BY ninupdates_reports.curdate SEPARATOR ', '), GROUP_CONCAT(DISTINCT ninupdates_reports.updateversion ORDER BY ninupdates_reports.updateversion SEPARATOR ', '), fssize, tmdsize, tiksize FROM ninupdates_titles, ninupdates_titleids, ninupdates_reports, ninupdates_regions WHERE ninupdates_titles.systemid=$systemid && ninupdates_reports.systemid=$systemid && ninupdates_titles.tid=ninupdates_titleids.id && ninupdates_reports.id=ninupdates_titles.reportid && ninupdates_regions.regioncode=ninupdates_titles.region";
if($reportquery!="")$query.= $reportquery;
if($soapquery!="")$query.= $soapquery;
if($regionquery!="")$query.= $regionquery;

$query.= " GROUP BY ninupdates_titles.tid, ninupdates_titles.region";

$result=mysql_query($query);
$numrows=mysql_numrows($result);

$updatesize = 0;
for($i=0; $i<$numrows; $i++)
{
	$row = mysql_fetch_row($result);
	$titleid = $row[0];
	$versions = $row[1];
	$reg = $row[2];
	$regionid = $row[3];
	$reportdates = $row[4];
	$updateversions = $row[5];
	$updatesize += $row[6] + $row[7] + $row[8];

	$versiontext = $versions;
	if($reportdates!=$reportdate)
	{
		$versiontext = "";
		$total_entries = 0;
		$version_array = array();
		$reportdate_array = array();
		$updateversion_array = array();

		$ver = strtok($versions, ", ");
		while($ver!==FALSE)
		{
			$version_array[] = "v$ver";
			$total_entries++;
			$ver = strtok(", ");
		}

		$cur_reportdate = strtok($reportdates, ", ");
		while($cur_reportdate!==FALSE)
		{
			$reportdate_array[] = $cur_reportdate;
			$cur_reportdate = strtok(", ");
		}

		$updatever = strtok($updateversions, ", ");
		while($updatever!==FALSE)
		{
			$updateversion_array[] = $updatever;
			$updatever = strtok(", ");
		}

		$first = 1;
		for($enti=0; $enti<$total_entries; $enti++)
		{
			$url = "titlelist.php?date=".$reportdate_array[$enti]."&amp;sys=$system";
			if($region!="")$url.= "&amp;reg=$reg";
			if($first==0)$versiontext .= ", ";
			$first = 0;

			if($genwiki=="")$versiontext .= "<a href =\"$url\">".$version_array[$enti]."</a>";
			if($genwiki!="")$versiontext .= "[[".$updateversion_array[$enti]."|".$version_array[$enti]."]]";
		}
	}
	else if($usesoap==0)
	{
		$versiontext = "v$versions";
	}

	$regtext = $regionid;
	if($reg!=$region)
	{
		$url = "titlelist.php?";
		if($reportdate!="")$url.= "date=$reportdate&amp;";
		$url.= "sys=$system&amp;reg=$reg";
		if($usesoap!="")$url.= "&amp;soap=1";
		$regtext = "<a href=\"$url\">$regionid</a>";
	}

	if($genwiki=="")
	{
		$con.= "<tr>\n";
		$con.= "<td>$titleid</td>\n";
		$con.= "<td>$regtext</td>\n";
		$con.= "<td>$versiontext</td>\n";
		$con.= "<td>$updateversions</td>\n";
		$con.= "</tr>\n";
	}
	else
	{
		$con.= "|-
| $titleid
| $regionid
| $versiontext\n";
	}
}

if($genwiki=="")
{
	$con.= "</table>";
}
else
{
	$con.= "|}\n";
}

if($genwiki=="" && $reportdate!="" && $soapquery=="")
{
	$con.= "<br />\n";
	$con.= "Total titles sizes: $updatesize<br />\n";
}

if($genwiki=="")$con .= "</body></html>";

dbconnection_end();

writeNormalLog("RESULT: 200");

echo $con;

?>
