<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

do_systems_soap();

function do_systems_soap()
{
	dbconnection_start();
	if(!db_checkmaintenance(0))
	{
		init_curl();

		$query="SELECT system FROM ninupdates_consoles";
		$result=mysql_query($query);
		$numrows=mysql_num_rows($result);

		for($i=0; $i<$numrows; $i++)
		{
			$row = mysql_fetch_row($result);
				dosystem($row[0]);
		}

		$query="UPDATE ninupdates_management SET lastscan=now()";
		$result=mysql_query($query);

		close_curl();

		dbconnection_end();
	}
}

function dosystem($console)
{
	global $region, $system, $sitecfg_emailhost, $sitecfg_target_email, $sitecfg_httpbase, $sysupdate_available, $soap_timestamp, $dbcurdate, $sysupdate_regions, $sysupdate_timestamp, $sysupdate_systitlehashes;

	$system = $console;
	$msgme_message = "";
	$html_message = "";
	$sysupdate_available = 0;
	$sysupdate_timestamp = "";
	$soap_timestamp = "";
	$sysupdate_regions = "";
	$dbcurdate = "";
	$sysupdate_systitlehashes = array();

	echo "System $system\n";

	$query="SELECT regions FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);
	$row = mysql_fetch_row($result);
	$regions = $row[0];

	for($i=0; $i<strlen($regions); $i++)
	{
		main(substr($regions, $i, 1));
	}

	if($sysupdate_available==0)
	{
		echo "System $system: No updated titles available.\n";
	}
	else
	{
		$msgme_message = "$sitecfg_httpbase/reports.php?date=".$sysupdate_timestamp."&sys=".$system;
		$email_message = "$msgme_message";
		
		$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE  ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
		$result=mysql_query($query);
		$numrows=mysql_num_rows($result);

		$initialscan = 0;
		if($numrows==0)$initialscan = 1;

		$updateversion = "N/A";
		if($initialscan)$updateversion = "Initial scan";

		$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$sysupdate_timestamp."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_reports.log='report'";
		$result=mysql_query($query);
		$numrows=mysql_num_rows($result);
		if($numrows==0)
		{
			$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
			$result=mysql_query($query);
			$row = mysql_fetch_row($result);
			$systemid = $row[0];

			$query = "INSERT INTO ninupdates_reports (reportdate, curdate, systemid, log, regions, updateversion, reportdaterfc, initialscan) VALUES ('".$sysupdate_timestamp."','".$dbcurdate."',$systemid,'report','".$sysupdate_regions."','".$updateversion."','".$soap_timestamp."',$initialscan)";
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

			$query="UPDATE ninupdates_titles SET reportid=$reportid WHERE curdate='".$dbcurdate."'";
			$result=mysql_query($query);
		}
		else
		{
			//this will only happen when the report row already exists and the --difflogs option was used.
			$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.regions='".$sysupdate_regions."' WHERE reportdate='".$sysupdate_timestamp."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id && log='report'";
			$result=mysql_query($query);
		}

		if($initialscan==0)echo "System $system: System update available for regions $sysupdate_regions.\n";
		if($initialscan)echo "System $system: Initial scan successful for regions $sysupdate_regions.\n";

		echo "\nSending IRC msg...\n";
		sendircmsg($msgme_message);
		echo "Sending email...\n";
        	if(!mail($sitecfg_target_email, "$system SOAP updates", $email_message, "From: ninsoap@$sitecfg_emailhost"))echo "Failed to send mail.\n";
	}
}

function initialize()
{
	global $hdrs, $soapreq, $fp, $system, $region, $sitecfg_workdir, $soapreq_data;
	
	error_reporting(E_ALL);

	$regionid = "";
	$countrycode = "";

	$query="SELECT deviceid, platformid FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);

	if($numrows)
	{
		$row = mysql_fetch_row($result);
		$deviceid = $row[0];
		$platformid = $row[1];

		if($platformid != NULL && $platformid != "")
		{
			$deviceid = ($platformid << 32) | rand(0, 0xffffffff);
		}
	}
	else
	{
		die("Row doesn't exist in the db for system $system.");
	}

	$query="SELECT regionid, countrycode FROM ninupdates_regions WHERE regioncode='".$region."'";
	$result=mysql_query($query);
	$row = mysql_fetch_row($result);

	if($numrows)
	{
		$regionid = $row[0];
		$countrycode = $row[1];
	}
	else
	{
		die("Row doesn't exist in the db for region $region.");
	}

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
}

