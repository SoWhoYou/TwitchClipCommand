<?php

/**
 * Create a Clip on a Twitch Channel
 * Created By: SOWHOYOUdotCOM
 * Website: https://sowhoyou.com
 *
 * USAGE: https://sowhoyou.com/api/twitch/clip.php?delay=<seconds>&oauth=<token>&channel=<id>
 *
 * Delay - Recommended 10 Seconds, to allow time for clip thumbnail generation.
 * Token - Twitch oAuth Token with 'clip:edit' permissions, don't include 'oauth:'
 * Channel - This must be the Twitch Channel ID, not the Twitch Username.
 */

$send_delay = isset($_GET['delay']) ? $_GET['delay'] : 10;

$oauth_token = isset($_GET['oauth']) ? $_GET['oauth'] : '';
if (empty($oauth_token)) {
    die('Error: Missing oAuth Token!');
}

$channel_id = isset($_GET['channel']) ? $_GET['channel'] : '';
if (empty($channel_id)) {
    die('Error: Missing Channel ID');
}

$get_clip = curl_init();
curl_setopt($get_clip, CURLOPT_URL, 'https://api.twitch.tv/helix/clips?broadcaster_id=' . $channel_id);
curl_setopt($get_clip, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($get_clip, CURLOPT_POST, 1);

$get_headers = array();
$get_headers[] = 'Authorization: Bearer ' . $oauth_token;
curl_setopt($get_clip, CURLOPT_HTTPHEADER, $get_headers);

$get_result = curl_exec($get_clip);

if (curl_errno($get_clip)) {
    echo 'Error: ' . curl_error($get_clip);
} else {
    $json_data = json_decode($get_result, true);
    if (null === $json_data['error']) {
        sleep($send_delay);
        echo 'https://clips.twitch.tv/' . $json_data['data']['0']['id'];
    } else {
        echo $json_data['message'];
    }
}
curl_close($get_clip);
?>
