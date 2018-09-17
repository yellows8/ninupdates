<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

//require_once(dirname(__FILE__) . "/Wikimate/globals.php");

require_once(dirname(__FILE__) . "/vendor/autoload.php");

include_once(dirname(__FILE__) . "/wikibot_config.php");
/*
The above file must contain the following settings:
$wikibot_user Account username for the wikibot.
$wikibot_pass Account password for the wikibot.
*/

require_once(dirname(__FILE__) . "/tweet.php");

function wikibot_writelog($str, $type, $reportdate)
{
	global $sitecfg_workdir;

	if($type==0)echo "Writing the following to the wikibot_error.log: $str\n";
	if($type==2)echo "$str\n";

	$path = "";
	if($type==0)$path = "$sitecfg_workdir/debuglogs/wikibot_error.log";
	if($type==1 || $type==2)$path = "$sitecfg_workdir/debuglogs/wikibot_status.log";

	$f = fopen($path, "a");
	if($f===FALSE)
	{
		echo "Failed to open: $path.\n";
		return 1;
	}

	fprintf($f, "%s reportdate=%s: %s\n", date("m-d-y_h-i-s"), $reportdate, $str);
	fclose($f);

	return 0;
}

function wikibot_updatenewspages($api, $services, $updateversion, $reportdate, $timestamp, $newspage_text, $newsarchivepage_text, $newspage, $newsarchivepage)
{
	global $wikibot_loggedin, $wikibot_user, $wikibot_pass;

	$sysupdate_date = date("j F y", $timestamp);

	$insertstring = "*'''$sysupdate_date''' Nintendo released system update [[$updateversion]].\n";

	wikibot_writelog("insertstring: $insertstring", 1, $reportdate);

	$locatestr = "</noinclude>\n";

	$startpos_news = strpos($newspage_text, $locatestr);
	if($startpos_news===FALSE)
	{
		wikibot_writelog("Failed to locate the first news entry on the wiki news page.", 0, $reportdate);
		return 2;
	}

	$startpos_news+= strlen($locatestr);

	$newline_needed = 1;

	$archiveline_pos = strlen($newspage_text)-1;//Locate the last line in the news-page.
	if($newspage_text[$archiveline_pos]=="\n")
	{
		$newline_needed = 0;
		$pos--;
	}

	while($archiveline_pos>0)
	{
		if($newspage_text[$archiveline_pos]=="\n")
		{
			$archiveline_pos++;
			break;
		}

		$archiveline_pos--;
	}

	if($archiveline_pos==0)
	{
		wikibot_writelog("Failed to locate the last line in the news-page.", 0, $reportdate);
		return 3;
	}

	$newspage_new = substr($newspage_text, 0, $startpos_news) . $insertstring . substr($newspage_text, $startpos_news, $archiveline_pos-$startpos_news);

	$archiveline = substr($newspage_text, $archiveline_pos, strlen($newspage_text)-$archiveline_pos);//Extract the last line from the news-page.
	if($newline_needed)$archiveline.= "\n";

	$newsarchivepage_new = $archiveline . $newsarchivepage_text;

	wikibot_writelog("New news-page: $newspage_new", 1, $reportdate);
	wikibot_writelog("New news-archive-page: $newsarchivepage_new", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending news pages edit requests...", 2, $reportdate);

		$content = new \Mediawiki\DataModel\Content($newspage_new);
		$revision = new \Mediawiki\DataModel\Revision($content, $newspage->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);

		/*if($newspage->setText($newspage_new)===FALSE)
		{
			wikibot_writelog("The http request for editing the news-page failed.", 0, $reportdate);
			return 4;
		}*/

		$content = new \Mediawiki\DataModel\Content($newsarchivepage_new);
		$revision = new \Mediawiki\DataModel\Revision($content, $newsarchivepage->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);

		/*if($newsarchivepage->setText($newsarchivepage_new)===FALSE)
		{
			wikibot_writelog("The http request for editing the news-archive-page failed.", 0, $reportdate);
			return 4;
		}*/

		wikibot_writelog("The news pages edit requests were successful.", 2, $reportdate);
	}

	return 0;
}

