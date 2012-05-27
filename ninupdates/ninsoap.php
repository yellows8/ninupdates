<?

include_once("config.php");
include_once("logs.php");
include_once("db.php");

$argc = $_SERVER["argc"];
$argv = $_SERVER["argv"];

$arg_difflogold = "";
$arg_difflognew = "";
$arg_diffregion = "";
$arg_diffsys = "";

date_default_timezone_set("America/New_York");

if($argc>1)
{
	if($argv[1]=="--difflogs" && $argc>=5)
	{
		$arg_difflogold = $argv[2];
		$arg_difflognew = $argv[3];
		$arg_diffsys = $argv[4];
		if($argc>=6)$arg_diffregion = $argv[5];
	}
}

if($arg_difflogold!="")
{
	do_systems_soap($arg_diffsys);
	return;
}
else
{
	do_systems_soap("");
}

function do_systems_soap($sys)
{
	if($sys=="")
	{
		while(1)
		{
			sleep(60);
			dbconnection_start();
			if(!db_checkmaintenance(0))
			{
				init_curl();

				$query="SELECT system FROM ninupdates_consoles";
				$result=mysql_query($query);
				$numrows=mysql_numrows($result);

				for($i=0; $i<$numrows; $i++)
				{
					$row = mysql_fetch_row($result);

					dosystem($row[0]);
				}

				close_curl();
			}
			dbconnection_end();
		}
	}
	else
	{
		dbconnection_start();
		init_curl();
		dosystem($sys);
		close_curl();
		dbconnection_end();
	}
}

function dosystem($console)
{
	global $region, $system, $emailhost, $target_email, $httpbase, $sysupdate_available, $sysupdate_timestamp, $workdir, $soap_timestamp, $sysupdate_regions, $arg_diffregion, $arg_difflognew, $sysupdate_timestamp, $sysupdate_systitlehashes;

	$system = $console;
	$msgme_message = "";
	$html_message = "";
	$sysupdate_available = 0;
	$sysupdate_timestamp = "";
	$soap_timestamp = "";
	$sysupdate_regions = "";
	$sysupdate_systitlehashes = array();

	if($arg_difflognew!="")$sysupdate_timestamp = $arg_difflognew;

	if($arg_diffregion=="")
	{
		$query="SELECT regions FROM ninupdates_consoles WHERE system='".$system."'";
		$result=mysql_query($query);
		$row = mysql_fetch_row($result);
		$regions = $row[0];

		for($i=0; $i<strlen($regions); $i++)
		{
			main(substr($regions, $i, 1));
		}
	}
	else
	{
		main($arg_diffregion);
	}

	if($sysupdate_available)
	{
		$msgme_message = "$httpbase/reports.php?date=".$sysupdate_timestamp."&sys=".$system;
		$email_message = "$msgme_message";
		
		

		$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$sysupdate_timestamp."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
		$result=mysql_query($query);
		$numrows=mysql_numrows($result);
		if($numrows==0)
		{
			$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
			$result=mysql_query($query);
			$row = mysql_fetch_row($result);
			$systemid = $row[0];

			$query = "INSERT INTO ninupdates_reports (reportdate, curdate, systemid, log, regions, updateversion, reportdaterfc) VALUES ('".$sysupdate_timestamp."',now(),$systemid,'report','".$sysupdate_regions."','N/A','".$soap_timestamp."')";
			$result=mysql_query($query);
			$reportid = mysql_insert_id();
			echo "query $query\n";

			$region = strtok($sysupdate_regions, ",");
			while($region!==FALSE)
			{
				$query = "INSERT INTO ninupdates_systitlehashes (reportid, region, titlehash) VALUES ('".$reportid."','".$region."','".$sysupdate_systitlehashes[$region]."')";
				$result=mysql_query($query);

				$region = strtok(",");
			}
		}
		else
		{
			//this will only happen when the report row already exists and the --difflogs option was used.
			$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.regions='".$sysupdate_regions."' WHERE reportdate='".$sysupdate_timestamp."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && log='report'";
			$result=mysql_query($query);
		}

		echo "\nSending IRC msg...\n";
		sendircmsg($msgme_message);
		echo "Sending email...\n";
        	if(!mail($target_email, "$system SOAP updates", $email_message, "From: ninsoap@$emailhost"))echo "Failed to send mail.\n";
	}
}

