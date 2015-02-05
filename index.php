<?php
require_once (__DIR__).'/raindrops/controller/Authentication.php';
require_once (__DIR__).'/raindrops/controller/Registration.php';
require_once (__DIR__).'/raindrops/controller/SessionHandler.php';
require_once (__DIR__).'/raindrops/model/Database.php';
require_once (__DIR__).'/raindrops/router/Router.php';
require_once (__DIR__).'/lib/AppConfig.php';
require_once (__DIR__).'/controller/Channel.php';
require_once (__DIR__).'/controller/ChannelMember.php';
require_once (__DIR__).'/controller/ChannelMessage.php';

use \Parallax\Channel;
use \Parallax\ChannelMessage;
use \Parallax\ChannelMember;
use \Parallax\AppConfig;

session_start();

$router = new \Raindrops\Router();
$db = new \Raindrops\Database('mysql');
$realm = 'slacknet';
$id = null;
$anon_routes = array( // dont check session for these routes
    'verify-session',
    '',
);

$router->add_route('!',
    $data = array(
        'realm' => $realm,
    ),
    function($data) use (& $db, & $id, & $anon_routes, & $router) {
        $response = null;

        if (! $db->connect()) {
            $response = array(
                'status' => 'error',
                'message' => 'Database error: '. join('',$db->log_tail(1)),
            );
        }

        if (! in_array($router->request_action, $anon_routes)) {

            $sh = new \Raindrops\SessionHandler($db, $data['realm'], session_id(), $_SERVER['REMOTE_ADDR']);
            if ($sh->verify()) {
                $id = $sh->id;
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Session is invalid, please login again: '. join('', $sh->log_tail(1)),
                );
            }
        }

        return $response;
    }
);

$router->add_route('verify-session',
	$data = array(
		'realm' => $realm,
		'session_id' => $_POST['session_id'],
		'session_ip' => (isset($_POST['session_ip']) ? $_POST['session_ip'] : $_SERVER['REMOTE_ADDR']),
	),
	function($data) use (& $db) {
		$sh = new \Raindrops\SessionHandler($db, $data['realm'], $data['session_id'], $data['session_ip']);
		if ($sh->verify($read_only = true)) {
			$response = array(
				'status' => 'success',
				'message' => 'Session is valid',
                'session_id' => $sh->session_id,
                'session_ip' => $sh->session_ip,
			);
		} else {
			$response = array(
				'status' => 'error',
				'message' => 'Session is invalid: '. join('', $sh->log_tail(1)),
                'session_id' => $sh->session_id,
                'session_ip' => $sh->session_ip,
			);
		}
        return $response;
	}
);

$router->add_route('auth-reply',
    $data = array(
		/**
		 * POST data:
		 * identity = string
		 * challenge = hash string
		 * signature = base64 signature
		 * device = device assoc for pubkey
		 */
        'data' => $_POST['data'],
        'realm' => $realm,
    ),
    function($data) use (& $db) {
        $json_incoming = json_decode($data['data']);
        $json_error = json_last_error();

        $crypto = new \Raindrops\Crypto();
        $sfa = new \Raindrops\Authentication($db, $json_incoming->{'identity'}, $data['realm']);
        if ($sfa->get_identity()) {
            if ($crypto->verify_signature($json_incoming->{'challenge'}, $json_incoming->{'signature'}, $sfa->pubkeys[$data['device']])) {
                $response = array(
                    'status' => 'success',
                    'message' => 'Authentication successful',
                    'identity' => $sfa->identity,
                );

                $sfa->generate_auth_token(array($_SERVER['REMOTE_ADDR']));
                $_SESSION['rd_auth_token'] = $sfa->token;
                $_SESSION['rd_auth_identity'] = $sfa->identity;

                $response = array(
                    'status' => 'success',
                    'message' => 'Authentication successful',
                    'identity' => $sfa->identity,
					'session_id' => session_id(),
                );

            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => join('',$sfa->log_tail(1)),
                'identity' => $sfa->identity,
            );
        }
        return $response;
    }
);

$router->add_route('auth-request',
    $data = array(
		/**
		 * POST auth-request_data:
		 * identity = string
		 */
        'data' => $_POST['data'],
        'realm' => $realm,
    ),
    function($data) use (& $db) {
        $json_incoming = json_decode($data['data']);
        $json_error = json_last_error();

        $sfa = new \Raindrops\Authentication($db, $json_incoming->{'identity'}, $data['realm']);
        if ($sfa->get_identity() && $sfa->create_challenge()) {
            $response = array(
                'status' => 'success',
                'challenge' => $sfa->challenge,
                'identity' => $sfa->identity,
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => join('',$sfa->log_tail(1)),
                'identity' => $sfa->identity,
            );
        }
        return $response;
    }
);

