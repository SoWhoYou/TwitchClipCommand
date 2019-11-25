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
header('Content-Type: text/plain');

$oauth_token = isset($_GET['oauth']) ? $_GET['oauth'] : '';
$channel_id = isset($_GET['channel']) ? $_GET['channel'] : '';

if (empty($oauth_token) || empty($channel_id)) {
    die('error: ' . (empty($oauth_token) ? 'missing oauth token!' : 'missing channel id!'));
}

$vtries = 0;
$verifed = false;
$clip = getClip();

if (!empty($clip)) {
    while ($verifed !== true) {
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
    echo $error_message;
} elseif ($verifed || !empty($clip)) {
    echo 'https://clips.twitch.tv/' . $clip;
} else {
    echo 'unexpected error!';
}

function getClip() {
    global $error_message, $channel_id, $oauth_token;

    $get_clip = curl_init();
    curl_setopt($get_clip, CURLOPT_URL, 'https://api.twitch.tv/helix/clips?broadcaster_id=' . $channel_id);
    curl_setopt($get_clip, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($get_clip, CURLOPT_POST, 1);

    $get_headers = array();
    $get_headers[] = 'Authorization: Bearer ' . $oauth_token;
    curl_setopt($get_clip, CURLOPT_HTTPHEADER, $get_headers);
    $get_result = curl_exec($get_clip);

    if (curl_errno($get_clip)) {
        $error_message = curl_error($get_clip);
    } else {
        $json_data = json_decode($get_result, true);
        if (null === $json_data['error']) {
            $id = $json_data['data']['0']['id'];
        } else {
            $error_message = $json_data['message'];
        }
    }

    curl_close($get_clip);
    return $id;
}

function verifyClip($clipID) {
    global $error_message, $oauth_token;

    $get = curl_init();
    curl_setopt($get, CURLOPT_URL, 'https://api.twitch.tv/helix/clips?id=' . $clipID);
    curl_setopt($get, CURLOPT_RETURNTRANSFER, 1);

    $headers = array();
    $headers[] = 'Authorization: Bearer ' . $oauth_token;
    curl_setopt($get, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($get);

    if (curl_errno($get)) {
        $error_message = curl_error($get);
    } else {
        $data = json_decode($result, true);
        $cid = $data['data']['0']['id'];
    }

    curl_close($get);
    return $cid;
}

?>
