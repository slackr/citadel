<?php namespace Parallax;

require_once (__DIR__).'/../lib/AppConfig.php';
require_once (__DIR__).'/../raindrops/lib/Object.php';
require_once (__DIR__).'/../controller/ChannelMember.php';

class Channel extends \Raindrops\Object {
    public $name = null;
    public $id = 0;
    public $modes = null; // not yet
    public $topic = null;
    public $key = null;
    public $inviteonly = 0;
    public $exists = false;

    public function __construct(& $db, $channel_id = 0, $channel_name = null) {
        $this->db = $db;
        $this->id = $channel_id;
        $this->name = $channel_name;

        if ((int)$this->id > 0 || $this->name != null) {
            $this->get_channel_data();
        }
    }

    public function get_channel_data() {
        if (! $this->sanity_check()) {
            $this->log("Invalid channel input '". $this->name_tostring() ."'", 3);
            return false;
        }

        $query = "select * from ". AppConfig::DB_TABLE_CHANNELS;
        if ((int)$this->id > 0) {
            $query .= " where id = :id";
            $params = array(
                ':id' => $this->id,
            );
        } else {
            $query .= " where channel = :channel";
            $params = array(
                ':channel' => $this->name,
            );
        }

        $this->db->query($query, $params, $limit = 1);
        $row = $this->db->fetch();

        if (isset($row['id'])) {
            $this->id = (int)$row['id'];
            $this->name = $row['channel'];
            $this->topic = $row['topic'];
            $this->modes = $row['modes'];
            $this->key = $row['key'];
            $this->inviteonly = (int)$row['inviteonly'];
            $this->exists = true;

            $this->log("Channel data retrieved for '". $this->name_tostring() ."'", 1);
            return true;
        }

        $this->exists = false;
        $this->log("Channel '". $this->name_tostring() ."' not found.", 3);
        return true; // do not error out
    }

    public function name_tostring() {
        return "(n:". $this->name .",i:". $this->id .")";
    }

    public function topic($new) {
        $query = "update ". AppConfig::DB_TABLE_CHANNELS ." set topic = :topic where id = :channelid";

        $params = array(
            ':channelid' => $this->id,
            ':topic' => $new,
        );

        if ($this->db->query($query, $params)) {
            $this->topic = $new;
            $this->log("Successfully set new topic on '". $this->name_tostring() ."' to '". $this->topic ."'", 1);
            return true;
        }

        $this->log("Failed to set new topic on '". $this->name_tostring() ."' to '". $this->topic ."'", 3);
        return false;
    }

    public function mode($mode = '', $param = array()) {
        $add_mode = true;
        $mode = preg_replace("/[". AppConfig::ALLOWED_CHANNEL_MODES ."]+/sg", "", $mode);
        $param_step = 0;

        for ($step = 0; $step < strlen($mode); $step++) {
            switch ($mode[$step]) {
                case "-":
                    $add_mode = false;
                    $step++; // move forward to select the mode

                case "+":
                    $add_mode = true;
                    $step++; // move forward to select the mode

                default:
                    $current_mode = $mode[$step];

                    switch ($current_mode) {
                        case "k":
                            $this->key($param[$param_step], $add_mode);
                            $param_step++;
                            break;
                        case "o":
                            $this->op($param[$param_step], $add_mode);
                            $param_step++;
                            break;
                        default:
                            $this->modes = preg_replace("/[".$mode[$step]."]+/sg", '', $this->modes)
                                            . ($add_mode ? $mode[$step] : '');
                            break;
                    }
                    break;
            }
        }

        $query = "update ". AppConfig::DB_TABLE_CHANNELS ." set modes = :modes where id = :channelid";

        $params = array(
            ':channelid' => $this->id,
            ':modes' => $this->modes,
        );

        if ($this->db->query($query, $params, $limit = 1)) {
            $this->log("Successfully set modes on '". $this->name_tostring() ."' to '". $this->modes ."'");
            return true;
        }

        $this->log("Failed to set modes on '". $this->name_tostring() ."' to '". $this->modes ."'", 3);
        return false;
    }

    public function set_key($key) {
        $key = (strlen($key) > 0 ? $key : null);

        $query = "update ". AppConfig::DB_TABLE_CHANNELS ." set channelkey = :key where "
                ." id = :id";

        $params = array(
            ':id' => $this->id,
            ':key' => $key,
        );

        if ($this->db->query($query, $params, $limit = 1)) {
            $this->key = $key;
            $this->log("Successfully set key on channel '". $this->name_tostring() ."' to '". $key ."'", 1);
            return true;
        }

        $this->log("Failed to set key on channel '". $this->name_tostring() ."' to '". $key ."'", 3);
        return false;
    }

