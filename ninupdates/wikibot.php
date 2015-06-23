<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

//This is currently experimental: atm this must be run from the cmd-line.

$settings['serverAuth'] = "";//Workaround for the below include, since it checks for this without using isset().

include_once("MediaWiki_Api/MediaWiki_Api_functions.php");//Probably not the final API which will be used by this wikibot.

function wikibot_updatenewspages($api, $updateversion, $reportdate, $timestamp, $newspage, $newsarchivepage)
{
	global $wikibot_loggedin, $wikibot_user, $wikibot_pass;

	$sysupdate_date = date("j F y", $timestamp);

	$insertstring = "*'''$sysupdate_date''' Nintendo released system update [[$updateversion]].\n";

	echo "insertstring: $insertstring";

	$locatestr = "</noinclude>\n";

	$startpos_news = strpos($newspage, $locatestr);
	if($startpos_news===FALSE)
	{
		echo "Failed to locate the first news entry on the wiki news page.\n";
		return 2;
	}

	$startpos_news+= strlen($locatestr);

	$newline_needed = 1;

	$archiveline_pos = strlen($newspage)-1;//Locate the last line in the news-page.
	if($newspage[$archiveline_pos]=="\n")
	{
		$newline_needed = 0;
		$pos--;
	}

	while($archiveline_pos>0)
	{
		if($newspage[$archiveline_pos]=="\n")
		{
			$archiveline_pos++;
			break;
		}

		$archiveline_pos--;
	}

	if($archiveline_pos==0)
	{
		echo "Failed to locate the last line in the news-page.\n";
		return 3;
	}

	$newspage_new = substr($newspage, 0, $startpos_news) . $insertstring . substr($newspage, $startpos_news, $archiveline_pos-$startpos_news);

	$archiveline = substr($newspage, $archiveline_pos, strlen($newspage)-$archiveline_pos);//Extract the last line from the news-page.
	if($newline_needed)$archiveline.= "\n";

	$newsarchivepage_new = $archiveline . $newsarchivepage;

	echo "New news-page: $newspage_new\n";
	echo "New news-archive-page: $newsarchivepage_new\n";

	/*
	if($wikibot_loggedin == 0)
	{
		$api->login($wikibot_user, $wikibot_pass);//This will just execute die() if it fails, so checking the retval here is pointless.
		$wikibot_loggedin = 1;
	}

	if(editPage($wiki_newspagetitle, $newspage_new, false, false, false)===null)
	{
		echo "The http request for editing the news-page failed.\n";
		return 4;
	}

	if(editPage($wiki_newsarchivepagetitle, $newsarchivepage_new, false, false, false)===null)
	{
		echo "The http request for editing the news-page failed.\n";
		return 4;
	}*/

	return 0;
}

