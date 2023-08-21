<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");
include_once(dirname(__FILE__) . "/api.php");
include_once(dirname(__FILE__) . "/logs.php");

require_once(dirname(__FILE__) . "/vendor/autoload.php");

include_once(dirname(__FILE__) . "/wikibot_config.php");
/*
The above file must contain the following settings:
$wikibot_user Account username for the wikibot.
$wikibot_pass Account password for the wikibot.
*/

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

	fprintf($f, "%s reportdate=%s: %s\n", gmdate(DATE_ATOM), $reportdate, $str);
	fclose($f);

	return 0;
}

function wikibot_updatenewspages($api, $services, $updateversion, $reportdate, $timestamp, $newspage_text, $newsarchivepage_text, $newspage, $newsarchivepage, $insertstring)
{
	global $wikibot_loggedin, $wikibot_user, $wikibot_pass;

	wikibot_writelog("wikibot_updatenewspages(): insertstring: $insertstring", 1, $reportdate);

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
			$table_entry.= gmdate("F j, Y", $timestamp);
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

function wikibot_edit_navboxversions($api, $services, $updateversion, $reportdate, $pagename, $page_text)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	if(strstr($page_text, $updateversion)!==FALSE)
	{
		wikibot_writelog("This updateversion is already listed on the NavboxVersions page.", 2, $reportdate);
		return 0;
	}

	$tmp_pos = strpos($updateversion, ".");
	if($tmp_pos===FALSE)
	{
		wikibot_writelog("Failed to locate '.' in the updateversion.", 0, $reportdate);
		return 2;
	}

	$major_version = "[[" . substr($updateversion, 0, $tmp_pos+1);

	$new_page = "";
	$new_text = "";

	$version_pos = strpos($page_text, $major_version);
	$posend = 0;
	if($version_pos===FALSE)
	{
		wikibot_writelog("Major version text '$major_version' not found on the NavboxVersions page, adding a new entry...", 2, $reportdate);

		$posend = strpos($page_text, "|}");
		if($posend===FALSE)
		{
			wikibot_writelog("Failed to locate the NavboxVersions table end.", 0, $reportdate);
			return 2;
		}

		$new_text = "| align=\"center\" | [[$updateversion]]\n|-\n";

		if(substr($page_text, $posend-1, 1) !== "\n") $new_text = "\n" . $new_text;
	}
	else
	{
		$posend = strpos($page_text, "|-", $version_pos);
		if($posend===FALSE)
		{
			wikibot_writelog("Failed to locate the NavboxVersions table entry end.", 0, $reportdate);
			return 2;
		}
		if(substr($page_text, $posend-1, 1) === "\n") $posend--;

		$new_text = " • [[$updateversion]]";
	}

	$new_page = substr($page_text, 0, $posend) . $new_text . substr($page_text, $posend);

	wikibot_writelog("Updated NavboxVersions page:\n$new_page", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending NavboxVersions page edit request...", 2, $reportdate);

		$newContent = new \Mediawiki\DataModel\Content($new_page);
		$title = new \Mediawiki\DataModel\Title($pagename);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		$text = "NavboxVersions page edit request was successful.";
		wikibot_writelog($text, 1, $reportdate);
	}

	return 0;
}

function wikibot_generate_titlelist_text(&$titles, &$new_text, $prefix, $print_titleid, $strip_desc)
{
	if(count($titles)>0)
	{
		$new_text.= $prefix;
		$cnt=0;
		foreach($titles as $title)
		{
			$desc = $title["titleid"];
			if($title["description"]!="" && $title["description"]!="N/A")
			{
				$desc = $title["description"];
				if($strip_desc)
				{
					$tmp_pos = strpos($desc, "-sysmodule");
					if($tmp_pos!==FALSE)
					{
						$desc = substr($desc, 0, $tmp_pos);
					}
					else
					{
						$start_pos = strpos($desc, '"');
						$end_pos = strpos($desc, '" applet');
						if($start_pos!==FALSE && $end_pos!==FALSE)
						{
							$desc = substr($desc, $start_pos+1, $end_pos-$start_pos-1);
						}
					}
				}
				if($print_titleid) $desc.= " (".$title["titleid"].")";
			}
			if($cnt>0)$new_text.= ", ";
			$new_text.= "$desc";
			$cnt++;
		}
		$new_text.= ".";
	}
}

function wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $rebootless_flag, $updateversion_norebootless, $system_generation)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	$page_text = "";

	$page_exists = False;
	$page_updated = False;

	$out_titlestatus_new = array();
	$out_titlestatus_changed = array();
	$ignore_titles = array();

	if($system_generation!=0) // Ignore the SystemUpdate/sysver titles.
	{
		$ignore_titles[] = "0100000000000816";
		$ignore_titles[] = "0100000000000809";
		$ignore_titles[] = "0100000000000826";
	}

	$report_titlelist = report_get_titlelist($system, $reportdate, out_titlestatus_new: $out_titlestatus_new, out_titlestatus_changed: $out_titlestatus_changed, ignore_titles: $ignore_titles);

	if($rebootless_flag===False)
	{
		$navbox_pagename = "Template:NavboxVersions";

		$tmp_page = $services->newPageGetter()->getFromTitle($navbox_pagename);
		$navbox_revision = $tmp_page->getRevisions()->getLatest();

		if($navbox_revision!==NULL)
		{
			wikibot_writelog("Updating NavboxVersions if needed...", 2, $reportdate);
			$navbox_text = $navbox_revision->getContent()->getData();
			wikibot_edit_navboxversions($api, $services, $updateversion_norebootless, $reportdate, $navbox_pagename, $navbox_text);
		}
	}

	$titlelist_text = "";
	if($system_generation!=0 && $rebootless_flag===False)
	{
		$out_titlestatus_changed_other = array();
		$out_titlestatus_changed_sysmodules = array();
		$out_titlestatus_changed_systemdata = array();
		$out_titlestatus_changed_applets = array();

		foreach($out_titlestatus_changed as &$title)
		{
			if(substr($title["titleid"], 0, 14)==="01000000000000")
			{
				$out_titlestatus_changed_sysmodules[] = $title;
			}
			else if(substr($title["titleid"], 0, 14)==="01000000000008")
			{
				$out_titlestatus_changed_systemdata[] = $title;
			}
			else if(substr($title["titleid"], 0, 13)==="0100000000001")
			{
				$out_titlestatus_changed_applets[] = $title;
			}
			else
			{
				$out_titlestatus_changed_other[] = $title;
			}
		}
		if(count($out_titlestatus_new)>0)
		{
			wikibot_generate_titlelist_text($out_titlestatus_new, $titlelist_text, "* The following new titles were added: ", True, False);
			$titlelist_text.= "\n";
		}
		if(count($out_titlestatus_changed)>0)
		{
			$titlelist_text.= "* The following titles were updated:\n";
			if(count($out_titlestatus_changed_sysmodules)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_sysmodules, $titlelist_text, "** Sysmodules: ", False, True);
				$titlelist_text.= "\n";
			}
			if(count($out_titlestatus_changed_systemdata)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_systemdata, $titlelist_text, "** SystemData (non-sysver): ", False, True);
				$titlelist_text.= "\n";
			}
			if(count($out_titlestatus_changed_applets)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_applets, $titlelist_text, "** Applets: ", False, True);
				$titlelist_text.= "\n";
			}
			if(count($out_titlestatus_changed_other)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_other, $titlelist_text, "** ", False, True);
				$titlelist_text.= "\n";
			}
		}
	}

	$tmp_page = $services->newPageGetter()->getFromTitle($updateversion_norebootless);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision!==NULL)
	{
		wikibot_writelog("Sysupdate page already exists, updating the page if needed...", 2, $reportdate);
		$page_exists = True;
		$page_text = $tmp_revision->getContent()->getData();
	}

	if(!$page_exists)
	{
		wikibot_writelog("Sysupdate page doesn't exist, generating a page...", 2, $reportdate);

		if($rebootless_flag)
		{
			wikibot_writelog("Sysupdate page doesn't exist but this report is rebootless, skipping page creation.", 0, $reportdate);
			return 0;
		}
	}

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.id, ninupdates_consoles.sysname, ninupdates_consoles.system, ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updateversion='".$updateversion."' && ninupdates_reports.systemid=ninupdates_consoles.id && wikibot_runfinished=0";
	$result_systems=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result_systems);

	if($numrows==0)
	{
		wikibot_writelog("wikibot_edit_updatepage(): Failed to load the report info where wikibot_runfinished=0.", 0, $reportdate);
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

		$searchtext = "/reports.php?date=$curreportdate&sys=$cursystem]";
		$text = "[$sitecfg_httpbase$searchtext";
		if(!$page_exists || strpos($page_text, $searchtext)===FALSE)
		{
			$reportlinks_list.= "* $text\n";
		}

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

	$timestamp_str = gmdate("F j, Y", $timestamp);
	$changelog_hdr = "==Change-log==";

	if(!$page_exists)
	{
		$page_updated = True;

		$page_text.= "The $sysnames_list ".$updateversion_norebootless." system update was released on ".$timestamp_str." (UTC). $regions_list\n\n";
		$page_text.= "Security flaws fixed: <fill this in manually later, see the updatedetails page from the ninupdates-report page(s) once available for now>.\n\n";

		$page_text.= $changelog_hdr."\n";
		$page_text.= "$changelog_text\n";

		$page_text.= "==System Titles==\n";
		if($titlelist_text==="")
		{
			$page_text.= "<fill this in (manually) later>\n";
		}
		else
		{
			$page_text.= $titlelist_text;
		}
		$page_text.= "\n";

		$page_text.= "==See Also==\n";
		$page_text.= "System update report(s):\n";
		$page_text.= $reportlinks_list;
		$page_text.= "\n";

		if($navbox_revision!==NULL)
		{
			$page_text.= "\n{{NavboxVersions}}\n\n";
			$page_text.= "[[Category:System versions]]\n";
		}
	}
	else
	{
		if($rebootless_flag===True)
		{
			if(strpos($page_text, $timestamp_str)===FALSE)
			{
				$insert_pos = strpos($page_text, $changelog_hdr);
				if($insert_pos===FALSE)
				{
					wikibot_writelog("wikibot_edit_updatepage(): Failed to locate the changelog_hdr, skipping adding the rebootless text.", 0, $reportdate);
				}
				else
				{
					$new_text = "Additionally, a rebootless $sysnames_list system update was released for ".$updateversion_norebootless." on ".$timestamp_str." (UTC). $regions_list";

					if(count($report_titlelist)>0)
					{
						wikibot_generate_titlelist_text($out_titlestatus_new, $new_text, " The following new titles were added: ", True, False);
						wikibot_generate_titlelist_text($out_titlestatus_changed, $new_text, " The following (non-sysver) titles were updated: ", False, False);
					}

					$new_text.= "\n\n";

					$page_updated = True;
					$page_text = substr($page_text, 0, $insert_pos) . $new_text . substr($page_text, $insert_pos);
				}
			}
		}

		if(strlen($reportlinks_list)>0)
		{
			$insert_pos = strpos($page_text, "System update report(s)");
			if($insert_pos!==FALSE)
			{
				$tmp_pos = $insert_pos;
				while($tmp_pos!==FALSE)
				{
					$tmp_pos = strpos($page_text, "\n", $tmp_pos);
					if($tmp_pos!==FALSE && $page_text[$tmp_pos+1]=="\n")
					{
						$tmp_pos++;
						break;
					}
					if($tmp_pos!==FALSE)
					{
						$tmp_pos++;
					}
				}
				if($tmp_pos!==FALSE)
				{
					$new_text = $reportlinks_list;

					$insert_pos = $tmp_pos;
					$page_text = substr($page_text, 0, $insert_pos) . $new_text . substr($page_text, $insert_pos);

					$page_updated = True;
				}
			}
		}
	}

	if($page_updated)
	{
		wikibot_writelog("New sysupdate page:\n$page_text", 1, $reportdate);
	}
	else
	{
		wikibot_writelog("Sysupdate page edit isn't needed, skipping sending the edit request.", 2, $reportdate);
	}

	if($wikibot_loggedin == 1 && $page_updated)
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
		$text = "Sending notif...";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);

		$msgtext = "created";
		$msgtextnew = "new";
		if($page_exists==1)
		{
			$msgtext = "updated";
			$msgtextnew = "";
		}

		$wiki_uribase = "wiki/";
		if($apiprefixuri == "")$wiki_uribase = "index.php?title=";

		$notif_msg = "The wiki page for the $msgtextnew $sysnames_list $updateversion sysupdate has been $msgtext: $serverbaseurl$wiki_uribase$updateversion";
		send_notif([$notif_msg, "--social"]);
	}

	return 0;
}