function wikibot_updatepage_homemenu($api, $services, $updateversion, $reportdate, $timestamp, $page, $page_text)
{
	global $wikibot_loggedin;

	if(strstr($page_text, $updateversion)!==FALSE)
	{
		wikibot_writelog("This updateversion is already listed on the home-menu page.", 2, $reportdate);
		return 0;
	}

	//echo "Updatever not found on home-menu page, this should not happen.\n";
	//return 0;

	echo "Home Menu page:\n$page_text\n";

	echo "Updating home-menu page...\n";

	$str = "=== System Versions List ===\n{| class=\"wikitable\"\n|-\n";

	$pos = strpos($page_text, $str);
	if($pos===FALSE)
	{
		wikibot_writelog("Failed to locate the system-versions table.", 0, $reportdate);
		return 2;
	}
	$pos+= strlen($str);

	$tableposend = strpos($page_text, "|}", $pos);
	if($tableposend===FALSE)
	{
		wikibot_writelog("Failed to locate the system-versions table end.", 0, $reportdate);
		return 2;
	}

	$tabletext = substr($page_text, $pos, ($tableposend+2)-$pos);
	$tablelen = ($tableposend+2)-$pos;

	$table_entry = "|-\n";
	$pos = 0;
	$line_end = 0;

	$posend = strpos($tabletext, "|-\n");
	if($posend===FALSE)
	{
		wikibot_writelog("Failed to locate the system-versions table-headers end.", 0, $reportdate);
		return 2;
	}

	while($pos < $posend)
	{
		if(substr($tabletext, $pos, 2) !== "! ")
		{
			wikibot_writelog("Invalid system-version table-header line.", 0, $reportdate);
			return 2;
		}

		$pos+= 2;
		$line_end = strpos($tabletext, "\n", $pos);

		if($line_end===FALSE)
		{
			wikibot_writelog("Invalid system-version table-header line: missing newline.", 0, $reportdate);
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

	$new_page = substr($page_text, 0, $tableposend) . $table_entry . substr($page_text, $tableposend, strlen($page_text) - $tableposend);

	wikibot_writelog("New entry added to the Home Menu sysupdates table:\n$table_entry", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending home-menu page edit request...", 2, $reportdate);

		$content = new \Mediawiki\DataModel\Content($new_page);
		$revision = new \Mediawiki\DataModel\Revision($content, $page->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);

		/*if($page->setText($new_page)===FALSE)
		{
			wikibot_writelog("The http request for editing the Home Menu page failed.", 0, $reportdate);
			return 4;
		}*/

		wikibot_writelog("The home-menu page edit request was successful.", 2, $reportdate);
	}

	echo "Finished updating the Home Menu page.\n";

	return 0;
}

function wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	$page_text = "";

	$page_exists = 0;

	$tmp_page = $services->newPageGetter()->getFromTitle($updateversion);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision!==NULL)
	{
		wikibot_writelog("Sysupdate page already exists, skipping editing.", 2, $reportdate);

		$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikipage_exists=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);

		$page_exists = 1;

		return 0;
	}

	wikibot_writelog("Sysupdate page doesn't exist, generating a page...", 2, $reportdate);

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.id, ninupdates_consoles.sysname, ninupdates_consoles.system, ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updateversion='".$updateversion."' && ninupdates_reports.systemid=ninupdates_consoles.id && wikibot_runfinished=0";
	$result_systems=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result_systems);

	if($numrows==0)
	{
		wikibot_writelog("wikibot_edit_updatepage(): Failed to get regions field from the report row.", 0, $reportdate);
		return 1;
	}

	$regions_list = "";
	$changelog_count = 0;

	$changelog_text = "";

	$sysnames_list = "";
	$total_systems = $numrows;

	$reportlinks_list = "";

	for($system_index=0; $system_index<$total_systems; $system_index++)
	{
		$row = mysqli_fetch_row($result_systems);
		$regions = $row[0];
		$reportid = $row[1];
		$cursysname = $row[2];
		$cursystem = $row[3];
		$curreportdate = $row[4];

		if($system_index>0)$sysnames_list.= "+";
		$sysnames_list.= $cursysname;

		if($system_index>0)$regions_list.=" ";
		$regions_list.= "This $cursysname update was released for the following regions: ";

		$reportlinks_list.= "* [$sitecfg_httpbase/reports.php?date=$curreportdate&sys=$cursystem]\n";

		$regions_count = 0;
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
				wikibot_writelog("wikibot_edit_updatepage(): Failed to load the regionid.", 0, $reportdate);
				return 2;
			}

			$row = mysqli_fetch_row($result);
			$regionid = $row[0];

			if($regions_count==0)
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

			if($system_index==0)//Normally when there's multiple systems' reports with the same updateversion, the changelog is loaded from the same page anyway.
			{
				$query="SELECT ninupdates_officialchangelog_pages.url, ninupdates_officialchangelog_pages.id FROM ninupdates_officialchangelog_pages, ninupdates_consoles, ninupdates_regions WHERE ninupdates_consoles.system='".$cursystem."' && ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id && ninupdates_officialchangelog_pages.regionid=ninupdates_regions.id && ninupdates_regions.regioncode='".$region."'";
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
			}

			$regions_count++;
			$region = $region_next;
		}

		$regions_list.= ".";
	}

	$page_text.= "The $sysnames_list $updateversion system update was released on ".date("F j, Y", $timestamp).". $regions_list\n\n";
	$page_text.= "Security flaws fixed: <fill this in manually later, see the updatedetails page from the ninupdates-report page(s) once available for now>.\n\n";

	$page_text.= "==Change-log==\n";
	$page_text.= "$changelog_text\n";

	$page_text.= "==System Titles==\n";
	$page_text.= "<fill this in (manually) later>\n";
	$page_text.= "\n";

	$page_text.= "==See Also==\n";
	$page_text.= "System update report(s):\n";
	$page_text.= $reportlinks_list;
	$page_text.= "\n";

	wikibot_writelog("New sysupdate page:\n$page_text", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending sysupdate page edit request...", 2, $reportdate);

		/*$content = new \Mediawiki\DataModel\Content($page_text);
		$revision = new \Mediawiki\DataModel\Revision($content, $page->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);*/

		$newContent = new \Mediawiki\DataModel\Content($page_text);
		$title = new \Mediawiki\DataModel\Title($updateversion);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		/*if($page->setText($page_text)===FALSE)
		{
			wikibot_writelog("The http request for editing the sysupdate page failed.", 0, $reportdate);
			return 4;
		}*/
		
		$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikipage_exists=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);		

		$text = "Sysupdate page edit request was successful.";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);
		$text = "Sending tweet...";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);

		$msgtext = "created";
		if($page_exists==1)$msgtext = "updated";

		$wiki_uribase = "wiki/";
		if($apiprefixuri == "")$wiki_uribase = "index.php?title=";

		sendtweet("The wiki page for the new $sysnames_list $updateversion sysupdate has been $msgtext: $serverbaseurl$wiki_uribase$updateversion");
	}

	return 0;
}