function wikibot_updatepage_homemenu($api, $updateversion, $reportdate, $timestamp, $page, $wiki_homemenutitle)
{
	global $wikibot_loggedin, $wikibot_user, $wikibot_pass;

	if(strstr($page, $updateversion)!==FALSE)
	{
		echo "This updateversion is already listed on the home-menu page.\n";
		return 0;
	}

	echo "Updating home-menu page...\n";

	$str = "=== System Versions List ===\n{| class=\"wikitable\"\n|-\n";

	$pos = strpos($page, $str);
	if($pos===FALSE)
	{
		echo "Failed to locate the system-versions table.\n";
		return 2;
	}
	$pos+= strlen($str);

	$tableposend = strpos($page, "|}", $pos);
	if($tableposend===FALSE)
	{
		echo "Failed to locate the system-versions table end.\n";
		return 2;
	}

	$tabletext = substr($page, $pos, ($tableposend+2)-$pos);
	$tablelen = ($tableposend+2)-$pos;

	$table_entry = "|-\n";
	$pos = 0;
	$line_end = 0;

	$posend = strpos($tabletext, "|-\n");
	if($posend===FALSE)
	{
		echo "Failed to locate the system-versions table-headers end.\n";
		return 2;
	}

	while($pos < $posend)
	{
		if(substr($tabletext, $pos, 2) !== "! ")
		{
			echo "Invalid system-version table-header line.\n";
			return 2;
		}

		$pos+= 2;
		$line_end = strpos($tabletext, "\n", $pos);

		if($line_end===FALSE)
		{
			echo "Invalid system-version table-header line: missing newline.\n";
			return 2;
		}

		$line = substr($tabletext, $pos, $line_end-$pos);
		$pos = $line_end+1;

		$table_entry.= "| ";

		$cmpstr = "System version";

		if(substr($line, 0, strlen($cmpstr)) === $cmpstr)
		{
			$table_entry.= "[[$updateversion|$updateversion]]";
		}
		else if(strpos($line, "Date")!==FALSE || strpos($line, "date")!==FALSE)
		{
			$table_entry.= date("F j, Y", $timestamp);
		}
		else if($line === "CDN Availability")
		{
			$table_entry.= "Available";
		}
		else if($line === "CUP Released")
		{
			$table_entry.= "No";
		}
		else if(strpos($line, "Changelog")!==FALSE)
		{
			$table_entry.= "See [[$updateversion|this]].";
		}

		$table_entry.= "\n";
	}

	$new_page = substr($page, 0, $tableposend) . $table_entry . substr($page, $tableposend, strlen($page) - $tableposend);

	echo "New entry added to the Home Menu sysupdates table:\n$table_entry\n";

	/*
	if($wikibot_loggedin == 0)
	{
		$api->login($wikibot_user, $wikibot_pass);//This will just execute die() if it fails, so checking the retval here is pointless.
		$wikibot_loggedin = 1;
	}

	if(editPage($wiki_homemenutitle, $new_page, false, false, false)===null)
	{
		echo "The http request for editing the Home Menu page failed.\n";
		return 4;
	}*/

	return 0;
}

function wikibot_edit_updatepage($api, $updateversion, $reportdate, $timestamp, $page_text)
{
	global $mysqldb, $system;

	$page_exists = 1;
	if($page_text===FALSE || $page_text=="")
	{
		$page_exists = 0;
		$page_text = "";
	}

	if($page_exists)return 0;

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.id FROM ninupdates_reports, ninupdates_consoles WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		echo "wikibot_edit_updatepage(): Failed to get regions field from the report row.\n";
		return 1;
	}

	$row = mysqli_fetch_row($result);
	$regions = $row[0];
	$reportid = $row[1];

	$regions_list = "";
	$changelog_count = 0;

	$changelog_text = "";

	$region = strtok($regions, ",");
	while($region!==FALSE)
	{
		if(strlen($region)>1)break;

		$region_next = strtok(",");

		$query="SELECT regionid FROM ninupdates_regions WHERE regioncode='".$region."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows==0)
		{
			echo "wikibot_edit_updatepage(): Failed to load the regionid.\n";
			return 2;
		}

		$row = mysqli_fetch_row($result);
		$regionid = $row[0];

		if(strlen($regions_list)==0)
		{
			$regions_list.= $regionid;
		}
		else
		{
			if($region_next!==FALSE)
			{
				$regions_list.= ", " . $regionid;
			}
			else
			{
				$regions_list.= ", and " . $regionid;
			}
		}

		$query="SELECT ninupdates_officialchangelog_pages.url, ninupdates_officialchangelog_pages.id FROM ninupdates_officialchangelog_pages, ninupdates_consoles, ninupdates_regions WHERE ninupdates_consoles.system='".$system."' && ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id && ninupdates_officialchangelog_pages.regionid=ninupdates_regions.id && ninupdates_regions.regioncode='".$region."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		if($numrows>0)
		{
			$changelog_count++;

			$row = mysqli_fetch_row($result);
			$pageurl = $row[0];
			$pageid = $row[1];

			$wiki_text = "";

			$query = "SELECT wiki_text FROM ninupdates_officialchangelogs WHERE pageid=$pageid && reportid=$reportid";
			$result=mysqli_query($mysqldb, $query);
			$numrows=mysqli_num_rows($result);
			if($numrows>0)
			{
				$row = mysqli_fetch_row($result);
				$wiki_text = $row[0];
			}

			if($wiki_text===FALSE)$wiki_text = "";
			if($wiki_text=="")$wiki_text = "* N/A, see the official page for the actual changelog.\n";

			$changelog_text.= "[$pageurl Official] $regionid change-log:\n$wiki_text";
		}

		$region = $region_next;
	}

	$page_text.= "The ".getsystem_sysname($system)." $updateversion system update was released on ".date("F j, Y", $timestamp)." for the following regions: $regions_list.\n\n";
	$page_text.= "Security flaws fixed: <fill this in manually later>.\n\n";

	$page_text.= "==Change-log==\n";
	$page_text.= "$changelog_text\n";

	$page_text.= "==System Titles==\n";
	$page_text.= "\n";

	$page_text.= "==See Also==\n";
	$page_text.= "System update reports:\n";
	$page_text.= "* [http://yls8.mtheall.com/ninupdates/reports.php?date=$reportdate&sys=$system]\n";
	$page_text.= "\n";

	echo "New sysupdate page:\n$page_text\n";

	return 0;
}

