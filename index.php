<?php
// Configuration ///////////////////////////////////////////////////////////////
$config['connection']['host'] = 'spirit.fh-schmalkalden.de';
$config['connection']['port'] = 80;
$config['connection']['timeout'] = 10;
$config['rest']['baseurl'] = '/rest/1.0/schedule';
$config['rest']['paramClassname'] = 'classname';
$config['rest']['paramShowEverything'] = 'week=w';
$config['json']['eventWeekly'] = 'w';
$config['json']['eventOdd'] = 'u';
$config['json']['eventEven'] = 'g';
$config['json']['courses'] = array('bai2', 'bai4', 'bai6',
                                   'bawi2', 'bawi4', 'bawi6',
								   'bais2', 'bais4', 'bais6',
								   'bamm2', 'bamm4', 'bamm6',
								   'mai2', 'mai4');
$config['ical']['semesterEnd'] = '20120713T043000Z';

////////////////////////////////////////////////////////////////////////////////

function base64url_encode($data) { 
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
} 

function base64url_decode($data) { 
	return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); 
} 

function myEncode($data) {
	return base64url_encode(gzcompress(json_encode($data), 9));
}

function myDecode($data) {
	return json_decode(gzuncompress(base64url_decode($data)));
}

function filterDate($date) {
	$date = str_replace(array('-',':'), '', $date);
	return substr($date, 0, strpos($date, '+'));
}

function shortenUrl($url) {
	$fp = fsockopen("tinyurl.com", 80, $errno, $errstr, 10);
	if (!$fp)
		return $url;
	else {
		$out = "GET /api-create.php?url=$url HTTP/1.0\r\n";
		$out .= "Host: tinyurl.com\r\n";
		$out .= "Connection: Close\r\n\r\n";
		fwrite($fp, $out);
		$content = '';
		while (!feof($fp))
			$content .= fgets($fp, 128);
		fclose($fp);
		$arr = explode("\r\n\r\n", $content, 2);
		return str_replace(array("\r", "\n", " ", "\t"), '', $arr[1]);
	}
}

function escapeForICal($s) {
	$result = "";

	foreach (str_split($s) as $c)
	{
		switch ($c)
		{
			case ";" : $result .= "\\;"; break;
			case "," : $result .= "\\,"; break;
			case "\n": $result .= "\\n"; break;
			case "\\": $result .= "\\" . $c; break;
			default:   $result .= $c; break;
		}
	}
	return $result;

}

function createId($currentEvent) {
	return myEncode(array(
	  'c' => $currentEvent->className,
	  't' => $currentEvent->appointment->time,
	  'w' => $currentEvent->appointment->week,
	  'd' => $currentEvent->appointment->day,
	  'r' => $currentEvent->appointment->location->place->room,
	  'b' => $currentEvent->appointment->location->place->building));
}

function restApiRequest($currentClass, &$config) {
	$fp = fsockopen($config['connection']['host'], $config['connection']['port'], $errno, $errstr, $config['connection']['timeout']);
	if (!$fp) {
		throw new Exception("$errstr ($errno)<br />\n");
	}
	$getUrl = $config['rest']['baseurl'] . '?' . $config['rest']['paramClassname'] . '=' . $currentClass . '&' . $config['rest']['paramShowEverything'];
    $out = "GET $getUrl HTTP/1.1\r\n";
    $out .= "Host: $config{['connection']['host']}\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);
	$content = '';
    while (!feof($fp)) {
        $content .= fgets($fp, 128);
	}
    fclose($fp);
	
	$explodedContent = explode("\r\n\r\n", $content, 2);

	$header = $explodedContent[0];
    $json = $explodedContent[1];

	
	return json_decode($json);
}

function createUrl($className, $deletedEvents) {
	$result = array(
		"className" => $className,
		"startDate" => time(),
		"deletedEvents" => $deletedEvents);
	
	return shortenUrl('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'] . '?ical=' . myEncode($result));
}

