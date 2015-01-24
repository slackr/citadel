<?php namespace Parallax;

require_once (__DIR__).'/../lib/AppConfig.php';
require_once (__DIR__).'/../raindrops/lib/Object.php';

class ChannelMember extends \Raindrops\Object {
    public $nickname_id = 0;
    public $isbanned = 0;
    public $isop = 0;
    public $isinvited = 0;
    public $channel_id = 0;
    public $nickname = null;
    public $join_timestamp = null;

    public function __construct(& $db, $nickname_id, $channel_id) {
        $this->db = $db;
        $this->nickname_id = $nickname_id;
        $this->channel_id = $channel_id;

        $this->get_channel_member_data();
    }

    public function get_channel_member_data() {
        $query = <<<EOF
    select
        cm.*
        ,ifnull(ci.id,0) as isinvited
        ,ifnull(cb.id,0) as isbanned
    from `%s` as cm
        left join `channel_invites` as ci
            on cm.channelid = ci.channelid
        left join `channel_bans` as cb
            on cm.channelid = cb.channelid
    where
        cm.nicknameid = :nicknameid and
        cm.channelid = :channelid
EOF;

        $query = sprintf($query, AppConfig::DB_TABLE_CHANNEL_MEMBERS);

        $params = array(
            ':nicknameid' => $this->nickname_id,
            ':channelid' => $this->channel_id,
        );

        if (! $this->db->query($query, $params, $limit = 1)) {
            $this->log("Failed to retrieve channel member data for '". $this->nickname_id ."@". $this->channel_id ."'", 3);
            return false;
        }

        $row = $this->db->fetch();

        $this->isbanned = (int)$row['isbanned'];
        $this->isinvited = (int)$row['isinvited'];

        if (isset($row['id'])) {
            $this->isop = (int)$row['isop'];
            $this->timestamp = $row['timestamp'];
            $this->ismember = 1;

            $this->log("Channel member data retrieved for '". $this->nickname_id ."' in '". $this->channel_id ."'", 1);
        } else {
            $this->timestamp = null;
            $this->isop = 0;
            $this->ismember = 0;

            $this->log("Could not find channel member '". $this->nickname_id ."' in '". $this->channel_id ."'", 1);
        }
        return true;
    }

    public function op($op = true) {
        $op = ($op ? 1 : 0);

        $query = "update ". AppConfig::DB_TABLE_CHANNEL_MEMBERS ." set isop = :op where "
                ." channelid = :channelid and nicknameid = :nicknameid";

        $params = array(
            ':channelid' => $this->channel_id,
            ':nicknameid' => $this->nickname_id,
            ':isop' => $op,
        );

        if ($this->db->query($query, $params, $limit = 1)) {
            $this->log("Successfully set op nickname '". $nickname_id ."' to '". $op ."' in '". $this->channel_id ."'", 1);
            return true;
        }

        $this->log("Failed to set op on nickname '". $nickname_id ."' to '". $op ."' in '". $this->channel_id ."'", 3);
        return false;
    }
}
?>
