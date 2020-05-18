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
            'client_clip_api_headers' => '',
            'clip_link_data' => '',
            'clip_link_was_null' => false,
            'clip_link' => '',
            'clip_verified' => false,
            'error_message' => '',
        );
        $this->client_auth = array(
            'channel' => '',
            'encrypt' => false,
            'format' => false,
            'oauth' => '',
            'sender' => '',
            'verify' => false,
            'version' => '0',
            'webhook' => '',
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
            $this->client_auth['sender'] = (isset($_GET['u']) ? $_GET['u'] : '');
        }
    }

    function swy_var_validation() {
        if ($this->client_auth['encrypt'] === true) {
            $this->client_auth['oauth'] = !empty($this->client_auth['oauth']) ? $this->swy_decrypt($this->client_auth['oauth']) : '';
            $this->client_auth['webhook'] = !empty($this->client_auth['webhook']) ? ('https://discordapp.com/api/webhooks/' . $this->swy_decrypt($this->client_auth['webhook'])) : '';
        } else {
            $this->client_auth['webhook'] = !empty($this->client_auth['webhook']) ? ('https://discordapp.com/api/webhooks/' . $this->client_auth['webhook']) : '';
        }

        if (empty($this->client_auth['oauth']) || empty($this->client_auth['channel'])) {
            die('Client Side Error! - ' . empty($this->client_auth['oauth']) ? 'OAuth Token is Missing!' : 'Channel ID is Missing!');
        }

        if (!is_numeric($this->client_auth['channel'])) {
            die('Client Side Error! - Invalid Channel ID!');
        }

        $this->clip_vars['client_clip_api_headers'] = array(
            'Authorization: Bearer ' . $this->client_auth['oauth'],
            'Client-ID: ' . $this->auth->twitch['clip_cmd_client_id'],
        );
    }

    function getClipLink() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, ('https://api.twitch.tv/helix/clips?broadcaster_id=' . $this->client_auth['channel']));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->clip_vars['client_clip_api_headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            if (isset($json['data']['0']['id'])) {
                $this->clip_vars['clip_link'] = $json['data']['0']['id'];
            } else {
                $this->process_clip_json_data($json);
            }
        } else {
            $this->swy_error_log(__FUNCTION__ . '1', curl_error($curl));
        }
        curl_close($curl);
    }

    function process_clip_json_data($json) {
        if (isset($json['message'])) {
            $update_message = 'Update your Clip Command at: https://www.sowhoyou.com/clipcommand/';

            if (strcasecmp($json['message'], 'Must provide a valid Client-ID or OAuth token') === 0) {
                $this->clip_vars['error_message'] = 'OAuth Token Expired! - ' .  $update_message;

            } elseif ((strcasecmp($json['message'], 'Invalid OAuth token') === 0) || (strcasecmp($json['message'], 'Missing scope: clips:edit') === 0)) {
                $this->clip_vars['error_message'] = 'Invalid OAuth Token! - ' .  $update_message;

            } elseif (strcasecmp($json['message'], 'Clipping is not possible for an offline channel.') === 0) {
                $this->clip_vars['error_message'] = 'Clipping is not possible at this time! If the stream just went live or restarted, try again later.';

            } elseif (!empty($json['message'])) {
                $this->clip_vars['error_message'] = $json['message'];
                $this->swy_error_log(__FUNCTION__ . ' 1', $this->clip_vars['error_message']);
            }
        } else {
            $this->swy_error_log(__FUNCTION__ . ' 2', json_encode($json));
        }
    }

    function checkForErrors() {
        if (!empty($this->clip_vars['error_message'])) {
            die($this->clip_vars['error_message']);
        }
    }

    /** Attempt to fix Twitch's Unknown Error */
    function nullCheck() {
        if (empty($this->clip_vars['clip_link'])) {
            if ($this->clip_vars['clip_link_was_null'] === false) {
                $this->getClipLink();
                $this->checkForErrors();
                $this->clip_vars['clip_link_was_null'] = true;
                $this->nullCheck();
            } else {
                $this->swy_error_log(__FUNCTION__ . ' 1', $this->clip_vars['error_message']);
                die('Twitch API Error! - Unknown Error! - RIP');
            }
        }
    }

    function verifyClip() {
        if ($this->client_auth['verify']) {
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
        curl_setopt($curl, CURLOPT_URL, ('https://api.twitch.tv/helix/clips?id=' . $this->clip_vars['clip_link']));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->clip_vars['client_clip_api_headers']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        if (!curl_errno($curl)) {
            $json = json_decode($result, true);
            $this->verify_clip_data = (isset($json['data']['0']['id'])) ? $json['data']['0']['id'] : '';
        } else {
            $this->swy_error_log(__FUNCTION__ . ' 1', curl_error($curl));
        }
        curl_close($curl);
    }

    function logClipToDatabase() {
        /** Log to My Discord */
        $this->clipLog->log($this->client_auth['channel'], $this->clip_vars['clip_link'], $this->client_auth['verify'], $this->client_auth['sender']);
        /** Log to Client Discord if Webhook is given */
        if (!empty($this->client_auth['webhook'])) {
            $this->clipLog->sendClientWebhook($this->client_auth['webhook'], $this->client_auth['channel'], $this->clip_vars['clip_link'], $this->client_auth['format'], $this->client_auth['sender']);
        }
    }

    function sendClipLinkReply() {
        die('https://clips.twitch.tv/' . $this->clip_vars['clip_link']);
    }

    function swy_decrypt($string) {
        $output = false;
        $encrypt_method = $this->auth->encryption['method'];
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

    function disable_cloudbot_thumb_gen() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $user_agent_found = false;
        /** Check if the bot is a cloud bot */
        if ($this->client_auth['verify'] === true) {
            $cloud_bots = $this->auth->ccmd_api_cloudbots();
            foreach ($cloud_bots as $bot) {
                if (stripos($user_agent, $bot) !== false) {
                    // $this->swy_error_log('Thumbnail Generation Disabled', $bot);
                    $this->client_auth['verify'] = false;
                    $user_agent_found = true;
                    break;
                }
            }
        }
        /**  Check if the bot is not a cloud bot */
        if ($this->client_auth['verify'] === true) {
            $non_cloud_bots = $this->auth->ccmd_api_non_cloudbots();
            foreach ($non_cloud_bots as $ok_bot) {
                if (stripos($user_agent, $ok_bot) !== false) {
                    $user_agent_found = true;
                    break;
                }
            }
        }
        /** Log all non registered bots that have thumb gen enabled */
        if ($this->client_auth['verify'] === true && $user_agent_found === false) {
            file_put_contents('agents.log', "\r\n" . $user_agent, FILE_APPEND);
            $this->swy_error_log('Thumbnail Generation', 'Bot Logged');
        }
    }

    function prevent_clips_from_browsers() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $found_browser = false;
        $found_chatbot = false;
        $browser_found = '';
        $chatbot_found = '';
        /** Known Browser Agents */
        $web_browsers = $this->auth->ccmd_api_known_webbrowsers();
        /** Known Chatbots */
        $valid_bots = $this->auth->ccmd_api_valid_chatbots();
        if (!empty($user_agent)) {
            /** Check Browser Agents */
            foreach ($web_browsers as $browser) {
                if (strpos($user_agent, $browser) !== false) {
                    $found_browser = true;
                    $browser_found .= ' ' . $browser;
                }
            }
            /** Check Chatbots */
            foreach ($valid_bots as $bot) {
                if (strpos($user_agent, $bot) !== false) {
                    $found_chatbot = true;
                    $chatbot_found .= ' '. $bot;
                }
            }
        }
        /** Pre Log Message */
        $msg = $this->client_auth['channel'] . ' >>> ';
        /** Browser Deny Message */
        $deny_msg = 'Sorry, but clips can only be made by valid Twitch Chatbots! ';
        $deny_msg .= 'If you feel this message was an error, please contact SOWHOYOUdotCOM';
        /** Logs & Errors for Browser Agents and Chatbots */
        if ($found_browser && $found_chatbot) {
            /** Agents & Bot */
            $msg .= 'Bot Allowed with Browser >> ' . $chatbot_found . ' & Agents >' . $browser_found;
            // error_log($msg, 0);
        } elseif ($found_browser) {
            /** Agents Only */
            file_put_contents('blocked.log', "\r\nBrowser Blocked >>> Channel: " . $this->client_auth['channel'] . ' >> ' . $user_agent, FILE_APPEND);
            /** Send Deny Msg */
            $msg .= 'Browser Blocked >> ' . $browser_found;
            die($deny_msg);
        } elseif ($found_chatbot) {
            /** Bot Only */
            $msg .= 'Bot Allowed >> ' . $chatbot_found;
            // error_log($msg, 0);
        } else {
            /** Unknown */
            if (!empty($user_agent)) {
                $msg .= 'Unknown Chatbot >> ' . $user_agent;
                error_log($msg, 0);
            }
        }
    }

    function deny_banned_users() {
        $banned_users = $this->auth->ccmd_api_banned_users();
        foreach ($banned_users as $user) {
            if (strcasecmp($this->client_auth['channel'], $user) === 0) {
                error_log($user . ' - Banned Access Denied!', 0);
                die('API Access Denied!');
            }
        }
    }

    function swy_clip_processing() {
        $this->getClipLink();
        $this->checkForErrors();
        $this->nullCheck();
        $this->verifyClip();
        $this->logClipToDatabase();
        $this->sendClipLinkReply();
    }

}

$clip = new Clip();
/** Primary Functions */
$clip->swy_url_processing();
$clip->swy_var_validation();
$clip->deny_banned_users();
/** Secondary Functions */
$clip->prevent_clips_from_browsers();
$clip->disable_cloudbot_thumb_gen();
/** Final Functions */
$clip->swy_clip_processing();

?>