function init_curl()
{
	global $curl_handle, $sitecfg_workdir, $error_FH;

	$error_FH = fopen("$sitecfg_workdir/debuglogs/error.log","w");
	$curl_handle = curl_init();
}

function close_curl()
{
	global $curl_handle, $error_FH;

	curl_close($curl_handle);
	fclose($error_FH);
}

function send_httprequest($url)
{
	global $hdrs, $soapreq, $httpstat, $sitecfg_workdir, $soapreq_data, $curl_handle, $system, $error_FH;

	$query="SELECT clientcertfn, clientprivfn FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);
	$row = mysql_fetch_row($result);
	$clientcertfn = $row[0];
	$clientprivfn = $row[1];

	curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle, CURLOPT_STDERR, $error_FH );

	curl_setopt($curl_handle, CURLOPT_USERAGENT, "ds libnup/2.0");

	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $hdrs);

	curl_setopt($curl_handle, CURLOPT_POST, 1);
	curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $soapreq_data);

	curl_setopt($curl_handle, CURLOPT_URL, $url);

	if(strstr($url, "https") && $clientcertfn!="" && $clientprivfn!="")
	{
		curl_setopt($curl_handle, CURLOPT_SSLCERTTYPE, "PEM");
		curl_setopt($curl_handle, CURLOPT_SSLCERT, "$sitecfg_workdir/sslcerts/$clientcertfn");
		curl_setopt($curl_handle, CURLOPT_SSLKEY, "$sitecfg_workdir/sslcerts/$clientprivfn");

		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl_handle, CURLOPT_SSLVERSION, 3);
	}

	$buf = curl_exec($curl_handle);

	$httpstat = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$buf = "HTTP request failed.<br>\n";
		$httpstat = "0";
	} else if($httpstat!="200")$buf = "HTTP error $httpstat<br>\n";

	return $buf;
}

function parse_soapresp($buf)
{
	global $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $system, $region, $sysupdate_systitlehashes;
	$title = $buf;
	$titleid_pos = 0;
	$titlever_pos = 0;
	$titlesize_pos = 0;
	$titleid = "";
	$titlever = "";
	$titlesize = 0;
	$titlesizetik = "";
	$titlesizetmd = "";
	$logbuf="";

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

		if($titlesize_posend===FALSE)
		{
			$titlesize_pos = strpos($title, "<RawSize>") + 9;
			$titlesize_posend = strpos($title, "</RawSize>");
		}

		if($titleid_pos!==FALSE)$titleid = substr($title, $titleid_pos, 16);
		if($titlever_posend!==FALSE)$titlever = substr($title, $titlever_pos, $titlever_posend - $titlever_pos);
		if($titlesize_posend!==FALSE)$titlesize = substr($title, $titlesize_pos, $titlesize_posend - $titlesize_pos);
		if($titlesizetik_posend!==FALSE)$titlesizetik = substr($title, $titlesizetik_pos, $titlesizetik_posend - $titlesizetik_pos);
		if($titlesizetmd_posend!==FALSE)$titlesizetmd = substr($title, $titlesizetmd_pos, $titlesizetmd_posend - $titlesizetmd_pos);

		if($titlever_posend!==FALSE)$titlever = intval($titlever);
		if($titlesize_posend!==FALSE)$titlesize = intval($titlesize);

		if($titleid_pos===FALSE)$titleid="tagsmissing";
		if($titlever_pos===FALSE || $titlever_posend===FALSE)$titlever = 0;
		if($titlesize_pos===FALSE || $titlesize_posend===FALSE)$titlesize = 0;
		if($titlesizetik_pos===FALSE || $titlesizetik_posend===FALSE)$titlesizetik = 0;
		if($titlesizetmd_pos===FALSE || $titlesizetmd_posend===FALSE)$titlesizetmd = 0;

		$newtitles[] = $titleid;
		$newtitlesversions[] = $titlever;
		$newtitles_sizes[] = $titlesize;
		$newtitles_tiksizes[] = $titlesizetik;
		$newtitles_tmdsizes[] = $titlesizetmd;

		$newtotal_titles++;

		$title = strstr($title, "</TitleVersion>");
	}

	$sysupdate_systitlehashes[$region] = "";

	$titlehash_pos = strpos($buf, "<TitleHash>") + 11;
	$titlehash_posend = strpos($buf, "</TitleHash>");
	if($titlehash_pos!==FALSE && $titlehash_posend!==FALSE)
	{
		$titlehash = substr($buf, $titlehash_pos, $titlehash_posend - $titlehash_pos);
		$sysupdate_systitlehashes[$region] = $titlehash;
	}
}

