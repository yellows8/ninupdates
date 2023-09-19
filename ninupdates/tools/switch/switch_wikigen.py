#!/usr/bin/python3
import sys
import os
import argparse
import json
import subprocess
import csv
from pathlib import Path
from os.path import exists
from datetime import datetime, date, time, timezone
from cryptography import x509

# https://github.com/yellows8/nx-tools
import ssl_bdf
import nx_meta

# PYTHONPATH should be set for nx-tools: "PYTHONPATH={nx-tools path} {python3 cmd}"

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

updatedetails_info = {}

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

def api_cli(region, titleid, args=[]):
    proc = subprocess.run(["php", "/home/yellows8/ninupdates/api_cli.php", "gettitleversions", insystem, region, titleid, "2", "--format=csv", *args], capture_output=True, encoding='utf8')
    if proc.returncode!=0:
        print("api_cli failed, stderr+stdout: %s" % (proc.stderr + proc.stdout))
        return ""
    else:
        return proc.stdout

def parse_updatedetails(inlines):
    bootpkg_line_found = False
    cmpstr = "bluetooth-sysmodule"
    cmpstr2 = "wlan-sysmodule"
    cmpstr3 = "BootImagePackage"

    cnt=0
    cnt2=0
    cnt3=0

    out = {}
    out['bootpkg_line_found'] = False

    for line in inlines:
        line = line.strip("\n")
        if len(line)>0:
            if line[:len(cmpstr)] == cmpstr:
                cnt=3
            elif line[:len(cmpstr2)] == cmpstr2:
                cnt2=1
            elif line[:len(cmpstr3)] == cmpstr3:
                cnt3=3
                out['bootpkg_line_found'] = True
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
                            out['bt_bsa_version_string'] = val_line
                        elif cmpline == "bsa_version_info_string":
                            out['bt_bsa_version_info_string'] = val_line
                        elif cmpline == "Config string":
                            out['bt_config_str'] = val_line
                    elif line_type==1:
                        if cmpline == "Firmware string":
                            out['wifi_fwstr'] = val_line
                    elif line_type==2:
                        if cmpline == "Using master-key":
                            out['bootpkg_masterkey_str'] = val_line
                        elif cmpline == "Total retail blown fuses":
                            out['bootpkg_retail_fuses'] = val_line
                        elif cmpline == "Total devunit blown fuses":
                            out['bootpkg_devunit_fuses'] = val_line
    return out

def IsBootImagePackage(Id):
    return Id=='0100000000000819' or Id=='010000000000081A' or Id=='010000000000081B' or Id=='010000000000081C'

def FindMetaPath(TitleDir, TitleType):
    Out = None

    if TitleType==0:
        DirListing = Path(TitleDir).glob('**/*plain_section0_pfs0/main.npdm')
    elif TitleType==1:
        DirListing = Path(TitleDir).glob('**/package2_outdir/INI1.bin')

    for CurPath in DirListing:
        Out = "%s" % (CurPath)
        break

    return Out