    // should verify channel existence
    public function set_inviteonly($inviteonly = 1) {
        $inviteonly = ($inviteonly ? 1 : 0);

        $query = "update ". AppConfig::DB_TABLE_CHANNELS ." set inviteonly = :inviteonly where "
                ."id = :id";

        $params = array(
            ':id' => $this->id,
            ':inviteonly' => $inviteonly,
        );

        if ($this->db->query($query, $params, $limit = 1)) {
            $this->inviteonly = $inviteonly;
            $this->log("Successfully set inviteonly on channel '". $this->name_tostring() ."' to '". $inviteonly ."'", 1);
            return true;
        }

        $this->log("Failed to set inviteonly on channel '". $this->name_tostring() ."' to '". $inviteonly ."'", 3);
        return false;
    }

    public function create() {
        if (! $this->sanity_check()) {
            $this->log("Invalid channel input '". $this->name_tostring() ."'", 3);
            return false;
        }

        $timestamp = date(AppConfig::DATE_FORMAT);

        $query = "insert into ". AppConfig::DB_TABLE_CHANNELS ." values "
                ." (:id, :channel, :topic, :channelkey, :inviteonly, :timestamp)";

        $params = array(
            ':id' => null,
            ':channel' => $this->name,
            ':topic' => AppConfig::DEFAULT_TOPIC,
            ':channelkey' => null,
            ':inviteonly' => 0,
            ':timestamp' => $timestamp,
        );

        if ($this->db->query($query, $params)) {
            $this->log("Successfully created new channel '". $this->name ."'", 1);
            return true;
        }

        $this->log("Failed to created new channel '". $this->name ."'", 3);
        return false;
    }

    public function join($nickname_id) {
        $auto_op = false; // should we aop user?

        if (! $this->exists) {
            if (! $this->create()) {
                $this->log("Failed to created new channel '". $this->name ."' before joining", 3);
                return false;
            }

            $this->get_channel_data(); // fetch newly created channel info
            $auto_op = true;
        }

        $timestamp = date(AppConfig::DATE_FORMAT);

        $cm = new ChannelMember($this->db, $nickname_id, $this->id);

        if ($cm->ismember) {
            $this->log("Nickname is already in the channel", 3);
            return false;
        }
        if ($this->inviteonly && ! $cm->isinvited) {
            $this->log("Channel is inviteonly", 3);
            return false;
        }
        if ($this->isbanned) {
            $this->log("Nickname is banned from the channel", 3);
            return false;
        }

        $query = "insert into ". AppConfig::DB_TABLE_CHANNEL_MEMBERS ." values "
                ." (:id, :channelid, :nicknameid, :isop, :timestamp)";

        $params = array(
            ':id' => null,
            ':channelid' => $this->id,
            ':nicknameid' => $nickname_id,
            ':isop' => ($auto_op ? 1 : 0),
            ':timestamp' => $timestamp,
        );

        if ($this->db->query($query, $params)) {
            $this->log("Successfully joined nickname '". $nickname_id ."' to '". $this->name_tostring() ."'", 1);
            return true;
        }

        $this->log("Failed to join nickname '". $nickname_id ."' to '". $this->name_tostring() ."'", 3);
        return false;
    }

    public function part($nickname_id) {
        $cm = new ChannelMember($this->db, $nickname_id, $this->id);
        if (! $cm->get_channel_member_data()) {
            $this->log("Failed to retrieve channel member data for '". $nickname_id ."@". $this->name_tostring() ."'", 3);
            return false;
        }

        if (! $cm->ismember) {
            $this->log("Nickname is not in the channel", 3);
            return false;
        }

        $query = "delete from ". AppConfig::DB_TABLE_CHANNEL_MEMBERS ." where "
                ." channelid = :channelid and nicknameid = :nicknameid";

        $params = array(
            ':channelid' => $this->id,
            ':nicknameid' => $nickname_id,
        );

        if ($this->db->query($query, $params, $limit = 1)) {
            $this->log("Successfully parted nickname '". $nickname_id ."' from '". $this->name_tostring() ."'", 0);
            return true;
        }

        $this->log("Failed to part nickname '". $nickname_id ."' from '". $this->name_tostring() ."'", 3);
        return false;
    }

    public function sanity_check() {
        if ((int)$this->id > 0) {
            $this->log("Channel input passed sanity check: 'valid int: ". $this->id ."'", 1);
            return true;
        }

        if (preg_match(AppConfig::VALID_CHANNEL_NAME_REGEX, $this->name)) {
            $this->log("Channel input passed sanity check: '". $this->name ."'", 1);
            return true;
        }

        $this->log("Channel input passed sanity check failed: '". $this->name ."'", 3);
        return false;
    }
}
?>
