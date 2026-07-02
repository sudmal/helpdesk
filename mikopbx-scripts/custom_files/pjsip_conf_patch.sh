#!/bin/bash

configPath="$1" # Path to the original config file

# Example 1: Replace all values max_contacts = 5 to max_contacts = 1 on pjsip.conf
# sed -i 's/max_contacts = 5/max_contacts = 1/g' "$configPath"

# Example 2: Change value max_contacts only for peer with extension 226 on pjsip.conf
# sed -i '/^\[226\]$/,/^\[/ s/max_contacts = 5/max_contacts = 2/' "$configPath"

# Example 3: Add en extra string into [playback-exit] section after the "same => n,Hangup()" string on extensions.conf
# sed -i '/^\[playback-exit\]$/,/^\[/ s/^\(\s*same => n,Hangup()\)/\1\n\tsame => n,NoOp("Your NoOp comment here")/' "$configPath"

# Attention! You will see changes after the background worker processes the script or after rebooting the system.
sed -i 's/qualify_frequency = 60/qualify_frequency = 10/g; s/qualify_timeout = 5/qualify_timeout = 3.0/g' /etc/asterisk/pjsip.conf
sed -i '/type = endpoint/a rtp_keepalive = 30' /etc/asterisk/pjsip.conf
sed -i 's/default_expiration = 3600/default_expiration = 120/g' /etc/asterisk/pjsip.conf

# DND detection: постоянно включённый PJSIP-логгер, нужен чтобы ловить точный
# текст SIP-ответа "486 Do Not Disturb" (стандартный DIALSTATUS его не сохраняет).
sed -i '/^\[global\]/a debug=yes' /etc/asterisk/pjsip.conf