function compare_titlelists()
{
	global $system, $difflogbuf, $region, $sysupdate_systitlehashes;

	$query="SELECT ninupdates_systitlehashes.titlehash FROM ninupdates_reports, ninupdates_consoles, ninupdates_systitlehashes WHERE ninupdates_systitlehashes.reportid=ninupdates_reports.id && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' && ninupdates_systitlehashes.region='".$region."' && ninupdates_reports.log='report' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);

	if($numrows==0)
	{
		echo "System $system Region $region: Titlehash is missing.\n";
		return titlelist_dbupdate();
	}
	else
	{
		$row = mysql_fetch_row($result);
		$titlehashold = $row[0];

		if($sysupdate_systitlehashes[$region]!=$titlehashold)
		{
			return titlelist_dbupdate();
		}
		else
		{
			return 0;
		}
	}

	return 0;
}

function main($reg)
{
	global $system, $log, $region, $httpstat, $syscmd, $sitecfg_httpbase, $sysupdate_available, $sysupdate_timestamp, $sitecfg_workdir, $curdate, $dbcurdate, $soap_timestamp, $sysupdate_regions, $arg_difflogold, $arg_difflognew, $newtotal_titles;

	$region = $reg;

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

	if($httpstat=="200")
	{
		parse_soapresp($ret);
	}
	else
	{
		echo "HTTP error " . $httpstat . ".\n";
		return;
	}
	
	$curtime = time();
	$soap_timestamp = date(DATE_RFC822, $curtime);

	$curdate = date("m-d-y_h-i-s", $curtime);
	if($sysupdate_timestamp!="")$curdate = $sysupdate_timestamp;
	$curdatefn = $curdate . ".html";

	if($dbcurdate=="")
	{	
		$query = "SELECT now()";
		$result=mysql_query($query);
		$row = mysql_fetch_row($result);
		$dbcurdate = $row[0];
	}

	$sendupdatelogs = 0;

	$query = "SELECT ninupdates_titles.version FROM ninupdates_titles, ninupdates_consoles WHERE ninupdates_titles.region='".$region."' && ninupdates_titles.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."'";
	$result=mysql_query($query);
	$numrows=mysql_num_rows($result);

	if($numrows==0 && $newtotal_titles>0)
	{
		titlelist_dbupdate();

		$fsoap = fopen("$sitecfg_workdir/soap$system/$region/$curdatefn.soap", "w");
		fwrite($fsoap, $ret);
		fclose($fsoap);

		echo "System $system: This is first scan for region $region, unknown if any titles were recently updated.\n";

		$sendupdatelogs = 1;
	}
	else
	{
		if(compare_titlelists())
		{
			$sendupdatelogs = 1;

			$fsoap = fopen("$sitecfg_workdir/soap$system/$region/$curdatefn.soap", "w");
			fwrite($fsoap, $ret);
			fclose($fsoap);
		}
		else
		{
			$sendupdatelogs = 0;
		}
	}
	
	if($sendupdatelogs)
	{
		$sysupdate_available = 1;
		if($sysupdate_timestamp=="")$sysupdate_timestamp = $curdate;

		if($sysupdate_regions!="")$sysupdate_regions.= ",";
		$sysupdate_regions.= $region;
	}

}

?>