function wikibot_edit_firmwarenews($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	$page_text = "";

	$page_exists = 1;

	$pagetitle = "FirmwareNews";

	$tmp_page = $services->newPageGetter()->getFromTitle($pagetitle);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision===NULL)
	{
		wikibot_writelog("FirmwareNews page doesn't exist.", 2, $reportdate);

		$page_exists = 0;

		return 0;
	}

	wikibot_writelog("FirmwareNews page exists, generating new page text if needed...", 2, $reportdate);

	$page_text = $tmp_revision->getContent()->getData();

	$strstart = strstr($page_text, "'''");
	if($strstart===FALSE)
	{
		wikibot_writelog("wikibot_edit_firmwarenews(): Failed to find the curupdatetext start.", 0, $reportdate);
		return 1;
	}

	$strendpos = strpos($strstart, "'''", 3);
	if($strendpos===FALSE)
	{
		wikibot_writelog("wikibot_edit_firmwarenews(): Failed to find the curupdatetext end.", 0, $reportdate);
		return 2;
	}

	$curupdatetext = substr($strstart, 3, $strendpos-3);
	$reportupdatetext = "[[$updateversion]]";

	wikibot_writelog("curupdatetext: $curupdatetext, reportupdatetext: $reportupdatetext", 2, $reportdate);

	if($curupdatetext === $reportupdatetext)
	{
		wikibot_writelog("curupdatetext and reportupdatetext already match.", 2, $reportdate);
		return 0;
	}

	wikibot_writelog("curupdatetext and reportupdatetext don't match, generating new page-text...", 2, $reportdate);

	$new_page_text = str_replace("'''$curupdatetext'''", "'''$reportupdatetext'''", $page_text);

	wikibot_writelog("New FirmwareNews page:\n$new_page_text", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending FirmwareNews page edit request...", 2, $reportdate);

		$newContent = new \Mediawiki\DataModel\Content($new_page_text);
		$title = new \Mediawiki\DataModel\Title($pagetitle);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		$text = "FirmwareNews page edit request was successful.";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);
	}

	return 0;
}

