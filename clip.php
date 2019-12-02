<?php

include __DIR__ . '/cliptracker.php';

/**
 * Create a Clip on a Twitch Channel
 * Created By: SOWHOYOUdotCOM
 * Website: https://www.sowhoyou.com/
 *
 *
 * USAGE: https://api.sowhoyou.com/twitch/clip?&oauth=<token>&channel=<id>&verify=<true/false>
 *
 * Token - Twitch oAuth Token with 'clip:edit' permissions. (auto strips 'oauth:' from token)
 * Channel - This can be the Twitch Channel ID or the Twitch Username.  (auto converts to id)
 * Verify - Optional. true or false. Verifies clips to ensure thumbnails. (delays clip reply)
 *
 *
 * Twitch Clip Command Link Generator: https://www.sowhoyou.com/clipapi/
 *
 */

class Clip {

    public $headers;
    public $channel_id;
    public $oauth_token;

    public $clip_link = '';
    public $error_message = '';
    public $verify_clip_data = '';

    public $verify_clip = false;
    public $clip_verified = false;

    public function __construct() {
        $this->verify_clip = isset($_GET['verify']) ? ((strcasecmp(trim($_GET['verify']), 'true') === 0) ? true : false) : false;
        $this->oauth_token = isset($_GET['oauth']) ? str_replace('oauth:', '', trim($_GET['oauth'])) : '';
        $this->channel_id = isset($_GET['channel']) ? trim($_GET['channel']) : '';

        if ($this->oauth_token == '' || $this->channel_id == '') {
            die('client side error: ' . (($this->oauth_token == '') ? 'missing oauth' : 'missing channel'));
        } else {
            $this->headers = array('Authorization: Bearer ' . $this->oauth_token);
            /**
             * Check Username is User ID
             */
            if (!is_numeric($this->channel_id)) {
                $this->convertChannelToId();
                $this->checkForErrors();
            }
            /**
             * Set Clip Link
             */
            $this->getClipLink();
            $this->checkForErrors();
            /**
             * Verify Clip Link
             */
            if ($this->verify_clip == true) {
                $this->verifyClipLinkLoop();
            }
            /**
             * Send Clip Link Reply
             */
            $this->sendClipLinkReply();
            /**
             * Log Clip for Analytics
             */
            $this->logClipToDatabase();
        }
    }

    function logClipToDatabase() {
        $clipLog = new ClipTracker();
        $clipLog->log($this->channel_id, $this->clip_link);
    }

    function checkForErrors() {
        if ($this->error_message != '') {
            header('Content-Type: text/plain');
            die($this->error_message);
        }
    }

    function sendClipLinkReply() {
        header('Content-Type: text/plain');
        echo (($this->clip_link != '') ? ('https://clips.twitch.tv/' . $this->clip_link) : ('RIP CLIP API -> @SOWHOYOUdotCOM'));
    }

    function verifyClipLinkLoop() {
        $triesCount = 0;
        while ($this->clip_verified != true) {
            $this->verifyClipLink();
            if (strcasecmp($this->clip_link, $this->verify_clip_data) === 0) {
                $this->clip_verified = true;
                break;
            } elseif ($triesCount >= 15) {
                break;
            } else {
                sleep(2);
                $triesCount++;
            }
        }
    }

    function getClipLink() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, ('https://api.twitch.tv/helix/clips?broadcaster_id=' . $this->channel_id));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            if (isset($json['data']['0']['id'])) {
                $this->clip_link = trim($json['data']['0']['id']);
            } else {
                $this->error_message = isset($json['message']) ? trim($json['message']) : 'unexpected error -> @SOWHOYOUdotCOM';
            }
        } else {
            $this->error_message = curl_error($curl);
        }
        curl_close($curl);
    }

    function verifyClipLink() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, ('https://api.twitch.tv/helix/clips?id=' . $this->clip_link));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            $this->verify_clip_data = isset($json['data']['0']['id']) ? trim($json['data']['0']['id']) : '';
        } else {
            $this->error_message = curl_error($curl);
        }
        curl_close($curl);
    }

    function convertChannelToId() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, ('https://api.twitch.tv/kraken/users?login=' . $this->channel_id));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.twitchtv.v5+json',
            'Client-ID: i81kirj6k74q4rmgas2kz8ytijsyhu',
        ));
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            if (isset($json['users']['0']['_id']) != '') {
                $this->channel_id = trim($json['users']['0']['_id']);
            } else {
                $this->error_message = 'channel not found';
            }
        } else {
            $this->error_message = curl_error($curl);
        }
        curl_close($curl);
    }

}

$clip = new Clip();

?>
