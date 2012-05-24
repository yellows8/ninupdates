<?

include_once("config.php");

$dbconn_started = 0;

function dbconnection_start()
{
	global $dbconn_started, $mysqldb_username, $mysqldb_database;
	if($dbconn_started==1)return;

	$password = file_get_contents("/home/yellows8/auth/pwd");

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

?>
