<?php
/*
 *  Moep.tv twitch random users
 *  Copyright (C) 2020 Max Lee aka. Phoenix616
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
$CLIENT_ID = $TWITCH_CLIENT_ID;
$OAUTH_TOKEN = $TWITCH_OAUTH_TOKEN;

$query = $_GET['query'];
if ($query === FALSE || empty($query)) {
    echo "Please specify a $query parameter!";
    # TODO: echo site
    exit(1);
}

echo json_encode(processQuery($query, $_GET));
exit;

function processQuery($query, $params) {
    global $CLIENT_ID, $OAUTH_TOKEN;

    header("Content-Type: application/json");
    $json = array();
    if ($query !== 'followers') {
        $json['error'] = "Please specify a valid $query parameter!";
        return $json;
    }

    $user = $params['user'];
    if ($user === FALSE || empty($user)) {
        $json['error'] = "Please specify a user parameter!";
        return $json;
    }

    $exclude = array();
    if (isset($params['exclude']) && !empty($params['exclude'])) {
        $exclude = preg_split("/,/", $params['exclude']);
        if (!$exclude) {
            $json['error'] = preg_last_error();
            $exclude = array();
        }
    }
    
    $id = FALSE;
    $userData = webQuery('https://api.twitch.tv/helix/users?login=' . $user);
    if ($userData !== FALSE && !is_null($userData) && isset($userData['data']) && count($userData['data']) > 0) {
        $id = $userData['data'][0]['id'];
        $json['id'] = $id;
        $json['profile_image'] = $userData['data'][0]['profile_image_url'];
        $json['display_name'] = $userData['data'][0]['display_name'];
    }

    $followerData = webQuery('https://api.twitch.tv/helix/users/follows?to_id=' . $id . '&first=100');
    # TODO: Pagination support for above 100 followers
    if ($followerData === FALSE || is_null($followerData)) {
        $json['error'] = "Could not load followered data for " . $user;
        $json['errorDetails'] = $followerData;
        return $json;
    }

    if (!isset($followerData['data']) || count($followerData['data']) == 0) {
        $json['error'] = $user . " has no followers :(";
        return $json;
    }

    $json['count'] = $followerData['total'];

    $follower = array();
    foreach ($followerData['data'] as $follow) {
        if (!in_array($follow['from_name'], $exclude)) {
            array_push($follower, $follow['from_name']);
        } else {
            if (!isset($json['excluded'])) {
                $json['excluded'] = array();
            }
            array_push($json['excluded'], $follow['from_name']);
        }
    }
    $json['users'] = $follower;
    $json['random'] = $follower[rand(0, sizeof($follower))];
    return $json;
}

?>