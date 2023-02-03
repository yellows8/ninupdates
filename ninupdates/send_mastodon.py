#!/usr/bin/python3
import sys
import os
from mastodon import Mastodon

if len(sys.argv) < 2:
    print("Usage: %s <message text content>" % sys.argv[0])
    sys.exit(1)

credpath = 'mastodon_usercred.secret'
if os.path.isfile(credpath) is False:
    print("Mastodon creds file doesn't exist.")
    sys.exit(2)

mastodon = Mastodon(access_token = credpath)
mastodon.toot(sys.argv[1])

