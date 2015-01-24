<?php

require_once (__DIR__).'/../controller/Channel.php';
require_once (__DIR__).'/../raindrops/model/Database.php';
require_once (__DIR__).'/../raindrops/controller/Identity.php';

use \Parallax\Channel;
use \Raindrops\Database;
use \Raindrops\Identity;

$db = new Database('mysql');
$db->connect();

$i = new Identity($db, 'test', 'slacknet');
$i->get_identity();

$c = new Channel($db, 0, 'newtest');

$join = $c->join($i->id);
assert('$join == true', 'Failed to join channel');
$part = $c->part($i->id);
assert('$part == true', 'Failed to part channel');

$c->id = 0;
$c->name = 'test';
$get_data = $c->get_channel_data();
assert('$get_data == true', 'Channel data failed to retrieve');

$set_key = $c->set_key('test');
assert('$set_key == true && $c->key == "test"', 'Failed to set channel key');
$set_key = $c->set_key(null);
assert('$set_key == true && $c->key == null', 'Failed to unset channel key');

$set_i = $c->set_inviteonly();
assert('$set_i == true && $c->inviteonly == 1', 'Failed to set channel inviteonly');
$set_i = $c->set_inviteonly(false);
assert('$set_i == true && $c->inviteonly == 0', 'Failed to unset channel inviteonly');

echo "Tests completed \r\n";
var_dump($db->log_tail());
var_dump($i->log_tail());
var_dump($c->log_tail());
?>
