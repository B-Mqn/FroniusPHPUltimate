# FroniusPHPUltimate
PVOutput PHP uploader script for fronius inverters

Gives every API option available from the inverter that can be uploaded and as a PVOutput donator lets you choose what to be uploaded to V7-V12 extended data.
Does single or three phase inverter with/without the smart meter.

i have little to no coding experience and no PHP so its messy but it works.

Its work in progress but for my setup has been running sstable for months.

i run as a Cron task in 5 min intervals on my Pi as below
crontab -e
*/5 * * * * /usr/bin/php /home/pi/froniusUltimate.php
