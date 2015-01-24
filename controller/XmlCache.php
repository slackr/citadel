<?php
require_once (__DIR__).'/../lib/_object.php';

class XMLCache extends Object {

    public $url = '';
    public $data = null;

    private $cache_expirey = 0;
    private $cache_table = '';
    private $db = null;
    private $dom = null;
    private $max_items = 0;

    final public function __construct(& $db, $url, $max_items = 10, $cache_table = "xmlcache_data", $cache_expirey = 3600) {
        $this->db = $db;
        $this->dom = new DOMDocument();
        $this->cache_table = '`'.preg_replace('/[^a-z0-9_]/si','',$cache_table).'`';
        $this->cache_expirey = $cache_expirey;
        $this->url = $url;
        $this->max_items = $max_items;
    }

    public function xml2data() {
        $i = 1;
        foreach ($this->dom->getElementsByTagName('item') as $node) {
            if ($i > $this->max_items) { return $d; }

            $d[] = (object) array(
                "title"     => $node->getElementsByTagName('title')->item(0)->nodeValue,
                "desc"      => $node->getElementsByTagName('description')->item(0)->nodeValue,
                "link"      => $node->getElementsByTagName('link')->item(0)->nodeValue,
                "date"      => $node->getElementsByTagName('pubDate')->item(0)->nodeValue
            );

            $i++;
        }
        return $d;
    }

    public function refresh_xml_data() {
        $options = array(
            'http' => array(
                'timeout' => 3,
                'method' => 'GET'
            ),
        );

        $context = stream_context_create($options);
        $xml_raw = file_get_contents($this->url, false, $context);

        if ($this->dom->loadXML($xml_raw)) {
            $this->data = $this->xml2data();
            $this->log("Data refresh from XML '".$this->url."' successful");
            return true;
        }

        $this->log("Data refresh from XML '".$this->url."' failed", 3);
        $this->data = null;
        return false;
    }

    public function retrieve_data() {
        $time = time();
        $query = "select * from ".$this->cache_table." where xml_url = :xml_url "
                ." and (:time - cache_timestamp) < :cache_expirey limit 1";

        $params = array(
            ':xml_url' => $this->url,
            ':time' => $time,
            ':cache_expirey' => $this->cache_expirey,
        );

        $this->db->query($query, $params);
        $row = $this->db->fetch();

        if ($row['id']) {
            $this->data = json_decode($row['data']);
            $this->log("Data retrieved from XML Cache");
            return true;
        } else {
            $this->log("No data found in cache (expired or non-existant)");
            if ($this->refresh_xml_data()) {
                $this->write_cache();
                return true;
            }
        }

        return false;
    }

    private function write_cache() {
        $time = time();
        $query = "select id from ".$this->cache_table." where xml_url = :xml_url limit 1";

        $params = array(
            ':xml_url' => $this->url,
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
            $query = "insert into ".$this->cache_table." (`id`,`xml_url`,`cache_timestamp`,`data`) "
                    ." values (NULL, :xml_url, :cache_timestamp, :data)";

            $params = array(
                ':data' => json_encode($this->data),
                ':cache_timestamp' => $time,
                ':xml_url' => $this->url,
            );
        }

        if ($this->db->query($query, $params)) {
            $this->log("Successfully wrote XML Cache");
        } else {
            $this->log("XML Cache write failed (SQL)", 3);
        }
    }

    public function clear_cache() {
        $query = $this->cache_table." where xml_url = :xml_url";

        $params = array(
            ':xml_url' => $this->url,
        );

        if ($this->db->delete($query, $params)) {
            $this->log("Successfully cleared XML Cache");
        } else {
            $this->log("XML Cache clear failed (SQL)", 3);
        }
    }
}
?>