function wikibot_edit_pagetable($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $pagetitle, $table_search, $searchtext, $entrydata)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_workdir;

	$page_text = "";

	$tmp_page = $services->newPageGetter()->getFromTitle($pagetitle);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision===NULL)
	{
		wikibot_writelog("$pagetitle page doesn't exist.", 2, $reportdate);

		return 0;
	}

	wikibot_writelog("$pagetitle page exists, generating new page text if needed...", 2, $reportdate);

	$page_text = $tmp_revision->getContent()->getData();

	$section_pos = strpos($page_text, $table_search);
	if($section_pos===FALSE)
	{
		wikibot_writelog("wikibot_edit_pagetable($pagetitle): Failed to find the section/table start.", 0, $reportdate);
		return 1;
	}

	$table_endpos = strpos($page_text, "|}", $section_pos);
	if($table_endpos===FALSE)
	{
		wikibot_writelog("wikibot_edit_pagetable($pagetitle): Failed to find the table end.", 0, $reportdate);
		return 2;
	}

	$table_text = substr($page_text, $section_pos, $table_endpos-$section_pos);
	if(strpos($table_text, "$searchtext")!==FALSE)
	{
		wikibot_writelog("wikibot_edit_pagetable($pagetitle): This entry already exists in the table.", 2, $reportdate);
		return 0;
	}

	$new_text = "";
	if(substr($page_text, $table_endpos-3, 3)!="|-\n")
	{
		$new_text.= "|-\n";
	}

	$lines = explode("\n", $table_text);
	$found = False;
	for($i=count($lines)-1; $i>=0; $i--)
	{
		if($i == count($lines)-1 && substr($lines[$i], 0, 2)==="|-")
		{
			continue;
		}
		if(substr($lines[$i], 0, 2)==="|-")
		{
			$linei = $i+1;
			$found = True;
			break;
		}
	}

	if($found === False)
	{
		wikibot_writelog("wikibot_edit_pagetable($pagetitle): Failed to find the last table entry.", 0, $reportdate);
		return 2;
	}

	$i=0;
	foreach($entrydata as $linetext)
	{
		if($linetext === "!LAST")
		{
			$new_text.= $lines[$linei+$i] . "\n";
		}
		else
		{
			$new_text.= "| $linetext\n";
		}
		$i++;
	}

	$new_page_text = substr($page_text, 0, $table_endpos) . $new_text . substr($page_text, $table_endpos);

	wikibot_writelog("New $pagetitle page:\n$new_page_text", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending $pagetitle page edit request...", 2, $reportdate);

		$newContent = new \Mediawiki\DataModel\Content($new_page_text);
		$title = new \Mediawiki\DataModel\Title($pagetitle);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		$text = "$pagetitle page edit request was successful.";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);
	}

	return 0;
}

