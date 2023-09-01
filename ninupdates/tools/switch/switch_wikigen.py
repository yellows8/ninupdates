#!/usr/bin/python3
import sys
import os
import argparse
import json
import subprocess
from os.path import exists
from datetime import datetime, date, time, timezone

parser = argparse.ArgumentParser(description='Generate wiki data.')
parser.add_argument('updatedir', help='Updatedir path.')
parser.add_argument('reportdate', help='reportdate')
parser.add_argument('system', help='system')
parser.add_argument('updatever', help='updatever')
parser.add_argument('outpath', help='Output file path.')

args = parser.parse_args()

updatedir = args.updatedir
reportdate = args.reportdate
insystem = args.system
updatever = args.updatever
outpath = args.outpath

if updatever.find('_rebootless')!=-1:
    print("This updatever is rebootless, aborting.")
    sys.exit(0)

updatedetails = "%s/updatedetails" % (updatedir)
if os.path.exists(updatedetails):
    with open(updatedetails, 'r') as updatef:
        updatedetails = updatef.readlines()
else:
    updatedetails = None
    print("Updatedetails file doesn't exist, skipping pages which require it.")

storage = []

titleinfo = {
    '0100000000000800': { # CertStore
        'wikipage': 'SSL_services#CertStore',
    },
    '0100000000000801': { # ErrorMessage
        'ignore': True,
    },
    '0100000000000806': { # NgWord
        'ignore': True,
    },
    '0100000000000809': { # SystemVersion
        'wikipage': 'System_Version_Title',
    },
    '010000000000080E': { # TimeZoneBinary
        'ignore': True,
    },
    '0100000000000818': { # FirmwareDebugSettings
        'wikipage': 'System_Settings',
    },
    '010000000000081A': { # BootImagePackageSafe
        'group': '0100000000000819',
    },
    '010000000000081B': { # BootImagePackageExFat
        'group': '0100000000000819',
    },
    '010000000000081C': { # BootImagePackageExFatSafe
        'group': '0100000000000819',
    },
    '010000000000081F': { # PlatformConfigIcosa
        'wikipage': 'System_Settings',
        'group': '0100000000000818',
    },
    '0100000000000820': { # PlatformConfigCopper
        'wikipage': 'System_Settings',
        'group': '0100000000000818',
    },
    '0100000000000821': { # PlatformConfigHoag
        'wikipage': 'System_Settings',
        'group': '0100000000000818',
    },
    '0100000000000823': { # NgWord2
        'ignore': True,
    },
    '0100000000000824': { # PlatformConfigIcosaMariko
        'wikipage': 'System_Settings',
        'group': '0100000000000818',
    },
    '0100000000000829': { # PlatformConfigCalcio
        'wikipage': 'System_Settings',
        'group': '0100000000000818',
    },
    '0100000000000831': { # PlatformConfigAula
        'wikipage': 'System_Settings',
        'group': '0100000000000818',
    },
    '010000000000100A': { # LibAppletWeb
        'wikipage': 'Internet_Browser',
    },
    '010000000000100B': { # LibAppletShop
        'wikipage': 'Internet_Browser',
        'group': '010000000000100A',
    },
    '010000000000100F': { # LibAppletOff
        'wikipage': 'Internet_Browser',
        'group': '010000000000100A',
    },
    '0100000000001010': { # LibAppletLns
        'wikipage': 'Internet_Browser',
        'group': '010000000000100A',
    },
    '0100000000001011': { # LibAppletAuth
        'wikipage': 'Internet_Browser',
        'group': '010000000000100A',
    },
}

dirfilter_msgs = {
    '/message/': 'Various data',
    '/lyt/': 'Various data',
    '/nro/netfront/': 'Various data',
}

def get_titledesc(titleid):
    proc = subprocess.run(["php", "/home/yellows8/ninupdates/manage_titledesc.php", titleid], capture_output=True, encoding='utf8')
    if proc.returncode!=0:
        print("manage_titledesc failed, stderr: %s" % (proc.stderr))
        return "N/A"
    else:
        return proc.stdout

