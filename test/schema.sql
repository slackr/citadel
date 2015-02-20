SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `channels` (
`id` int(11) NOT NULL,
  `channel` varchar(32) NOT NULL,
  `topic` text,
  `channelkey` varchar(32) DEFAULT NULL,
  `inviteonly` tinyint(1) NOT NULL DEFAULT '0',
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `channel_bans` (
`id` int(11) NOT NULL,
  `channelid` int(11) NOT NULL,
  `nicknameid` int(11) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `channel_invites` (
`id` int(11) NOT NULL,
  `channelid` int(11) NOT NULL,
  `nicknameid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `channel_members` (
`id` int(11) NOT NULL,
  `channelid` int(11) NOT NULL,
  `nicknameid` int(11) NOT NULL,
  `isop` tinyint(1) NOT NULL DEFAULT '0',
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `channel_messages` (
`id` int(11) NOT NULL,
  `message` text,
  `nicknameid` int(11) NOT NULL,
  `channelid` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `messagetype` int(11) NOT NULL,
  `messagedata` text
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `rd_identities` (
`id` int(11) NOT NULL,
  `identity` varchar(32) NOT NULL,
  `password` text,
  `email` text,
  `timestamp` datetime NOT NULL,
  `realm` varchar(64) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `rd_keys` (
`id` int(11) NOT NULL,
  `identity_id` int(11) NOT NULL,
  `device` varchar(32) NOT NULL,
  `pubkey` text NOT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `rd_nonce_history` (
`id` int(11) NOT NULL,
  `nonce` varchar(128) NOT NULL,
  `nonce_identity` varchar(128) NOT NULL,
  `timestamp` datetime NOT NULL,
  `realm` varchar(64) DEFAULT NULL,
  `device` varchar(32) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8;


ALTER TABLE `channels`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `channel_bans`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `channel_invites`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `channel_members`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `channel_messages`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `rd_identities`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `rd_keys`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `rd_nonce_history`
 ADD PRIMARY KEY (`id`);


ALTER TABLE `channels`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=7;
ALTER TABLE `channel_bans`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
ALTER TABLE `channel_invites`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `channel_members`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8;
ALTER TABLE `channel_messages`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=19;
ALTER TABLE `rd_identities`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=55;
ALTER TABLE `rd_keys`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=23;
ALTER TABLE `rd_nonce_history`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=36;
