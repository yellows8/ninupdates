<?php

include_once("config.php");

$dbconn_started = 0;

function dbconnection_start()
{
	global $dbconn_started, $mysqldb_username, $mysqldb_pwdpath, $mysqldb_database;
	if($dbconn_started==1)return;

	$password = file_get_contents($mysqldb_pwdpath);

	@mysql_connect("localhost",$mysqldb_username,$password) or die("Failed to connect to mysql");
	@mysql_select_db($mysqldb_database) or die("Failed to select database");

	$dbconn_started = 1;
}

function dbconnection_end()
{
	global $dbconn_started;
	if($dbconn_started==0)return;
	@mysql_close();

	$dbconn_started = 0;
}

function db_checkmaintenance($abort)
{
	$query="SELECT maintenanceflag FROM ninupdates_management";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);
	if($numrows)
	{
		$row = mysql_fetch_row($result);
		if($row[0]==1)
		{
			if($abort)
			{
				writeNormalLog("RESULT: 500");

				dbconnection_end();
				die("Site is currently under maintenance.");
			}

			return 1;
		}
	}
	
	return 0;
}

?>