sysver_fullversionstr_path = "%s/sysver_fullversionstr" % (updatedir)
sysver_hexstr_path = "%s/sysver_hexstr" % (updatedir)
sysver_digest_path = "%s/sysver_digest" % (updatedir)
if os.path.exists(sysver_fullversionstr_path) and os.path.exists(sysver_hexstr_path):
    with open(sysver_fullversionstr_path, 'r') as tmpf:
        sysver_fullversionstr = tmpf.read()
    with open(sysver_hexstr_path, 'r') as tmpf:
        sysver_hexstr = tmpf.read()

    if os.path.exists(sysver_digest_path):
        with open(sysver_digest_path, 'r') as tmpf:
            sysver_digest = tmpf.read()
    else:
        sysver_digest = "N/A"
        print("The sysver digest file doesn't exist, using 'N/A' for the digest instead.")

    page = {
        "page_title": "System_Version_Title",
        "search_section": "== Retail ==",
        "targets": [
            {
                "search_section": "{|",
                "insert_row_tables": [
                    {
                        "search_text": updatever,
                        "columns": [
                            updatever,
                            sysver_fullversionstr,
                            sysver_hexstr,
                            sysver_digest,
                            ""
                        ],
                    },
                ],
            },
        ],
    }
    storage.append(page)

else:
    print("The sysver files don't exist, skipping page handling for that.")

info_path = "%s/sdk_versions.info" % (updatedir)
if os.path.exists(info_path):
    with open(info_path, 'r') as infof:
        info_lines = infof.readlines()

    first_line = None
    for line in info_lines:
        line = line.strip("\n")
        if len(line)>0:
            pos = line.find(" (.0)")
            if pos!=-1:
                line = line[:pos]

            if first_line is None:
                first_line = line
            last_line = line

    if last_line == first_line:
        sdk_versions = first_line
    else:
        sdk_versions = "%s-%s" % (first_line, last_line)

    build_date = None
    titledirpath = "%s/0100000000000819" % (updatedir)
    if os.path.isdir(titledirpath) is True:
        pkg1_info_path = None
        for dirpath, dirnames, filenames in os.walk(titledirpath):
            for f in filenames:
                if f == "nx_package1_hactool.info":
                    pkg1_info_path = os.path.join(dirpath, f)
                    break
            if pkg1_info_path is not None:
                break

        if pkg1_info_path is not None:
            with open(pkg1_info_path, 'r') as infof:
                info_lines = infof.readlines()
            for line in info_lines:
                line = line.strip("\n")
                if len(line)>0:
                    if line.find("Build Date")!=-1:
                        pos = line.rfind(" ")
                        if pos!=-1:
                            tmpstr = line[pos+1:]
                            build_datetime = datetime.strptime(tmpstr, '%Y%m%d%H%M%S')
                            build_date = build_datetime.strftime('%Y-%m-%d %H:%M:%S')
        else:
            print("Failed to find the bootpkg nx_package1_hactool.info.")
    else:
        build_date = "!LAST"
        print("This updatedir doesn't include bootpkg, using build_date from the last wiki entry.")

    if build_date is None:
        print("Loading the build_date failed, skipping the System_Versions page.")
    else:
        updatever_link = "[[%s]]" % (updatever)
        page = {
            "page_title": "System_Versions",
            "search_section": "{| ",
            "targets": [
                {
                    "insert_row_tables": [
                        {
                            "search_text": updatever_link,
                            "columns": [
                                updatever_link,
                                "!TIMESTAMP",
                                build_date,
                                sdk_versions,
                            ],
                        },
                    ],
                },
            ],
        }
        storage.append(page)

else:
    print("Skipping System_Versions page since the sdk_versions.info file doesn't exist.")

