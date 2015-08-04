<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/updatedetailsphp";

dbconnection_start();

db_checkmaintenance(1);

$reportdate = "";
$system = "";
if(isset($_REQUEST['date']))$reportdate = mysqli_real_escape_string($mysqldb, $_REQUEST['date']);
if(isset($_REQUEST['sys']))$system = mysqli_real_escape_string($mysqldb, $_REQUEST['sys']);


if($system=="")
{
	dbconnection_end();
	writeNormalLog("SYSTEM NOT SPECIFIED. RESULT: 200");
	echo "System not specified.\n";
	return;
}

if($reportdate=="")
{
	dbconnection_end();
	writeNormalLog("REPORTDATE NOT SPECIFIED. RESULT: 200");
	echo "Report-date not specified.\n";
	return;
}

$sys = getsystem_sysname($system);

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);
$systemid = $row[0];

$query="SELECT id, updateversion FROM ninupdates_reports WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_reports.systemid=$systemid && ninupdates_reports.log='report'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	writeNormalLog("REPORT ROW NOT FOUND. RESULT: 200");
	echo "Invalid reportdate.\n";
	return;
}

$reportname = "";

$row = mysqli_fetch_row($result);
if($row[1]=="N/A")
{
	$reportname = "$sys $reportdate";
}
else
{
	$reportname = "$sys ".$row[1];
}

$con = "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update Report Update Details</title></head>\n<body>";

$con.= "$sitecfg_sitenav_header<a href=\"reports.php\">Homepage</a> -> ";
if($reportdate!="")$con.= "<a href=\"reports.php?date=$reportdate&sys=$system\">$reportname report</a> -> ";
$con.= "Update details";
$con.= "<hr><br/><br/>\n";

$details_path = "$sitecfg_workdir/updatedetails/$system/$reportdate";

$details_exists = file_exists($details_path);
if($details_exists===TRUE)$updatedetails_text = file_get_contents($details_path);

if($details_exists===FALSE || $updatedetails_text===FALSE)
{
	dbconnection_end();
	writeNormalLog("UPDATEDETAILS FOR THIS REPORT N/A. RESULT: 200");
	echo "Update-details are not available for this report.\n";
	return;
}

$con.= nl2br($updatedetails_text);

$con.= "\n</body></html>";

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");

echo $con;

?>
