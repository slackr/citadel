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

$router = new \Raindrops\Router();
$db = new \Raindrops\Database('mysql');
$realm = 'parallax';
$id = null;
$anon_routes = array( // dont check session for these routes
    'verify-session',
    'auth-request',
    'auth-reply',
    'register',
    'lib',
    'controller',
    'view',
    'app.js',
    '',
);

session_start();

$router->add_route('!',
    $data = array(
        'realm' => $realm,
    ),
    function($data) use (& $db, & $id, & $anon_routes, & $router) {
        $response = null;

        if (! $db->connect()) {
            $response = array(
                'status' => 'error',
                'message' => 'Database error',
                'db_log' => $db->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        }

        if (! in_array($router->request_action, $anon_routes)) {
            $sh = new \Raindrops\SessionHandler($db, $data['realm']);
            if ($sh->verify()) {
                $id = $sh->id;
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Not authenticated, please login or register',
                    'db_log' => $sh->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                    'log' => $sh->log_tail(AppConfig::DEBUG_LOG_TAIL),
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
				'message' => 'Session verified',
                'session_id' => $sh->session_id,
                'session_ip' => $sh->session_ip,
                'db_log' => $sh->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sh->log_tail(AppConfig::DEBUG_LOG_TAIL),
			);
		} else {
			$response = array(
				'status' => 'error',
				'message' => 'Session did not verify',
                'session_id' => $sh->session_id,
                'session_ip' => $sh->session_ip,
                'db_log' => $sh->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sh->log_tail(AppConfig::DEBUG_LOG_TAIL),
			);
		}
        return $response;
	}
);

$router->add_route('auth-reply',
    $data = array(
		/**
		 * POST:
		 * nonce_identity = string
		 * nonce = hash string
		 * nonce_signature = base64 signature
		 * device = device assoc for pubkey
		 */
        'nonce_identity' => $_POST['nonce_identity'],
        'nonce' => $_POST['nonce'],
        'nonce_signature' => $_POST['nonce_signature'],
        'device' => $_POST['device'],
        'realm' => $realm,
    ),
    function($data) use (& $db) {
        $crypto = new \Raindrops\Crypto();
        $sfa = new \Raindrops\Authentication($db, $data['nonce_identity'], $data['realm']);
        if ($sfa->get_identity()) {
            if ($sfa->verify_challenge_response($data)) {
                $sfa->generate_auth_token(array($_SERVER['REMOTE_ADDR']));
                $_SESSION['rd_auth_token'] = $sfa->token;
                $_SESSION['rd_auth_identity'] = $sfa->identity;

                $response = array(
                    'status' => 'success',
                    'message' => 'Authentication successful',
                    'identity' => $sfa->identity,
                    'device' => $data['device'],
					'session_id' => session_id(),
                    'db_log' => $sfa->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                    'log' => $sfa->log_tail(AppConfig::DEBUG_LOG_TAIL),
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Challenge verification failed',
                    'nonce_identity' => $sfa->identity,
                    'device' => $data['device'],
                    'nonce_signature' => $data['nonce_signature'],
                    'nonce' => $data['nonce'],
                    'db_log' => $sfa->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                    'log' => $sfa->log_tail(AppConfig::DEBUG_LOG_TAIL),
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Identity retrieval failed',
                'nonce_identity' => $sfa->identity,
                'device' => $data['device'],
                'nonce_signature' => $data['nonce_signature'],
                'nonce' => $data['nonce'],
                'db_log' => $sfa->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfa->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        }

        return $response;
    }
);

$router->add_route('auth-request',
    $data = array(
		/**
		 * POST:
		 * identity = string
		 * device = string
		 */
        'identity' => $_POST['identity'],
        'device' => $_POST['device'],
        'realm' => $realm,
    ),
    function($data) use (& $db) {
        $sfa = new \Raindrops\Authentication($db, $data['identity'], $data['realm']);
        if ($sfa->get_identity() && $sfa->create_challenge($data['device'])) {
            $response = array(
                'status' => 'success',
                'nonce' => $sfa->challenge,
                'identity' => $sfa->identity,
                'device' => $data['device'],
                'db_log' => $sfa->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfa->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Authentication request failed',
                'identity' => $sfa->identity,
                'device' => $data['device'],
                'db_log' => $sfa->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfa->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        }
        return $response;
    }
);

$router->add_route('register',
    $data = array(
		/**
		 * POST:
		 * identity = string
		 * pubkey = string
		 */
        'identity' => $_POST['identity'],
        'pubkey' => $_POST['pubkey'],
        'device' => $_POST['device'],
        'email' => $_POST['email'],
        'realm' => $realm,
    ),
    function($data) use (& $db) {
        $sfr = new \Raindrops\Registration($db, $data['identity'], $data['realm']);

        $identity_data = array(
            'pubkey' => $data['pubkey'],
            'device' => $data['device'],
            'email' => $data['email'],
        );
        if ($sfr->create_identity($identity_data)) {
            $response = array(
                'status' => 'success',
                'message' => 'Identity created',
                'identity' => $sfr->identity,
                'email' => $sfr->email,
                'device' => $data['device'],
                'pubkeys' => $sfr->pubkeys,
                'db_log' => $sfr->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfr->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Registration failed',
                'identity' => $sfr->identity,
                'email' => $sfr->email,
                'device' => $data['device'],
                'pubkeys' => $sfr->pubkeys,
                'db_log' => $sfr->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfr->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        }
        return $response;
    }
);

$router->add_route('delete-identity',
    $data = array(
		/**
		 * POST:
		 * identity = string
		 */
        'identity' => $_POST['identity'],
        'realm' => $realm,
    ),
    function($data) use (& $db, & $id) {
        if ($id->identity !== $data['identity']) {
            $response = array(
                'status' => 'error',
                'message' => 'Please confirm deletion by posting your identity',
                'identity' => $data['identity'],
                'auth_identity' => $id->identity,
            );
            return $response;
        }

        $sfr = new \Raindrops\Registration($db, $data['identity'], $data['realm']);

        if ($sfr->delete_identity()) {
            $response = array(
                'status' => 'success',
                'message' => 'Identity deleted',
                'identity' => $sfr->identity,
                'db_log' => $sfr->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfr->log_tail(AppConfig::DEBUG_LOG_TAIL),
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Identity deletion failed',
                'identity' => $sfr->identity,
                'pubkeys' => $sfr->pubkeys,
                'db_log' => $sfr->db->log_tail(AppConfig::DEBUG_LOG_TAIL),
                'log' => $sfr->log_tail(AppConfig::DEBUG_LOG_TAIL),
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
						'message' => "Failed to join channel: ". json_encode($channel->log_tail(5)),
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
						'message' => "Failed to part channel: ". json_encode($channel->log_tail(5)),
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
							'message' => "Failed to send message: ". json_encode($channel_message->log_tail(5)),
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
						'message' => "Failed to get messages: ". json_encode($channel_message->log_tail(5)),
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

$router->add_route('lib',
    $data = array(
    ),
    function($data) use(& $router) {
        switch ($router->secondary_action) {
            case 'config.js':
            case 'object.js':
            case 'crypto.js':
            case 'ext/jquery.js':
            case 'ext/jquery-ui.js':
            case 'ext/socket.io.js':
                $response = array(
                    'include' => (__DIR__) .'/client/'. $router->request_action .'/'. $router->secondary_action
                );
            break;
        }

        return $response;
    }
);
$router->add_route('view',
    $data = array(
    ),
    function($data) use(& $router) {
        switch ($router->secondary_action) {
            case 'ui.html':
            case 'ui.css':
            case 'normalize.css':
            case 'theme/blu.css':
            case 'theme/dark.css':
                $response = array(
                    'include' => (__DIR__) .'/client/'. $router->request_action .'/'. $router->secondary_action
                );
            break;
        }

        return $response;
    }
);
$router->add_route('controller',
    $data = array(
    ),
    function($data) use(& $router) {
        switch ($router->secondary_action) {
            case 'client.js':
            case 'socket.js':
            case 'ui.js':
                $response = array(
                    'include' => (__DIR__) .'/client/'. $router->request_action .'/'. $router->secondary_action
                );
            break;
        }

        return $response;
    }
);
$router->add_route('app.js',
    $data = array(
    ),
    function($data) use(& $router) {
        $response = array(
            'include' => (__DIR__) .'/client/'. $router->request_action
        );

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
    $content_types = array(
        'css' => 'text/css',
        'js' => 'text/javascript',
        'html' => 'text/html'
    );

    $ext = pathinfo($view['include'], PATHINFO_EXTENSION);
    header('Content-type: '. $content_types[$ext]);

	include $view['include'];
    //echo $view['include'];
} else {
    if (! AppConfig::DEBUG) {
        $view['log'] = [];
        $view['db_log'] = [];
    }
	echo json_encode($view);
}
?>