if (isset($_GET['requestUrl'])) {
	//echo "<pre>";	var_dump($_POST); echo "</pre>";
	//var_dump($_POST);
	
	//var_dump(myDecode($_POST[0]));
	
	$deletedEvents = array();
	foreach ($_POST as $event)
		$deletedEvents[] = myDecode($event);
	
	if (count($deletedEvents) == 0)
		die("error");
	
	echo createUrl($deletedEvents[0]->c, $deletedEvents);//shortenUrl(getCurrentUrl() . myEncode($result));
	exit();
}

if (isset($_GET['ical'])):
	header('Content-type: text/calendar; charset=utf-8');
	header('Content-disposition: attachment; filename="mySchedule.ics"');
	//header('Content-type: text/html; charset=utf-8');
?>
BEGIN:VCALENDAR
PRODID:-//Mincemeat Inc//Mincemeat Calendar 1.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:My Schedule
X-WR-TIMEZONE:Europe/Berlin
X-WR-CALDESC:My personalized Schedule
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
<?php 
$obj = @myDecode($_GET['ical']);
if (is_object($obj)):
try {
	if ($obj->deletedEvents == NULL)
		$obj->deletedEvents = array();
	$weekdays = array(
		'Montag' => 'monday',
		'Dienstag' => 'tuesday',
		'Mittwoch' => 'wednesday',
		'Donnerstag' => 'thursday',
		'Freitag' => 'friday');
	$weekdaysShort = array(
		'Montag' => 'MO',
		'Dienstag' => 'TU',
		'Mittwoch' => 'WE',
		'Donnerstag' => 'TH',
		'Freitag' => 'FR');
		

	$data = restApiRequest($obj->className, $config);
	
	date_default_timezone_set('GMT');
	$creationDate = filterDate(date("c", $obj->startDate)) . 'Z';
	//$modificationDate = filterDate(date("c", time())) . 'Z';

	date_default_timezone_set('Europe/Berlin');

	foreach ($data as $currentEntry) :
		$dontDisplay = false;
		foreach ($obj->deletedEvents as $currentFilter) {
		//var_dump($currentFilter);
			if (
				$currentFilter->c == $currentEntry->className &&
				$currentFilter->t == $currentEntry->appointment->time &&
		        $currentFilter->w == $currentEntry->appointment->week &&
		        $currentFilter->d == $currentEntry->appointment->day &&
		        $currentFilter->r == $currentEntry->appointment->location->place->room &&
				$currentFilter->b == $currentEntry->appointment->location->place->building) {
				
				$dontDisplay = true;
				break;
			}
		}
		if ($dontDisplay == true)
			continue;
		
		$times = explode("-", $currentEntry->appointment->time);
		$startTime = str_replace('.', ':', $times[0]);
		$endTime = str_replace('.', ':', $times[1]);

		$week = (int)date_create("this ".$weekdays[$currentEntry->appointment->day])->format("W");
		$addWeek = "";
		$interval = 1;
		if (strpos($currentEntry->appointment->week, $config['json']['eventEven']) !== false) {
			if ($week % 2 != 0)
				$addWeek = " +1 week"; 
			$interval = 2;
		}
		elseif (strpos($currentEntry->appointment->week, $config['json']['eventOdd']) !== false) {
			if ($week % 2 == 0)
				$addWeek = " +1 week"; 
			$interval = 2;
		}
		//echo "<br>week: " .$week%2; var_dump($currentEntry->appointment->week);echo "<br>class: ".$currentEntry->titleShort . " starts " . "this " . $weekdays[$currentEntry->appointment->day] . "$addWeek " . $startTime . " -- intervall: $interval<br><br>";
		$dtstart = filterDate(date_create("this " . $weekdays[$currentEntry->appointment->day] . "$addWeek " . $startTime)->format("c"));
		$dtend = filterDate(date_create("this " . $weekdays[$currentEntry->appointment->day] . "$addWeek " . $endTime)->format("c"));
		//echo $dtstart . "<br>";
		
		$byday = $weekdaysShort[$currentEntry->appointment->day];
		
		$uid = md5(createId($currentEntry)) . '@fh-sm.de';
		
		$location = escapeForICal($currentEntry->appointment->location->place->building . "-" . $currentEntry->appointment->location->place->room);
		$summary = escapeForICal($currentEntry->titleShort . " (" . $currentEntry->eventType . ")");
		
		$grp = preg_replace('/[^0-9]/', '', $currentEntry->group);
		if (!empty($grp))
			$grp = "\nGroup: " . $grp;
		$description = escapeForICal("Lecturer: " . $currentEntry->member[0]->name . $grp);
?>
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:<?= $dtstart ?>

DTEND;TZID=Europe/Berlin:<?= $dtend ?>

RRULE:FREQ=WEEKLY;UNTIL=<?= $config['ical']['semesterEnd'] ?>;INTERVAL=<?= $interval ?>;BYDAY=<?= $byday ?>

DTSTAMP:<?= $creationDate ?>

UID:<?= $uid ?>

CREATED:<?= $creationDate ?>

DESCRIPTION:<?= $description ?>

LAST-MODIFIED:<?= $creationDate ?>

LOCATION:<?= $location ?>

SEQUENCE:0
STATUS:CONFIRMED
SUMMARY:<?= $summary ?>

TRANSP:OPAQUE
END:VEVENT
<?php
	endforeach;
} catch (Exception $e) { echo "<br>".$e->getMessage()."<br>";}

