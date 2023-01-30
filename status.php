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
include_once 'common.php';
$USERAGENT = "twitch status v0.1.1";


$bgColor = ["r" => 255, "g" => 255, "b" => 255, "a" => 0];
$textColor = ["r" => 45, "g" => 45, "b" => 45, "a" => 0];
$linkColor = ["r" => 99, "g" => 71, "b" => 159, "a" => 0];

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
    echo "Id for " . $user . " was not found?\n";
    var_dump($userData);
    if (isset($userData['status']) && $userData['status'] == 401) {
        echo "\nAuthorize at https://id.twitch.tv/oauth2/authorize?client_id=" . $CLIENT_ID . "&redirect_uri=https://twitch.moep.tv&response_type=token";
    }
    exit(1);
}

$streamData = webQuery('https://api.twitch.tv/helix/streams?user_id=' . $id);
if ($streamData !== FALSE && !is_null($streamData) && isset($streamData['data']) && count($streamData['data']) > 0) {
    $count = $streamData['data'][0]['viewer_count'];
    $title = $streamData['data'][0]['title'];
}

$image = @imagecreatetruecolor($width, $height) or die("Cannot Initialize new GD image stream");
imagesavealpha($image, true);

$imgBackgroundColor = imagecolorallocatealpha($image, $bgColor['r'], $bgColor['g'], $bgColor['b'], $bgColor['a']);
$imgRedColor = imagecolorallocate($image, 255, 0, 0);
$imgTextColor = imagecolorallocatealpha($image, $textColor['r'], $textColor['g'], $textColor['b'], $textColor['a']);
$imgLinkColor = imagecolorallocatealpha($image, $linkColor['r'], $linkColor['g'], $linkColor['b'], $linkColor['a']);

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

?>