function initialize()
{
	global $hdrs, $soapreq, $log, $fp, $system, $region, $workdir, $soapreq_data, $twldeviceid, $ctrdeviceid;
	
	error_reporting(E_ALL);

	$regionid = "";
	$countrycode = "";

	$query="SELECT deviceid FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);
	$row = mysql_fetch_row($result);

	$deviceid = $row[0];

	if($region=="E")
	{
		$regionid = "USA";
		$countrycode = "US";
	}
	if($region=="P")
	{
		$regionid = "EUR";
		$countrycode = "EU";
	}
	if($region=="J")
	{
		$regionid = "JPN";
		$countrycode = "JP";
	}
	if($region=="C")
	{
		$regionid = "CHN";
		$countrycode = "CN";
	}
	if($region=="K")
	{
		$regionid = "KOR";
		$countrycode = "KR";
	}
	if($region=="T")
	{
		$regionid = "TWN";
		$countrycode = "TW";
	}
	if($region=="A")
	{
		$regionid = "AUS";
		$countrycode = "AU";
	}
	
	if($regionid=="")die("Unknown region $region");

	$soapreq_data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
  <soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\"
                    xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"
                    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
    <soapenv:Body>
      <GetSystemUpdateRequest xmlns=\"urn:nus.wsapi.broadon.com\">
        <Version>1.0</Version>
        <MessageId>1</MessageId>
        <DeviceId>$deviceid</DeviceId>
        <RegionId>$regionid</RegionId>
        <CountryCode>$countrycode</CountryCode>
        <Attribute>2</Attribute>
      </GetSystemUpdateRequest>
    </soapenv:Body>
  </soapenv:Envelope>";

	$hdrs = array('SOAPAction: "urn:nus.wsapi.broadon.com/GetSystemUpdate"', 'Content-Type: application/xml', 'Content-Size: '.strlen($soapreq_data), 'Connection: Keep-Alive', 'Keep-Alive: 30');

	init_titlelistarray();

	$log = fopen("$workdir/soap$system/$region/current.html", "w");
	$text = "<html><head></head><body>\n";
	fprintf($log, $text);
}

function init_titlelistarray()
{
	global $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles;

	$newtitles = array();
	$newtitlesversions = array();
	$newtitles_sizes = array();
	$newtitles_tiksizes = array();
	$newtitles_tmdsizes = array();
	$newtotal_titles = 0;

	$oldtitles = array();
	$oldtitlesversions = array();
	$oldtitles_sizes = array();
	$oldtitles_tiksizes = array();
	$oldtitles_tmdsizes = array();
	$oldtotal_titles = 0;
}

function init_curl()
{
	global $curl_handle;

	$curl_handle = curl_init();
}

function close_curl()
{
	global $curl_handle;

	curl_close($curl_handle);
}

function send_httprequest($url)
{
	global $hdrs, $soapreq, $httpstat, $workdir, $soapreq_data, $curl_handle, $system;

	$query="SELECT clientcertfn, clientprivfn FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);
	$row = mysql_fetch_row($result);
	$clientcertfn = $row[0];
	$clientprivfn = $row[1];

	$error_FH = fopen("$workdir/debuglogs/error.log","w");

	curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle, CURLOPT_STDERR, $error_FH );

	curl_setopt($curl_handle, CURLOPT_USERAGENT, "ds libnup/2.0");

	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $hdrs);

	curl_setopt($curl_handle, CURLOPT_POST, 1);
	curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $soapreq_data);

	curl_setopt($curl_handle, CURLOPT_URL, $url);

	curl_setopt($curl_handle, CURLOPT_SSLCERTTYPE, "PEM");
	curl_setopt($curl_handle, CURLOPT_SSLCERT, "$workdir/sslcerts/$clientcertfn");
	curl_setopt($curl_handle, CURLOPT_SSLKEY, "$workdir/sslcerts/$clientprivfn");

	curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($curl_handle, CURLOPT_SSLVERSION, 3);

	$buf = curl_exec($curl_handle);

	$httpstat = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$buf = "HTTP request failed.<br>\n";
		$httpstat = "0";
	} else if($httpstat!="200")$buf = "HTTP error $httpstat<br>\n";

	fclose($error_FH);

	return $buf;
}