def ProcessMetaDiffKc(KcDiff):
    Text = ""

    for KcKey, KcValue in KcDiff.items():
        if KcKey == 'EnableSystemCalls' or KcKey == 'EnableInterrupts':
            if len(Text)>0 and Text[-1]!=' ':
                Text = Text + " "
            if KcKey == 'EnableSystemCalls':
                Text = Text + "SVC access: "
            elif KcKey == 'EnableInterrupts':
                Text = Text + "Interrupt access: "
            for ChangeKey, ChangeValue in KcDiff[KcKey].items():
                if len(Text)>0 and Text[-1]!=' ':
                    Text = Text + ", "
                Text = Text + "%s " % (ChangeKey.lower())
                for Id in ChangeValue:
                    if len(Text)>0 and Text[-1]!=' ':
                        Text = Text + ", "
                    if KcKey == 'EnableSystemCalls': # TODO: SVC names
                        TmpStr = "SVC 0x%02X" % (Id)
                    elif KcKey == 'EnableInterrupts':
                        TmpStr = "0x%03X" % (Id)
                    Text = Text + TmpStr
            Text = Text + "."
        else:
            if len(Text)>0 and Text[-1]!=' ':
                Text = Text + " "
            if KcKey == 'Descriptor':
                TmpStr = "Unknown KernelCap descriptor(s): "
            else:
                TmpStr = "KernelCap %s: " % (KcKey)
            Text = Text + TmpStr
            for ChangeKey, ChangeValue in KcDiff[KcKey].items():
                if len(Text)>0 and Text[-1]!=' ':
                    Text = Text + ", "
                Text = Text + "%s " % (ChangeKey.lower())
                for TmpKey, TmpValue in KcDiff[KcKey][ChangeKey].items():
                    if TmpKey == 'Value':
                        continue
                    elif TmpKey == 'Descriptors':
                        for KcEntry in KcDiff[KcKey][ChangeKey][TmpKey]:
                            if len(Text)>0 and Text[-1]!=' ':
                                Text = Text + ", "
                            for DescKey, DescValue in KcEntry.items():
                                if DescKey == 'Value' or DescKey == 'Value0' or DescKey == 'Value1':
                                    continue
                                if DescKey == 'Reserved' and ChangeKey != 'Updated' and DescValue==0:
                                    continue

                                if len(Text)>0 and Text[-1]!=' ':
                                    Text = Text + " "
                                Text = Text + "%s" % (DescKey)

                                if DescKey == 'PermissionType' or DescKey == 'MappingType':
                                    if ChangeKey == 'Updated':
                                        Vals = "%s -> %s" % (DescValue[0], DescValue[1])
                                    else:
                                        Vals = "%s" % (DescValue)
                                else:
                                    if ChangeKey == 'Updated' and DescKey != 'BeginAddress':
                                        Vals = "0x%X -> 0x%X" % (DescValue[0], DescValue[1])
                                    else:
                                        Vals = "0x%X" % (DescValue)

                                if ChangeKey == 'Updated' and DescKey != 'BeginAddress':
                                    Text = Text + " = %s" % (Vals)
                                else:
                                    Text = Text + "=%s" % (Vals)
                    else:
                        if TmpKey == 'Reserved' and ChangeKey != 'Updated' and TmpValue==0:
                            continue

                        if len(Text)>0 and Text[-1]!=' ':
                            Text = Text + " "
                        Text = Text + "%s" % (TmpKey)
                        if ChangeKey == 'Updated':
                            Text = Text + " = 0x%X -> 0x%X" % (TmpValue[0], TmpValue[1])
                        else:
                            Text = Text + "=0x%X" % (TmpValue)
            Text = Text + "."

    return Text