$router->add_route('register',
    $data = array(
		/**
		 * POST register_data:
		 * identity = string
		 * pubkey = string
		 */
        'data' => str_replace(array("\n", "\r"), "\\n", $_POST['data']),
        'realm' => $realm,
    ),
    function($data) use (& $db) {
        $json_incoming = json_decode($data['data']);
        $json_error = json_last_error();

        $sfr = new \Raindrops\Registration($db, $json_incoming->{'identity'}, $data['realm']);

        $identity_data = array(
            'pubkey' => $json_incoming->{'pubkey'},
        );
        if ($sfr->create_identity($identity_data)) {
            $response = array(
                'status' => 'success',
                'identity' => $sfr->identity,
                'pubkeys' => $sfr->pubkeys,
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => join('',$sfr->log_tail(1)),
                'identity' => $sfr->identity,
                'pubkeys' => $sfr->pubkeys,
            );
        }
        return $response;
    }
);

$router->add_route('channel',
    $data = array(
		/**
		 * POST data:
		 * channel_id = int
		 * channel_name = string
		 * command = string
		 * message = string
		 * message_data = ban reason, kick reason, public key broadcast?
		 * message_type = int
		 * last_message_id = int - for get command
		 */
        'incoming' => str_replace(array("\n", "\r"), "\\n", $_POST['data']),
        'realm' => $realm,
    ),
    function($data) use (& $db, & $id) {
		if (! isset($id->id)) {
            $response = array(
                'status' => 'error',
                'message' => 'Unauthorized, please login first',
            );
			return $response;
		}
        $json_incoming = json_decode($data['incoming']);
        $json_error = json_last_error();

		$channel = new Channel($db, (int)$json_incoming->{'channel_id'}, $json_incoming->{'channel_name'});

		switch ($json_incoming->{'command'}) {
			case 'join':
				if ($channel->join($id->id)) {
					$response = array(
						'status' => 'success',
						'message' => "Successfully joined channel '". $channel->name ."'",
					);
				} else {
					$response = array(
						'status' => 'error',
						'message' => "Failed to join channel: ". join('',$channel->log_tail(1)),
					);
				}
			break;
			case 'part':
				if ($channel->part($id->id)) {
					$response = array(
						'status' => 'success',
						'message' => "Successfully parted channel '". $channel->name ."'",
					);
				} else {
					$response = array(
						'status' => 'error',
						'message' => "Failed to part channel: ". join('',$channel->log_tail(1)),
					);
				}
			break;
			case 'msg':
				$message = $json_incoming->{'message'};
				$message_data = $json_incoming->{'message_data'};
				$message_type = (int)$json_incoming->{'message_type'};

				$allowed_msg_command_types = array(
					AppConfig::MESSAGE_TYPE_MESSAGE,
					AppConfig::MESSAGE_TYPE_ACTION
				);

				if (in_array($message_type, $allowed_msg_command_types)) {
					$channel_message = new ChannelMessage($db, $id->id, $channel->id);

					if ($channel_message->send($message, $message_type, $message_data)) {
						$response = array(
							'status' => 'success',
							'message' => "Message sent to '". $channel->name ."'",
						);
					} else {
						$response = array(
							'status' => 'error',
							'message' => "Failed to send message: ". join('',$channel_message->log_tail(1)),
						);
					}
				} else {
					$response = array(
						'status' => 'error',
						'message' => "Failed to send message, invalid message_type. Allowed: ". join(', ', $allowed_msg_command_types),
					);
				}
			break;
			case 'get':
				$since_last_message_id = (int)$json_incoming->{'last_message_id'};

				$channel_message = new ChannelMessage($db, $id->id, $channel->id);

				if ($channel_message->get($since_last_message_id)) {
					$response = $channel_message->messages;
				} else {
					$response = array(
						'status' => 'error',
						'message' => "Failed to get messages: ". join('', $channel_message->log_tail(1)),
					);
				}
			break;
			default:
				$response = array(
					'status' => 'error',
					'message' => "Invalid channel command '". $json_incoming->{'command'} ."'",
				);
			break;

		}
        return $response;
    }
);

$router->add_route('*',
    $data = array(
    ),
    function($data) {
        $response = array(
            'include' => (__DIR__).'/client/view/ui.html',
        );

        return $response;
    }
);

$view = $router->process();
if (isset($view['include'])) {
	include $view['include'];
} else {
	echo json_encode($view);
}
?>
