#!/usr/bin/python3
import sys
import struct
import os
import requests
import argparse
import json
import csv
import re
from datetime import datetime, date, time, timezone

from proc_config import *

datadir = sys.argv[1]
storage_path = "%s/storage.json" % (datadir)
csvpath = "%s/tmp.csv" % (datadir)

if os.path.exists(datadir) == False:
    os.mkdir(datadir)

storage = {}
storage["data"] = []
if os.path.isfile(storage_path) is True:
    if os.path.getsize(storage_path) > 0:
        with open(storage_path, 'r') as json_f:
            storage = json.load(json_f)

useragent = "NintendoSDK Firmware/%s (platform:NX; did:%s; eid:%s)" % (useragent_fw, deviceid, eid)

r = requests.get("https://aqua.hac.%s.d4c.nintendo.net/required_system_update_meta?device_id=%s" % (eid, deviceid), headers={"User-Agent": useragent, "Accept": "application/json"}, timeout=10, verify=False, cert=(tlsclient_certpath, tlsclient_privkpath))
aqua_resp = {"status": r.status_code, "httpdata": r.text, "headers": r.headers}
r.raise_for_status()
aqua_json = r.json()

print(aqua_resp)
print(aqua_json)

title_id = aqua_json["contents_delivery_required_title_id"]
title_ver = aqua_json["contents_delivery_required_title_version"]
print("%s %d" % (title_id, title_ver))

if len(title_id) != 16 or re.search('[0-9a-f]{16}$', title_id) is None:
    print("Bad title_id.")
    sys.exit(1)

lastmod = None
if "last-modified" in aqua_resp["headers"]:
    lastmod = aqua_resp["headers"]["last-modified"]
    print(lastmod)
    lastmod_datetime = datetime.strptime(lastmod, '%a, %d %b %Y %H:%M:%S %Z')
    lastmod_datestr = '{0:%B} {1}, {0:%Y}'.format(lastmod_datetime, lastmod_datetime.day)

found = False
for entry in storage["data"]:
    tmp_json = json.JSONDecoder()
    tmp_json = tmp_json.decode(entry["httpdata"])
    tmp_title_id = tmp_json["contents_delivery_required_title_id"]
    tmp_title_ver = tmp_json["contents_delivery_required_title_version"]
    if tmp_title_id == title_id and tmp_title_ver == title_ver:
        found = True
        break

if found is True:
    sys.exit(0)

storage["data"].append(aqua_resp)

with open(storage_path, 'w') as json_f:
    json.dump(storage, json_f)

if len(storage["data"]) <= 1:
    sys.exit(0)

msg = "The required sysver returned by aqua for eShop content download changed: v%d" % (title_ver)

reportdate = None
updateversion = None

ret = os.system("php /home/yellows8/ninupdates/api_cli.php gettitleversions hac ALL %s 0 --format=csv > %s" % (title_id, csvpath))
if ret!=0:
    print("api_cli failed: %d" % (ret))
    msg += "."
else:
    with open(csvpath) as csvfile:
        csvrd = csv.reader(csvfile)
        cnt=0
        for row in csvrd:
            cnt = cnt + 1
            if cnt==1:
                continue
            ver = row[0]
            if ver[0]=='v':
                ver = ver[1:]
            ver = int(ver)
            if ver == title_ver:
                reportdate = row[1]
                updateversion = row[2]
                msg += ", for sysupdate: https://yls8.mtheall.com/ninupdates/reports.php?date=%s&sys=hac" % (reportdate)
                break

if lastmod is not None:
    msg += " Last-Modified: %s" % (lastmod)

if lastmod is not None and reportdate is not None and updateversion is not None:
    wikigen_path = "%s/%s_wikigen.json" % (datadir, reportdate)
    print("Writing wikigen to the following path, and then running the wikibot: %s" % (wikigen_path))

    pos = updateversion.find('_rebootless')
    if pos!=-1:
        updateversion_norebootless = updateversion[:pos]
    else:
        updateversion_norebootless = updateversion

    prefix = "On %s (UTC), the required" % (lastmod_datestr)

    wikigen = []

    page = {
        "page_title": updateversion_norebootless,
        "search_section": "update was released",
        "targets": [
            {
                "search_section_end": "\n==Change-log==",
                "text_sections": [
                    {
                        "search_text": prefix,
                        "insert_text": "%s system-version returned by [[Network|aqua]] for eShop contents-download was updated to this sysver (%s).\n" % (prefix, updateversion),
                    },
                ],
            },
        ],
    }
    wikigen.append(page)

    with open(wikigen_path, 'w') as json_f:
        json.dump(wikigen, json_f)

    os.system("php /home/yellows8/ninupdates/wikibot.php hac '--wikigen=%s' >> /home/yellows8/ninupdates/debuglogs/wikibot_aqua_wikigen_log 2>&1 &" % (wikigen_path))

print("Sending notifs with msg: %s" % msg)

ret = os.system("cd /home/yellows8/ninupdates/ && ./send_notif.py \"%s\" --social --webhook >> /home/yellows8/ninupdates/sendnotif_log 2>&1 &" % (msg))
sys.exit(ret)