function parse_soapresp($buf)
{
	global $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $log, $system, $region, $sysupdate_systitlehashes;
	$title = $buf;
	$titleid_pos = 0;
	$titlever_pos = 0;
	$titlesize_pos = 0;
	$titleid = "";
	$titlever = "";
	$titlesize = 0;
	$titlesizetik = "";
	$titlesizetmd = "";

	while(($title = strstr($title, "<TitleVersion>")))
	{
		$titleid_pos = strpos($title,  "<TitleId>") + 9;
		$titlever_pos = strpos($title, "<Version>") + 9;
		$titlever_posend = strpos($title, "</Version>");
		$titlesize_pos = strpos($title, "<FsSize>") + 8;
		$titlesize_posend = strpos($title, "</FsSize>");
		$titlesizetik_pos = strpos($title, "<TicketSize>") + 12;
		$titlesizetik_posend = strpos($title, "</TicketSize>");
		$titlesizetmd_pos = strpos($title, "<TMDSize>") + 9;
		$titlesizetmd_posend = strpos($title, "</TMDSize>");

		if($titleid_pos!==FALSE)$titleid = substr($title, $titleid_pos, 16);
		if($titlever_pos!==FALSE)$titlever = substr($title, $titlever_pos, $titlever_posend - $titlever_pos);
		if($titlesize_pos!==FALSE)$titlesize = substr($title, $titlesize_pos, $titlesize_posend - $titlesize_pos);
		if($titlesizetik_pos!==FALSE)$titlesizetik = substr($title, $titlesizetik_pos, $titlesizetik_posend - $titlesizetik_pos);
		if($titlesizetmd_pos!==FALSE)$titlesizetmd = substr($title, $titlesizetmd_pos, $titlesizetmd_posend - $titlesizetmd_pos);

		if($titlever_pos!==FALSE)$titlever = intval($titlever);
		if($titlesize_pos!==FALSE)$titlesize = intval($titlesize);

		if($titleid_pos===FALSE)$titleid="tagsmissing";
		if($titlever_pos===FALSE || $titlever_posend===FALSE)$titlever="tagsmissing";
		if($titlesize_pos===FALSE || $titlesize_posend===FALSE)$titlesize="tagsmissing";
		if($titlesizetik_pos===FALSE || $titlesizetik_posend===FALSE)$titlesizetik="tagsmissing";
		if($titlesizetmd_pos===FALSE || $titlesizetmd_posend===FALSE)$titlesizetmd="tagsmissing";

		$newtitles[] = $titleid;
		$newtitlesversions[] = $titlever;
		$newtitles_sizes[] = $titlesize;
		$newtitles_tiksizes[] = $titlesizetik;
		$newtitles_tmdsizes[] = $titlesizetmd;

		$newtotal_titles++;
		$extra_text = "";
		$text = "titleid $titleid ver $titlever size $titlesize";
		if($system=="ctr")$text.= " tiksize $titlesizetik tmdsize $titlesizetmd";
		$text.= "$extra_text<br>\n";
		fprintf($log, $text);

		$title = strstr($title, "</TitleVersion>");
	}

	if($system=="ctr")
	{
		$titlehash_pos = strpos($buf, "<TitleHash>") + 11;
		$titlehash_posend = strpos($buf, "</TitleHash>");
		if($titlehash_pos!==FALSE && $titlehash_posend!==FALSE)
		{
			$titlehash = substr($buf, $titlehash_pos, $titlehash_posend - $titlehash_pos);
			$sysupdate_systitlehashes[$region] = $titlehash;

			$text = "titlehash $titlehash<br>\n";
			fprintf($log, $text);
		}
	}
}

function compare_logs($oldlog, $curlog, $curdatefn)
{
	global $system, $difflogbuf, $region;

	if($system!="ctr")
	{
		return diff_titlelists($oldlog, $curdatefn);
	}

	$query="SELECT ninupdates_systitlehashes.titlehash FROM ninupdates_reports, ninupdates_consoles, ninupdates_systitlehashes WHERE ninupdates_systitlehashes.reportid=ninupdates_reports.id && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' && ninupdates_systitlehashes.region='".$region."' && ninupdates_reports.log='report' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
	$result=mysql_query($query);
	$numrows=mysql_numrows($result);

	$titlehashcur_pos = strpos($curlog, "titlehash");
	if($titlehashcur_pos===FALSE || $numrows==0)
	{
		echo "Titlehash is missing from log.\n";
		return diff_titlelists($oldlog, $curdatefn);
	}
	else
	{
		$row = mysql_fetch_row($result);

		$titlehashcur_pos+= 10;

		$titlehashcur = substr($curlog, $titlehashcur_pos, 32);
		$titlehashold = $row[0];

		if($titlehashcur!=$titlehashold)
		{
			diff_titlelists($oldlog, $curdatefn);
			return 1;
		}
		else
		{
			return 0;
		}
	}

	return 0;
}

