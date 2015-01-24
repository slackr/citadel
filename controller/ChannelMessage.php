<?php namespace Parallax;

require_once (__DIR__).'/../lib/AppConfig.php';
require_once (__DIR__).'/../raindrops/lib/Object.php';
require_once (__DIR__).'/../controller/ChannelMember.php';

class ChannelMessage extends \Raindrops\Object {
    public $channel_id = 0;
    public $nickname_id = 0;
    public $nick = null;
    public $messages = null;

    public function __construct(& $db, $nickname_id, $channel_id) {
        $this->db = $db;
        $this->channel_id = $channel_id;
        $this->nickname_id = $nickname_id;

        $this->nick = new ChannelMember($this->db, $this->nickname_id, $this->channel_id);
    }

    public function get($since_last_message_id = 0) {
        $this->messages = null;

        if (! $this->nick->ismember) {
            $this->log("Nickname is not in the channel, join first", 3);
            return false;
        }

        $query = "select id,message,messagetype from ". AppConfig::DB_TABLE_CHANNEL_MESSAGES
                ." where nicknameid = :nicknameid and channelid = :channelid "
                ." and id > :since_last_message_id"
                ." and timestamp >= :nick_join_timestamp";

        $params = array(
            ':nicknameid' => $this->nickname_id,
            ':channelid' => $this->channel_id,
            ':since_last_message_id' => (int)$since_last_message_id,
            ':nick_join_timestamp' => $this->nick->timestamp,
        );

        if (! $this->db->query($query, $params, AppConfig::MESSAGE_GET_LIMIT)) {
            $this->log("Failed to get message for '". $this->nickname_id ."' from '". $this->channel_id ."'", 3);
            return false;
        }

        $rows = $this->db->fetch_all();
        $last_message = $rows[sizeof($rows)-1];

        $this->messages = array(
            'channel_id' => $this->channel_id,
            'last_message_id' => (isset($last_message) ? (int)$last_message['id'] : 0),
            'messages' => $rows,
        );

        $this->log("Successfully retrieved messages for '". $this->nickname_id ."' from '". $this->channel_id ."'", 1);
        return true;
    }

    public function send($message, $message_type = AppConfig::MESSAGE_TYPE_MESSAGE, $message_data = null) {
        $timestamp = date(AppConfig::DATE_FORMAT);

        if (! $this->nick->ismember) {
            $this->log("Nickname is not in the channel, join first", 3);
            return false;
        }
        if ($this->nick->isbanned && ! $this->nick->isop) {
            $this->log("Nickname is banned from the channel", 3);
            return false;
        }

        $query = "insert into ". AppConfig::DB_TABLE_CHANNEL_MESSAGES ." values "
                ." (:id, :message, :nicknameid, :channelid, :timestamp, :messagetype, :messagedata)";

        $params = array(
            ':id' => null,
            ':message' => $message,
            ':nicknameid' => $this->nickname_id,
            ':channelid' => $this->channel_id,
            ':timestamp' => $timestamp,
            ':messagedata' => $message_data,
            ':messagetype' => (int)$message_type,
        );

        if ($this->db->query($query, $params)) {
            $this->log("Successfully sent message from nickname '". $this->nickname_id ."' to '". $this->channel_id ."'", 1);
            return true;
        }

        $this->log("Failed to send message from '". $this->nickname_id ."' to '". $this->channel_id ."'", 3);
        return false;
    }
}
?>