function wikibot_edit_systemversiondata($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri)
{
	global $system, $sitecfg_workdir;

	$pagetitle = "System_Version_Title";

	$sysver_fullversionstr = "";
	$sysver_hexstr = "";
	$sysver_digest = "N/A";

	$updatedir = "$sitecfg_workdir/sysupdatedl/autodl_sysupdates/$reportdate-$system";

	$sysver_fullversionstr_path = "$updatedir/sysver_fullversionstr";
	$sysver_hexstr_path = "$updatedir/sysver_hexstr";
	$sysver_digest_path = "$updatedir/sysver_digest";

	if(file_exists($sysver_fullversionstr_path)===FALSE || file_exists($sysver_hexstr_path)===FALSE)
	{
		wikibot_writelog("wikibot_edit_systemversiondata(): The sysver files doesn't exist.", 0, $reportdate);
		return 3;
	}

	if(file_exists($sysver_digest_path)===FALSE)
	{
		wikibot_writelog("wikibot_edit_systemversiondata(): The sysver digest file doesn't exist, using 'N/A' for the digest instead.", 2, $reportdate);
	}
	else
	{
		$sysver_digest = file_get_contents($sysver_digest_path);
	}

	$sysver_fullversionstr = file_get_contents($sysver_fullversionstr_path);
	$sysver_hexstr = file_get_contents($sysver_hexstr_path);

	if($sysver_digest===FALSE || $sysver_fullversionstr===FALSE || $sysver_hexstr===FALSE)
	{
		wikibot_writelog("wikibot_edit_systemversiondata(): Failed to load the sysver files.", 0, $reportdate);
		return 3;
	}

	$entrydata = array($updateversion, $sysver_fullversionstr, $sysver_hexstr, $sysver_digest, "");

	return wikibot_edit_pagetable($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $pagetitle, "== Retail ==", "| $updateversion\n", $entrydata);
}

