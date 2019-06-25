#Fleet-Yo!#
Fleet management and tracking tool for EVE Online.
[Source](https://bitbucket.org/snitchashor/fleet-yo/src/master/)
Copyright 2019 Snitch Ashor of MBLOC.

Fleet-Yo is a fleet management and tracking tool intended for small gangs and medium sized fleets. This tool is inspired by others out there which made use of the ingame browser (namely the Agony Fleet Manager) but also newer ones (Erik Kalkoken's Fleet report). Being a thing of the past I replaced all the ingame browser features with API calls using the EVE swagger interface, added some extra convenience and a few features, I thought might be useful and here we go.

#Requirements#
+ php 7.1+
+ php-curl
+ php-gmp
+ php-mbstring
+ MySQL 5.7+
+ php-mysqli
+ For certain features (cookies), site should be running via ssl

#Installation#

1. Create a MySQL Database for the app.
2. Import schema.sql from the SQL subfolder
3. Go to https://developers.eveonline.com/ and register an app with the following scopes:
    + esi-characters.read_notifications.v1
    + esi-location.read_location.v1
    + esi-location.read_ship_type.v1
    + esi-universe.read_structures.v1
    + esi-ui.write_waypoint.v1
    + esi-fleets.read_fleet.v1
    + esi-fleets.write_fleet.v1

    The callback url should be http(s)://<domain>/<app path>/login.php

4. Rename config.php.sample to config.php and edit it. Fill in the database and developer app credentials, sitename, cookiedomain, useragent... and put a random string for the salt. This one is used to add some security to authentication cookies. Add at least one admin by his or her characterID. If you want to keep track of what you added you can use associative arrays like array("Snitch" => 90976676,). If you want restrict access to certain Characters, Corporations or Alliances, add them to the allowed entities section. If you leave all three sections empty, access will be public.
5. Run the file cron_updatesde.php once in order to fetch the required static data from fuzzwork. (Alternatively, check sql/required.txt and import the mentioned tables manually)
6. Setup cron_runningfleets.php to run e.g. once every 10 minutes.
7. (Optional) Setup cron_updatesde.php and cron_clearcache.php to run once a day or less frequent (SDE will only be updated if it changed).
8. (Optional but appreciated) Log in to EVE and throw some ISK at Snitch Ashor

Done. If you need any help come find me on the tweetfleet slack.

#Version history#

+ 0.1b First public release
+ 1.0 Added Statistics

#Artwork and stuff#
EVE Online, the EVE logo, EVE and all associated logos and designs are the intellectual property of CCP hf. All artwork, screenshots, characters, vehicles, storylines, world facts or other recognizable features of the intellectual property relating to these trademarks are likewise the intellectual property of CCP hf. EVE Online and the EVE logo are the registered trademarks of CCP hf. All rights are reserved worldwide. All other trademarks are the property of their respective owners. CCP hf. has granted permission to Moon mining overview to use EVE Online and all associated logos and designs for promotional and information purposes on its website but does not endorse, and is not in any way affiliated with, BLOC moons. CCP is in no way responsible for the content on or functioning of this website, nor can it be liable for any damage arising from the use of this website.

#Thanks to...#
A lot of helpful people in #esi and #sso on tweetfleet.  
Steve Ronuken  
CCP  
Jeff Bridges, because he deserves it  
Bacon, because it's underappreciated  