info_path = "%s/nca_masterkey.info" % (updatedir)
if os.path.exists(info_path):
    val = None
    with open(info_path, 'r') as infof:
        info_lines = infof.readlines()
    for line in info_lines:
        line = line.strip("\n")
        if len(line)>0:
            pos = line.find(' ')
            if pos!=-1:
                line = line[:pos]
            curval = int(line, 16)
            if val is None:
                val = curval
            elif curval>val:
                val = curval
                print("nca_masterkey.info: Multiple lines detected with a newer value, using val = 0x%X." % (val))

    if val is None:
        print("Failed to load key val from nca_masterkey.info, skipping NCA page.")
    else:
        entry_text = "0x%02X = [[" % (val+1)

        page = {}
        page["page_title"] = "NCA"
        page["search_section"] = "= Header ="
        page["targets"] = []

        target = {}
        target["table_lists"] = []

        table_list = {}
        table_list["target_text_prefix"] = "KeyGeneration "
        table_list["insert_before_text"] = "0xFF = Invalid)"
        table_list["search_text"] = entry_text
        table_list["insert_text"] = "%s%s]]" % (entry_text, updatever)
        target["table_lists"].append(table_list)
        page["targets"].append(target)

        storage.append(page)
else:
    print("Skipping NCA page since the .info file doesn't exist.")

diff_titles = {}
info_path = "%s/romfs_alltitles.diff.info" % (updatedir)

if not os.path.exists(info_path):
    info_path = "%s/romfs_alltitles_ALL.diff.info" % (updatedir)

if not os.path.exists(info_path):
    info_path = "%s/romfs_alltitles_CHN.diff.info" % (updatedir)

if os.path.exists(info_path):
    with open(info_path, 'r') as infof:
        info_lines = infof.readlines()
    #differ_lines = []
    #only_lines = []
    system_searchstr = "-%s/" % (insystem)
    for line in info_lines:
        line = line.strip("\n")
        if len(line)>0:
            parse_str = None
            change_type = None
            if line[:6] == "Files " and line[len(line)-7:] == " differ":
                line = line[7:len(line)-7]
                pos = line.find(" and ")
                if pos!=-1:
                    prevpath = line[:pos]
                    curpath = line[pos+5:]
                    parse_str = curpath
                    change_type = "updated"
                    #differ_lines.append([prevpath, curpath])
                else:
                    print("Unrecognized 'differ' line in romfs_alltitles.diff.info, ' and ' is missing: %s" % (line))
            elif line[:8] == "Only in ":
                line = line[8:]
                pos = line.find(": ")
                if pos!=-1:
                    path = line[:pos]
                    path_name = line[pos+2:]
                    parse_str = path + "/" + path_name
                    if os.path.isdir(parse_str) is True:
                        parse_str = parse_str + "/"
                    pos = path.find("%s%s" % (reportdate, system_searchstr))
                    if pos!=-1:
                        change_type = "added"
                    else:
                        change_type = "removed"
                    #only_lines.append([path, path_name])
                else:
                    print("Unrecognized 'Only in' line in romfs_alltitles.diff.info, ': ' is missing: %s" % (line))
            else:
                print("Unrecognized line in romfs_alltitles.diff.info: %s" % (line))
            if parse_str is not None and change_type is not None:
                pos = parse_str.find(system_searchstr)
                if pos!=-1:
                    tmp = parse_str[pos+len(system_searchstr):]
                    pos = tmp.find('/')
                    if pos!=-1:
                        titleid = tmp[:pos]
                        pos = tmp.find('romfs/')
                        if pos!=-1:
                            path = tmp[pos+5:]
                            #print("titleid = %s, path = %s" % (titleid, path))
                            if titleid in diff_titles:
                                title = diff_titles[titleid]
                            else:
                                title = {}
                                title['desc'] = get_titledesc(titleid)
                                title['changes'] = []
                                title['group_titles'] = []

                            if 'dirpath' not in title and (change_type=="updated" or "added"):
                                tmp_pos = parse_str.find('romfs/')
                                title['dirpath'] = parse_str[:tmp_pos+5]

                            change = {'type': change_type, 'path': path}

                            title['changes'].append(change)

                            if titleid not in diff_titles:
                                diff_titles[titleid] = title
                        else:
                            print("Failed to find 'romfs/' in parse_str: %s" % parse_str)
                    else:
                        print("Failed to find '/' after system_searchstr in parse_str: %s" % parse_str)
                else:
                    print("Failed to find system_searchstr in parse_str: %s" % parse_str)

    #print("differ_lines: ")
    #print(differ_lines)
    #print("only_lines: ")
    #print(only_lines)
    #print("diff_titles:")
    #print(diff_titles)