endif; ?>
END:VCALENDAR
<?php
	exit();
endif;
?>
<!DOCTYPE html>
<html><head>
<title>SpiriCal</title>
<meta charset="UTF-8">
<meta name="description" content="" />
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
<!--[if lt IE 9]><script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script><![endif]-->
<script type="text/javascript" src="js/prettify.js"></script>                                   <!-- PRETTIFY -->
<script type="text/javascript" src="js/kickstart.js"></script>    
<script type="text/javascript" src="js/jquery.zclip.min.js"></script>   
<script>
$(function() {

	xhr = null;
	
	$("#iconCopyLoad").hide();
	$("#iconCopyDone").hide();
	
	$(".event").click(function() {
		$(this).toggleClass('deleted');
		
		var myData = {};
		var i = 0;
		$(".deleted").each(function () {
			myData[i++] = $(this).attr('id');
		});
		//alert(myData.length);
		
		if (i > 0) {
			if (xhr != null)
				xhr.abort();
				
			xhr = $.ajax({
				type: "POST",
				url: "index.php?requestUrl",
				data: myData,
				beforeSend: function() {
					$("#urlResult").attr('value', 'Creating your Link...');
				},
				success: function(d){
					//alert( "Data Saved: \n" + msg );
					$("#urlResult").attr('value', d);
				}
			});
		} else {
			$("#urlResult").attr('value', $("#urlResult").attr('default-value'));
		}
		
	});
	
	$("#urlResult").click(function(){
		$("#urlResult").select();
		//return false;
	});


    $("#btnCopy").zclip({
        path:'js/ZeroClipboard.swf',
        copy:function () { return $('#urlResult').attr('value') },
        beforeCopy:function(){
            $('#iconCopy').hide();
			$('#iconCopyLoad').show();
			$('#iconCopyDone').hide();
        },
        afterCopy:function(){
            $('#iconCopy').hide();
			$('#iconCopyLoad').hide();
			$('#iconCopyDone').show();
			setTimeout(function() {
				$('#iconCopy').show();
				$('#iconCopyLoad').hide();
				$('#iconCopyDone').hide();
			}, 1500);
        }
    });
});
</script>                              <!-- KICKSTART -->
<link rel="stylesheet" type="text/css" href="css/kickstart.css" media="all" />                  <!-- KICKSTART -->
<link rel="stylesheet" type="text/css" href="style.css" media="all" />                          <!-- CUSTOM STYLES -->
</head><body><a id="top-of-page"></a><div id="wrap" class="clearfix">
<!-- ===================================== END HEADER ===================================== -->
<div id="nav">
<?php $currentClass = isset($_GET['create']) && in_array($_GET['create'], $config['json']['courses']) ? $_GET['create'] : null; ?>
	<ul class="menu right">
		<li><a href="#"><span class="icon" data-icon="a"></span>Select your Course</a>
			<ul>
<?php foreach ($config['json']['courses'] as $course): ?>
				<li><a href="?create=<?= $course ?>"<?= ($currentClass == $course) ? "" : 'class="current"' ?>><?= $course ?></a></li>
