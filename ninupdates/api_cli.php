<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

include_once(dirname(__FILE__) . "/api.php");

if($argc<6)
{
	echo "Usage:\nphp api_cli.php <command> <system> <region> <titleid> <filterent: 0 = none, 1 = only first entry, 2 = only last entry> <options>\n";
	echo "Options:\n--format=csv\n--prevreport=<reportname>\n--prevtitlever=<titlever>\n";
	exit(1);
}

$outformat = "plain";
$prevreport = "";
$prevtitlever = "";

$select_previousentry = 0;

if($argc>=7)
{
	for($argi=6; $argi<$argc; $argi++)
	{
		if($argv[$argi] === "--format=csv")$outformat = "csv";
		if(substr($argv[$argi], 0, 13) === "--prevreport=")$prevreport = substr($argv[$argi], 13);
		if(substr($argv[$argi], 0, 15) === "--prevtitlever=")$prevtitlever = substr($argv[$argi], 15);
	}
}

$retval = ninupdates_api($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
if($retval!=0)
{
	echo("API returned error $retval.\n");
	exit($retval);
}

if($outformat === "csv")
{
	echo "Title version,Report date,Update version\n";
}

if($prevreport!=="" && $prevtitlever!=="")
{
	echo "prevreport and prevtitlever can't be used at same time.\n";
	exit(1);
}

if($prevreport!=="" || $prevtitlever!=="")$select_previousentry = 1;

if($select_previousentry === 1 && $ninupdatesapi_out_total_entries<2)
{
	echo "prev-entry was specified but there's less than 2 output entries.\n";
	exit(1);
}

$version = "";
$reportdate = "";
$updateversion = "";
$foundflag = 0;

for($i=0; $i<$ninupdatesapi_out_total_entries; $i++)
{
	$prev_version = $version;
	$prev_reportdate = $reportdate;
	$prev_updateversion = $updateversion;

	$version = $ninupdatesapi_out_version_array[$i];
	$reportdate = $ninupdatesapi_out_reportdate_array[$i];
	$updateversion = $ninupdatesapi_out_updateversion_array[$i];

	if($select_previousentry === 1 && $i>0)
	{
		if(($prevreport!=="" && $reportdate === $prevreport) || ($prevtitlever!=="" && $version === $prevtitlever))
		{
			$foundflag = 1;

			$version = $prev_version;
			$reportdate = $prev_reportdate;
			$updateversion = $prev_updateversion;
		}
	}

	if($select_previousentry === 0 || $foundflag===1)
	{
		if($outformat === "plain")echo "ent $i: titleversion = $version, reportdate = $reportdate, updateversion = $updateversion.\n";

		if($outformat === "csv")echo "$version,$reportdate,$updateversion\n";
	}

	if($foundflag===1)break;
}

if($select_previousentry === 1 && $foundflag===0)
{
	echo "Specified prev-entry not found.\n";
	exit(1);
}

?>