if len(diff_titles)>0:
    for titleid, title in diff_titles.items():
        if titleid in titleinfo and 'group' in titleinfo[titleid]:
            group = titleinfo[titleid]['group']
            if group in diff_titles:
                grouptitle = diff_titles[group]
                tmplen = len(grouptitle['changes'])
                if tmplen == len(title['changes']):
                    found = False
                    for i in range(tmplen):
                        title_change = title['changes'][i]
                        group_change = grouptitle['changes'][i]
                        if title_change['type'] != group_change['type'] or title_change['path'] != group_change['path']:
                            found = True
                            break

                    if found is False:
                        desc = titleid
                        if title['desc']!="N/A":
                            desc = title['desc']

                        diff_titles[group]['group_titles'].append((titleid, desc))
                        diff_titles[titleid]['group'] = group

if len(diff_titles)>0:
    page = {
        "page_title": "!UPDATEVER",
        "search_section": "==System Titles==",
        "targets": []
    }

    target = {
        "search_section_end": "\n=",
        "text_sections": []
    }

    bootpkgs_text = ""

    insert_text = "RomFs changes:\n"
    for titleid, title in diff_titles.items():
        if 'group' in title:
            continue

        desc_list = [(titleid, title['desc'])]
        for tmp in title['group_titles']:
            desc_list.append(tmp)

        desc = ''
        for cur_titleid, tmpdesc in desc_list:
            curdesc = cur_titleid
            if tmpdesc!="N/A":
                curdesc = tmpdesc

            is_applet = False
            pos = curdesc.find('" applet')
            if curdesc[0]=='"' and pos!=-1:
                curdesc = curdesc[1:pos]
                if len(desc_list)==1:
                    curdesc = "%s applet" % (curdesc)
                    is_applet = True

            if desc=='':
                desc = curdesc
            else:
                if is_applet is False:
                    desc = desc + "/" + curdesc
                else:
                    desc = desc + " / " + curdesc

        if titleid in titleinfo and 'wikipage' in titleinfo[titleid]:
            wikipage = titleinfo[titleid]['wikipage']
            desc = "[[%s|%s]]" % (wikipage, desc)

        title_text = "* %s:" % (desc)

        allfiles_updated = False
        if 'dirpath' in title:
            titledirpath = title['dirpath']
            titledirpath_len = len(titledirpath)
            dir_filelisting = []
            for dirpath, dirnames, filenames in os.walk(titledirpath):
                for f in filenames:
                    dir_filelisting.append(os.path.join(dirpath[titledirpath_len:], f))

            if len(title['changes']) == len(dir_filelisting):
                found = False
                for change in title['changes']:
                    if change['type'] != 'updated':
                        found = True
                        break

                    found2 = False
                    for path in dir_filelisting:
                        if path == change['path']:
                            found2 = True
                            break
                    if found is False:
                        found = True
                        break
                if found is True:
                    allfiles_updated = True

        if allfiles_updated is True:
            title_text = title_text + " All files updated.\n"
        elif len(title['changes']) < 7:
            title_text = title_text + " "

            cnt=0
            for change in title['changes']:
                cnt=cnt+1
                path = change['path']
                if cnt>1:
                    title_text = title_text + ", "
                title_text = title_text + "\"%s\" %s" % (path, change['type'])

            title_text = title_text + "\n"
        else:
            if titleid in titleinfo and 'ignore' in titleinfo[titleid] and titleinfo[titleid]['ignore'] is True:
                title_text = title_text + " updated\n"
            else:
                title_text = title_text + "\n"

                filters_flag={}

                for change in title['changes']:
                    path = change['path']

                    if change['type'] == 'updated':
                        found = False
                        for filterpath, msg in dirfilter_msgs.items():
                            if path[:len(filterpath)] == filterpath:
                                if not filterpath in filters_flag:
                                    filters_flag[filterpath] = True
                                    title_text = title_text + "** \"%s\": %s %s.\n" % (filterpath, msg, change['type'])
                                found = True
                                break
                        if found is True:
                            continue

                    title_text = title_text + "** \"%s\" %s\n" % (path, change['type'])

        if titleid=='0100000000000819' or titleid=='010000000000081A' or titleid=='010000000000081B' or titleid=='010000000000081C': # While unlikely, check titleid for every bootpkg just in case grouping isn't used (different changes in a title compared against the main-bootpkg).
            bootpkgs_text = bootpkgs_text + title_text
        else:
            insert_text = insert_text + title_text

    text_section = {
        "search_text": "RomFs",
        "insert_text": insert_text
    }

    #print(insert_text)
    target["text_sections"].append(text_section)
    page["targets"].append(target)

    target = {
        "search_section_end": "\n==See Also==",
        "text_sections": []
    }

    if len(bootpkgs_text)>0:
        insert_text = "\n=== BootImagePackages ===\nRomFs changes:\n"

        # If all bootpkgs are present, remove the title-listing. Could just compare the full "name/name/..." str, but don't assume order.
        pos = bootpkgs_text.find(": ")
        if pos!=-1:
            tmpstr = bootpkgs_text[:pos+1]
            if (tmpstr.find("BootImagePackage/")!=-1 or tmpstr.find("BootImagePackage:")!=-1) and tmpstr.find("BootImagePackageSafe")!=-1 and tmpstr.find("BootImagePackageExFat")!=-1 and tmpstr.find("BootImagePackageExFatSafe")!=-1:
                bootpkgs_text = bootpkgs_text[:2] + bootpkgs_text[len(tmpstr)+1:]

        if bootpkgs_text.count("\n") == 1: # Unlikely to ever be >1, but only run this for a single bootpkg line.
            if bootpkgs_text.find("All files updated")!=-1:
                insert_text = insert_text[:len(insert_text)-1] + " a" + bootpkgs_text[3:]
                bootpkgs_text = ""

        if bootpkgs_text!="":
            insert_text = insert_text + bootpkgs_text

        text_section = {
            "insert_before_text": "\n=",
            "search_text": "= BootImagePackages",
            "insert_text": insert_text
        }

        target["text_sections"].append(text_section)
        #print(insert_text)

    if os.path.exists("%s/swipcgen_server_ready" % (updatedir)):
        info_path = "%s/swipcgen_server_modern_alltitles.diff.info" % (updatedir)
        if os.path.exists(info_path):
            with open(info_path, 'r') as infof:
                info_lines = infof.readlines()

            insert_text = "\n=== IPC Interface Changes ===\n"

            #unkintf_prev_cnt=0
            #unkintf_cur_cnt=0
            #for line in info_lines:
            #    line = line.strip("\n")
            #    if len(line)>0:
            #        if line.find("Unknown Interface prev-version:")!=-1:
            #            unkintf_prev_cnt=unkintf_prev_cnt+1
            #        if line.find("Unknown Interface cur-version:")!=-1:
            #            unkintf_cur_cnt=unkintf_cur_cnt+1

            linecnt=0
            for line in info_lines:
                line = line.strip("\n")
                if len(line)>0:
                    line_text = "* "
                    tmpcnt=0
                    for i in range(len(line)):
                        if line[i].isspace():
                            tmpcnt=tmpcnt+1
                            if tmpcnt==1:
                                line_text = "*" + line_text
                        else:
                            #if line.find("Unknown Interface ")==-1 or unkintf_prev_cnt!=unkintf_cur_cnt:
                            line_text = line_text + line[i:] + "\n"
                            insert_text = insert_text + line_text
                            linecnt=linecnt+1
                            break

            if linecnt==0:
                line_text = "No changes.\n"
                insert_text = insert_text + line_text

            text_section = {
                "insert_before_text": "\n=",
                "search_text": "IPC Interface Changes",
                "insert_text": insert_text
            }

            target["text_sections"].append(text_section)
        else:
            print("The swipcgen .info file doesn't exist even though the ready file exists, skipping IPC section.")

    page["targets"].append(target)

    storage.append(page)