function runwikibot_newsysupdate($updateversion, $reportdate)
{
	global $wikibot_user, $wikibot_pass, $wikibot_loggedin;

	//All of these hard-coded config values including the password one are temporary, later these will be moved into proper config elsewhere.
	$wiki_apibaseurl = "http://3dbrew.org/w";
	$wiki_newspagetitle = "News";
	$wiki_newsarchivepagetitle = "News/Archive";
	$wiki_homemenutitle = "Home_Menu";

	$wikibot_user = "Yls8bot";//Not a real account/password at the time of writing.
	$wikibot_pass = "Yls8bot_pass";

	$wikibot_loggedin = 0;

	$month = (int)substr($reportdate, 0*3, 2);
	$day = (int)substr($reportdate, 1*3, 2);
	$year = (int)substr($reportdate, 2*3, 2);

	$timestamp = mktime(0, 0, 0, $month, $day, $year, -1);

	if(!isset($wiki_homemenutitle))$wiki_homemenutitle = "";

	$api = new MediaWikiApi($wiki_apibaseurl);

	$newspage = $api->readPage($wiki_newspagetitle);
	$newsarchivepage = $api->readPage($wiki_newsarchivepagetitle);

	if($newspage === FALSE || $newspage=="")
	{
		echo "Failed to get the news wiki page.";
		return 1;
	}

	if($newsarchivepage === FALSE || $newsarchivepage=="")
	{
		echo "Failed to get the news-archive wiki page.";
		return 1;
	}

	echo "News page:\n$newspage\n";
	echo "News archive page:\n$newsarchivepage\n";

	$updatelisted = 0;
	if(strstr($newspage, $updateversion)!==FALSE)
	{
		$updatelisted = 1;
	}
	else if(strstr($newsarchivepage, $updateversion)!==FALSE)
	{
		$updatelisted = 2;
	}

	echo "updatelisted: $updatelisted\n";

	if($updatelisted)
	{
		echo "This updatever is already listed on the wiki.\n";
	}
	else
	{
		wikibot_updatenewspages($api, $updateversion, $reportdate, $timestamp, $newspage, $newsarchivepage);
	}

	if($wiki_homemenutitle!="")$homemenu_page = $api->readPage($wiki_homemenutitle);

	$sysupdate_page = $api->readPage($updateversion);

	if($homemenu_page===FALSE || $homemenu_page=="")
	{
		echo "Failed to get the home-menu page.\n";
	}
	else
	{
		wikibot_updatepage_homemenu($api, $updateversion, $reportdate, $timestamp, $homemenu_page, $wiki_homemenutitle);
	}

	if($sysupdate_page===FALSE || $sysupdate_page=="")
	{
		echo "The sysupdate page doesn't exist.\n";
	}
	else
	{
		echo "Sysupdate page: \n$sysupdate_page\n";
	}

	wikibot_edit_updatepage($api, $updateversion, $reportdate, $timestamp, $sysupdate_page);

	return 0;
}

if($argc<3)
{
	echo "Usage:\nphp wikibot.php <updateversion> <reportdate> <system>\n";
	return 0;
}

dbconnection_start();

$updateversion = mysqli_real_escape_string($mysqldb, $argv[1]);
$reportdate = mysqli_real_escape_string($mysqldb, $argv[2]);
$system = mysqli_real_escape_string($mysqldb, $argv[3]);

runwikibot_newsysupdate($updateversion, $reportdate);

dbconnection_end();

?>