def ProcessMetaDiffMeta(Desc, Diff, IgnoreVersion=True):
    Text = "* %s: " % (Desc)

    for Key, Value in Diff.items():
        if Key == 'Acid':
            for AcidKey, AcidValue in Diff[Key].items():
                if 'Updated' in AcidValue:
                    if len(Text)>0 and Text[-1]!=' ':
                        Text = Text + " "
                    Text = Text + "Acid.%s updated: 0x%X -> 0x%X." % (AcidKey, AcidValue['Updated'][0], AcidValue['Updated'][1])
        elif Key != 'Aci':
            if 'Updated' in Value:
                if Key == 'Version' and IgnoreVersion is True:
                    continue

                if len(Text)>0 and Text[-1]!=' ':
                    Text = Text + " "

                if Key == 'Name' or Key == 'ProductCode' or Key == 'Reserved_x40':
                    Vals = "%s -> %s" % (Value['Updated'][0], Value['Updated'][1])
                else:
                    Vals = "0x%X -> 0x%X" % (Value['Updated'][0], Value['Updated'][1])
                Text = Text + "%s updated: %s." % (Key, Vals)
        else:
            for AciKey, AciValue in Diff[Key].items():
                if AciKey!='Fac' and AciKey!='Sac' and AciKey!='Kc':
                    if 'Updated' in AciValue:
                        if len(Text)>0 and Text[-1]!=' ':
                            Text = Text + " "
                        Text = Text + "%s updated: 0x%X -> 0x%X." % (AciKey, AciValue['Updated'][0], AciValue['Updated'][1])
                elif AciKey=='Fac':
                    for FacKey, FacValue in Diff[Key][AciKey].items():
                        if FacKey != 'ContentOwnerInfo' and FacKey != 'SaveDataOwnerInfo':
                            if 'Updated' in FacValue:
                                if len(Text)>0 and Text[-1]!=' ':
                                    Text = Text + " "

                                if Key == 'Padding':
                                    Vals = "%s -> %s" % (Value['Updated'][0], Value['Updated'][1])
                                else:
                                    Vals = "0x%X -> 0x%X" % (FacValue['Updated'][0], FacValue['Updated'][1])

                                Text = Text + "Fac.%s updated: %s." % (FacKey, Vals)
                        else:
                            TmpText = "Fac.%s " % (FacKey)
                            for ChangeKey, ChangeValue in Diff[Key][AciKey][FacKey].items():
                                if len(TmpText)>0 and TmpText[-1]!=' ':
                                    TmpText = TmpText + ", "
                                for Info in ChangeValue:
                                    if ChangeKey == 'Updated':
                                        IdStr = "%016X" % (Info[0]['Id'])
                                        TmpDesc = get_titledesc(IdStr)
                                        if TmpDesc=='N/A':
                                            TmpDesc = ""
                                        else:
                                            TmpDesc = " (%s)" % (TmpDesc)
                                        if FacKey=='SaveDataOwnerInfo':
                                            TmpText = TmpText + "%s %s%s access %s -> %s" % (ChangeKey.lower(), IdStr, TmpDesc, nx_meta.metaSaveDataOwnerAccessToStr(Info[0]['Access']), nx_meta.metaSaveDataOwnerAccessToStr(Info[1]['Access']))
                                    else:
                                        IdStr = "%016X" % (Info['Id'])
                                        TmpDesc = get_titledesc(IdStr)
                                        if TmpDesc=='N/A':
                                            TmpDesc = ""
                                        else:
                                            TmpDesc = " (%s)" % (TmpDesc)
                                        TmpText = TmpText + "%s %s%s" % (ChangeKey.lower(), IdStr, TmpDesc)
                                        if FacKey=='SaveDataOwnerInfo':
                                            TmpText = TmpText + " access %s" % (nx_meta.metaSaveDataOwnerAccessToStr(Info['Access']))
                            if len(TmpText)>0:
                                if len(Text)>0 and Text[-1]!=' ':
                                    Text = Text + " "
                                Text = Text + TmpText + "."
                elif AciKey=='Sac':
                    for SacKey, SacValue in Diff[Key][AciKey].items():
                        TmpText = "Service %s access: " % (SacKey.lower())
                        for TmpKey, TmpValue in Diff[Key][AciKey][SacKey].items():
                            for ChangeKey, ChangeValue in Diff[Key][AciKey][SacKey][TmpKey].items():
                                if len(TmpText)>0 and TmpText[-1]!=' ':
                                    TmpText = TmpText + ", "
                                TmpText = TmpText + "%s \"%s\"" % (ChangeKey.lower(), TmpKey)
                                if ChangeKey=='Updated':
                                    TmpText = TmpText + " (control byte 0x%X -> 0x%X)" % (ChangeValue[0], ChangeValue[1])
                                elif ChangeValue & 0x78: # Reserved bits set.
                                    TmpText = TmpText + " (control byte 0x%X)" % (ChangeValue)
                        if len(TmpText)>0 and TmpText[-1]!=' ':
                            if len(Text)>0 and Text[-1]!=' ':
                                Text = Text + " "
                            Text = Text + TmpText + "."
                elif AciKey=='Kc':
                    TmpText = ProcessMetaDiffKc(Diff[Key][AciKey])
                    if len(TmpText)>0:
                        if len(Text)>0 and Text[-1]!=' ':
                            Text = Text + " "
                        Text = Text + TmpText

    if len(Text)>0 and Text[-1]==' ':
        Text = ""
    else:
        Text = Text + "\n"
    return Text

