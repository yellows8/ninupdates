<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");
include_once("/home/yellows8/ninupdates/tweet.php");

dbconnection_start();

db_checkmaintenance(1);

$tmptoken = "";
$msg = "";
if(isset($_REQUEST['token']))$tmptoken = mysqli_real_escape_string($mysqldb, $_REQUEST['token']);
if(isset($_REQUEST['msg']))$msg = mysqli_real_escape_string($mysqldb, $_REQUEST['msg']);

if($tmptoken=="")
{
	dbconnection_end();
	echo "ERROR: Token not specified.\n";
	return;
}

if($msg=="")
{
	dbconnection_end();
	echo "ERROR: Msg not specified.\n";
	return;
}

$token_found = 0;
$msg_prefix = "";

$tmpconfigpath = "$sitecfg_workdir/sendtweet_config";
if (($handle = fopen($tmpconfigpath, "r")) !== FALSE)
{
	while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
	{
		$num = count($data);

		if($num < 2)continue;

		if(strcmp($tmptoken, $data[0])===0)
		{
			$token_found = 1;
			$msg_prefix = $data[1];
		}
	}
}

dbconnection_end();

if($token_found === 0)
{
	echo "Invalid token.\n";
	return;
}

sendtweet($msg_prefix . $msg);

?>
