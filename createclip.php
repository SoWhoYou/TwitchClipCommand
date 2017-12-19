<?php

/*
 * Create Clips on a Twitch Channel
 * Created by: SOWHOYOUdotCOM
 * Website: https://www.sowhoyou.com
 *
 */

$ch = curl_init();

// Needs "_id" from: https://api.twitch.tv/kraken/channels/STREAMERS_CHANNEL_NAME?oauth_token=
curl_setopt($ch, CURLOPT_URL, "https://api.twitch.tv/helix/clips?broadcaster_id=00000000"); // 00000000 = Channel "_id" to make clips on.
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);

$headers = array();
// Needs "OAuth Token" with "clip:edit" Permissions. Can Generate a "GOD" Token here if you want: https://www.sowhoyou.com/oauth/
$headers[] = "Authorization: Bearer 00000000"; // 00000000 = ChatBot "OAuth Token" that has "clip:edit" permissions. 
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);

if (curl_errno($ch)) {
	// Detailed Error if something goes wrong. (Not really needed, just for debugging really)
    echo 'Error:' . curl_error($ch);
}
curl_close ($ch);

$json_data = json_decode($result, true);

if ($json_data['data']['0']['edit_url'] !== null) {
	// Link to the Created Clip on the Streamers Channel by the ChatBot.
	echo substr($json_data['data']['0']['edit_url'], 0, -5);;
} else {
	// Message for when there is an error or stream is offline.
	echo '/me Can\'t Clip Right Now, Sorry!';
}

?>
