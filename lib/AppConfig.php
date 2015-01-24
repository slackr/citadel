<?php namespace Parallax;

class AppConfig {
    const DB_TABLE_USERS = 'channel_members';
    const DB_TABLE_CHANNEL_MEMBERS = 'channel_members';
    const DB_TABLE_CHANNEL_MESSAGES = 'channel_messages';
    const DB_TABLE_CHANNELS = 'channels';

    const MESSAGE_TYPE_MESSAGE = 0;
    const MESSAGE_TYPE_EMESSAGE = 1;
    const MESSAGE_TYPE_ACTION = 2;
    const MESSAGE_TYPE_EACTION = 3;

    const MESSAGE_TYPE_JOIN = 10;
    const MESSAGE_TYPE_PART = 11;
    const MESSAGE_TYPE_KICK = 12;
    const MESSAGE_TYPE_BAN = 13;
    const MESSAGE_TYPE_SET_KEY = 14;
    const MESSAGE_TYPE_SET_INVITEONLY = 15;

    const ALLOWED_CHANNEL_MODES = 'skim';

    const DATE_FORMAT = 'Y-m-d H:i:s';  // should be compatible with MSSQL/MySQL string->datetime conversion

    const DEFAULT_TOPIC = null;

    const VALID_CHANNEL_NAME_REGEX = '/^[#a-z0-9\-\_]{1,31}$/si';

    const MESSAGE_GET_LIMIT = 50;
}
?>
