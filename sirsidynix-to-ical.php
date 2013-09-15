<?php
/**
  * sirsidynix-to-ical.php
  *
  * A PHP script to login to a SirsiDynix online library system
  * retrieve "checked out" items, and create iCal events for the 
  * due dates.
  *
  * Requirements: 
  *  - PHP (http://www.php.net) with cURL support
  * 
  * @version 0.1, 15 September 2013
  * @link http://hacker.vre.upei.ca/automating-sirsidynix-patron-login
  * @author Peter Rukavina <peter@rukavina.net> 
  * @copyright Reinvented Inc., 2013
  * @license http://www.fsf.org/licensing/licenses/gpl.txt GNU Public License
  */

$user_id = ""; 	// replace with your card number
$pin = ""; // replace with your PIN

$baseurl = "http://24.224.240.218"; // root URI of SirsiDynix server

$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $baseurl . "/uhtbin/cgisirsi.exe/x/x/0/49/"); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_COOKIESESSION, true); 
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/sirsidynix-cookies.txt"); 
curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/sirsidynix-cookies.txt"); 
$result = curl_exec($ch); 

preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
parse_str($m[1], $cookies);

$session_security_cookie = $cookies['session_security'];

$loginurl = $baseurl . "/uhtbin/cgisirsi.exe/?ps=8N5QS6vOI2/PLS/" . $session_security_cookie . "/303";
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $loginurl); 
curl_setopt($ch, CURLOPT_POSTFIELDS, "user_id=$user_id&password=$pin"); 
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/sirsidynix-cookies.txt"); 
curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/sirsidynix-cookies.txt"); 
$result = curl_exec($ch); 

$itemsurl = $baseurl . "/uhtbin/cgisirsi.exe/?ps=07P15KtNyC/CHA/" . $session_security_cookie . "/30#";
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $itemsurl); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/sirsidynix-cookies.txt"); 
curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/sirsidynix-cookies.txt"); 
$result = curl_exec($ch); 

$regex = '/\\<a\\ href="(.{0,})"\\>Details\\<\/a\\>/';
preg_match_all($regex, $result, $matches);
$checked_out_items = $matches[1];

foreach($checked_out_items as $key => $url) {
	$item = $baseurl . $url;
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $item); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/sirsidynix-cookies.txt"); 
	curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/sirsidynix-cookies.txt"); 
	$result = curl_exec($ch); 

	$regex = '/(?:(?Us)\\<dd\\ class="title"\\>(.{0,})\\<\/dd\\>)/';
	preg_match($regex, $result, $matches);
	$items[$key]['title'] = trim(html_entity_decode($matches[1]));

	$regex = '/(?:(?Us)\\<!\\-\\-\\ Print\\ the\\ author,\\ if\\ one\\ exists\\ \\-\\-\\>\\n(.{0,})&nbsp;.{0,}\\<\/dd\\>)/';
	preg_match($regex, $result, $matches);
	$items[$key]['author'] = trim(html_entity_decode($matches[1]));

	$regex = '/(?:(?Us)\\<dd\\ class="isbn"\\>(.{0,})\\<\/dd\\>)/';
	preg_match($regex, $result, $matches);
	$items[$key]['isbn'] = trim(html_entity_decode($matches[1]));

	$regex = '/(?:(?Us)\\<td\\ class="holdingslist"\\ align="left"\\>Due:(.{0,})\\<\/td\\>)/';
	preg_match($regex, $result, $matches);
	$items[$key]['datedue'] = trim(html_entity_decode($matches[1]));
	$items[$key]['datedue_unixtime'] = strtotime($items[$key]['datedue']);
}

foreach($items as $key => $item) {
	$ical = "";
	$ical .= "BEGIN:VCALENDAR\n";
	$ical .= "CALSCALE:GREGORIAN\n";
	$ical .= "PRODID:-//Date Due Processor //DateDue 1.1//EN\n";
	$ical .= "VERSION:2.0\n";
	$ical .= "METHOD:PUBLISH\n";
	$ical .= "BEGIN:VEVENT\n";
	$ical .= "TRANSP:TRANSPARENT\n";
	$ical .= "STATUS:CONFIRMED\n";
	$ical .= "SUMMARY:" . $item['title'] . "\n";
	$ical .= "DTSTART;VALUE=DATE:" . strftime("%Y%m%d", $item['datedue_unixtime']) . "\n";
	$ical .= "DTEND;VALUE=DATE:" . strftime("%Y%m%d", $item['datedue_unixtime']) . "\n";
	$ical .= "END:VEVENT\n";
	$ical .= "END:VCALENDAR\n";
	
	$fp = fopen("icalendar-" . $key . ".ics","w");
	fwrite($fp,$ical);
	fclose($fp);
}