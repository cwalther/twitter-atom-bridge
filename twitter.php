<?php

/*
Copyright (c) 2013 Christian Walther

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

//ini_set('display_errors','On');
//error_reporting(E_ALL | E_STRICT);

$bearer_token = 'INSERT TOKEN HERE';

if (!isset($_GET['screen_name']) || !isset($_GET['count'])) exit('param');

$screen_name = urlencode($_GET['screen_name']);
$count = urlencode($_GET['count']);

$curl = curl_init("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name={$screen_name}&count={$count}");
//curl_setopt($curl, CURLOPT_HEADER, True);
curl_setopt($curl, CURLOPT_FAILONERROR, True);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, True);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $bearer_token));
$text = curl_exec($curl);
if (!$text) {
	exit(curl_getinfo($curl, CURLINFO_HTTP_CODE) . '  ' . curl_error($curl));
}
curl_close($curl);

$data = json_decode($text, True);
//print_r($data); exit();

function dateTo3339($dateString) {
	// %a %b %d %H:%M:%S %z %Y -> %Y-%m-%dT%H:%M:%S%z
	$tm = strptime($dateString, '%a %b');
	return preg_replace('/^[^ ]+ [^ ]+ ([0-9]+) ([0-9]+:[0-9]+:[0-9]+) ([+-][0-9][0-9])([0-9][0-9]) ([0-9]+)$/', '$5-' . sprintf('%02d', $tm['tm_mon'] + 1) . '-$1T$2$3:$4', $dateString);
}

function processEntity(&$pieces, &$replacements, $entity, $replacementFunc) {
	$entstart = $entity['indices'][0];
	$entend = $entity['indices'][1];
	$piecestart = 0;
	for ($i = 0; $i < count($pieces); $i++) {
		$pieceend = $piecestart + $pieces[$i];
		if ($piecestart <= $entstart && $entend <= $pieceend) {
			array_splice($pieces, $i, 1, array($entstart - $piecestart, $entend - $entstart, $pieceend - $entend));
			array_splice($replacements, $i/2, 0, array($replacementFunc($entity)));
			break;
		}
		$piecestart = $pieceend;
	}	
}

function urlEntityReplacement($entity) {
	return '<a href="' . htmlspecialchars($entity['expanded_url']) . '" title="' . htmlspecialchars($entity['url']) . '">' . htmlspecialchars($entity['display_url']) . '</a>';
}

function hashtagEntityReplacement($entity) {
	return '<a href="https://twitter.com/search?q=%23' . htmlspecialchars(urlencode($entity['text'])) . '&src=hash">#' . htmlspecialchars($entity['text']) . '</a>';
}

function userEntityReplacement($entity) {
	return '<a href="https://twitter.com/' . htmlspecialchars($entity['screen_name']) . '" title="' . htmlspecialchars($entity['name']) . '">@' . htmlspecialchars($entity['screen_name']) . '</a>';
}

function mediaEntityReplacement($entity) {
	return '<a href="' . htmlspecialchars($entity['expanded_url']) . '" title="' . htmlspecialchars($entity['url']) . '">' . htmlspecialchars($entity['display_url']) . '</a> (<a href="' . htmlspecialchars($entity['media_url']) . '">' . htmlspecialchars($entity['type']) . '</a>)';
}

mb_internal_encoding('UTF-8');

header('Content-type: application/atom+xml');

print('<?xml version="1.0" encoding="utf-8"?>' . "\n");
print('<feed xmlns="http://www.w3.org/2005/Atom">' . "\n");

print('	<title>Twitter / ' . htmlspecialchars($data[0]['user']['screen_name']) . '</title>' . "\n");
if ($data[0]['user']['description'] != NULL) { // "" == NULL (but not === NULL)
	print('	<subtitle>' . htmlspecialchars($data[0]['user']['description']) . '</subtitle>');
}
print('	<link href="https://twitter.com/' . htmlspecialchars($data[0]['user']['screen_name']) . '"/>' . "\n");
print('	<link rel="self" type="application/atom+xml" href="http://' . htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '"/>' . "\n");
print('	<id>https://twitter.com/' . htmlspecialchars($data[0]['user']['screen_name']) . '</id>' . "\n");
print('	<icon>https://abs.twimg.com/favicons/favicon.ico</icon>' . "\n");
print('	<author><name>' . htmlspecialchars($data[0]['user']['name'] . ' (' . $data[0]['user']['screen_name'] . ')') . '</name></author>' . "\n");
print('	<updated>' . dateTo3339($data[0]['created_at']) . '</updated>' . "\n"); // first entry seems to be newest (maybe sort explicitly to be sure)

foreach ($data as $tweet) {
	// it looks like some (newer?) tweets are already fully htmlspecialchars()-escaped, while others (older?) only for '>' but not for '&', normalize that
	// (replace any '&' that is not part of an '&amp;' or '&gt;' or '&lt;' by '&amp;')
	$tweettext = preg_replace('/&([^agl]|a[^m]|g[^t]|l[^t]|am[^p]|gt[^;]|lt[^;]|amp[^;])/', '&amp;$1', $tweet['text']);
	
	$htmltext = 'hi';
	$pieces = array(mb_strlen($tweettext));
	$replacements = array();
	if (isset($tweet['entities']['urls'])) {
		foreach ($tweet['entities']['urls'] as $entity) {
			processEntity($pieces, $replacements, $entity, "urlEntityReplacement");
		}
	}
	if (isset($tweet['entities']['hashtags'])) {
		foreach ($tweet['entities']['hashtags'] as $entity) {
			processEntity($pieces, $replacements, $entity, "hashtagEntityReplacement");
		}
	}
	if (isset($tweet['entities']['user_mentions'])) {
		foreach ($tweet['entities']['user_mentions'] as $entity) {
			processEntity($pieces, $replacements, $entity, "userEntityReplacement");
		}
	}
	if (isset($tweet['entities']['media'])) {
		foreach ($tweet['entities']['media'] as $entity) {
			processEntity($pieces, $replacements, $entity, "mediaEntityReplacement");
		}
	}
	$htmltext = '<p>';
	$piecestart = 0;
	for ($i = 0; $i < count($pieces); $i++) {
		if ($i % 2 == 0) {
			$htmltext .= mb_substr($tweettext, $piecestart, $pieces[$i]);
		}
		else {
			$htmltext .= $replacements[($i-1)/2];
		}
		$piecestart += $pieces[$i];
	}
	$htmltext .= '</p>';
	
	$htmltext .= '<p style="font-size: 80%; color: #666666;"><strong>' . htmlspecialchars($tweet['user']['name']) . '</strong>';
	if (isset($tweet['user']['description']) && $tweet['user']['description'] != NULL) { // isset() includes a check for !== NULL but not for != NULL ("" == NULL)
		$htmltext .= ' • ' . htmlspecialchars($tweet['user']['description']);
	}
	if (isset($tweet['user']['location']) && $tweet['user']['location'] != NULL) {
		$htmltext .= ' • ' . htmlspecialchars($tweet['user']['location']);
	}
	if (isset($tweet['user']['entities']['url']['urls'][0])) {
		$htmltext .= ' • <a href="' . htmlspecialchars($tweet['user']['entities']['url']['urls'][0]['expanded_url']) . '">' . htmlspecialchars($tweet['user']['entities']['url']['urls'][0]['display_url']) . '</a>';
	}
	$htmltext .= '</p>';
	if (isset($tweet['retweeted_status'])) {
		$htmltext .= '<p style="font-size: 80%; color: #666666;">retweeting <a href="https://twitter.com/' . htmlspecialchars($tweet['retweeted_status']['user']['screen_name']) . '/status/' . $tweet['retweeted_status']['id_str'] . '">' . htmlspecialchars($tweet['retweeted_status']['user']['screen_name']) . '</a>: ' . $tweet['retweeted_status']['text'] . '</p>';
	}
	if (isset($tweet['in_reply_to_status_id_str'])) {
		$htmltext .= '<p style="font-size: 80%; color: #666666;">in reply to <a href="' . htmlspecialchars('https://twitter.com/' . $tweet['in_reply_to_screen_name'] . '/status/' . $tweet['in_reply_to_status_id_str']) . '">' . htmlspecialchars($tweet['in_reply_to_screen_name']) . '</a></p>';
	}
	if (isset($tweet['contributors'])) {
		$htmltext .= '<p style="font-size: 80%; color: #666666;">contributors ';
		for ($i = 0; $i < count($tweet['contributors']); $i++) {
			if ($i != 0) $htmltext .= ', ';
			$htmltext .= '<a href="https://twitter.com/' . htmlspecialchars($tweet['contributors'][$i]['screen_name']) . '">' . htmlspecialchars($tweet['contributors'][$i]['screen_name']) . '</a>';
		}
		$htmltext .= '</p>';
	}
	if (isset($tweet['coordinates'])) {
		$htmltext .= sprintf('<p style="font-size: 80%%; color: #666666;">at (%.7f, %.7f)</p>', $tweet['coordinates']['coordinates'][0], $tweet['coordinates']['coordinates'][1]); //TODO make a google maps link or something
	}
	if (isset($tweet['place'])) {
		$htmltext .= '<p style="font-size: 80%; color: #666666;">place ' . htmlspecialchars($tweet['place']['name'] . ' (' . $tweet['place']['id'] . ')') . '</p>';
	}
	if (isset($tweet['source'])) {
		$htmltext .= '<p style="font-size: 80%; color: #666666;">via ' . $tweet['source'] . '</p>';
	}

	print('	<entry>' . "\n");
	print('		<author><name>' . htmlspecialchars($tweet['user']['name'] . ' (' . $tweet['user']['screen_name'] . ')') . '</name></author>' . "\n");
	print('		<link href="https://twitter.com/' . htmlspecialchars($tweet['user']['screen_name']) . '/status/' . $tweet['id_str'] . '"/>' . "\n");
	print('		<id>https://twitter.com/' . htmlspecialchars($tweet['user']['screen_name']) . '/status/' . $tweet['id_str'] . '</id>' . "\n");
	print('		<title>' . $tweettext . '</title>' . "\n");
	print('		<updated>' . dateTo3339($tweet['created_at']) . '</updated>' . "\n");
	print('		<content type="html"');
	if (isset($tweet['lang']) && $tweet['lang'] != 'und') print(' xml:lang="' . htmlspecialchars($tweet['lang']) . '"');
	print('>' . htmlspecialchars($htmltext) . '</content>' . "\n");
	print('	</entry>' . "\n");
}

print('</feed>' . "\n");

?>
