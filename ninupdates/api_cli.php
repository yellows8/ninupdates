<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

include_once(dirname(__FILE__) . "/api.php");

if($argc<6)
{
	die("Usage:\nphp api_cli.php <command> <system> <region> <titleid> <filterent: 0 = none, 1 = only first entry, 2 = only last entry>\n");
}

$retval = ninupdates_api($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
if($retval!=0)die("API returned error $retval.\n");

for($i=0; $i<$ninupdatesapi_out_total_entries; $i++)
{
	$version = $ninupdatesapi_out_version_array[$i];
	$reportdate = $ninupdatesapi_out_reportdate_array[$i];
	$updateversion = $ninupdatesapi_out_updateversion_array[$i];

	echo "ent $i: titleversion = $version, reportdate = $reportdate, updateversion = $updateversion.\n";
}

?>
