<?php

require_once (__DIR__).'/../lib/AppConfig.php';
require_once (__DIR__).'/../controller/Channel.php';
require_once (__DIR__).'/../controller/ChannelMessage.php';
require_once (__DIR__).'/../raindrops/model/Database.php';

use \Parallax\AppConfig;
use \Parallax\Channel;
use \Parallax\ChannelMessage;
use \Raindrops\Database;

$db = new Database('mysql');
$db->connect();

$c = new Channel($db, $channel_id = 1);

$c->join($nickname_id = 1);
$m = new ChannelMessage($db, $nickname_id = 1, $channel_id = 1); // to #test
$msg = $m->send("testing", AppConfig::MESSAGE_TYPE_MESSAGE);
assert('$msg == true', 'Failed to send message to channel');
$c->part($nickname_id = 1);

$m_not_in = new ChannelMessage($db, $nickname_id = 1, $channel_id = 0);
$msg = $m_not_in->send("test fail", AppConfig::MESSAGE_TYPE_MESSAGE);
assert('$msg == false', 'Send should have failed to invalid channel');

$m_banned = new ChannelMessage($db, $nickname_id = 1, $channel_id = 4); // to #underchat
$msg = $m_banned->send("test banned", AppConfig::MESSAGE_TYPE_MESSAGE);
assert('$msg == false', 'Send should have failed to banned channel');

$c->join($nickname_id = 1);
$m_get = new ChannelMessage($db, $nickname_id = 1, $channel_id = 1); // to #test
$msg = $m_get->get(null);
assert('$msg == true && $msg->messages != null', 'Failed to get messages from channel');
$c->part($nickname_id = 1);


echo "Tests completed \r\n";
var_dump($db->log_tail());
var_dump($m->log_tail());
var_dump($m_not_in->log_tail());
var_dump($m_banned->log_tail());
var_dump($m_get->log_tail());
?>
