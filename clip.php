<?php

require_once '/home/sowhoyou/public_html/api/tools/auth.php';
require_once '/home/sowhoyou/public_html/api/twitch/cliptracker.php';

/**
 * Twitch Clip Command API
 * Created By: SOWHOYOUdotCOM
 * Website: https://www.sowhoyou.com/
 *
 * Twitch Clip Command Link Generator:
 * https://www.sowhoyou.com/clipcommand/
 *
 */

class Clip {

    public $auth;
    public $clipLog;

    public $strings;
    public $clip_vars;
    public $client_auth;

    public function __construct() {
        $this->auth = new Auth();
        $this->clipLog = new ClipTracker();
        $this->clip_vars = array(
            'pre_message' => '',
            'error_message' => '',
            'clip_link' => '',
            'clip_link_data' => '',
            'clip_verified' => false,
            'clip_link_was_null' => false,
            'channel_exists_in_db' => false,
            'client_clip_api_headers' => '',
        );
        $this->strings = array(
            'channel_id_not_numerical' => 'Client Side Error! - Invalid Channel ID!',
            'channel_offline' => 'Clipping is not possible at this time! If the stream just went live or restarted, try again later.',
            'discord_webhook_url_prefix' => 'https://discordapp.com/api/webhooks/',
            'first_thumb_msg_client' => 'Warning: First Time Use & Thumbnail Generation was Enabled. This API Works! Try Clipping again! If the clip doesn\'t work, disable thumbnail generation, because your bot doesn\'t support it. ',
            'first_thumb_msg_logs' => 'First Time Use + Thumb Gen Message Sent',
            'header_auth_prefix' => 'Authorization: Bearer ',
            'header_client_id' => 'Client-ID: 8utds3yp9mk0mebqy4lirqnnyzalkl',
            'missing_channel' => 'Client Side Error! - Channel ID is Missing!',
            'missing_oauth' => 'Client Side Error! - OAuth Token is Missing!',
            'null_clip_issue' => 'Twitch API Error! - Unknown RIP',
            'oauth_expired' => 'OAuth Token Expired! - Update your Clip Command at: https://www.sowhoyou.com/clipcommand/',
            'twitch_clip_api_url' => 'https://api.twitch.tv/helix/clips?broadcaster_id=',
            'twitch_clip_url_prefix' => 'https://clips.twitch.tv/',
            'twitch_clip_verify_api_url' => 'https://api.twitch.tv/helix/clips?id=',
            'twitch_error_invalid_oauth' => 'Must provide a valid Client-ID or OAuth token',
            'twitch_error_stream_offline' => 'Clipping is not possible for an offline channel.',
        );
        $this->client_auth = array(
            'version' => '0',
            'channel' => '',
            'webhook' => '',
            'oauth' => '',
            'format' => false,
            'encrypt' => false,
            'verify' => false,
        );
    }

    function swy_url_processing() {
        if (isset($_GET['channel']) && isset($_GET['oauth'])) {
            $this->client_auth['version'] = (isset($_GET['version']) ? trim($_GET['version']) : '0');
            $this->client_auth['channel'] = (isset($_GET['channel']) ? trim($_GET['channel']) : '');
            $this->client_auth['webhook'] = (isset($_GET['webhook']) ? trim($_GET['webhook']) : '');
            $this->client_auth['oauth'] = (isset($_GET['oauth']) ? trim($_GET['oauth']) : '');
            $this->client_auth['format'] = (isset($_GET['format']) ? ((strcasecmp(trim($_GET['format']), '2') === 0) ? true : false) : false);
            $this->client_auth['encrypt'] = (isset($_GET['encrypt']) ? ((strcasecmp(trim($_GET['encrypt']), '1') === 0) ? true : false) : false);
            $this->client_auth['verify'] = (isset($_GET['verify']) ? ((strcasecmp(trim($_GET['verify']), 'true') === 0) ? true : false) : false);
        } elseif (isset($_GET['c']) && isset($_GET['o'])) {
            $this->client_auth['version'] = (isset($_GET['x']) ? $_GET['x'] : '0');
            $this->client_auth['channel'] = (isset($_GET['c']) ? $_GET['c'] : '');
            $this->client_auth['webhook'] = (isset($_GET['h']) ? $_GET['h'] : '');
            $this->client_auth['oauth'] = (isset($_GET['o']) ? $_GET['o'] : '');
            $this->client_auth['format'] = (isset($_GET['f']) ? ((strcasecmp($_GET['f'], '1') === 0) ? true : false) : false);
            $this->client_auth['encrypt'] = (isset($_GET['e']) ? ((strcasecmp($_GET['e'], '1') === 0) ? true : false) : false);
            $this->client_auth['verify'] = (isset($_GET['v']) ? ((strcasecmp($_GET['v'], '1') === 0) ? true : false) : false);
        }
    }

    function swy_var_validation() {
        if ($this->client_auth['encrypt'] === true) {
            $this->client_auth['oauth'] = !empty($this->client_auth['oauth']) ? $this->swy_decrypt($this->client_auth['oauth']) : '';
            $this->client_auth['webhook'] = !empty($this->client_auth['webhook']) ? ($this->strings['discord_webhook_url_prefix'] . $this->swy_decrypt($this->client_auth['webhook'])) : '';
        } else {
            $this->client_auth['webhook'] = !empty($this->client_auth['webhook']) ? ($this->strings['discord_webhook_url_prefix'] . $this->client_auth['webhook']) : '';
        }
        if (empty($this->client_auth['oauth']) || empty($this->client_auth['channel'])) {
            die(empty($this->client_auth['oauth']) ? $this->strings['missing_oauth'] : $this->strings['missing_channel']);
        }
        if (!is_numeric($this->client_auth['channel'])) {
            die($this->strings['channel_id_not_numerical']);
        }
        $this->clip_vars['client_clip_api_headers'] = array(
            $this->strings['header_auth_prefix'] . $this->client_auth['oauth'],
            $this->clip_vars['header_client_id'],
        );
        $this->clip_vars['channel_exists_in_db'] = $this->clipLog->exists($this->client_auth['channel']);
    }

