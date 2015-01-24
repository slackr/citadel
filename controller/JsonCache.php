<?php
require_once (__DIR__).'/../lib/_object.php';

class JSONCache extends Object {

    public $url = '';
    public $data = null;

    private $cache_expirey = 0;
    private $cache_table = '';
    private $api_name = '';

    private $api_urls = array(
        "twitter"       => "http://api.twitter.com/1/statuses/user_timeline.json",
        "facebook"     => "",
        "flickr"       => "",
    );

    private $db = null;

    final public function __construct(& $db, $api_name, $properties, $cache_table = "jsoncache_data", $cache_expirey = 3600) {
        $this->db = $db;
        $this->cache_table = '`'.preg_replace('/[^a-z0-9_]/si','',$cache_table).'`';
        $this->api_name = $api_name;
        $this->cache_expirey = $cache_expirey;

        $this->url = $this->build_url($properties);
    }

    public function build_url($properties) {
        $i = 0;
        $u = "";

        $u = $this->api_urls[$this->api_name]."?";
        foreach ($properties as $key => $val) {
            if ($i > 0) { $u .= "&"; }

            $u .= urlencode($key)."=".urlencode($val);

            $i++;
        }

        return $u;
    }

    public function refresh_json_data() {
        $options = array(
            'http' => array(
                'timeout' => 3,
                'method' => 'GET'
            ),
        );

        $context  = stream_context_create($options);
        $json_raw = file_get_contents($this->url, false, $context);

        if ($json_raw) {
            $this->data = json_decode($json_raw);
            $this->log("Data refresh from API '".$this->api_name."' successful");
            return true;
        }
        $this->log("Data refresh from API '".$this->api_name."' failed", 3);
        $this->data = null;
        return false;
    }

    public function retrieve_data() {
        $time = time();

        $query  = "select * from ".$this->cache_table." where api_name = :api_name "
                . " and (:time - cache_timestamp) < :cache_expirey limit 1";

        $params = array(
            ':api_name'         => $this->api_name,
            ':time'             => $time,
            ':cache_expirey'    => $this->cache_expirey,
        );

        $this->db->query($query, $params);
        $row = $this->db->fetch();

        if ($row['id']) {
            $this->data = json_decode($row['data']);
            $this->log("Data retrieved from JSON Cache");
            return true;

        } else {
            $this->log("No data found in cache (expired or non-existant)");
            if ($this->refresh_json_data()) {
                $this->write_cache();
                return true;
            }
        }

        return false;
    }

    private function write_cache() {
        $time = time();

        $query = "select id from ".$this->cache_table." where api_name = :api_name limit 1";

        $params = array(':api_name' => $this->api_name,
                        );

        $this->db->query($query, $params);
        $row = $this->db->fetch();

        if ($row['id']) {
            $query = "update ".$this->cache_table." set data = :data, cache_timestamp = :cache_timestamp where id = :id";

            $params = array(
                ':data' => json_encode($this->data),
                ':cache_timestamp' => $time,
                ':id' => $row['id'],
            );

        } else {
            $query  = "insert into ".$this->cache_table." (`id`,`api_name`,`cache_timestamp`,`data`) "
                    . " values (NULL, :api_name, :cache_timestamp, :data)";

            $params = array(
                ':data' => json_encode($this->data),
                ':cache_timestamp' => $time,
                ':api_name' => $this->api_name,
            );
        }

        if ($this->db->query($query, $params)) {
            $this->log("Successfully wrote JSON Cache");
        } else {
            $this->log("JSON Cache write failed (SQL)", 3);
        }
    }

    public function clear_cache() {
        $query = $this->cache_table." where api_name = :api_name";

        $params = array(
            ':api_name' => $this->api_name,
        );

        if ($this->db->delete($query, $params)) {
            $this->log("Successfully cleared JSON Cache");
        } else {
            $this->log("JSON Cache clear failed (SQL)", 3);
        }
    }
}
?>
