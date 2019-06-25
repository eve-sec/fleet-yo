SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `authTokens` (
  `id` int(11) NOT NULL,
  `selector` char(32) DEFAULT NULL,
  `token` char(64) DEFAULT NULL,
  `characterID` bigint(16) NOT NULL,
  `expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `config` (
  `cfg_key` varchar(32) NOT NULL,
  `value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `esisso` (
  `id` int(11) NOT NULL,
  `characterID` bigint(16) NOT NULL,
  `characterName` varchar(255) DEFAULT NULL,
  `refreshToken` varchar(500) NOT NULL,
  `accessToken` varchar(4096) DEFAULT NULL,
  `expires` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ownerHash` varchar(255) NOT NULL,
  `failcount` int(11) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `fleetmembers` (
  `characterID` bigint(16) NOT NULL,
  `fleetID` bigint(16) DEFAULT NULL,
  `backupfc` tinyint(1) NOT NULL DEFAULT '0',
  `wingID` bigint(16) NOT NULL,
  `squadID` bigint(16) NOT NULL,
  `role` varchar(32) DEFAULT NULL,
  `fleetWarp` tinyint(1) NOT NULL DEFAULT '1',
  `joined` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `fleetMetrics` (
  `fleetID` bigint(20) NOT NULL,
  `kills` int(11) NOT NULL DEFAULT '0',
  `losses` int(11) NOT NULL DEFAULT '0',
  `iskDestroyed` float NOT NULL DEFAULT '0',
  `iskLost` float NOT NULL DEFAULT '0',
  `dmgDone` float NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `fleets` (
  `fleetID` bigint(16) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `boss` bigint(16) NOT NULL,
  `fc` bigint(16) NOT NULL,
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastFetch` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fleetfail` int(11) DEFAULT NULL,
  `ended` timestamp NULL DEFAULT NULL,
  `stats` enum('public','private','corporation','alliance','fleet') NOT NULL DEFAULT 'fleet',
  `tracking` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `kills` (
  `fleetID` bigint(20) NOT NULL,
  `killID` bigint(20) NOT NULL,
  `type` enum('kill','loss') NOT NULL,
  `shipID` int(11) NOT NULL,
  `killTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `value` float NOT NULL DEFAULT '0',
  `systemID` bigint(20) NOT NULL,
  `killmail` json DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `participation` (
  `fleetID` bigint(20) NOT NULL,
  `characterID` bigint(20) NOT NULL,
  `corporationID` bigint(20) NOT NULL,
  `allianceID` bigint(20) DEFAULT '0',
  `ships` varchar(500) DEFAULT NULL,
  `kills` int(11) NOT NULL DEFAULT '0',
  `losses` int(11) NOT NULL DEFAULT '0',
  `iskDestroyed` float NOT NULL DEFAULT '0',
  `iskLost` float NOT NULL DEFAULT '0',
  `finalBlows` int(11) NOT NULL DEFAULT '0',
  `dmgDone` float NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `pilots` (
  `characterID` bigint(16) NOT NULL,
  `characterName` varchar(255) NOT NULL,
  `locationID` bigint(16) NOT NULL,
  `shipTypeID` int(11) NOT NULL,
  `stationID` int(11) DEFAULT NULL,
  `structureID` bigint(16) DEFAULT NULL,
  `fitting` varchar(500) DEFAULT NULL,
  `corporationID` bigint(20) DEFAULT NULL,
  `allianceID` bigint(20) DEFAULT NULL,
  `lastFetch` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `statsFleets` (
  `fleetID` bigint(16) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `fc` bigint(16) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended` timestamp NULL DEFAULT NULL,
  `stats` enum('public','private','corporation','alliance','fleet') NOT NULL DEFAULT 'fleet'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `structures` (
  `solarSystemID` int(11) NOT NULL,
  `structureID` bigint(16) NOT NULL,
  `structureName` varchar(255) DEFAULT NULL,
  `lastUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `authTokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `characterID` (`characterID`);

ALTER TABLE `config`
  ADD PRIMARY KEY (`cfg_key`),
  ADD UNIQUE KEY `cfg_key` (`cfg_key`);

ALTER TABLE `esisso`
  ADD PRIMARY KEY (`characterID`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `characterID` (`characterID`);

ALTER TABLE `fleetmembers`
  ADD PRIMARY KEY (`characterID`),
  ADD UNIQUE KEY `characterID` (`characterID`);

ALTER TABLE `fleetMetrics`
  ADD PRIMARY KEY (`fleetID`),
  ADD UNIQUE KEY `fleetID` (`fleetID`);

ALTER TABLE `fleets`
  ADD PRIMARY KEY (`fleetID`),
  ADD UNIQUE KEY `fleetID` (`fleetID`);

ALTER TABLE `kills`
  ADD UNIQUE KEY `killFleet` (`killID`,`fleetID`) USING BTREE;

ALTER TABLE `participation`
  ADD UNIQUE KEY `fleet_char` (`fleetID`,`characterID`);

ALTER TABLE `pilots`
  ADD PRIMARY KEY (`characterID`),
  ADD UNIQUE KEY `characterID` (`characterID`);

ALTER TABLE `statsFleets`
  ADD PRIMARY KEY (`fleetID`),
  ADD UNIQUE KEY `fleetID` (`fleetID`);

ALTER TABLE `structures`
  ADD PRIMARY KEY (`structureID`),
  ADD UNIQUE KEY `structureID` (`structureID`),
  ADD KEY `structureID_2` (`structureID`);


ALTER TABLE `authTokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `esisso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `statsFleets`
  MODIFY `fleetID` bigint(16) NOT NULL AUTO_INCREMENT;
COMMIT;