<?php endforeach; ?>
			</ul>
		</li>
	</ul>
</div>
<h2>SpiriCal</h2>
	<div class="col_9">
	<p>This Project is meant to create an individual Schedule and provide it as URL in 
	iCal format.</p>
	<p>To create your personal iCalendar file just hit the button in the upper right
	and select your course. After that, mark unwanted events simply by clicking and
	copy your personal link. This link can be used in Apps like Google Calendar or
	Outlook.</p>
	</div>
	<div class="col_3 right"><span class="icon gray x-large" data-icon="6" style="font-size: 220px;"></span></div>

<?php
$currentClass = isset($_GET['create']) && in_array($_GET['create'], $config['json']['courses']) ? $_GET['create'] : null;
if ($currentClass != null) :

	try {
		$data = restApiRequest($currentClass, $config);

		//echo "<pre>";	var_dump($data); echo "</pre>";

		$timeArray = array(
			"08.15-09.45" => 0,
			"10.00-11.30" => 1,
			"11.45-13.15" => 2,
			"14.15-15.45" => 3,
			"16.00-17.30" => 4,
			"17.45-19.15" => 5,
			"19.30-21.00" => 6);
		$schedule = array(
			'Montag' => array(),
			'Dienstag' => array(),
			'Mittwoch' => array(),
			'Donnerstag' => array(),
			'Freitag' => array());
		$eventStyle = array(
			$config['json']['eventEven'] => 'evenweek',
			$config['json']['eventOdd'] => 'oddweek',
			$config['json']['eventWeekly'] => 'weekly');
		$currentUrl = createUrl($currentClass, null);

		foreach ($data as $key => $val) {
			//echo $val->titleShort;
			$schedule[$val->appointment->day][$timeArray[$val->appointment->time]][] = $val;
		}
		//echo "<pre>";	var_dump($schedule); echo "</pre>";
	} catch (Exception $e) {
		echo $e->getMessage();
	}


?>
<hr />
<h4>Create your personal Schedule</h4>
<div class="col_12">
	<label>Your personal URL:</label>
	<input id="urlResult" type="text" style="width: 70%" disabled="disabled" value="<?= $currentUrl ?>" default-value="<?= $currentUrl ?>" />
	<button class="small" id="btnCopy"><span id="iconCopy" class="icon small" data-icon="v"></span><span id="iconCopyLoad" class="icon small" data-icon="E"></span><span id="iconCopyDone" class="icon small" data-icon="c"></span>Copy</button>
</div>
<table cellspacing="0" cellpadding="0" class="striped">
<thead><tr>
	<th> </th>
	<?php foreach (array_keys($schedule) as $currentDay): ?>
	<th><?= $currentDay ?></th>
	<?php endforeach; ?>
</tr></thead>
<tbody>
<?php foreach (array_keys($timeArray) as $currentTime): ?>
<tr>
	<th><?= $currentTime; ?></th>
<?php foreach (array_keys($schedule) as $currentDay): ?>
	<td>
<?php if (is_array($schedule[$currentDay][$timeArray[$currentTime]])):
          foreach ($schedule[$currentDay][$timeArray[$currentTime]] as $currentEvent): ?>
		<div class="col_12 event <?= $eventStyle[$currentEvent->appointment->week] ?>" id="<?= createId($currentEvent) ?>">
			<div><?= $currentEvent->titleShort ?>, <?= $currentEvent->eventType ?> <?php $grp = preg_replace('/[^0-9]/', '', $currentEvent->group); if(!empty($grp)) echo "[Grp".$grp."]"; ?></div>
			<div>by <?= $currentEvent->member[0]->name ?> in <?= $currentEvent->appointment->location->place->building ?>-<?= $currentEvent->appointment->location->place->room ?></div>
		</div>
<?php endforeach; endif; ?>
	</td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<!-- ===================================== START FOOTER ===================================== -->
<div class="clear"></div>
<div id="footer">
&copy; Copyright 2012 All Rights Reserved.
<a id="link-top" href="#top-of-page">Top</a>
</div>

</div><!-- END WRAP -->
</body></html>