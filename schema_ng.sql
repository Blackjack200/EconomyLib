create database xyron;
use xyron;

create table player_xuid
(
    xuid        varchar(128) unique not null,
    player_name varchar(16) unique  not null,
    primary key (xuid)
) default charset = utf8;

create table player_account
(
    xuid varchar(128) unique not null,
    foreign key (xuid) references player_xuid (xuid)
        on delete cascade,
    primary key (xuid)
) default charset = utf8;

create table rank_registry
(
    basename varchar(128) unique not null,
    display  varchar(256)        not null,
    primary key (basename)
) default charset = utf8;

create table rank_player_data
(
    xuid     varchar(128) not null,
    basename varchar(128) not null,
    foreign key (xuid) references player_xuid (xuid)
        on delete cascade,
    foreign key (basename) references rank_registry (basename)
        on delete cascade,
    primary key (xuid, basename)
) default charset = utf8;

create table statistics_data
(
    id   BIGINT unsigned unique not null auto_increment,
    type varchar(128),
    data json,
    primary key (id)
) default charset = utf8;

create table statistics_player_data
(
    xuid varchar(128)    not null,
    id   BIGINT unsigned not null,
    foreign key (xuid) references player_xuid (xuid)
        on delete cascade,
    foreign key (id) references statistics_data (id)
        on delete cascade,
    primary key (xuid, id)
) default charset = utf8;

create table ban_info
(
    xuid     varchar(128) not null,
    ban_time timestamp    not null,
    ban_end  timestamp    not null,
    source   varchar(128) not null default 'unknown',
    reason   blob(25565)  not null default 'unknown reason',
    foreign key (xuid) references player_xuid (xuid)
        on delete cascade,
    primary key (xuid)
) default charset = utf8;

drop table rank_player_data;
drop table rank_registry;
drop table statistics_player_data;
drop table statistics_data;
drop table player_account;
drop table player_xuid;

select *
from player_xuid;
select *
from player_account;
select *
from rank_registry;
select *
from rank_player_data;
select *
from statistics_data;
select *
from statistics_player_data;