def ProcessMetaDiffIni1(Desc, Diff):
    Text = "* %s: " % (Desc)
    KipText = ""

    for ChangeKey, ChangeValue in Diff.items():
        for Key, Value in Diff[ChangeKey].items():
            if Key == 'Size':
                continue
            if Key != 'Kips':
                if ChangeKey == 'Updated':
                    if len(Text)>0 and Text[-1]!=' ':
                        Text = Text + " "
                    Text = Text + "INI1.%s updated: 0x%X -> 0x%X." % (Key, Value[0], Value[1])
            else:
                if ChangeKey == 'Updated':
                    for KipKeyId, KipEntry in Diff[ChangeKey][Key].items():
                        TmpStr = KipKeyId
                        pos = KipKeyId.find("_")
                        if pos!=-1:
                            TmpStr = "%s (%s)" % (KipKeyId[:pos], KipKeyId[pos+1:])
                        KipText = KipText + "** %s: " % (TmpStr)
                        for KipKey, KipValue in Diff[ChangeKey][Key][KipKeyId].items():
                            if KipKey != 'Kc':
                                if KipKey == 'Name':
                                    Vals = "%s -> %s" % (KipValue[0], KipValue[1])
                                else:
                                    Vals = "0x%X -> 0x%X" % (KipValue[0], KipValue[1])

                                if len(KipText)>0 and KipText[-1]!=' ':
                                    KipText = KipText + " "
                                KipText = KipText + "%s updated: %s." % (KipKey, Vals)
                            else:
                                TmpText = ProcessMetaDiffKc(Diff[ChangeKey][Key][KipKeyId][KipKey])
                                if len(TmpText)>0:
                                    if len(KipText)>0 and KipText[-1]!=' ':
                                        KipText = KipText + " "
                                    KipText = KipText + TmpText
                        KipText = KipText + "\n"
                else:
                    for Kip in Diff[ChangeKey][Key]:
                        if len(Text)>0 and Text[-1]!=' ':
                            Text = Text + " "
                        Text = Text + "%s KIP %016X (%s)." % (ChangeKey, Kip['ProgramId'], Kip['Name'])

    if len(Text)>0 and Text[-1]==' ' and len(KipText)==0:
        Text = ""
    else:
        Text = Text + "\n" + KipText
    return Text

def ProcessMetaDiff(MetaDiff):
    Out = {'Meta': '', 'Ini1': ''}

    for Id, Diff in MetaDiff.items():
        Desc = get_titledesc(Id)
        if Desc=="N/A":
            Desc = Id
        if 'Meta' in Diff:
            Out['Meta'] = Out['Meta'] + ProcessMetaDiffMeta(Desc, Diff['Meta'])
        elif 'Ini1' in Diff:
            Out['Ini1'] = Out['Ini1'] + ProcessMetaDiffIni1(Desc, Diff['Ini1'])
        else:
            print("ProcessMetaDiff(): The entry for '%s' doesn't have the required data." % (Id))

    return Out

