#!/usr/bin/python3
import sys
import requests

# This should be set in webhook_config.py, or have an empty file to disable it.
webhookcfg = None

from webhook_config import *

if webhookcfg is None:
    print("Webhook config is missing.")
    sys.exit(1)

if len(sys.argv) < 2:
    print("Usage: %s <message text content> [target_hook id]" % sys.argv[0])
    sys.exit(1)

target_hook = 0
if len(sys.argv) >= 3:
    target_hook = int(sys.argv[2])

if target_hook >= len(webhookcfg):
    print("target_hook is out-of-bounds for the config.")
    sys.exit(1)

headers = {'User-Agent': 'ninupdates-bot (https://github.com/yellows8/ninupdates/, 1.1.0)'}

for hookcfg in webhookcfg[target_hook]:
    payload = {hookcfg['json_msg_field_name']: sys.argv[1]}
    r = requests.post(hookcfg['url'], headers=headers, json=payload)

