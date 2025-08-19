#!/usr/bin/python3
import sys
import os
import argparse
import json
import subprocess
import csv
import configparser
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

page_prefix = ""

Platform = "0100"
if insystem == "bee":
    Platform = "0400"
    page_prefix = "Switch 2: "

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
    Platform + '000000000800': { # CertStore
        'wikipage': 'SSL_services#CertStore',
    },
    Platform + '000000000806': { # NgWord
        'ignore': True,
    },
    Platform + '000000000809': { # SystemVersion
        'wikipage': 'System_Version_Title',
    },
    Platform + '00000000080E': { # TimeZoneBinary
        'ignore': True,
    },
    Platform + '000000000818': { # FirmwareDebugSettings
        'wikipage': page_prefix + 'System_Settings',
    },
    Platform + '00000000081B': { # BootImagePackageExFat
        'group': Platform + '000000000819',
    },
    Platform + '000000000823': { # NgWord2
        'ignore': True,
    },
    Platform + '00000000100F': { # LibAppletOff
        'wikipage': 'Internet_Browser',
    },
}

if insystem == "hac":
    titleinfo['0100000000000801'] = { # ErrorMessage
        'ignore': True,
    }
    titleinfo[Platform + '00000000081A'] = { # BootImagePackageSafe
        'group': Platform + '000000000819',
    }
    titleinfo[Platform + '00000000081C'] = { # BootImagePackageExFatSafe
        'group': Platform + '000000000819',
    }
    titleinfo['010000000000081F'] = { # PlatformConfigIcosa
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['0100000000000820'] = { # PlatformConfigCopper
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['0100000000000821'] = { # PlatformConfigHoag
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['0100000000000824'] = { # PlatformConfigIcosaMariko
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['0100000000000829'] = { # PlatformConfigCalcio
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['0100000000000831'] = { # PlatformConfigAula
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['010000000000100A'] = { # LibAppletWeb
        'wikipage': 'Internet_Browser',
        'group': Platform + '00000000100F',
    }
    titleinfo['010000000000100B'] = { # LibAppletShop
        'wikipage': 'Internet_Browser',
        'group': Platform + '00000000100F',
    }
    titleinfo['0100000000001010'] = { # LibAppletLns
        'wikipage': 'Internet_Browser',
        'group': Platform + '00000000100F',
    }
    titleinfo['0100000000001011'] = { # LibAppletAuth
        'wikipage': 'Internet_Browser',
        'group': Platform + '00000000100F',
    }
elif insystem == "bee":
    titleinfo['0400000000000834'] = {
        'wikipage': page_prefix + 'System_Settings',
        'group': Platform + '000000000818',
    }
    titleinfo['0400000000000835'] = { # ErrorMessageUtf8
        'ignore': True,
    }
    titleinfo['0400000000001042'] = { # LibAppletShop
        'wikipage': 'Internet_Browser',
        'group': Platform + '00000000100F',
    }
    titleinfo['0400000000001043'] = { # OpenWeb
        'wikipage': 'Internet_Browser',
        'group': Platform + '00000000100F',
    }

dirfilter_msgs = {
    '/message/': 'Various data',
    '/lyt/': 'Various data',
    '/ui/': 'Various data',
    '/nro/netfront/': 'Various data',
}

def TitleDescStrip(Desc):
    Pos = Desc.find("-sysmodule")
    if Pos!=-1:
        return Desc[:Pos]
    else:
        PosStart = Desc.find('"')
        PosEnd = Desc.find('" applet')
        if PosStart!=-1 and PosEnd!=-1:
            return Desc[PosStart+1:PosEnd]
        else:
            Pos = Desc.find(" (")
            if Pos!=-1:
                return Desc[:Pos]
    return Desc

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

def GetTitlePrevInfo(Id):
    TmpRow = None
    Regions = ["G"]
    if insystem == "hac":
        Regions.append("C")
    for Region in Regions:
        apiout = api_cli(Region, Id, args=["--prevreport=%s" % (reportdate)])
        if apiout!="":
            ApiLines = apiout.split("\n")
            Reader = csv.DictReader(ApiLines, delimiter=',', quoting=csv.QUOTE_NONE)
            for Row in Reader:
                TmpRow = Row # Only handle the last row.
            if TmpRow is not None:
                break
    return TmpRow

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

    if inlines is None:
        return out

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
    Id = Id[4:]
    return Id=='000000000819' or Id=='00000000081A' or Id=='00000000081B' or Id=='00000000081C'

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

def ProcessMetaDiffPrintValue(Key, Val):
    if Key == 'PermissionType' or Key == 'MappingType' or Key == 'Name':
        return "%s" % (Val)
    elif Key[:9] == 'ProgramId':
        return "%016X" % (Val)
    else:
        return "0x%X" % (Val)

def ProcessMetaDiffKcRegionMap(TmpKey, Val):
    TmpText = "["

    Pos=0
    for CurVal in Val:
        if Pos>0:
            TmpText = TmpText + ", "
        Pos=Pos+1
        if TmpKey == 'RegionsType':
            EntVal = nx_meta.metaKcRegionMapTypeGetStr(CurVal)
        else:
            if Val==0:
                EntVal = "R-"
            else:
                EntVal = "RW"
        TmpText = TmpText + EntVal

    return TmpText + "]"

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
                    if KcKey == 'EnableSystemCalls':
                        TmpStr = "!TABLE[SVC,0,0x%02X,2,NOLINK]" % (Id)
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

                                if ChangeKey == 'Updated' and DescKey != 'BeginAddress':
                                    Vals = "%s -> %s" % (ProcessMetaDiffPrintValue(DescKey, DescValue[0]), ProcessMetaDiffPrintValue(DescKey, DescValue[1]))
                                else:
                                    Vals = "%s" % (ProcessMetaDiffPrintValue(DescKey, DescValue))

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

                        if TmpKey == 'Version':
                            if ChangeKey == 'Updated':
                                Vals = "%u.%u -> %u.%u" % (TmpValue[0]['Major'], TmpValue[0]['Minor'], TmpValue[1]['Major'], TmpValue[0]['Minor'])
                            else:
                                Vals = "0x%X" % (TmpValue['Major'], TmpValue['Minor'])
                        elif TmpKey == 'RegionsType' or TmpKey == 'RegionsIsReadOnly':
                            if ChangeKey == 'Updated':
                                Vals = ProcessMetaDiffKcRegionMap(TmpKey, TmpValue[0]) + " -> " + ProcessMetaDiffKcRegionMap(TmpKey, TmpValue[1])
                            else:
                                Vals = ProcessMetaDiffKcRegionMap(TmpKey, TmpValue)
                        else:
                            if ChangeKey == 'Updated':
                                Vals = "0x%X -> 0x%X" % (TmpValue[0], TmpValue[1])
                            else:
                                Vals = "0x%X" % (TmpValue)

                        if ChangeKey == 'Updated':
                            Text = Text + " = %s" % (Vals)
                        else:
                            Text = Text + "=%s" % (Vals)
            Text = Text + "."

    return Text

def ProcessMetaDiffFsAccessMask(Mask):
    TmpStr = "("
    Bits = nx_meta.metaMaskToList(Mask)

    for CurBit in Bits:
        if len(TmpStr)>0 and TmpStr[-1]!=' ' and TmpStr[-1]!='(':
            TmpStr = TmpStr + ", "
        TmpStr = TmpStr + "!TABLE[NPDM,0,%u,1,DEFAULT=bit%u,MATCH=EXACT]" % (CurBit, CurBit)

    TmpStr = TmpStr + ")"
    return TmpStr

def ProcessMetaDiffMeta(Desc, Diff, IgnoreVersion=True):
    Text = "* %s: " % (Desc)

    for Key, Value in Diff.items():
        if Key == 'Acid':
            for AcidKey, AcidValue in Diff[Key].items():
                if 'Updated' in AcidValue:
                    if len(Text)>0 and Text[-1]!=' ':
                        Text = Text + " "
                    Text = Text + "Acid.%s updated: %s -> %s." % (AcidKey, ProcessMetaDiffPrintValue(AcidKey, AcidValue['Updated'][0]), ProcessMetaDiffPrintValue(AcidKey, AcidValue['Updated'][1]))
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
                        Text = Text + "%s updated: %s -> %s." % (AciKey, ProcessMetaDiffPrintValue(AciKey, AciValue['Updated'][0]), ProcessMetaDiffPrintValue(AciKey, AciValue['Updated'][1]))
                elif AciKey=='Fac':
                    for FacKey, FacValue in Diff[Key][AciKey].items():
                        if FacKey != 'ContentOwnerInfo' and FacKey != 'SaveDataOwnerInfo':
                            if 'Updated' in FacValue:
                                if len(Text)>0 and Text[-1]!=' ':
                                    Text = Text + " "

                                if FacKey == 'Padding':
                                    Vals = "%s -> %s" % (FacValue['Updated'][0], FacValue['Updated'][1])
                                elif FacKey != 'FsAccessFlag':
                                    Vals = "0x%X -> 0x%X" % (FacValue['Updated'][0], FacValue['Updated'][1])
                                else:
                                    MaskAdded = FacValue['Updated'][1] & ~FacValue['Updated'][0]
                                    MaskRemoved = FacValue['Updated'][0] & ~FacValue['Updated'][1]
                                    Vals = ""
                                    if MaskAdded!=0:
                                        Vals = "set bitmask 0x%016X %s" % (MaskAdded, ProcessMetaDiffFsAccessMask(MaskAdded))
                                    if MaskRemoved!=0:
                                        if len(Vals)>0:
                                            Vals = Vals + ", "
                                        Vals = Vals + "cleared bitmask 0x%016X %s" % (MaskRemoved, ProcessMetaDiffFsAccessMask(MaskRemoved))

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
                                            TmpDesc = " (%s)" % (TitleDescStrip(TmpDesc))
                                        if FacKey=='SaveDataOwnerInfo':
                                            TmpText = TmpText + "%s %s%s access %s -> %s" % (ChangeKey.lower(), IdStr, TmpDesc, nx_meta.metaSaveDataOwnerAccessToStr(Info[0]['Access']), nx_meta.metaSaveDataOwnerAccessToStr(Info[1]['Access']))
                                    else:
                                        IdStr = "%016X" % (Info['Id'])
                                        TmpDesc = get_titledesc(IdStr)
                                        if TmpDesc=='N/A':
                                            TmpDesc = ""
                                        else:
                                            TmpDesc = " (%s)" % (TitleDescStrip(TmpDesc))
                                        TmpText = TmpText + "%s %s%s" % (ChangeKey.lower(), IdStr, TmpDesc)
                                        if FacKey=='SaveDataOwnerInfo':
                                            TmpText = TmpText + " access %s" % (nx_meta.metaSaveDataOwnerAccessToStr(Info['Access']))
                            if len(TmpText)>0:
                                if len(Text)>0 and Text[-1]!=' ':
                                    Text = Text + " "
                                Text = Text + TmpText + "."
                elif AciKey=='Sac':
                    for SacKey, SacValue in Diff[Key][AciKey].items():
                        TmpStr = " " + SacKey.lower()
                        if TmpStr == " client":
                            TmpStr = ""
                        TmpText = "Service%s access: " % (TmpStr)
                        SacChanges = {}
                        SacChanges['Updated'] = []
                        SacChanges['Added'] = []
                        SacChanges['Removed'] = []
                        for TmpKey, TmpValue in Diff[Key][AciKey][SacKey].items():
                            for ChangeKey, ChangeValue in Diff[Key][AciKey][SacKey][TmpKey].items():
                                CurText = "%s" % (TmpKey)
                                if ChangeKey=='Updated':
                                    CurText = CurText + " (control byte 0x%X -> 0x%X)" % (ChangeValue[0], ChangeValue[1])
                                elif ChangeValue & 0x78: # Reserved bits set.
                                    CurText = CurText + " (control byte 0x%X)" % (ChangeValue)
                                SacChanges[ChangeKey].append(CurText)

                        for ChangeKey, ChangeValue in SacChanges.items():
                            if len(ChangeValue)>0:
                                if len(TmpText)>0 and TmpText[-1]!=' ':
                                    TmpText = TmpText + ", "
                                TmpText = TmpText + "%s " % (ChangeKey.lower())
                                for SacChange in ChangeValue:
                                    if len(TmpText)>0 and TmpText[-1]!=' ':
                                        TmpText = TmpText + ", "
                                    TmpText = TmpText + SacChange

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
                                Vals = "%s -> %s" % (ProcessMetaDiffPrintValue(KipKey, KipValue[0]), ProcessMetaDiffPrintValue(KipKey, KipValue[1]))

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
        else:
            Desc = TitleDescStrip(Desc)
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

                TitleRow = GetTitlePrevInfo(Id)
                if TitleRow is not None:
                    PrevReportDate = TitleRow['Report date']

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

updatedetails_info = parse_updatedetails(updatedetails)

SystemUpdateInfo = GetTitlePrevInfo(Platform + "000000000816")
if SystemUpdateInfo is None:
    print("Failed to get API info for the SystemUpdate title.")
    sys.exit(1)
else:
    updatever_prev = SystemUpdateInfo['Update version']
    TmpPos = updatever_prev.find('_rebootless')
    if TmpPos!=-1:
        updatever_prev = updatever_prev[:TmpPos]

bootpkg_masterkey = None
updatedetails_prev_info = {}

if updatedetails_info['bootpkg_line_found'] is True:
    apiout = api_cli("G", Platform + "000000000819", args=["--prevreport=%s" % (reportdate)])
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

    table_columns = [
        updatever,
        sysver_fullversionstr,
        sysver_hexstr,
        sysver_digest,
    ]

    search_prefix = ""
    if insystem == "hac":
        search_prefix = "== NX ==\n"
    elif insystem == "bee":
        search_prefix = "== Ounce ==\n"

    table_columns.append("")

    page = {
        "page_title": "System_Version_Title",
        "search_section": search_prefix + "=== Retail ===",
        "targets": [
            {
                "search_section": "{|",
                "insert_row_tables": [
                    {
                        "search_text": updatever,
                        "search_column": 0,
                        "columns": table_columns,
                    },
                ],
            },
        ],
    }
    storage.append(page)

else:
    print("The sysver files don't exist, skipping page handling for that.")

info_path = "%s/sdk_versions.info" % (updatedir)
sdk_versions = None
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

if sdk_versions is not None or insystem == "bee":
    if insystem == "bee" and sdk_versions is None:
        sdk_versions = ""

    build_date = None
    titledirpath = "%s/%s" % (updatedir, Platform + "000000000819")
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

    if insystem == "bee":
        build_date = ""
        print("Using an empty build_date since system is bee.")

    if build_date is None:
        print("Loading the build_date failed, skipping the System_Versions page.")
    else:
        if page_prefix=="":
            updatever_link = "[[%s]]" % (updatever)
        else:
            updatever_link = "[[%s%s|%s]]" % (page_prefix, updatever, updatever)
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
    diff_titles_sorted = sorted(diff_titles.items())
    diff_titles = {}
    for titleid, title in diff_titles_sorted:
        diff_titles[titleid] = title
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

def SettingsGetValue(Key, Value):
    TmpPos = Value.find("!")
    if TmpPos==-1:
        print("SettingsGetValue(\"%s\", \"%s\"): Failed to find '!', returning the raw value." % (Key, Value))
        return Value
    else:
        ValueType = Value[:TmpPos]
        ValueData = Value[TmpPos+1:]
        if ValueType=="str":
            return ValueData
        elif ValueType=="u8":
            ValueDataTmp = int(ValueData, 16)
            if ValueDataTmp==0:
                return "false"
            elif ValueDataTmp==1:
                return "true"
            else:
                print("SettingsGetValue(\"%s\", \"%s\"): Unrecogized value for u8, returning the raw value." % (Key, Value))
                return Value
        elif ValueType=="u32":
            ValueDataTmp = int(ValueData, 16)
            return "%u (0x%x)" % (ValueDataTmp, ValueDataTmp)
        else:
            print("SettingsGetValue(\"%s\", \"%s\"): Unrecogized type, returning the raw value." % (Key, Value))
            return Value

def ProcessSystemSettingsDiff(Diff):
    SettingsPage = {
        "page_title": page_prefix + "System_Settings",
        "targets": []
    }

    for ChangeKey, ChangeValue in Diff.items():
        for Section, SectionValue in Diff[ChangeKey].items():
            SectionHeader = "= %s =" % (Section)

            TmpTarget = {
                "search_section": SectionHeader,
                "search_section_end": "",
                "text_sections": [],
                "insert_row_tables": []
            }

            if ChangeKey == 'AddedSections':
                SectionPrev = None
                if 'SectionPrev' in SectionValue:
                    SectionPrev = SectionValue['SectionPrev']
                IsSectionLast = SectionValue['IsSectionLast']
                ConfigNames = SectionValue['ConfigNames']

                Found = False
                for CurName in ConfigNames:
                    if CurName=='FirmwareDebugSettings':
                        Found = True
                        break

                TmpStr = ""
                if Found is False:
                    TmpStr = " (only present in PlatformConfig* SystemData)"

                InsertText = "%s\nThis class does not exist before %s%s.\n\n" % (SectionHeader, updatever, TmpStr)
                InsertText = InsertText + "{| class=\"wikitable\" border=\"1\"\n|-\n"
                InsertText = InsertText + "! Name || Versions || Default Values || Description\n"

                InsertText = InsertText + "|-\n"

                # The table is filled in via insert_row_tables below, in case the section already exists.

                InsertText = InsertText + "|}\n"

                if SectionPrev is not None:
                    InsertText = "\n" + InsertText
                else:
                    InsertText = InsertText + "\n"

                TextSection = {
                    "search_text": SectionHeader + "\n",
                    "insert_before_text": "=",
                    "insert_text": InsertText
                }
                if SectionPrev is None:
                    del(TmpTarget["search_section"])
                    TmpTarget["full_page"] = True
                    TextSection["insert_before_text"] = "="
                else:
                    TmpTarget["search_section"] = "= %s =" % (SectionPrev)
                    if IsSectionLast is False:
                        TextSection["insert_before_text"] = "\n="
                    else:
                        del(TextSection["insert_before_text"])

                TmpTarget["text_sections"].append(TextSection)
            else:
                TmpTarget["search_section_end"] = "|}"
                for Key, ConfigNames in Diff[ChangeKey][Section].items():
                    Value = ""
                    ConfigData = []

                    # Create a list where each unique Value is associated with the ConfigNames using it.
                    for ConfigName, ValueData in Diff[ChangeKey][Section][Key].items():
                        ConfigEntry = None
                        for CurData in ConfigData:
                            if CurData['Value'] == ValueData:
                                ConfigEntry = CurData
                                CurData['Names'].append(ConfigName)
                                break
                        if ConfigEntry is None:
                            ConfigData.append({'Value': ValueData, 'Names': [ConfigName]})

                    for CurData in ConfigData:
                        ValueStr = SettingsGetValue("%s.%s" % (Section, Key), CurData['Value'])
                        if CurData['Names'][0]!='FirmwareDebugSettings' or len(CurData['Names'])>1:
                            ConfigStr = ""
                            for ConfigName in CurData['Names']:
                                if len(ConfigStr)>0:
                                    ConfigStr = ConfigStr + "/"
                                ConfigStr = ConfigStr + ConfigName
                            ValueStr = "%s = %s" % (ConfigStr, ValueStr)
                        if len(Value)>0:
                            Value = Value + "\n"
                        Value = Value + ValueStr

                    EditText = "-" + updatever_prev

                    InsertRowTable = {
                        "search_text": Key,
                        "search_text_rowspan": [updatever + "+", updatever + "-"],
                        "search_column": 0,
                        "search_column_rowspan": 1,
                        "search_type": 1,
                        "search_type_rowspan": 2,
                    }

                    if ChangeKey == 'Added':
                        InsertRowTable["sort"] = 0
                    InsertRowTable["columns"] = [
                        "rowspan=\"1\" |%s" % (Key),
                        "%s+" % (updatever),
                        Value,
                        "rowspan=\"1\" |"]

                    if ChangeKey == 'Updated' or ChangeKey == 'Removed':
                        InsertRowTable["rowspan_edit_prev"] = [
                            {
                                "column": 1,
                                "findreplace_list": [
                                    {
                                        "find_text": "+",
                                        "replace_text": EditText
                                    },
                                ],
                            },
                        ]

                    if ChangeKey == 'Updated':
                        InsertRowTable["columns"] = [
                            "%s+" % (updatever),
                            Value]
                    elif ChangeKey == 'Removed':
                        InsertRowTable["columns"] = []
                        InsertRowTable["search_text_rowspan"] = EditText
                        InsertRowTable["search_type_rowspan"] = 0

                    TmpTarget["insert_row_tables"].append(InsertRowTable)

            if len(TmpTarget["text_sections"])>0 or len(TmpTarget["insert_row_tables"])>0:
                SettingsPage["targets"].append(TmpTarget)

    if len(SettingsPage["targets"])>0:
        storage.append(SettingsPage)

def DiffSettings(ConfigName, Config, ConfigPrev, Diff):
    SectionPrev = None
    SectionLast = list(Config)[-1]

    for Section in Config:
        if Section == 'DEFAULT':
            continue
        SectionHeader = "= %s =" % (Section)

        if Section not in ConfigPrev: # Section added
            if Section not in Diff['AddedSections']:
                Diff['AddedSections'][Section] = {'ConfigNames': []}
                Diff['AddedSections'][Section]['IsSectionLast'] = Section == SectionLast
            if 'SectionPrev' not in Diff['AddedSections'][Section] and SectionPrev is not None:
                Diff['AddedSections'][Section]['SectionPrev'] = SectionPrev
            Diff['AddedSections'][Section]['ConfigNames'].append(ConfigName)

            for Key, Value in Config[Section].items():
                if Section not in Diff['Added']:
                    Diff['Added'][Section] = {}
                if Key not in Diff['Added'][Section]:
                    Diff['Added'][Section][Key] = {}
                Diff['Added'][Section][Key][ConfigName] = Value
        else:
            for Key, Value in Config[Section].items():
                if Key not in ConfigPrev[Section]: # Field added
                    #print("%s.%s added" % (Section, Key))

                    if Section not in Diff['Added']:
                        Diff['Added'][Section] = {}
                    if Key not in Diff['Added'][Section]:
                        Diff['Added'][Section][Key] = {}
                    Diff['Added'][Section][Key][ConfigName] = Value

                elif Value != ConfigPrev[Section][Key]: # Field updated
                    #print("%s.%s: %s -> %s" % (Section, Key, Value, ConfigPrev[Section][Key]))

                    if Section not in Diff['Updated']:
                        Diff['Updated'][Section] = {}
                    if Key not in Diff['Updated'][Section]:
                        Diff['Updated'][Section][Key] = {}
                    Diff['Updated'][Section][Key][ConfigName] = Value

        SectionPrev = Section

    for Section in ConfigPrev:
        for Key, Value in ConfigPrev[Section].items():
            if Section not in Config or Key not in Config[Section]: # Field removed
                #print("%s.%s removed" % (Section, Key))

                if Section not in Diff['Removed']:
                    Diff['Removed'][Section] = {}
                if Key not in Diff['Removed'][Section]:
                    Diff['Removed'][Section][Key] = {}
                Diff['Removed'][Section][Key][ConfigName] = Value

def ProcessSystemSettings(Titles):
    Diff = {}
    Diff['AddedSections'] = {}
    Diff['Added'] = {}
    Diff['Removed'] = {}
    Diff['Updated'] = {}

    CfgStr = "PlatformConfig"
    CfgStrLen = len(CfgStr)

    for Id, Title in Titles.items():
        if Id[4:]=='000000000818' or (Titles[Id]['desc']!="N/A" and Titles[Id]['desc'][:CfgStrLen] == CfgStr):
            for Change in Titles[Id]['changes']:
                if Change['type'] == 'updated':
                    TmpPath = os.path.join(Title['dirpath'], "..")
                    TmpPath = os.path.join(TmpPath, "settings_parseout.info")
                    TmpPathPrev = os.path.join(Title['dirpath_prev'], "..")
                    TmpPathPrev = os.path.join(TmpPathPrev, "settings_parseout.info")

                    Desc = Id
                    if Titles[Id]['desc']!="N/A":
                        Desc = Titles[Id]['desc']
                        if Desc[:CfgStrLen] == CfgStr:
                            Desc = Desc[CfgStrLen:]

                    if os.path.exists(TmpPath) and os.path.exists(TmpPathPrev):
                        Config = configparser.ConfigParser(interpolation=None)
                        ConfigPrev = configparser.ConfigParser(interpolation=None)
                        Config.read(TmpPath)
                        ConfigPrev.read(TmpPathPrev)
                        DiffSettings(Desc, Config, ConfigPrev, Diff)
                    else:
                        print("ProcessSystemSettings(): File doesn't exist. TmpPath = %s, TmpPathPrev = %s" % (TmpPath, TmpPathPrev))

                    break

    ProcessSystemSettingsDiff(Diff)

page = {
    "page_title": "!UPDATEVER",
    "search_section": "==System Titles==",
    "targets": []
}

target = {
    "search_section_end": "\n=",
    "parse_tables": [],
    "text_sections": []
}

bootpkgs_text = ""

MetaOut = GetMetaText(updatedir)

insert_text = "[[NPDM]] changes (besides usual version-bump):"

if len(MetaOut['Meta'])>0 or len(MetaOut['Ini1'])>0:
    target["parse_tables"].append({"page_title": "SVC", "search_section": "= System calls"})
    target["parse_tables"].append({"page_title": "NPDM", "search_section": "== FsAccessFlag"})

if len(MetaOut['Meta'])>0:
    insert_text = insert_text + "\n" + MetaOut['Meta']
else:
    insert_text = insert_text + " none.\n"

text_section = {
    "search_text": "NPDM",
    "insert_text": insert_text
}
if len(MetaOut['Meta'])>0:
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

        if titleid[4:]=='000000000800': # CertStore
            process_certstore(title, title_text)

    text_section = {
        "search_text": "RomFs",
        "insert_text": insert_text
    }

    #print(insert_text)
    target["text_sections"].append(text_section)

    ProcessSystemSettings(diff_titles)

if len(target["text_sections"])>0:
    page["targets"].append(target)

target = {
    "search_section_end": "See Also==",
    "text_sections": []
}

if len(bootpkgs_text)>0:
    insert_text = "\n=== BootImagePackages ===\nRomFs changes:\n"

    # If all bootpkgs are present, remove the title-listing. Could just compare the full "name/name/..." str, but don't assume order.
    pos = bootpkgs_text.find(": ")
    if pos!=-1 and insystem == "hac":
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
    "parse_tables": [],
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
    target["parse_tables"].append({"page_title": "SVC", "search_section": "= System calls"})
    text_section = {
        "search_text": "INI1",
        "insert_text": "[[Package2|INI1]] changes:\n" + MetaOut['Ini1']
    }
    target["text_sections"].append(text_section)

if len(target["text_sections"])>0:
    page["targets"].append(target)

if len(page["targets"])>0:
    storage.append(page)

if insystem == "hac" and (updatedetails_info['bootpkg_line_found'] is False or (updatedetails_info['bootpkg_line_found'] is True and 'bootpkg_retail_fuses' in updatedetails_info and 'bootpkg_devunit_fuses' in updatedetails_info)):
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