# TODO
page = {
    "page_title": "!UPDATEVER",
    "search_section": "==System Titles==",
    "targets": [
        {
            "search_section_end": "\n=",
            "text_sections": [
                {
                    "search_text": "NPDM",
                    "insert_text": "[[NPDM]] changes (besides usual version-bump):\n* TODO\n"
                },
                {
                    "search_text": "RomFs",
                    "insert_text": "RomFs changes (non-sysver):\n* TODO\n"
                },
            ],
        },
        {
            "search_section_end": "\n==See Also==",
            "text_sections": [
                {
                    "insert_before_text": "\n=",
                    "search_text": "IPC Interface Changes",
                    "insert_text": "\n=== IPC Interface Changes ===\n* TODO\n",
                },
            ],
        },
    ],
}

bt_bsa_version_string = None
bt_bsa_version_info_string = None
bt_config_str = None
wifi_fwstr = None

bootpkg_line_found = False
bootpkg_masterkey_str = None # Extract this from updatedetails since it's present anyway, even though this script has no use for it currently.
bootpkg_retail_fuses = None
bootpkg_devunit_fuses = None

cmpstr = "bluetooth-sysmodule"
cmpstr2 = "wlan-sysmodule"
cmpstr3 = "BootImagePackage"

if updatedetails is not None:
    cnt=0
    cnt2=0
    cnt3=0
    for line in updatedetails:
        line = line.strip("\n")
        if len(line)>0:
            if line[:len(cmpstr)] == cmpstr:
                cnt=3
            elif line[:len(cmpstr2)] == cmpstr2:
                cnt2=1
            elif line[:len(cmpstr3)] == cmpstr3:
                cnt3=3
                bootpkg_line_found = True
            elif cnt>0 or cnt2>0 or cnt3>0:
                if cnt>0:
                    cnt = cnt-1
                    line_type=0
                elif cnt2>0:
                    cnt2 = cnt2-1
                    line_type=1
                elif cnt3>0:
                    cnt3 = cnt3-1
                    line_type=2

                pos = line.find(': ')
                if pos!=-1:
                    cmpline = line[:pos]
                    val_line = line[pos+2:]

                    if line_type==0:
                        if cmpline == "bsa_version_string":
                            bt_bsa_version_string = val_line
                        elif cmpline == "bsa_version_info_string":
                            bt_bsa_version_info_string = val_line
                        elif cmpline == "Config string":
                            bt_config_str = val_line
                    elif line_type==1:
                        if cmpline == "Firmware string":
                            wifi_fwstr = val_line
                    elif line_type==2:
                        if cmpline == "Using master-key":
                            bootpkg_masterkey_str = val_line
                        elif cmpline == "Total retail blown fuses":
                            bootpkg_retail_fuses = val_line
                        elif cmpline == "Total devunit blown fuses":
                            bootpkg_devunit_fuses = val_line