function load_oldtitlelist($oldlog)
{
	global $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles;

	load_titlelist($oldlog, $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles);
}

function load_newtitlelist($newlog)
{
	global $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles;

	load_titlelist($newlog, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles);
}

function load_titlelist($soaplog, &$titleids, &$titleversions, &$titles_sizes, &$titles_tiksizes, &$titles_tmdsizes, &$total_titles)
{
	$text = $soaplog;
	$titleid = "";
	$titlever = "";
	$titlesize = "0";
	$titlesizetik = "";
	$titlesizetmd = "";

	while(($text = strstr($text, "titleid")))
	{
		$titlever_pos = strpos($text, "ver ") + 4;
		$titlever_posend = strpos($text, "size");
		if($titlever_posend===FALSE)
		{
			$titlever_posend = strpos($text, "<br>");
		}
		else
		{
			$titlever_posend -= 1;
		}

		$titlesize_pos = 0;
		$titlesize_posend = 0;

		if(strstr($text, "size"))
		{
			$titlesize_pos = strpos($text, "size ") + 5;
			$titlesize_posend = strpos($text, "tiksize");
			if($titlesize_posend===FALSE)
			{
				$titlesize_posend = strpos($text, "<br>");
			}
			else
			{
				$titlesize_posend -= 1;
			}
		}

		$titleid = substr($text, 8, 16);
		$titlever = substr($text, $titlever_pos, $titlever_posend - $titlever_pos);
		if($titlesize_pos!=0)$titlesize = substr($text, $titlesize_pos, $titlesize_posend - $titlesize_pos);

		$titleids[] = $titleid;
		$titleversions[] = intval($titlever);
		$titles_sizes[] = intval($titlesize);
		$titles_tiksizes[] = $titlesizetik;
		$titles_tmdsizes[] = $titlesizetmd;

		$total_titles++;

		$text = strstr($text, "<br>");
	}
}

function diff_titlelists($oldlog, $curdatefn)
{
	global $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $difflogbuf, $system, $region, $workdir, $soap_timestamp;

	$difflogbuf = "<html><head></head><body>\n";
	$text = "";
	$found = 0;
	$oldtitleid="";
	$titleid="";
	$oldtitlever="";
	$titlever="";
	$updatedtitles = 0;
	$update_size = 0;

	load_oldtitlelist($oldlog);

	for($newi=0; $newi<$newtotal_titles; $newi++)
	{
		$found = 0;

		$titleid = $newtitles[$newi];
		$titlever = $newtitlesversions[$newi];
		$titlesize = $newtitles_sizes[$newi];

		for($oldi=0; $oldi<$oldtotal_titles; $oldi++)
		{
			$oldtitleid = $oldtitles[$oldi];
			$oldtitlever = $oldtitlesversions[$oldi];

			if($titleid==$oldtitleid)
			{
				if($titlever<=$oldtitlever)
				{
					$found = 2;
					break;//skip titles which weren't updated
				}
				$found = 1;
			}
		}

		if($found!=2)
		{
			$updatedtitles++;
			$update_size+= $titlesize;
			$status="";
			if($found==0)$status = "new";
			if($found==1)$status = "updated";

			$text = "titleid $titleid ver $titlever titlesize $titlesize status $status<br>\n";
			$difflogbuf.= $text;
			echo $text;
		}
	}

	if($updatedtitles==0)return 0;

	$difflogbuf .= "Update content size: $update_size<br>\n";
	$difflogbuf .= "</body></html>";

	$freport = fopen("$workdir/reports$system/$region/$curdatefn", "w");
	fwrite($freport, $difflogbuf);
	fclose($freport);

	return 1;
}

