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

create table `player_info`
(
    `player_name` varchar(16) not null,
    `kill`        integer     not null default 0,
    `death`       integer     not null default 0,
    primary key (`player_name`)
) default charset = utf8;

