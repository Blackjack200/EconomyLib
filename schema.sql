create database if not exists `mirekits`;
use `mirekits`;

create table if not exists `rank_data`
(
    `player_name` varchar(16) not null,
    primary key (`player_name`)
) default charset = utf8;

create table if not exists `rank_display`
(
    `basename` varchar(128) not null,
    `display`  blob         not null,
    primary key (`basename`)
) default charset = utf8;

create table if not exists `player_info`
(
    `player_name` varchar(16) not null,
    `kill`        integer     not null default 0,
    `death`       integer     not null default 0,
    primary key (`player_name`)
) default charset = utf8;

create table if not exists `ban_info`
(
    `player_name` char(16)        not null,
    `ban_time`    bigint unsigned not null default 0,
    `ban_end`     bigint unsigned not null default 0,
    `source`      char(16)        not null default 'unknown',
    `reason`      blob(25565)     not null default 'unknown reason',
    primary key (`player_name`)
) default charset = utf8;