function main($reg)
{
	global $system, $log, $region, $httpstat, $syscmd, $httpbase, $sysupdate_available, $sysupdate_timestamp, $workdir, $curdate, $soap_timestamp, $sysupdate_regions, $arg_difflogold, $arg_difflognew;

	$region = $reg;

	echo "Region $reg System $system\n";

	if($arg_difflogold=="")
	{
		$query="SELECT nushttpsurl FROM ninupdates_consoles WHERE system='".$system."'";
		$result=mysql_query($query);
		$row = mysql_fetch_row($result);
		$nushttpsurl = $row[0];

		initialize();
		for($i=0; $i<5; $i++)
		{
			$ret = send_httprequest("$nushttpsurl/nus/services/NetUpdateSOAP");
			if($httpstat!="0")break;
		}
	}
	else
	{
		init_titlelistarray();
	}

	if($arg_difflogold=="")
	{
		if($httpstat=="200")
		{
			parse_soapresp($ret);
		}
		else
		{
			return;
		}
	}
	
	$soap_timestamp = date(DATE_RFC822);
	if($arg_difflogold=="")
	{
		$text = "SOAP request timestamp: " . $soap_timestamp . "</body></html>";
		fprintf($log, $text);
		fclose($log);
	}

	$curlog = "";
	if($arg_difflognew=="")$curlog = file_get_contents("$workdir/soap$system/$region/current.html");

	$curdate = date("m-d-y_h-i-s");
	if($sysupdate_timestamp!="")$curdate = $sysupdate_timestamp;
	$curdatefn = $curdate . ".html";
	$sendupdatelogs = 1;
	
	if($arg_difflogold!="")
	{
		$logexists = 1;
		if(!file_exists("$workdir/soap$system/$region/$arg_difflogold.html"))
		{
			$logexists = 0;
			echo "Log for $arg_difflogold doesn't exist.\n";
		}
		if(!file_exists("$workdir/soap$system/$region/$arg_difflognew.html"))
		{
			$logexists = 0;
			echo "Log for $arg_difflognew doesn't exist.\n";
		}

		if($logexists==0)
		{
			$sendupdatelogs = 0;
		}
		else
		{
			$logstripped = getlogcontents("$workdir/soap$system/$region/$arg_difflognew.html");
			$oldlogstripped = getlogcontents("$workdir/soap$system/$region/$arg_difflogold.html");
			load_newtitlelist($logstripped);

			if(compare_logs($oldlogstripped, $logstripped, $curdatefn))
			{
				echo "Updated titles found in the logs.\n";
				$sendupdatelogs = 1;
			}
			else
			{
				echo "Logs are identical.\n";
				$sendupdatelogs = 0;
			}
		}
	}
	else
	{
		if(!file_exists("$workdir/soap$system/$region/old.html"))
		{
			$fold = fopen("$workdir/soap$system/$region/old.html", "w");
			fwrite($fold, $curlog);
			fclose($fold);
			$fdatelog = fopen("$workdir/soap$system/$region/$curdatefn", "w");
			fwrite($fdatelog, $curlog);
			fclose($fdatelog);
			$fsoap = fopen("$workdir/soap$system/$region/$curdatefn.soap", "w");
			fwrite($fsoap, $ret);
			fclose($fsoap);
			echo "This is first scan for region $region, unknown if any titles were recently updated.\n";

			$sendupdatelogs = 0;
		}
		else
		{
			$logstripped = getlogcontents("$workdir/soap$system/$region/current.html");
			$oldlogstripped = getlogcontents("$workdir/soap$system/$region/old.html");
			$logstripped.= "</body></html>\n";
			$oldlogstripped.= "</body></html>\n";
		
			if(compare_logs($oldlogstripped, $logstripped, $curdatefn))
			{
				$fold = fopen("$workdir/soap$system/$region/old.html", "w");
				fwrite($fold, $curlog);
				fclose($fold);
				$fdatelog = fopen("$workdir/soap$system/$region/$curdatefn", "w");
				fwrite($fdatelog, $curlog);
				fclose($fdatelog);
				$sendupdatelogs = 1;
				$fsoap = fopen("$workdir/soap$system/$region/$curdatefn.soap", "w");
				fwrite($fsoap, $ret);
				fclose($fsoap);
				echo "Titles were updated since last update scan.\n";
			}
			else
			{
				echo "No titles were updated since last update scan.\n";
				$sendupdatelogs = 0;
			}
		}
	}
	
	if($sendupdatelogs)
	{
		$sysupdate_available = 1;
		if($sysupdate_timestamp=="")$sysupdate_timestamp = $curdate;

		echo "System update available.\n";

		if($sysupdate_regions!="")$sysupdate_regions.= ",";
		$sysupdate_regions.= $region;
	}

}

?>