function wikibot_edit_systemversions($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri)
{
	global $mysqldb, $system, $sitecfg_workdir, $ninupdatesapi_out_total_entries, $ninupdatesapi_out_version_array, $ninupdatesapi_out_reportdate_array;

	$pagetitle = "System_Versions";

	$build_date = "";
	$sdk_versions = "";

	$updatedir = "$sitecfg_workdir/sysupdatedl/autodl_sysupdates/$reportdate-$system";

	$sdk_versions_path = "$updatedir/sdk_versions.info";

	if(file_exists($sdk_versions_path)===FALSE)
	{
		wikibot_writelog("wikibot_edit_systemversions(): The sdk_versions file doesn't exist.", 0, $reportdate);
		return 3;
	}

	$sdk_versions = file_get_contents($sdk_versions_path);

	if($sdk_versions===FALSE)
	{
		wikibot_writelog("wikibot_edit_systemversions(): Failed to load the sdk_versions file.", 0, $reportdate);
		return 3;
	}

	$sdk_versions = str_replace(" (.0)", "", $sdk_versions);
	if($sdk_versions[strlen($sdk_versions)-1]=="\n") $sdk_versions = substr($sdk_versions, 0, strlen($sdk_versions)-1);

	$lines = explode("\n", $sdk_versions);
	$first_line = $lines[0];
	$last_line = $lines[count($lines)-1];

	if($last_line == $first_line)
	{
		$sdk_versions = $first_line;
	}
	else
	{
		$sdk_versions = "$first_line-$last_line";
	}

	$titleid = "0100000000000819";

	$query="SELECT ninupdates_reports.regions FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		wikibot_writelog("wikibot_edit_systemversions(): Failed to get regions field from the report row.", 0, $reportdate);
		return 1;
	}

	$row = mysqli_fetch_row($result);
	$regions = $row[0];

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
			wikibot_writelog("wikibot_edit_systemversions(): Failed to load the regionid.", 0, $reportdate);
			return 2;
		}

		$row = mysqli_fetch_row($result);
		$regionid = $row[0];

		$retval = ninupdates_api("gettitleversions", $system, $region, $titleid, 0);
		$region = $region_next;
		if($retval!=0)
		{
			wikibot_writelog("wikibot_edit_systemversions(): API returned error $retval.", 0, $reportdate);
			return($retval);
		}

		$found = False;
		for($i=0; $i<$ninupdatesapi_out_total_entries; $i++)
		{
			$version = $ninupdatesapi_out_version_array[$i];
			$entry_reportdate = $ninupdatesapi_out_reportdate_array[$i];

			if($reportdate === $entry_reportdate)
			{
				$found = True;
				break;
			}
		}
		if($found===False) continue;

		$titledir = "$updatedir/$titleid/$regionid/$version";
		if(is_dir($titledir)===FALSE)
		{
			$titledir = "$updatedir/$titleid/$version";
		}

		if(is_dir($titledir)===FALSE)
		{
			wikibot_writelog("wikibot_edit_systemversions(): The titledir doesn't exist: $titledir", 0, $reportdate);
			return 5;
		}
		else
		{
			$path = "$titledir/nx_package1_hactool.info";
			if(file_exists($path)===FALSE)
			{
				wikibot_writelog("wikibot_edit_systemversions(): The nx_package1_hactool file doesn't exist.", 0, $reportdate);
				return 5;
			}
			else
			{
				$hactoolinfo = file_get_contents($path);
				if($hactoolinfo===FALSE)
				{
					wikibot_writelog("wikibot_edit_systemversions(): Failed to load the nx_package1_hactool file.", 0, $reportdate);
					return 5;
				}
				else
				{
					if(strlen($hactoolinfo)==0)
					{
						wikibot_writelog("wikibot_edit_systemversions(): The nx_package1_hactool file is empty.", 0, $reportdate);
						return 5;
					}
					else
					{
						$lines = explode("\n", $hactoolinfo);
						foreach($lines as $line)
						{
							if(strpos($line, "Build Date")!==FALSE)
							{
								$str = substr($line, strrpos($line, " ")+1);
								$pos=0;
								$poslen = 4;
								for($i=0; $i<6; $i++)
								{
									$val = substr($str, $pos, $poslen);
									$pos+= $poslen;
									$poslen = 2;
									$build_date.= $val;

									if($i<2)
									{
										$build_date.= "-";
									}
									else if($i==2)
									{
										$build_date.= " ";
									}
									else if($i<5)
									{
										$build_date.= ":";
									}
								}
								break;
							}
						}
					}
				}
			}
		}
		if($build_date!=="") break;
	}

	if($build_date==="")
	{
		$build_date = "!LAST";
		wikibot_writelog("wikibot_edit_systemversions(): This report doesn't include bootpkg, loading build_date from the last wiki entry.", 2, $reportdate);
	}

	$entrydata = array("[[$updateversion]]", gmdate("F j, Y", $timestamp)." (UTC)", $build_date, $sdk_versions);

	return wikibot_edit_pagetable($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $pagetitle, "{| ", "| [[$updateversion]]\n", $entrydata);
}