def GetMetaText(InDirpath):
    TitleInfo = {} # Use this for sorting.

    if os.path.isdir(InDirpath) is True:
        for d_name in os.listdir(InDirpath):
            CurPath = os.path.join(InDirpath, d_name)
            if os.path.isdir(CurPath) is True and len(d_name)==16:
                Id = d_name
                PrevReportDate = None

                TitleType = 0
                if Id[16-4:16-2] == "08": # Ignore SystemData, for non-bootpkg.
                    if IsBootImagePackage(Id) is False:
                        continue
                    else:
                        TitleType = 1

                for Region in ["G", "C"]:
                    apiout = api_cli(Region, Id, args=["--prevreport=%s" % (reportdate)])
                    if apiout!="":
                        ApiLines = apiout.split("\n")
                        Reader = csv.DictReader(ApiLines, delimiter=',', quoting=csv.QUOTE_NONE)
                        TmpRow = None
                        for Row in Reader:
                            TmpRow = Row # Only handle the last row.
                        if TmpRow is not None:
                            PrevReportDate = TmpRow['Report date']
                            break

                if PrevReportDate is None:
                    print("GetMetaText(): Failed to get the prevreport for: '%s'." % (Id))
                else:
                    TitleDir = "%s/%s" % (InDirpath, Id)
                    PrevTitleDir = "/home/yellows8/ninupdates/sysupdatedl/autodl_sysupdates/%s-%s/%s" % (PrevReportDate, insystem, Id)
                    if os.path.isdir(PrevTitleDir) is True:
                        TitleInfo[Id] = {'TitleType': TitleType, 'TitleDir': TitleDir, 'PrevTitleDir': PrevTitleDir}
                    else:
                        print("GetMetaText(): PrevTitleDir doesn't exist, skipping this Id. PrevTitleDir: %s" % (PrevTitleDir))

    TitleInfo = sorted(TitleInfo.items())
    MetaInfo = {}

    for Id, Title in TitleInfo:
        TitleType = Title['TitleType']
        TitleDir = Title['TitleDir']
        PrevTitleDir = Title['PrevTitleDir']

        TitleMeta = FindMetaPath(TitleDir, TitleType)
        TitlePrevMeta = FindMetaPath(PrevTitleDir, TitleType)

        if TitleMeta is None or TitlePrevMeta is None:
            print("GetMetaText(): FindMetaPath failed to find the meta path for Id = '%s', TitleDir = '%s', PrevTitleDir = '%s'." % (Id, TitleDir, PrevTitleDir))
            continue

        MetaInfo[Id] = {'Prev': TitlePrevMeta, 'Cur': TitleMeta}

    return ProcessMetaDiff(nx_meta.metaDiffPathArray(MetaInfo))

if updatedetails is not None:
    updatedetails_info = parse_updatedetails(updatedetails)

bootpkg_masterkey = None
updatedetails_prev_info = {}

if updatedetails_info['bootpkg_line_found'] is True:
    apiout = api_cli("G", "0100000000000819", args=["--prevreport=%s" % (reportdate)])
    if apiout!="":
        apilines = apiout.split("\n")
        reader = csv.DictReader(apilines, delimiter=',', quoting=csv.QUOTE_NONE)
        tmprow = None
        for row in reader:
            tmprow = row # Only handle the last row.
        if tmprow is not None:
            prev_reportdate = tmprow['Report date']
            filepath = "/home/yellows8/ninupdates/updatedetails/%s/%s" % (insystem, prev_reportdate)
            if os.path.exists(filepath):
                with open(filepath, 'r') as updatef:
                    updatedetails_prev = updatef.readlines()
                    updatedetails_prev_info = parse_updatedetails(updatedetails_prev)
                    if 'bootpkg_masterkey_str' in updatedetails_prev_info:
                        if 'bootpkg_masterkey_str' in updatedetails_info:
                            if updatedetails_prev_info['bootpkg_masterkey_str'] != updatedetails_info['bootpkg_masterkey_str']:
                                bootpkg_masterkey = {'prev': updatedetails_prev_info['bootpkg_masterkey_str'], 'cur': updatedetails_info['bootpkg_masterkey_str']}
                        else:
                            print("bootpkg_masterkey_str in updatedetails_info not found.")
                    else:
                        print("bootpkg_masterkey_str in updatedetails_prev_info not found.")
            else:
                print("Updatedetails file for prev_reportdate %s doesn't exist, skipping processing for it." % (prev_reportdate))

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
                        "search_column": 0,
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
                            "search_column": 0,
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

nca_info_set = False

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
        nca_info_set = True
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
                line = line[6:len(line)-7]
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
                    prevpath = parse_str
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

                            if 'dirpath_prev' not in title and (change_type=="updated" or "removed"):
                                tmp_pos = prevpath.find('romfs/')
                                title['dirpath_prev'] = prevpath[:tmp_pos+5]

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

