<?php

/**
 * Create a Clip on a Twitch Channel
 * Created By: SOWHOYOUdotCOM
 * Website: https://sowhoyou.com
 *
 * USAGE: https://sowhoyou.com/api/twitch/clip?&oauth=<token>&channel=<id>
 *
 * Token - Twitch oAuth Token with 'clip:edit' permissions, don't include 'oauth:'
 * Channel - This must be the Twitch Channel ID, not the Twitch Username.
 */

if (!isset($jsonData)) {
    $jsonData = new stdClass();
}
if (!isset($error_message)) {
    $error_message = new stdClass();
}

$oauth_token = isset($_GET['oauth']) ? $_GET['oauth'] : '';
if (empty($oauth_token)) {
    header("Content-Type: application/json; charset=UTF-8");
    $jsonData->error = 'Bad Request';
    $jsonData->status = 422;
    $jsonData->message = 'missing oauth token!';
    $myJson = json_encode($jsonData);
    die($myJson);
}

$channel_id = isset($_GET['channel']) ? $_GET['channel'] : '';
if (empty($channel_id)) {
    header("Content-Type: application/json; charset=UTF-8");
    $jsonData->error = 'Bad Request';
    $jsonData->status = 422;
    $jsonData->message = 'missing channel id!';
    $myJson = json_encode($jsonData);
    die($myJson);
}

$vtries = 0;
$verifed = FALSE;
$clip = getClip();

if (!empty($clip)) {
    while ($verifed !== TRUE) {
        $test = verifyClip($clip);
        if (strcasecmp($clip, $test) === 0) {
            $verifed = TRUE;
            break;
        } elseif ($vtries > 30) {
            break;
        } else {
            sleep(2);
            $vtries++;
        }
    }
}

if (!empty($error_message)) {
    header("Content-Type: application/json; charset=UTF-8");
    echo $error_message;
} elseif ($verifed || !empty($clip)) {
    header('Content-Type: text/plain');
    echo 'https://clips.twitch.tv/' . $clip;
} else {
    header("Content-Type: application/json; charset=UTF-8");
    $jsonData->error = 'Bad Request';
    $jsonData->status = 422;
    $jsonData->message = 'unexpected error!';
    $myJson = json_encode($jsonData);
    echo $myJson;
}

function getClip() {
    global $error_message, $channel_id, $oauth_token, $jsonData;
    $id = '';

    $get_clip = curl_init();
    curl_setopt($get_clip, CURLOPT_URL, 'https://api.twitch.tv/helix/clips?broadcaster_id=' . $channel_id);
    curl_setopt($get_clip, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($get_clip, CURLOPT_POST, 1);

    $get_headers = array();
    $get_headers[] = 'Authorization: Bearer ' . $oauth_token;
    curl_setopt($get_clip, CURLOPT_HTTPHEADER, $get_headers);
    $get_result = curl_exec($get_clip);

    if (curl_errno($get_clip)) {
        $jsonData->error = 'Bad Request';
        $jsonData->status = 422;
        $jsonData->message = curl_error($get_clip);
        $error_message = json_encode($jsonData);
    } else {
        $json_data = json_decode($get_result, true);
        if (null === $json_data['error']) {
            $id = $json_data['data']['0']['id'];
        } else {
            $jsonData->error = 'Bad Request';
            $jsonData->status = 422;
            $jsonData->message = $json_data['message'];
            $error_message = json_encode($jsonData);
        }
    }

    curl_close($get_clip);
    return $id;
}

function verifyClip($clipID) {
    global $error_message, $oauth_token, $jsonData;
    $cid = '';

    $get = curl_init();
    curl_setopt($get, CURLOPT_URL, 'https://api.twitch.tv/helix/clips?id=' . $clipID);
    curl_setopt($get, CURLOPT_RETURNTRANSFER, 1);

    $headers = array();
    $headers[] = 'Authorization: Bearer ' . $oauth_token;
    curl_setopt($get, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($get);

    if (curl_errno($get)) {
        $jsonData->error = 'Bad Request';
        $jsonData->status = 422;
        $jsonData->message = curl_error($get);
        $error_message = json_encode($jsonData);
    } else {
        $data = json_decode($result, true);
        $cid = $data['data']['0']['id'];
    }

    curl_close($get);
    return $cid;
}

?>
