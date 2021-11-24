<?php

require_once(dirname(__FILE__) . "/config.php");

$dbconn_refcount = 0;

function dbconnection_start()
{
	global $mysqldb, $dbconn_refcount, $sitecfg_mysqldb_username, $sitecfg_mysqldb_pwdpath, $sitecfg_mysqldb_database;

	$dbconn_refcount++;
	if($dbconn_refcount>1)return;

	$password = file_get_contents($sitecfg_mysqldb_pwdpath);

	@$mysqldb = mysqli_connect("localhost", $sitecfg_mysqldb_username, $password, $sitecfg_mysqldb_database);
	if(mysqli_connect_errno())die("Failed to connect to mysql.\n");
}

function dbconnection_end()
{
	global $mysqldb, $dbconn_refcount;
	if($dbconn_refcount==0)return;
	$dbconn_refcount--;
	if($dbconn_refcount>0)return;
	@mysqli_close($mysqldb);
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