if bootpkg_line_found is False or (bootpkg_line_found is True and bootpkg_retail_fuses is not None and bootpkg_devunit_fuses is not None):
    fuse_columns = []
    if bootpkg_retail_fuses is not None and bootpkg_devunit_fuses is not None:
        fuse_columns.append(bootpkg_retail_fuses)
        fuse_columns.append(bootpkg_devunit_fuses)

    page = {
        "page_title": "Fuses",
        "search_section": "= Anti-downgrade =",
        "targets": [
            {
                "search_section": "{|",
                "tables_updatever_range": [
                    {
                        "columns": fuse_columns,
                    },
                ],
            },
        ],
    }
    storage.append(page)

if bt_bsa_version_string is not None and bt_bsa_version_info_string is not None and bt_config_str is not None:
    page = {
        "page_title": "Bluetooth_Driver_services",
        "search_section": "== Versions ==",
        "targets": [
            {
                "search_section": "{|",
                "tables_updatever_range": [
                    {
                        "columns": [
                            bt_bsa_version_string,
                            bt_bsa_version_info_string,
                            bt_config_str,
                        ],
                    },
                ],
            },
        ],
    }
    storage.append(page)

if wifi_fwstr is not None:
    page = {
        "page_title": "WLAN_services",
        "search_section": "= FwVersion =",
        "targets": [
            {
                "search_section": "{|",
                "tables_updatever_range": [
                    {
                        "columns": [
                            wifi_fwstr,
                        ],
                    },
                ],
            },
        ],
    }
    storage.append(page)

with open(outpath, 'w') as json_f:
    json.dump(storage, json_f)

