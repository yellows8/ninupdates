<?php

include_once("config.php");

$dbconn_started = 0;

function dbconnection_start()
{
	global $mysqldb, $dbconn_started, $sitecfg_mysqldb_username, $sitecfg_mysqldb_pwdpath, $sitecfg_mysqldb_database;
	if($dbconn_started==1)return;

	$password = file_get_contents($sitecfg_mysqldb_pwdpath);

	@$mysqldb = mysqli_connect("localhost", $sitecfg_mysqldb_username, $password, $sitecfg_mysqldb_database);
	if(mysqli_connect_errno($mysqldb))die("Failed to connect to mysql.\n");

	$dbconn_started = 1;
}

function dbconnection_end()
{
	global $mysqldb, $dbconn_started;
	if($dbconn_started==0)return;
	@mysqli_close($mysqldb);

	$dbconn_started = 0;
}

function db_checkmaintenance($abort)
{
	global $mysqldb;

	$query="SELECT maintenanceflag FROM ninupdates_management";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows)
	{
		$row = mysqli_fetch_row($result);
		if($row[0]==1)
		{
			if($abort)
			{
				writeNormalLog("RESULT: 500");

				dbconnection_end();
				die("Site is currently under maintenance.\n");
			}

			return 1;
		}
	}
	
	return 0;
}

?>
