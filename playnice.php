<?php

//
// A little script to scrape your iPhone's location from MobileMe
// and update Google Latitude with your iPhone's current position.
//
// Uses sosumi from http://github.com/tylerhall/sosumi/tree/master and
// some google scraping code from Jack Catchpoole <jack@catchpoole.com>.
//
// Nat Friedman <nat@nat.org>
//
// August 22nd, 2009
//
// MIT license.
//

include 'class.google.php';
include 'class.sosumi.php';

if ($argc < 3) {
    $help = <<<HELP
Usage:
    php playnice.php <username> <deviceId>
Where:
    username - Pick something.  Anything.  It can be your me.com or google.com
               username, but remember it because that will be the "key" to
               finding your cached me.com and google.com credentials later.
    deviceId - First time running the script, pick something.  Like the number
               1.  You'll see a list of real device IDs (they look like SHA1s)
               listed in the order as shown on your "Find My iPhone" page on
               the me.com site.  Second time running he script use the device
               ID that goes with the me.com and google.com credentials as
               identified by the <username> argument.

HELP;
    echo $help;
    exit;
}

$username = $argv[1];
$deviceId = $argv[2];

$mobileMePasswordFile = "./mobile-me-password-$username.txt";

$google = new GoogleLatitude($username);

function promptForLogin($serviceName)
{
    echo "$serviceName username: ";
    $username = trim(fgets(STDIN));

    if (empty($username)) {
	die("Error: No username specified.\n");
    }

    echo "$serviceName password: ";
    system ('stty -echo');
    $password = trim(fgets(STDIN));
    system ('stty echo');
    // add a new line since the users CR didn't echo
    echo "\n";

    if (empty ($password)) {
	die ("Error: No password specified.\n");
    }

    return array ($username, $password);
}

if (! file_exists ($mobileMePasswordFile)) {
    echo "You will need to type your MobileMe username/password. They will be\n";
    echo "saved in $mobileMePasswordFile so you don't have to type them again.\n";
    echo "If you're not cool with this, you probably want to delete that file\n";
    echo "at some point (they are stored in plaintext).\n\n";
    echo "You do need a working MobileMe account for playnice to work, and you\n";
    echo "need to have enabled the Find My iPhone feature on your phone.\n\n";
    

    list($mobileMeUsername, $mobileMePassword) = promptForLogin("MobileMe");

    $f = fopen ($mobileMePasswordFile, "w");
    fwrite ($f, "<?php\n\$mobileMeUsername=\"$mobileMeUsername\";\n\$mobileMePassword=\"$mobileMePassword\";\n?>\n");
    fclose ($f);
    chmod($mobileMePasswordFile, 0600);

    echo "\n";

} else {
    @include($mobileMePasswordFile);
}

if (! $google->haveCookie()) {
    echo "No Google cookie found. You will need to authenticate with your\n";
    echo "Google username/password. You should only need to do this once;\n";
    echo "we will save the session cookie for the future.\n\n";

    list($username, $password) = promptForLogin("Google");

    echo "Acquiring Google session cookie...";
    $google->login($username, $password);
    echo "got it.\n";
}

// Get the iPhone location from MobileMe
echo "Fetching iPhone information...";
$mobileMe = new Sosumi ($mobileMeUsername, $mobileMePassword);
if (! $mobileMe->authenticated) {
    echo "Unable to authenticate to MobileMe. Is your password correct?\n";
    exit;
}
if (count ($mobileMe->devices) == 0) {
    echo "No iPhones found in your MobileMe account.\n";
    exit;
}
echo "found ".count($mobileMe->devices)." device(s):\n";
reset($mobileMe->devices);
foreach ($mobileMe->devices as $device) {
  echo "Device ID: ".$device['deviceId']."\n";
}
echo "Determining iPhone location...";
$iphoneLocation = $mobileMe->locate($mobileMe->devices[$deviceId]);
echo "got it.\n";
echo "iPhone location: $iphoneLocation->latitude, $iphoneLocation->longitude\n";

// Now update Google Latitude
echo "Updating Google Latitude...";
$google->updateLatitude($iphoneLocation->latitude, $iphoneLocation->longitude,
			$iphoneLocation->accuracy);

// All done.
echo "Done!\n";