    function swy_clip_processing() {
        $this->getClipLink();
        $this->checkForErrors();
        $this->nullCheck();
        $this->verifyClip();
        $this->logClipToDatabase();
        $this->sendClipLinkReply();
    }

    function getClipLink() {
        $curl = curl_init();
        $twitch_api_parms = $this->client_auth['channel'];
        $twitch_api_url = $this->strings['twitch_clip_api_url'];
        curl_setopt($curl, CURLOPT_URL, ($twitch_api_url . $twitch_api_parms));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->clip_vars['client_clip_api_headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            if (isset($json['data']['0']['id'])) {
                $this->clip_vars['clip_link'] = $json['data']['0']['id'];
            } else {
                if (isset($json['message'])) {
                    if (strcasecmp($json['message'], $this->strings['twitch_error_invalid_oauth']) === 0) {
                        $this->clip_vars['error_message'] = $this->strings['oauth_expired'];
                    } elseif (strcasecmp($json['message'], $this->strings['twitch_error_stream_offline']) === 0) {
                        $this->clip_vars['error_message'] = $this->strings['channel_offline'];
                    } else {
                        if (!empty($json['message'])) {
                            $this->clip_vars['error_message'] = $json['message'];
                            $this->swy_error_log(__FUNCTION__ . '1', $this->clip_vars['error_message']);
                        }
                    }
                } else { $this->swy_error_log(__FUNCTION__ . '2', json_encode($json));}
            }
        } else { $this->swy_error_log(__FUNCTION__ . '3', curl_error($curl));}
        curl_close($curl);
    }

    function checkForErrors() {
        if (!empty($this->clip_vars['error_message'])) {
            die($this->clip_vars['error_message']);
        }
    }

    function nullCheck() {
        if (empty($this->clip_vars['clip_link'])) {
            if ($this->clip_vars['clip_link_was_null'] === false) {
                $this->getClipLink();
                $this->checkForErrors();
                $this->clip_vars['clip_link_was_null'] = true;
                $this->nullCheck();
            } else {
                $this->swy_error_log(__FUNCTION__ . '1', $this->clip_vars['error_message']);
                die($this->strings['null_clip_issue']);
            }
        }
    }

    function verifyClip() {
        if ($this->client_auth['verify'] && $this->clip_vars['channel_exists_in_db']) {
            $this->verifyClipLinkLoop();
        } else {
            $this->verifyClipLink();
        }
    }

    function verifyClipLinkLoop() {
        $triesCount = 0;
        while ($this->clip_vars['clip_verified'] === false) {
            $this->verifyClipLink();
            if (strcasecmp($this->clip_vars['clip_link'], $this->clip_vars['clip_link_data']) === 0) {
                $this->clip_vars['clip_verified'] = true;
                break;
            } elseif ($triesCount >= 5) {
                break;
            } else {
                sleep(3);
                $triesCount++;
            }
        }
    }

    function verifyClipLink() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, ($this->strings['twitch_clip_verify_api_url'] . $this->clip_vars['clip_link']));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->clip_vars['client_clip_api_headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            $this->verify_clip_data = (isset($json['data']['0']['id'])) ? $json['data']['0']['id'] : '';
        } else {
            $this->swy_error_log(__FUNCTION__ . '1', curl_error($curl));
        }
        curl_close($curl);
    }

    function logClipToDatabase() {
        if ($this->client_auth['verify'] && !$this->clip_vars['channel_exists_in_db']) {
            $this->clip_vars['pre_message'] = $this->strings['first_thumb_msg_client'];
            $this->swy_error_log(__FUNCTION__ . '1', $this->strings['first_thumb_msg_logs']);
        }
        $this->clipLog->log($this->client_auth['channel'], $this->clip_vars['clip_link']);
        if (!empty($this->client_auth['webhook'])) {
            $this->clipLog->sendClientWebhook($this->client_auth['webhook'], $this->client_auth['channel'], $this->clip_vars['clip_link'], $this->client_auth['format']);
        }
    }

    function sendClipLinkReply() {
        die($this->clip_vars['pre_message'] . $this->strings['twitch_clip_url_prefix'] . $this->clip_vars['clip_link']);
    }

    function swy_decrypt($string) {
        $output = false;
        $encrypt_method = "AES-128-OFB";
        $key = hash('sha256', $this->auth->encryption['key']);
        $iv = substr(hash('sha256', $this->auth->encryption['iv']), 0, 16);
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {$data .= substr('====', $mod4);}
        $output = openssl_decrypt(base64_decode($data), $encrypt_method, $key, 0, $iv);
        return $output;
    }

    function swy_error_log($location, $error) {
        error_log('>>> ' . $this->client_auth['channel'] . ' >> ' . $location . ' > ' . $error, 0);
    }

}

$clip = new Clip();
$clip->swy_url_processing();
$clip->swy_var_validation();
$clip->swy_clip_processing();

?>
