<?php

require_once '/home/sowhoyou/public_html/api/tools/auth.php';

class ClipTracker {

    private $auth;
    private $conn;

    public function __construct() {
        $this->auth = new Auth();
    }

    /**
     * Log to Database, then to My Discord
     */
    public function log($channelID, $clipName, $verify, $sender) {
        try {
            $this->conn = new mysqli(
                $this->auth->mysql['clips_db_host'],
                $this->auth->mysql['clips_db_user'],
                $this->auth->mysql['clips_db_pass'],
                $this->auth->mysql['clips_db_name']
            );
            if ($this->conn->connect_errno) {
                // Should not have any errors to log
            } else {
                $this->conn->query("CREATE TABLE `$channelID` ( `id` INT NOT NULL AUTO_INCREMENT , `clip` VARCHAR(50) NOT NULL , PRIMARY KEY (`id`))");
                $this->conn->query("INSERT INTO `$channelID` (`clip`) VALUES ('$clipName')");
            }
            $this->conn->close();
        } catch (Exception $e) {
            error_log('>> Channel ID: ' . $channelID . ' >> Clip ID: ' . $clipName . ' >> Clip Tracker > Log >> ' . $e, 0);
        }
        $this->logToDiscord($channelID, $clipName, $verify, $sender);
    }

    /**
     * Send Clip to My Discord
     */
    public function logToDiscord($channelID, $clipID, $verify, $sender) {
        try {
            $webhookurl = $this->auth->discord['clips_webhook']; // CLIPS CHANNEL
            $channelLink = $this->getChannelLink($channelID);
            if ($verify === true) {
                $hookObject = $this->create_embedded($clipID, $sender);
            } else {
                $hookObject = $this->create_codeblock($clipID, $sender, false, $channelLink);
            }
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_URL => $webhookurl,
                CURLOPT_POSTFIELDS => $hookObject,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                ],
            ]);
            $response = curl_exec($ch);
            if (curl_errno($ch)) { 
                error_log('>> Channel ID: ' . $channelID . ' >> Clip ID: ' . $clipID . ' >> Clip Tracker > logToDiscord1 >> ' . curl_error($ch), 0);
            }
            curl_close($ch);
        } catch (Exception $e) {
            error_log('>> Channel ID: ' . $channelID . ' >> Clip ID: ' . $clipID . ' >> Clip Tracker > logToDiscord s >> ' . $e, 0);
        }
    }

    /**
     * Send Clip to Client Discord
     */
    public function sendClientWebhook($webhookurl, $channelID, $clipID, $embedded, $sender) {
        try {
            if ($embedded === false) {
                $hookObject = $this->create_codeblock($clipID, $sender, true, null);
            } else {
                $hookObject = $this->create_embedded($clipID, $sender);
            }
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_URL => $webhookurl,
                CURLOPT_POSTFIELDS => $hookObject,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                ],
            ]);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log('>> Channel ID: ' . $channelID . ' >> Clip ID: ' . $clipID . ' >> logToClientDiscord1 >> ' . curl_error($ch), 0);
            }
            curl_close($ch);
        } catch (Exception $e) {
            error_log('>> Channel ID: ' . $channelID . ' >> Clip ID: ' . $clipID . ' >> Clip Tracker > sendClientWebhook >> ' . $e, 0);
        }
    }

    /**
     * Get Channel Link for My Discord
     */
    public function getChannelLink($id) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => [
                    'Client-ID: ' . $this->auth->twitch['swy_god_token_client_id'],
                    'Authorization: Bearer ' . $this->auth->twitch['swy_god_token_client_token'],
                ],
                CURLOPT_URL => 'https://api.twitch.tv/helix/users?id=' . $id,
            ]);
            $result = curl_exec($curl);
            if (!curl_errno($curl)) {
                $json = json_decode($result, true);
                if (isset($json['data']['0']['login'])) {
                    $id = $json['data']['0']['login'];
                }
            } else { error_log('>> Channel ID: ' . $id . ' >> Clip Tracker > getChannelLink >> ' . curl_error($curl), 0); }
            curl_close($curl);
        } catch (Exception $e) {
            error_log('>> Channel ID: ' . $id . ' >> Clip Tracker > getChannelLink >> ' . $e, 0);
        }
        return 'https://www.twitch.tv/' . $id;
    }
    
    /**
     * Get Random Color for Codeblock
     */
    public function random_color() {
        return hexdec(('#' . (str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT)) 
        . (str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT)) . (str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT))));
    }

    /**
     * Create Codeblock Hook Object
     */
    public function create_codeblock($clip, $sender, $is_client, $channel_link = null) {
        if ($is_client) {
            if (!empty($sender)) {
                return json_encode([
                    "embeds" => [
                        [
                            "type" => "rich",
                            "color" => $this->random_color(),

                            "title" => "A New Clip was Created by: " . $sender,

                            "description" => "https://clips.twitch.tv/" . $clip,
                            "footer" => [
                                "text" => "Created with the SOWHOYOUdotCOM Clip Command",
                                "icon_url" => "https://cdn.betterttv.net/emote/5aa0f2aa156cfc58a4db544c/3x"
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                return json_encode([
                    "embeds" => [
                        [
                            "type" => "rich",
                            "color" => $this->random_color(),

                            "description" => "https://clips.twitch.tv/" . $clip,
                            "footer" => [
                                "text" => "Created with the SOWHOYOUdotCOM Clip Command",
                                "icon_url" => "https://cdn.betterttv.net/emote/5aa0f2aa156cfc58a4db544c/3x"
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        } else {
            return json_encode([
                "username" => "SOWHOYOUdotCOM / CLIPCOMMAND",
                "tts" => false,
                "embeds" => [
                    [
                        "type" => "rich",
                        "color" => $this->random_color(),

                        "title" => (!empty($sender) ? ("A New Clip was Created by: " . $sender) : $channel_link),
                        "url" => $channel_link,

                        "description" => "https://clips.twitch.tv/" . $clip,
                        "footer" => [
                            "text" => "Created with the SOWHOYOUdotCOM Clip Command",
                            "icon_url" => "https://cdn.betterttv.net/emote/5aa0f2aa156cfc58a4db544c/3x"
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Create Embed Hook Object
     */
    public function create_embedded($clip, $sender) {
        if (!empty($sender)) {
            return json_encode([
                "content" => "**A New Clip was Created by: " . $sender . "**\nhttps://clips.twitch.tv/" . $clip . "\n> ᴄʟɪᴘ ᴄʀᴇᴀᴛᴇᴅ ᴡɪᴛʜ ᴛʜᴇ ꜱᴏᴡʜᴏʏᴏᴜᴅᴏᴛᴄᴏᴍ ᴄʟɪᴘ ᴄᴏᴍᴍᴀɴᴅ",
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            return json_encode([
                "content" => "**New Clip!** https://clips.twitch.tv/" . $clip . "\n> ᴄʟɪᴘ ᴄʀᴇᴀᴛᴇᴅ ᴡɪᴛʜ ᴛʜᴇ ꜱᴏᴡʜᴏʏᴏᴜᴅᴏᴛᴄᴏᴍ ᴄʟɪᴘ ᴄᴏᴍᴍᴀɴᴅ",
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

}

?>
