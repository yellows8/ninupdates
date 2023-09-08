#!/usr/bin/python3
import sys
import os
import argparse
from mastodon import Mastodon

parser = argparse.ArgumentParser(description='Send Fediverse/Mastodon-API-compatible messages.')
parser.add_argument('msg', help='Notification message text.')
parser.add_argument('--visibility', nargs='?', dest='visibility', help='String value for the visibility field, otherwise the account default is used.')
parser.add_argument('--delete', nargs='?', dest='delete', help='If specified, this status id is deleted instead of posting the input message.')

args = parser.parse_args()

msg = args.msg
visibility = args.visibility
if visibility is not None and len(visibility)==0:
    visibility = None

delete_id = args.delete
if delete_id is not None and len(delete_id)==0:
    delete_id = None

# This creds file should be located in the same directory as this script.
credpath = os.path.join(os.path.realpath(os.path.dirname(__file__)), 'mastodon_usercred.secret')
if os.path.isfile(credpath) is False:
    print("Mastodon creds file doesn't exist.")
    sys.exit(2)

mastodon = Mastodon(access_token = credpath)

if delete_id is None:
    mastodon.status_post(msg, visibility=visibility)
else:
    mastodon.status_delete(delete_id)

