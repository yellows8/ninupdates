#!/usr/bin/python3
import sys
import os
import argparse
from shlex import quote

parser = argparse.ArgumentParser(description='Send notification messages.')
parser.add_argument('msg', help='Notification message text.')
parser.add_argument('--irctarget', dest='irctarget', help='Target IRC filename.')
parser.add_argument('--irc', nargs='?', dest='irc', const='', help='Send IRC notification, with optional message text which overrides msg.')
parser.add_argument('--twitter', nargs='?', dest='twitter', const='', help='Send Twitter notification, with optional message text which overrides msg.')
parser.add_argument('--webhook', nargs='?', dest='webhook', const='', help='Send webhook notification, with optional message text which overrides msg.')

args = parser.parse_args()

msg = args.msg
irc_target = args.irctarget
irc_msg = args.irc
if irc_target is None or irc_msg is None:
    irc_msg = None
else:
    if len(irc_msg)==0:
        irc_msg = msg

twitter_msg = args.twitter
if twitter_msg is not None and len(twitter_msg)==0:
    twitter_msg = msg

webhook_msg = args.webhook
if webhook_msg is not None and len(webhook_msg)==0:
    webhook_msg = msg

if irc_msg is not None:
    os.system("php send_irc.php %s %s" % (quote(irc_msg), quote(irc_target)))

if twitter_msg is not None:
    os.system("php tweet_cli.php %s" % quote(twitter_msg))

if webhook_msg is not None:
    os.system("./webhook.py %s" % quote(webhook_msg))

