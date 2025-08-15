<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/logs.php");

$arg_difflogold = "";
$arg_difflognew = "";
$arg_difflog = "";
$arg_diffsys = "";
$arg_diffregion = "";

if($argc>1)
{
	if($argv[1]=="--insertdblog" && $argc>=5)
	{
		$arg_difflog = $argv[2];
		$arg_diffsys = $argv[3];
		$arg_diffregion = $argv[4];
		if($argc>=6)$arg_logpath = $argv[5];
	}
	else if($argv[1]=="--difflogs" && $argc>=5)
	{
		$arg_difflogold = $argv[2];
		$arg_difflognew = $argv[3];
		$arg_diffsys = $argv[4];
		if($argc>=6)$arg_diffregion = $argv[5];
	}
}

if($arg_difflog=="" && $arg_difflognew=="")return;

$system = $arg_diffsys;

dbconnection_start();

if($arg_difflog!="")diffinsert_main();
if($arg_difflognew!="")difflogshtml();

dbconnection_end();

function diffinsert_main()
{
	global $mysqldb, $dbcurdate, $arg_difflog, $arg_diffregion, $system, $reportid, $arg_logpath;

	$query = "SELECT ninupdates_reports.curdate, ninupdates_reports.id FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$arg_difflog."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	$reportid = 0;

	if($numrows)
	{
		$row = mysqli_fetch_row($result);
		$dbcurdate = $row[0];
		$reportid = $row[1];
	}
	else
	{
		$query = "SELECT now()";
		$result=mysqli_query($mysqldb, $query);
		$row = mysqli_fetch_row($result);
		$dbcurdate = $row[0];
	}

	if($arg_diffregion=="")
	{
	        if($arg_logpath!=="")
	        {
	                echo "Can't use arg_logpath without input region.\n";
	                return;
	        }
		$query="SELECT regions FROM ninupdates_consoles WHERE system='".$system."'";
		$result=mysqli_query($mysqldb, $query);
		$row = mysqli_fetch_row($result);
		$regions = $row[0];

		for($i=0; $i<strlen($regions); $i++)
		{
			diffinsert(substr($regions, $i, 1));
		}
	}
	else
	{
		diffinsert($arg_diffregion);
	}
}

function difflogshtml()
{
	global $region, $system, $arg_difflogold, $arg_difflognew, $sitecfg_workdir;

	$logexists = 1;
	if(!file_exists("$sitecfg_workdir/soap$system/$region/$arg_difflogold.html"))
	{
		$logexists = 0;
		echo "Log for $arg_difflogold doesn't exist.\n";
	}
	if(!file_exists("$sitecfg_workdir/soap$system/$region/$arg_difflognew.html"))
	{
		$logexists = 0;
		echo "Log for $arg_difflognew doesn't exist.\n";
	}

	if($logexists==0)
	{
		return;
	}
	else
	{
		$logstripped = getlogcontents("$sitecfg_workdir/soap$system/$region/$arg_difflognew.html");
		$oldlogstripped = getlogcontents("$sitecfg_workdir/soap$system/$region/$arg_difflogold.html");
		load_newtitlelist($logstripped);

		if(diff_titlelists($oldlogstripped, $curdatefn))
		{
			echo "Updated titles found in the logs.\n";
		}
		else
		{
			echo "Logs are identical.\n";
		}
	}
}

function diffinsert($reg)
{
	global $mysqldb, $region, $system, $arg_difflog, $sitecfg_workdir, $reportid, $dbcurdate, $arg_logpath;
	$region = $reg;

	echo "region $region\n";

	if($arg_logpath==="")
	{
		$curlog = getlogcontents("$sitecfg_workdir/soap$system/$region/$arg_difflog.html");
	}
	else
	{
		$curlog = file_get_contents($arg_logpath);
	}
	if($curlog==="" || $curlog===FALSE)
	{
		echo "failed to open log region $region\n";
		if($arg_logpath!=="") echo "arg_logpath: $arg_logpath\n";
		return;
	}

	init_titlelistarray();
	if($arg_logpath==="")
	{
		load_newtitlelist($curlog);
	}
	else
	{
		parse_soapresp($curlog, 1);
	}
	$total = titlelist_dbupdate();
	if($total)
	{
		$query="UPDATE ninupdates_titles SET reportid=$reportid WHERE curdate='".$dbcurdate."'";
		$result=mysqli_query($mysqldb, $query);
	}
	echo "inserted $total new titles into the db.\n";
}

?>
