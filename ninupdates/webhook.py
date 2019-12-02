#!/usr/bin/python3
import sys
import requests

# These should be set in webhook_config.py, or have an empty file to disable it.
webhookcfg_url = None
webhookcfg_json_msg_field_name = None

from webhook_config import *

if webhookcfg_url is None or webhookcfg_json_msg_field_name is None:
    print("Webhook config is missing.")
    sys.exit(1)

if len(sys.argv) < 2:
    print("Usage: %s <message text content>" % sys.argv[0])
    sys.exit(1)

headers = {'User-Agent': 'ninupdates-bot (https://github.com/yellows8/ninupdates/, 1.0.0)'}
payload = {webhookcfg_json_msg_field_name: sys.argv[1]}

r = requests.post(webhookcfg_url, headers=headers, json=payload)