function runwikibot_newsysupdate($updateversion, $reportdate)
{
	global $mysqldb, $wikibot_loggedin, $wikibot_user, $wikibot_pass, $system;

	$wikibot_loggedin = 0;

	$query="SELECT ninupdates_wikiconfig.serverbaseurl, ninupdates_wikiconfig.apiprefixuri, ninupdates_wikiconfig.news_pagetitle, ninupdates_wikiconfig.newsarchive_pagetitle, ninupdates_wikiconfig.homemenu_pagetitle FROM ninupdates_wikiconfig, ninupdates_consoles WHERE ninupdates_wikiconfig.wikibot_enabled=1 && ninupdates_wikiconfig.id=ninupdates_consoles.wikicfgid && ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		echo "Wiki config is not available for this system($system), or wikibot processing is disabled for this wiki, skipping wikibot processing for it.\n";

		$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikibot_runfinished=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);

		return 0;
	}

	$row = mysqli_fetch_row($result);
	$serverbaseurl = $row[0];
	$apiprefixuri = $row[1];
	$wiki_newspagetitle = $row[2];
	$wiki_newsarchivepagetitle = $row[3];
	$wiki_homemenutitle = $row[4];

	$wiki_apibaseurl = $serverbaseurl . $apiprefixuri;

	$month = (int)substr($reportdate, 0*3, 2);
	$day = (int)substr($reportdate, 1*3, 2);
	$year = (int)substr($reportdate, 2*3, 2);

	$timestamp = mktime(0, 0, 0, $month, $day, $year);

	if(!isset($wiki_homemenutitle))$wiki_homemenutitle = "";

	//try
	//{
		$api = new \Mediawiki\Api\MediawikiApi("$wiki_apibaseurl/api.php");

		//$api = new Wikimate($wiki_apibaseurl . "/api.php");

		if(!isset($wikibot_user) || !isset($wikibot_pass))
		{
			wikibot_writelog("Wikibot account config isn't setup, skipping login(edited pages will not be uploaded).", 0, $reportdate);
		}
		else
		{
			//if($api->login($wikibot_user, $wikibot_pass)===TRUE)
			if($api->login(new \Mediawiki\Api\ApiUser($wikibot_user, $wikibot_pass))===TRUE)
			{
				echo "Successfully logged into the wiki.\n";
				wikibot_writelog("Successfully logged into the wiki.", 1, $reportdate);
				$wikibot_loggedin = 1;
			}
			else
			{
				//$error = $api->getError();
				wikibot_writelog("Wiki login failed.", 0, $reportdate);
				return 7;
			}
		}

		$services = new \Mediawiki\Api\MediawikiFactory($api);
	//}

	/*catch(UsageException $e)
	{
		echo "Exception caught.\n";
		wikibot_writelog("Wiki api error: " . $e->getMessage(), 0, $reportdate);
		return 7;
	}*/

	if($wikibot_loggedin == 1)
	{
		$text = "Remote page editing is enabled.";
	}
	else
	{
		$text = "Remote page editing is disabled.";
	}

	wikibot_writelog($text, 2, $reportdate);

	$newspage = $services->newPageGetter()->getFromTitle($wiki_newspagetitle);
	$revision = $newspage->getRevisions()->getLatest();
	$newspage_text = $revision->getContent()->getData();

	$newsarchivepage = $services->newPageGetter()->getFromTitle($wiki_newsarchivepagetitle);
	$revision = $newsarchivepage->getRevisions()->getLatest();
	$newsarchivepage_text = $revision->getContent()->getData();

	$updatelisted = 0;
	if(strstr($newspage_text, $updateversion)!==FALSE)
	{
		$updatelisted = 1;
	}
	else if(strstr($newsarchivepage_text, $updateversion)!==FALSE)
	{
		$updatelisted = 2;
	}

	if($updatelisted)
	{
		wikibot_writelog("This updatever is already listed on the wiki news.", 2, $reportdate);
	}
	else
	{
		$ret = wikibot_updatenewspages($api, $services, $updateversion, $reportdate, $timestamp, $newspage_text, $newsarchivepage_text, $newspage, $newsarchivepage);
		if($ret!=0)return $ret;
	}

	if($wiki_homemenutitle!="")
	{
		$page = $services->newPageGetter()->getFromTitle($wiki_homemenutitle);
		$revision = $page->getRevisions()->getLatest();
		$homemenu_page_text = $revision->getContent()->getData();
		/*if($homemenu_page===FALSE || $homemenu_page->exists()==FALSE)
		{
			wikibot_writelog("Failed to get the home-menu page.", 0, $reportdate);
		}
		else
		{*/
			$ret = wikibot_updatepage_homemenu($api, $services, $updateversion, $reportdate, $timestamp, $page, $homemenu_page_text);
			if($ret!=0)return $ret;
		//}
	}

	/*$page = $services->newPageGetter()->getFromTitle($updateversion);
	$revision = $page->getRevisions()->getLatest();
	$sysupdate_page = $revision->getContent()->getData();*/
	$page=0;

	/*if($sysupdate_page===FALSE || $sysupdate_page->exists()==FALSE)
	{
		wikibot_writelog("The sysupdate page doesn't exist.", 1, $reportdate);
	}
	else
	{*/
		//wikibot_writelog("Sysupdate page:\n".$sysupdate_page, 1, $reportdate);
	//}

	/*if($sysupdate_page!==FALSE)*/wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);

	echo "Updating the report's wikibot_runfinished field...\n";
	$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikibot_runfinished=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
	$result=mysqli_query($mysqldb, $query);

	if($wikibot_loggedin == 1)$api->logout();

	echo "Wikibot run finished.\n";

	return 0;
}

$wikibot_scheduledtask = 0;

if($argc<3)
{
	if($argc != 2 || $argv[1]!=="scheduled")
	{
		echo "Usage:\nphp wikibot.php <updateversion> <reportdate> <system>\n";
		return 0;
	}
	else
	{
		$wikibot_scheduledtask = 1;
	}
}

dbconnection_start();

if($wikibot_scheduledtask == 0)
{
	$updateversion = mysqli_real_escape_string($mysqldb, $argv[1]);
	$reportdate = mysqli_real_escape_string($mysqldb, $argv[2]);
	$system = mysqli_real_escape_string($mysqldb, $argv[3]);

	runwikibot_newsysupdate($updateversion, $reportdate);
}
else
{
	$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE updatever_autoset=1 && wikibot_runfinished=0 && ninupdates_reports.systemid=ninupdates_consoles.id";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	echo "Doing a scheduled wikibot run for $numrows reports...\n";

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysqli_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];

		echo "Starting wikibot processing with the following report: $reportdate-$system, updateversion=$updateversion.";
		echo "\n";

		runwikibot_newsysupdate($updateversion, $reportdate);

		echo "Wikibot processing for this report finished.\n";

		if($i != $numrows-1)echo "\n";
	}
}

dbconnection_end();

?>