def process_certstore(title, title_text):
    updatever_text = "[%s+]" % (updatever)

    status_table = {-1: 'Invalid', 0: 'Removed', 1: 'EnabledTrusted', 2: 'EnabledNotTrusted', 3: 'Revoked'}

    ssl_page = {
        "page_title": "SSL_services",
        "targets": [],
    }

    ssl_target = {
        "search_section": "= CaCertificateId",
        "insert_row_tables": [],
        "table_lists": [],
    }

    for change in title['changes']:
        if change['path'].find("TrustedCerts")==-1:
            print("process_certstore(): Skipping processing for file: \"%s\"" % (change['path']))
            continue
        if change['type'] == 'updated':
            tmp_path = os.path.join(title['dirpath'], change['path'][1:])
            tmp_path_prev = os.path.join(title['dirpath_prev'], change['path'][1:])
            cur_bdf = ssl_bdf.bdf_read(tmp_path)
            prev_bdf = ssl_bdf.bdf_read(tmp_path_prev)
            ssl_diff = ssl_bdf.bdf_diff(prev_bdf, cur_bdf)
            if ssl_diff is None: # error occured
                print("process_certstore(): bdf_diff failed with path: \"%s\"" % (change['path']))
                continue

            for ent in ssl_diff:
                strid = "%d" % (ent['entry']['id'])
                if change['path'] != "/ssl_TrustedCerts.bdf" and ent['entry']['id'] < 0x10000:
                    print("process_certstore(): Skipping processing for ssl_diff entry type='%s' id=%s, path: \"%s\"" % (ent['type'], strid, change['path']))
                    continue

                if ent['type'] == 'added':
                    tmpattr = ent['entry']['data_x509'].subject.get_attributes_for_oid(x509.NameOID.COMMON_NAME)
                    tmpstr = tmpattr[0].value
                    rowentry = {
                        "search_text": strid,
                        "search_column": 0,
                        "search_type": 1,
                        "sort": 1,
                        "columns": [
                            strid,
                            '%s "%s"' % (updatever_text, tmpstr)
                        ],
                    }
                    ssl_target["insert_row_tables"].append(rowentry)
                elif ent['type'] == 'updated':
                    str0 = ""
                    str1 = ""

                    status = ent['entry']['status']
                    if status in status_table:
                        status = status_table[status]
                    else:
                        status = "%d" % (status)

                    if ent['status_updated'] is True:
                        str0 = "[[#TrustedCertStatus]] is %s" % (status)

                    if ent['data_updated'] is True:
                        if len(str0)==0:
                            str1 = "C"
                        else:
                            str1 = ", c"
                        str1 = str1 + "ert data updated"

                    table_list = {}
                    table_list["target_text_prefix"] = strid + " "
                    table_list["delimiter"] = " "
                    table_list["target_column"] = 1
                    table_list["search_text"] = updatever_text
                    table_list["insert_text"] = "(%s %s%s)" % (updatever_text, str0, str1)
                    ssl_target["table_lists"].append(table_list)
                elif ent['type'] == 'removed':
                    table_list = {}
                    table_list["target_text_prefix"] = strid + " "
                    table_list["delimiter"] = " "
                    table_list["target_column"] = 1
                    table_list["search_text"] = updatever_text
                    table_list["insert_text"] = "(%s Removed)" % (updatever_text)
                    ssl_target["table_lists"].append(table_list)
        else:
            print("process_certstore(): Skipping processing for change type '%s', path: \"%s\"" % (change['type'], change['path']))

    ssl_page["targets"].append(ssl_target)

    ssl_target = {
        "search_section": "= CertStore",
        "text_sections": [],
    }

    insert_text = title_text
    pos = insert_text.find(": ")
    if pos!=-1:
        insert_text = insert_text[pos+1:]
    insert_text = "\n" + updatever_text + insert_text

    text_section = {
        "insert_before_text": "\n[[#ISslContext]]",
        "search_text": updatever_text,
        "insert_text": insert_text
    }

    ssl_target["text_sections"].append(text_section)

    ssl_page["targets"].append(ssl_target)
    storage.append(ssl_page)

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

MetaOut = GetMetaText(updatedir)

if len(MetaOut['Meta'])>0:
    text_section = {
        "search_text": "NPDM",
        "insert_text": "[[NPDM]] changes (besides usual version-bump):\n" + MetaOut['Meta']
    }
    target["text_sections"].append(text_section)
    #print(text_section["insert_text"])

