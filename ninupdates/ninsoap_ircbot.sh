#!/bin/bash

echo "Running fresh ninsoap scan..."
php /home/yellows8/ninupdates/ninsoap.php > /home/yellows8/ninupdates/ninsoap_ircbottmp
scantext="System update available for regions"
echo -n "Total detected title-listing changes for each of the scanned platforms: " && grep -c "$scantext" /home/yellows8/ninupdates/ninsoap_ircbottmp
grep "$scantext" /home/yellows8/ninupdates/ninsoap_ircbottmp
echo "Scan finished."

