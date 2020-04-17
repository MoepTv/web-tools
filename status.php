<?php
/*
 *  Moep.tv twitch status
 *  Copyright (C) 2019 Max Lee aka. Phoenix616
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */
include_once 'config.php';
$USERAGENT = "twitch status v0.1.1";
$CLIENT_ID = $TWITCH_CLIENT_ID;


$bgColor = ["r" => 255, "g" => 255, "b" => 255];
$textColor = ["r" => 45, "g" => 45, "b" => 45];
$linkColor = ["r" => 99, "g" => 71, "b" => 159];

$height = 64;
$width = 300;

$user = $_GET['user'];
if ($user === FALSE || empty($user)) {
    echo "Please specify a user parameter!";
    exit(1);
}

if (array_key_exists('width', $_GET) && !empty($_GET['width'])) {
    $width = $_GET['width'];
}

if (array_key_exists('height', $_GET) && !empty($_GET['height'])) {
    $height = $_GET['height'];
}

if (array_key_exists('bgcolor', $_GET) && !empty($_GET['bgcolor'])) {
    $bgColor = getFromHex($_GET['bgcolor'], $bgColor);
}

if (array_key_exists('textcolor', $_GET) && !empty($_GET['textcolor'])) {
    $textColor = getFromHex($_GET['textcolor'], $textColor);
}

if (array_key_exists('linkcolor', $_GET) && !empty($_GET['linkcolor'])) {
    $linkColor = getFromHex($_GET['linkcolor'], $linkColor);
}

$id = FALSE;
$count = -1;
$title = "";
$profilePicUrl = "";
$name = $user;
$userData = webQuery('https://api.twitch.tv/helix/users?login=' . $user);
if ($userData !== FALSE && !is_null($userData) && isset($userData['data']) && count($userData['data']) > 0) {
    $id = $userData['data'][0]['id'];
    $profilePicUrl = $userData['data'][0]['profile_image_url'];
    $name = $userData['data'][0]['display_name'];
}

if ($id === FALSE || empty($id)) {
    echo "Id for " . $user . " was not found?";
    var_dump($userData);
    exit(1);
}

$streamData = webQuery('https://api.twitch.tv/helix/streams?user_id=' . $id);
if ($streamData !== FALSE && !is_null($streamData) && isset($streamData['data']) && count($streamData['data']) > 0) {
    $count = $streamData['data'][0]['viewer_count'];
    $title = $streamData['data'][0]['title'];
}

$image = @imagecreatetruecolor($width, $height) or die("Cannot Initialize new GD image stream");

$imgBackgroundColor = imagecolorallocate($image, $bgColor['r'], $bgColor['g'], $bgColor['b']);
$imgRedColor = imagecolorallocate($image, 255, 0, 0);
$imgTextColor = imagecolorallocate($image, $textColor['r'], $textColor['g'], $textColor['b']);
$imgLinkColor = imagecolorallocate($image, $linkColor['r'], $linkColor['g'], $linkColor['b']);

imagefill($image, 0, 0, $imgBackgroundColor);
$profilePic = getImage($profilePicUrl, $height - 8, $height - 8);

$font = '/fonts/Roboto-Regular.ttf';
$boldFont = '/fonts/Roboto-Bold.ttf';
putenv('GDFONTPATH=' . realpath('.'));
imagettftext($image, 16, 0, $height, $height / 2 - 4, $imgLinkColor, $boldFont, $name);
if ($count > -1) {
    imagefilledellipse($image, $height + 6, $height - 17, 12, 12, $imgRedColor);
    imagettftext($image, 10, 0, $height + 16, $height - 12, $imgRedColor, $font, $count);
    $size = imagettfbbox(10, 0, $font, $count);
    imagettftext($image, 10, 0, $height + 16 + $size[2] + 4, $height - 12, $imgTextColor, $font, $title);
} else {
    imagettftext($image, 10, 0, $height, $height - 10, $imgTextColor, $font, "Offline");
    imagefilter($profilePic, IMG_FILTER_GRAYSCALE);
}

if ($profilePic !== FALSE) {
    imagecopy($image, $profilePic, 4, 4, 0, 0, $height - 8, $height - 8);
}
header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);

exit;

function write($file, $text) {
    $fh = fopen($file, "w");
    fwrite($fh, $text);
    fclose($fh);
}

function getImage($url, $width, $height) {
    $cacheFile = '/tmp/image-' . md5($url) . '-' . $width . '-' . $height . '.png';
    if (file_exists($cacheFile)) {
        return imagecreatefrompng($cacheFile);
    }

    if (endsWith($url, ".png")) {
        $image = imagecreatefrompng($url);
    } else if (endsWith($url, ".jpg") || endsWith($url, ".jpeg")) {
        $image = imagecreatefromjpeg($url);
    } else if (endsWith($url, ".gif")) {
        $image = imagecreatefromgif($url);
    } else {
        return FALSE;
    }
    if ($image === FALSE) {
        return $image;
    }

    imagesavealpha($image, true);

    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);

    $result = imagecreatetruecolor($width, $height);
    imagealphablending($result, false);
    imagesavealpha($result, true);
    // Copy cut and resized image to new image
    imagecopyresampled($result, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

    imagepng($result, $cacheFile, 0);

    return $result;
}

function webQuery($url) {
    $cacheFile = '/tmp/twitch_api_cache-' . md5($url);
    $interval = 60; // don't query same URL more than once a minute, also ensures different scripts don't query twice
    if (file_exists($cacheFile) && filemtime($cacheFile) + $interval > time()) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    global $USERAGENT, $CLIENT_ID;
    //$options     = ['http' => ['user_agent' => $USERAGENT, 'header' => ['Client-ID: ' . $CLIENT_ID]]];
    //$context     = stream_context_create($options);
    //$response    = @file_get_contents($url, false, $context);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERAGENT, $USERAGENT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Client-ID: ' . $CLIENT_ID]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === FALSE || !isset($response) || is_null($response)) {
        fwrite(STDERR, "Error while querying '$url'. It did not return any response?\n");
        return FALSE;
    }
    return json_decode($response, true);
}

function endsWith($string, $endString) {
    $len = strlen($endString);
    if ($len == 0) {
        return true;
    }
    return (substr($string, -$len) === $endString);
}

function getFromHex($hexColor, $default = ["r" => 0, "g" => 0, "b" => 0]) {
    if (empty($hexColor)) {
        return $default;
    }

    if ($hexColor[0] == '#') {
        $hexColor = substr($hexColor, 1);
    }

    if (strlen($hexColor) == 6) {
        $hex = [$hexColor[0] . $hexColor[1], $hexColor[2] . $hexColor[3], $hexColor[4] . $hexColor[5]];
    } elseif (strlen($hexColor) == 3) {
        $hex = [$hexColor[0] . $hexColor[0], $hexColor[1] . $hexColor[1], $hexColor[2] . $hexColor[2]];
    } else {
        return $default;
    }

    $rgb = array_map('hexdec', $hex);
    return ["r" => $rgb[0], "g" => $rgb[1], "b" => $rgb[2]];
}

?>