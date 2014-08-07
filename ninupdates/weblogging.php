<?php

//This is based on: http://www.ebrueggeman.com/php_site_access_log.php

$logging_dir = 'logs';

function getRealIpAddr()
{
	//$headers = apache_request_headers();
	$headers = $_SERVER;
       if (!empty($headers['HTTP_CLIENT_IP']))   //check ip from share internet
              {
                       $ip=$headers['HTTP_CLIENT_IP'];
                           }
           elseif (!empty($headers['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
                  {
                           $ip=$headers['HTTP_X_FORWARDED_FOR'];
                               }
           else
                  {
                           $ip=$headers['REMOTE_ADDR'];
                               }
           return $ip;
}

function writeNormalLog($app)
{
//ASSIGN VARIABLES TO USER INFO
$time = date("M j G:i:s Y"); 
$ip = getRealIpAddr();
$userAgent = getenv('HTTP_USER_AGENT');
$referrer = getenv('HTTP_REFERER');
$query = getenv('QUERY_STRING');
$uri = $_SERVER['REQUEST_URI'];

//COMBINE VARS INTO OUR LOG ENTRY
$msg = "IP: " . $ip . " TIME: " . $time . " REFERRER: " . $referrer . " SEARCHSTRING: " . $query . " USERAGENT: " . $userAgent . " URI: " . $uri . " " . $app;

//CALL OUR LOG FUNCTION
writeToLogFile($msg);
}

function writeToLogFile($msg)
{
     global $logging_dir;
     $today = date("Y_m_d"); 
     $logfile = $today.".log"; 
     $saveLocation=$logging_dir . '/' . $logfile;
	
     if(!file_exists($saveLocation))
     {
		$handle = @fopen($saveLocation, "w+");
		@fclose($handle);
     }
     if (!$handle = @fopen($saveLocation, "a"))
     {
          exit;
     }
     else
     {
          if(@fwrite($handle,"$msg\r\n")===FALSE) 
          {
               exit;
          }
  
          @fclose($handle);
     }
}

?>
