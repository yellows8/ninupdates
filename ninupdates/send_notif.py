#!/usr/bin/python3
import sys
import os
import argparse
from shlex import quote
import asyncio

notif_config_admin = None

try:
    from notif_config import *
except ImportError:
    pass

parser = argparse.ArgumentParser(description='Send notification messages.')
parser.add_argument('msg', help='Notification message text.')
parser.add_argument('--irctarget', nargs='*', dest='irctarget', help='Target IRC filename. Multiple filenames can be specified as seperate args following --irctarget.')
parser.add_argument('--irc', nargs='?', dest='irc', const='', help='Send IRC notification, with optional message text which overrides msg.')
parser.add_argument('--fedi', nargs='?', dest='fedi', const='', help='Send Fediverse/Mastodon-API-compatible notification, with optional message text which overrides msg.')
parser.add_argument('--fedivisibility', nargs='?', dest='fedivisibility', help='String value for the visibility field (with --fedi), otherwise the account default is used.')
parser.add_argument('--twitter', nargs='?', dest='twitter', const='', help='Send Twitter notification, with optional message text which overrides msg.')
parser.add_argument('--webhook', nargs='?', dest='webhook', const='', help='Send webhook notification, with optional message text which overrides msg.')
parser.add_argument('--webhooktarget', dest='webhooktarget', help='Webhook target_hook id.')
parser.add_argument('--social', nargs='?', dest='social', const='', help='Send social notification, with optional message text which overrides msg. Same as specifying --fediverse and --twitter, those args can optionally be used seperately if overriding the msg is desired.')
parser.add_argument('--admin', nargs='?', dest='admin', const='', help='Send admin notification, with optional message text which overrides msg. Alias which sends notifs with targets specified by config. Other args if specified will override any fields loaded from config.')

args = parser.parse_args()

msg = args.msg

irc_target = None
irc_msg = None

fedi_msg = None
fedivisibility = None
twitter_msg = None

webhook_msg = None
webhooktarget = None

admin_msg = None
if args.admin is not None:
    admin_msg = args.admin
    if len(admin_msg)==0:
        admin_msg = msg
    if notif_config_admin is None:
        print("Admin notifs are not available since the config isn't set.")
    else:
        if 'irc' in notif_config_admin:
            irc_msg = admin_msg
            irc_target = notif_config_admin['irc']
        if 'fedi' in notif_config_admin:
            fedi_msg = admin_msg
            if 'msg_prefix' in notif_config_admin['fedi']:
                fedi_msg = "%s %s" % (notif_config_admin['fedi']['msg_prefix'], fedi_msg)
            if 'visibility' in notif_config_admin['fedi']:
                fedivisibility = notif_config_admin['fedi']['visibility']
        if 'webhook' in notif_config_admin:
            webhook_msg = admin_msg
            webhooktarget = notif_config_admin['webhook']

if args.irctarget is not None:
    irc_target = args.irctarget
if args.irc is not None:
    irc_msg = args.irc
if irc_target is None or irc_msg is None:
    irc_msg = None
else:
    if len(irc_msg)==0:
        irc_msg = msg

social_msg = args.social
if social_msg is not None:
    if len(social_msg)==0:
        social_msg = msg
    fedi_msg = social_msg
    twitter_msg = social_msg

if args.fedi is not None:
    fedi_msg = args.fedi
    if fedi_msg is not None and len(fedi_msg)==0:
        fedi_msg = msg

if args.fedivisibility is not None:
    fedivisibility = args.fedivisibility
    if len(fedivisibility)==0:
        fedivisibility = None

if args.twitter is not None:
    twitter_msg = args.twitter
    if twitter_msg is not None and len(twitter_msg)==0:
        twitter_msg = msg

if args.webhook is not None:
    webhook_msg = args.webhook
    if len(webhook_msg)==0:
        webhook_msg = msg

if args.webhooktarget is not None:
    webhooktarget = args.webhooktarget

async def run_notif(program, *args):
    proc = await asyncio.create_subprocess_exec(
        program, *args)
    return await proc.communicate()

notifcnt=0

if irc_msg is not None:
    notifcnt=notifcnt+1
    asyncio.run(run_notif("php", "send_irc.php", irc_msg, *irc_target))

if fedi_msg is not None:
    notifcnt=notifcnt+1
    args = [sys.executable, "./send_mastodon.py"]
    if fedivisibility is not None:
        args.append("--visibility")
        args.append(fedivisibility)
    args.append(fedi_msg)
    asyncio.run(run_notif(*args))

if twitter_msg is not None:
    notifcnt=notifcnt+1
    asyncio.run(run_notif("php", "tweet.php", twitter_msg))

if webhook_msg is not None:
    notifcnt=notifcnt+1
    args = ["./webhook.py", webhook_msg]
    if webhooktarget is not None:
        args.append(webhooktarget)
    asyncio.run(run_notif(sys.executable, *args))

if notifcnt==0:
    if admin_msg is not None:
        tmp = " --admin was specified, but the admin config didn't specify any targets."
    else:
        tmp = ""
    print("No notif was sent, notif targets must be specified.%s" % (tmp))
    sys.exit(1)