if len(diff_titles)>0:
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

        if IsBootImagePackage(titleid): # While unlikely, check titleid for every bootpkg just in case grouping isn't used (different changes in a title compared against the main-bootpkg).
            bootpkgs_text = bootpkgs_text + title_text
        else:
            insert_text = insert_text + title_text

        if titleid=='0100000000000800': # CertStore
            process_certstore(title, title_text)

    text_section = {
        "search_text": "RomFs",
        "insert_text": insert_text
    }

    #print(insert_text)
    target["text_sections"].append(text_section)

if len(target["text_sections"])>0:
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

if len(target["text_sections"])>0:
    page["targets"].append(target)

# This is done seperately to make sure it's added when the BootImagePackages section already exists on wiki. This expects the BootImagePackages section to already exist at this point (such as via the above target).
target = {
    "search_section": "= BootImagePackages",
    "search_section_end": "\n=",
    "text_sections": []
}

if bootpkg_masterkey is not None:
    insert_text = "Using updated master-key: %s (previously %s)." % (bootpkg_masterkey['cur'], bootpkg_masterkey['prev'])

    if nca_info_set is True:
        insert_text = insert_text + " See [[NCA]] for the KeyGeneration listing."
    insert_text = insert_text + "\n"

    text_section = {
        "search_text": "master-key",
        "insert_text": insert_text
    }

    target["text_sections"].append(text_section)

# If the fuse info changed, add a text_section for it.
if updatedetails_info['bootpkg_line_found'] is True and 'bootpkg_retail_fuses' in updatedetails_info and 'bootpkg_devunit_fuses' in updatedetails_info:
    if 'bootpkg_line_found' in updatedetails_prev_info and updatedetails_prev_info['bootpkg_line_found'] is True and 'bootpkg_retail_fuses' in updatedetails_prev_info and 'bootpkg_devunit_fuses' in updatedetails_prev_info:
        if updatedetails_info['bootpkg_retail_fuses'] != updatedetails_prev_info['bootpkg_retail_fuses'] or updatedetails_info['bootpkg_devunit_fuses'] != updatedetails_prev_info['bootpkg_devunit_fuses']:
            insert_text = "The anti-downgrade fuses were [[Fuses#Anti-downgrade|updated]].\n"

            text_section = {
                "search_text": "[[Fuses",
                "insert_text": insert_text
            }

            target["text_sections"].append(text_section)

if len(MetaOut['Ini1'])>0:
    text_section = {
        "search_text": "INI1",
        "insert_text": "[[Package2|INI1]] changes:\n" + MetaOut['Ini1']
    }
    target["text_sections"].append(text_section)

if len(target["text_sections"])>0:
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

if updatedetails_info['bootpkg_line_found'] is False or (updatedetails_info['bootpkg_line_found'] is True and 'bootpkg_retail_fuses' in updatedetails_info and 'bootpkg_devunit_fuses' in updatedetails_info):
    fuse_columns = []
    if 'bootpkg_retail_fuses' in updatedetails_info and 'bootpkg_devunit_fuses' in updatedetails_info:
        fuse_columns.append(updatedetails_info['bootpkg_retail_fuses'])
        fuse_columns.append(updatedetails_info['bootpkg_devunit_fuses'])

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

if 'bt_bsa_version_string' in updatedetails_info and 'bt_bsa_version_info_string' in updatedetails_info and 'bt_config_str' in updatedetails_info:
    page = {
        "page_title": "Bluetooth_Driver_services",
        "search_section": "== Versions ==",
        "targets": [
            {
                "search_section": "{|",
                "tables_updatever_range": [
                    {
                        "columns": [
                            updatedetails_info['bt_bsa_version_string'],
                            updatedetails_info['bt_bsa_version_info_string'],
                            updatedetails_info['bt_config_str'],
                        ],
                    },
                ],
            },
        ],
    }
    storage.append(page)

if 'wifi_fwstr' in updatedetails_info:
    page = {
        "page_title": "WLAN_services",
        "search_section": "= FwVersion =",
        "targets": [
            {
                "search_section": "{|",
                "tables_updatever_range": [
                    {
                        "columns": [
                            updatedetails_info['wifi_fwstr'],
                        ],
                    },
                ],
            },
        ],
    }
    storage.append(page)

with open(outpath, 'w') as json_f:
    json.dump(storage, json_f)