function wikibot_edit_fuses($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_workdir;

	$pagetitle = "Fuses";

	$table_search = "= Anti-downgrade =";

	$page_text = "";

	$updatedetails = "";
	$retail_fuses = "";
	$dev_fuses = "";

	$updatedir = "$sitecfg_workdir/sysupdatedl/autodl_sysupdates/$reportdate-$system";

	$updatedir_path = "$updatedir/updatedetails";

	if(file_exists($updatedir_path)===FALSE)
	{
		wikibot_writelog("wikibot_edit_fuses(): The updatedetails file doesn't exist.", 0, $reportdate);
		return 3;
	}

	$updatedetails = file_get_contents($updatedir_path);

	if($updatedetails===FALSE)
	{
		wikibot_writelog("wikibot_edit_fuses(): Failed to load the updatedetails file.", 0, $reportdate);
		return 3;
	}

	if(strpos($updatedetails, "BootImagePackage")!==FALSE)
	{
		$searchstr = "Total retail blown fuses: ";
		$tmp = strstr($updatedetails, $searchstr);
		if($tmp!==FALSE)
		{
			$line_end = strpos($tmp, "\n");
			$tmp = substr($tmp, strlen($searchstr), $line_end-strlen($searchstr));
			$retail_fuses = $tmp;
		}

		$searchstr = "Total devunit blown fuses: ";
		$tmp = strstr($updatedetails, $searchstr);
		if($tmp!==FALSE)
		{
			$line_end = strpos($tmp, "\n");
			$tmp = substr($tmp, strlen($searchstr), $line_end-strlen($searchstr));
			$dev_fuses = $tmp;
		}

		if($retail_fuses==="" || $dev_fuses==="")
		{
			wikibot_writelog("wikibot_edit_fuses(): Failed to load the fuse values from the updatedetails file.", 0, $reportdate);
			return 4;
		}
	}

	$tmp_page = $services->newPageGetter()->getFromTitle($pagetitle);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision===NULL)
	{
		wikibot_writelog("$pagetitle page doesn't exist.", 2, $reportdate);

		return 0;
	}

	wikibot_writelog("$pagetitle page exists, generating new page text if needed...", 2, $reportdate);

	$page_text = $tmp_revision->getContent()->getData();

	$section_pos = strpos($page_text, $table_search);
	if($section_pos===FALSE)
	{
		wikibot_writelog("wikibot_edit_fuses($pagetitle): Failed to find the section/table start.", 0, $reportdate);
		return 1;
	}

	$table_endpos = strpos($page_text, "|}", $section_pos);
	if($table_endpos===FALSE)
	{
		wikibot_writelog("wikibot_edit_fuses($pagetitle): Failed to find the table end.", 0, $reportdate);
		return 2;
	}

	$table_text = substr($page_text, $section_pos, $table_endpos-$section_pos);
	if(strpos($table_text, $updateversion)!==FALSE)
	{
		wikibot_writelog("wikibot_edit_fuses($pagetitle): This entry already exists in the table.", 2, $reportdate);
		return 0;
	}

	$new_text = "";
	if(substr($page_text, $table_endpos-3, 3)!="|-\n")
	{
		$new_text.= "|-\n";
	}

	$lines = explode("\n", $table_text);
	$found = False;
	for($i=count($lines)-1; $i>=0; $i--)
	{
		if($i == count($lines)-1 && substr($lines[$i], 0, 2)==="|-")
		{
			continue;
		}
		if(substr($lines[$i], 0, 2)==="|-")
		{
			$linei = $i+1;
			$found = True;
			break;
		}
	}

	if($found === False)
	{
		wikibot_writelog("wikibot_edit_fuses($pagetitle): Failed to find the last table entry.", 0, $reportdate);
		return 2;
	}

	$last_retail_fuses = substr($lines[$linei+1], 2);
	$last_dev_fuses = substr($lines[$linei+2], 2);

	if(($retail_fuses!=="" && $dev_fuses!=="") && ($retail_fuses!==$last_retail_fuses || $dev_fuses!==$last_dev_fuses))
	{
		$new_text.= "| $updateversion\n";
		$new_text.= "| $retail_fuses\n";
		$new_text.= "| $dev_fuses\n";

		$new_page_text = substr($page_text, 0, $table_endpos) . $new_text . substr($page_text, $table_endpos);
	}
	else
	{
		$new_text = "";
		for($i=0; $i<count($lines); $i++)
		{
			$linetext = $lines[$i];
			if($i==count($lines)-1 && strlen($linetext)==0) continue;
			if($linei == $i)
			{
				$tmp = strpos($linetext, "-");
				if($tmp===FALSE)
				{
					$linetext = "| $updateversion";
				}
				else
				{
					$linetext = substr($linetext, 0, $tmp+1) . "$updateversion";
				}
			}
			$new_text.= $linetext . "\n";
		}
		$new_page_text = substr($page_text, 0, $section_pos) . $new_text . substr($page_text, $table_endpos);
	}

	wikibot_writelog("New $pagetitle page:\n$new_page_text", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending $pagetitle page edit request...", 2, $reportdate);

		$newContent = new \Mediawiki\DataModel\Content($new_page_text);
		$title = new \Mediawiki\DataModel\Title($pagetitle);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		$text = "$pagetitle page edit request was successful.";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);
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

	$query="SELECT ninupdates_reports.reportdaterfc, ninupdates_consoles.generation, ninupdates_reports.postproc_runfinished FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.reportdate='".$reportdate."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		wikibot_writelog("Failed to find the report with reportdate $reportdate.", 0, $reportdate);
		return 12;
	}

	$row = mysqli_fetch_row($result);
	$timestamp = date_timestamp_get(date_create_from_format(DateTimeInterface::RFC822, $row[0]));
	$system_generation = $row[1];
	$postproc_runfinished = $row[2];

	if(!isset($wiki_homemenutitle))$wiki_homemenutitle = "";

	$updateversion_norebootless = $updateversion;
	$rebootless_flag = False;
	$rebootless_pos = strpos($updateversion, "_rebootless");
	if($rebootless_pos!==FALSE)
	{
		wikibot_writelog("This report updateversion is rebootless, skipping handling for the relevant pages.", 2, $reportdate);

		$rebootless_flag = True;
		$updateversion_norebootless = substr($updateversion, 0, $rebootless_pos);
	}

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

	$sysupdate_date = gmdate("j F y", $timestamp);

	$news_searchtext = "update [[$updateversion]]";
	$insertstring = "*'''$sysupdate_date''' Nintendo released system ".$news_searchtext.".";

	if($rebootless_flag===True)
	{
		$insertstring = "*'''$sysupdate_date''' Nintendo released a rebootless system update for [[".$updateversion_norebootless."]].";
		$news_searchtext = $insertstring;
	}
	if(strstr($newspage_text, $news_searchtext)!==FALSE)
	{
		$updatelisted = 1;
	}
	else if(strstr($newsarchivepage_text, $news_searchtext)!==FALSE)
	{
		$updatelisted = 2;
	}

	if($updatelisted)
	{
			wikibot_writelog("This updatever is already listed on the wiki news.", 2, $reportdate);
	}
	else
	{
		$ret = wikibot_updatenewspages($api, $services, $updateversion, $reportdate, $timestamp, $newspage_text, $newsarchivepage_text, $newspage, $newsarchivepage, $insertstring."\n");
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

	/*if($sysupdate_page!==FALSE)*/wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $rebootless_flag, $updateversion_norebootless, $system_generation);

	$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);

		$tmp_reportdate = $row[0];

		if($tmp_reportdate === $reportdate)
		{
			if($rebootless_flag===False)
			{
				$ret = wikibot_edit_firmwarenews($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);
				if($ret!=0)return $ret;
			}

			if($system_generation!=0)
			{
				if($postproc_runfinished!=0 && $rebootless_flag===False)
				{
					$ret = wikibot_edit_systemversiondata($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);
					if($ret!=0)return $ret;

					wikibot_edit_systemversions($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);
					if($ret!=0)return $ret;

					wikibot_edit_fuses($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);
					if($ret!=0)return $ret;
				}
				else
				{
					wikibot_writelog("Skipping generation1 page handling since the report post-processing isn't finished / updateversion is rebootless.", 2, $reportdate);
				}
			}
		}
		else
		{
			wikibot_writelog("Skipping FirmwareNews and generation1 page handling since this report isn't the latest one.", 2, $reportdate);
		}
	}

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

$lock_fp = fopen("$sitecfg_workdir/wikibot_lock", "w");
if($lock_fp===FALSE)
{
	echo "Failed to open lock file.\n";
	exit(1);
}

if(!flock($lock_fp, LOCK_EX))
{
	echo "Failed to obtain the lock.\n";
	exit(1);
}

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

		$ret = runwikibot_newsysupdate($updateversion, $reportdate);
		if($ret!=0)
		{
			echo "Sending notif since an error occured...\n";
			$msg = "wikibot: An error occured while processing $reportdate-$system.";
			send_notif([$msg, "--webhook", "--webhooktarget=1"]);
		}

		echo "Wikibot processing for this report finished.\n";

		if($i != $numrows-1)echo "\n";
	}
}

fclose($lock_fp);

dbconnection_end();

?>
