<?php

include_once 'config.php';

$CLIENT_ID = $TWITCH_CLIENT_ID;
$CLIENT_SECRET = $TWITCH_CLIENT_SECRET;

$tokenResponse = getToken();
if ($tokenResponse === FALSE || empty($tokenResponse)) {
    die("Could not authorize!");
}
$OAUTH_TOKEN = $tokenResponse['access_token'];

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

function getToken() {
    $cacheFile = '/tmp/twitch_api_cache-token';
    if (file_exists($cacheFile)) {
        $tokenData = json_decode(file_get_contents($cacheFile), true);
        if ($tokenData != NULL) {
            if (time() < $tokenData['expires_at']) {
                return $tokenData;
            }
        }
    }
    
    global $USERAGENT, $CLIENT_ID, $CLIENT_SECRET;
    //$options     = ['http' => ['user_agent' => $USERAGENT, 'header' => ['Client-ID: ' . $CLIENT_ID]]];
    //$context     = stream_context_create($options);
    //$response    = @file_get_contents($url, false, $context);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://id.twitch.tv/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERAGENT, $USERAGENT);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        "client_id" => $CLIENT_ID,
        "client_secret" => $CLIENT_SECRET,
        "grant_type" => "client_credentials"
    )));
    curl_setopt($ch, CURLOPT_HEADER, "Content-Type: application/x-www-form-urlencoded");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === FALSE || !isset($response) || is_null($response)) {
        fwrite(STDERR, "Error while requesting token. It did not return any response?\n");
        return FALSE;
    }
    $responseData= json_decode($response, true);
    $responseData['expires_at'] = time() + $responseData['expires_in'];
    return $responseData;
}

function webQuery($url) {
    $cacheFile = '/tmp/twitch_api_cache-' . md5($url);
    $interval = 60; // don't query same URL more than once a minute, also ensures different scripts don't query twice
    if (file_exists($cacheFile) && filemtime($cacheFile) + $interval > time()) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    global $USERAGENT, $CLIENT_ID, $OAUTH_TOKEN;
    //$options     = ['http' => ['user_agent' => $USERAGENT, 'header' => ['Client-ID: ' . $CLIENT_ID]]];
    //$context     = stream_context_create($options);
    //$response    = @file_get_contents($url, false, $context);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERAGENT, $USERAGENT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Client-ID: ' . $CLIENT_ID, 'Authorization: Bearer ' . $OAUTH_TOKEN]);
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

function getFromHex($hexColor, $default = ["r" => 0, "g" => 0, "b" => 0, "a" => 0]) {
    if (empty($hexColor)) {
        return $default;
    }
    
    if ($hexColor[0] == '#') {
        $hexColor = substr($hexColor, 1);
    }
    
    if (strlen($hexColor) == 8) {
        $hex = [$hexColor[0] . $hexColor[1], $hexColor[2] . $hexColor[3], $hexColor[4] . $hexColor[5], $hexColor[6] . $hexColor[7]];
    } elseif (strlen($hexColor) == 6) {
        $hex = [$hexColor[0] . $hexColor[1], $hexColor[2] . $hexColor[3], $hexColor[4] . $hexColor[5], "00"];
    } elseif (strlen($hexColor) == 3) {
        $hex = [$hexColor[0] . $hexColor[0], $hexColor[1] . $hexColor[1], $hexColor[2] . $hexColor[2], "00"];
    } else {
        return $default;
    }
    
    $rgb = array_map('hexdec', $hex);
    return ["r" => $rgb[0], "g" => $rgb[1], "b" => $rgb[2], "a" => $rgb[3] / 2];
}