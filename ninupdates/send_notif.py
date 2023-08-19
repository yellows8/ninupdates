#!/usr/bin/python3
import sys
import os
import argparse
from shlex import quote
import asyncio

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

args = parser.parse_args()

msg = args.msg
irc_target = args.irctarget
irc_msg = args.irc
if irc_target is None or irc_msg is None:
    irc_msg = None
else:
    if len(irc_msg)==0:
        irc_msg = msg

fedi_msg = None
twitter_msg = None

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

fedivisibility = args.fedivisibility
if fedivisibility is not None and len(fedivisibility)==0:
    fedivisibility = None

if args.twitter is not None:
    twitter_msg = args.twitter
    if twitter_msg is not None and len(twitter_msg)==0:
        twitter_msg = msg

webhook_msg = args.webhook
if webhook_msg is not None and len(webhook_msg)==0:
    webhook_msg = msg

webhooktarget = args.webhooktarget

async def run_notif(program, *args):
    proc = await asyncio.create_subprocess_exec(
        program, *args)
    return await proc.communicate()

if irc_msg is not None:
    asyncio.run(run_notif("php", "send_irc.php", irc_msg, *irc_target))

if fedi_msg is not None:
    args = [sys.executable, "./send_mastodon.py"]
    if fedivisibility is not None:
        args.append("--visibility")
        args.append(fedivisibility)
    args.append(fedi_msg)
    asyncio.run(run_notif(*args))

if twitter_msg is not None:
    asyncio.run(run_notif("php", "tweet.php", twitter_msg))

if webhook_msg is not None:
    args = ["./webhook.py", webhook_msg]
    if webhooktarget is not None:
        args.append(webhooktarget)
    asyncio.run(run_notif(sys.executable, *args))

